<?php

namespace Inkwell\Vercel;

/**
 * Keeps Kirby user accounts in sync across serverless instances.
 *
 * On Vercel, Kirby's accounts folder lives in each instance's private /tmp.
 * Accounts created at runtime (sign-ups) are therefore mirrored into Redis:
 *  - push():    after any user change, upload changed files + bump a version
 *  - hydrate(): before Kirby boots, download accounts if the version changed
 *
 * Each instance remembers which files it last synced, so a file is only
 * deleted when it was really deleted somewhere — never just because one
 * instance hasn't seen it yet. File modification times are preserved:
 * Kirby ends every session that started before the password file's mtime,
 * so a fresh mtime on download would log the member out everywhere.
 * Password hashes live in the private Redis database, never in Git.
 */
class AccountMirror
{
    public const KEY     = 'inkwell:accounts';
    public const VERSION = 'inkwell:accounts:version';

    public function __construct(
        protected Redis $redis,
        protected string $root,
        protected array $exclude = []   // top-level account folders to leave alone (env admin)
    ) {
    }

    /**
     * Relative path => ['data' => contents, 'mtime' => timestamp].
     */
    public function scan(): array
    {
        $files = [];

        foreach (glob($this->root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (in_array(basename($dir), $this->exclude, true)) {
                continue;
            }

            foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $name) {
                if (is_file($dir . '/' . $name)) {
                    $files[basename($dir) . '/' . $name] = [
                        'data'  => file_get_contents($dir . '/' . $name),
                        'mtime' => filemtime($dir . '/' . $name),
                    ];
                }
            }
        }

        return $files;
    }

    protected function remote(): array
    {
        $flat  = $this->redis->command(['HGETALL', static::KEY]) ?? [];
        $files = [];

        for ($i = 0; $i + 1 < count($flat); $i += 2) {
            $entry = json_decode($flat[$i + 1], true);
            if (is_array($entry)) {
                $files[$flat[$i]] = ['data' => base64_decode($entry['d']), 'mtime' => (int)$entry['m']];
            }
        }

        return $files;
    }

    /** @return array{version: string, paths: string[]} */
    protected function state(): array
    {
        $state = json_decode((string)@file_get_contents($this->root . '/.mirror-state'), true);
        return is_array($state) ? $state + ['version' => '', 'paths' => []] : ['version' => '', 'paths' => []];
    }

    protected function saveState(string $version): void
    {
        @mkdir($this->root, 0775, true);
        file_put_contents($this->root . '/.mirror-state', json_encode([
            'version' => $version,
            'paths'   => array_keys($this->scan()),
        ]));
    }

    protected function write(string $path, array $file): void
    {
        if (str_contains($path, '..') || in_array(explode('/', $path)[0], $this->exclude, true)) {
            return;
        }
        @mkdir(dirname($this->root . '/' . $path), 0775, true);
        file_put_contents($this->root . '/' . $path, $file['data']);
        touch($this->root . '/' . $path, $file['mtime']);
    }

    protected function delete(string $path): void
    {
        if (str_contains($path, '..')) {
            return;
        }
        @unlink($this->root . '/' . $path);
        @rmdir(dirname($this->root . '/' . $path));
    }

    /**
     * Downloads accounts from Redis when another instance changed them.
     */
    public function hydrate(): void
    {
        $version = (string)($this->redis->command(['GET', static::VERSION]) ?? '0');
        $state   = $this->state();

        if ($state['version'] === $version) {
            return;
        }

        $remote = $this->remote();

        // files we had synced before that are gone remotely were deleted elsewhere
        foreach ($state['paths'] as $path) {
            if (!array_key_exists($path, $remote)) {
                $this->delete($path);
            }
        }

        foreach ($remote as $path => $file) {
            $this->write($path, $file);
        }

        $this->saveState($version);
    }

    /**
     * Uploads local account changes to Redis.
     */
    public function push(): void
    {
        $local  = $this->scan();
        $remote = $this->remote();
        $state  = $this->state();

        $set = ['HSET', static::KEY];
        foreach ($local as $path => $file) {
            $known = $remote[$path] ?? null;
            if ($known === null || $known['data'] !== $file['data'] || $known['mtime'] !== $file['mtime']) {
                array_push($set, $path, json_encode(['m' => $file['mtime'], 'd' => base64_encode($file['data'])]));
            }
        }

        // only files this instance had synced before and has now removed
        $deleted = array_values(array_filter(
            $state['paths'],
            fn ($path) => !array_key_exists($path, $local) && array_key_exists($path, $remote)
        ));

        // pick up accounts other instances created in the meantime
        foreach ($remote as $path => $file) {
            if (!array_key_exists($path, $local) && !in_array($path, $deleted, true) && !in_array($path, $state['paths'], true)) {
                $this->write($path, $file);
            }
        }

        if (count($set) === 2 && $deleted === []) {
            return;
        }

        if (count($set) > 2) {
            $this->redis->command($set);
        }
        if ($deleted !== []) {
            $this->redis->command(['HDEL', static::KEY, ...$deleted]);
        }

        $this->saveState((string)$this->redis->command(['INCR', static::VERSION]));
    }
}
