<?php

namespace Inkwell\GitHubSync;

use Kirby\Cms\App;
use Kirby\Exception\Exception;

/**
 * Persists Panel edits made on a serverless deployment by committing the
 * content folder to GitHub. The push triggers a new Vercel deployment,
 * which bakes the change into the read-only file system.
 *
 * How it works:
 *  - The deployed `content/` folder is the baseline (what's in Git).
 *  - Kirby edits a writable copy in /tmp.
 *  - After each Panel action we diff the copy against the last synced state
 *    and push all changes as ONE commit via the Git Data API
 *    (blobs → tree → commit → ref), rebasing onto the branch head.
 */
class GitHubSync
{
    /** Kirby internals that must never be committed */
    protected const IGNORE = '#(^|/)(_changes|\.lock$|\.DS_Store$)#';

    public function __construct(
        protected string $contentRoot,
        protected string $baselineRoot,
        protected string $stateFile,
        protected string $repo,
        protected string $branch,
        protected string $token,
        protected string $repoPath = 'content'
    ) {
    }

    /**
     * Builds an instance from environment variables, or returns null
     * when syncing is not configured or not needed (e.g. locally, where
     * Kirby already writes straight into the Git working copy).
     */
    public static function fromEnv(App $kirby): static|null
    {
        $token = getenv('GITHUB_SYNC_TOKEN');
        $repo  = getenv('GITHUB_SYNC_REPO') ?: (
            getenv('VERCEL_GIT_REPO_OWNER') && getenv('VERCEL_GIT_REPO_SLUG')
                ? getenv('VERCEL_GIT_REPO_OWNER') . '/' . getenv('VERCEL_GIT_REPO_SLUG')
                : null
        );

        $contentRoot  = realpath($kirby->root('content'));
        $baselineRoot = realpath($kirby->root('index') . '/content');

        if (!$token || !$repo || !$contentRoot || !$baselineRoot || $contentRoot === $baselineRoot) {
            return null;
        }

        return new static(
            contentRoot: $contentRoot,
            baselineRoot: $baselineRoot,
            stateFile: dirname($contentRoot) . '/.github-sync.json',
            repo: $repo,
            branch: getenv('GITHUB_SYNC_BRANCH') ?: getenv('VERCEL_GIT_COMMIT_REF') ?: 'main',
            token: $token,
        );
    }

    /**
     * Returns relative path => content hash for every syncable file.
     */
    public function scan(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($root) + 1);

            if ($file->isFile() && !preg_match(static::IGNORE, $relative)) {
                $files[$relative] = sha1_file($file->getPathname());
            }
        }

        ksort($files);
        return $files;
    }

    /**
     * Compares the working copy to the last synced state.
     *
     * @return array{changed: string[], deleted: string[], current: array}
     */
    public function diff(): array
    {
        $previous = is_file($this->stateFile)
            ? json_decode(file_get_contents($this->stateFile), true)
            : $this->scan($this->baselineRoot);

        $current = $this->scan($this->contentRoot);

        $changed = array_keys(array_filter(
            $current,
            fn ($hash, $path) => ($previous[$path] ?? null) !== $hash,
            ARRAY_FILTER_USE_BOTH
        ));
        $deleted = array_keys(array_diff_key($previous, $current));

        return compact('changed', 'deleted', 'current');
    }

    /**
     * Commits all pending changes. Returns the new commit SHA,
     * or null when there was nothing to commit.
     */
    public function push(string $message): string|null
    {
        ['changed' => $changed, 'deleted' => $deleted, 'current' => $current] = $this->diff();

        if ($changed === [] && $deleted === []) {
            return null;
        }

        // upload new/changed files as blobs (independent of the branch head)
        $tree = [];
        foreach ($changed as $path) {
            $blob = $this->api('POST', "/repos/{$this->repo}/git/blobs", [
                'content'  => base64_encode(file_get_contents($this->contentRoot . '/' . $path)),
                'encoding' => 'base64',
            ]);
            $tree[] = ['path' => $this->repoPath . '/' . $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $blob['sha']];
        }
        foreach ($deleted as $path) {
            $tree[] = ['path' => $this->repoPath . '/' . $path, 'mode' => '100644', 'type' => 'blob', 'sha' => null];
        }

        // build the commit on top of the current branch head;
        // retry once if someone pushed in the meantime
        for ($attempt = 1; ; $attempt++) {
            $head   = $this->api('GET', "/repos/{$this->repo}/git/ref/heads/{$this->branch}")['object']['sha'];
            $base   = $this->api('GET', "/repos/{$this->repo}/git/commits/{$head}")['tree']['sha'];
            $newTree = $this->api('POST', "/repos/{$this->repo}/git/trees", ['base_tree' => $base, 'tree' => $tree]);
            $commit  = $this->api('POST', "/repos/{$this->repo}/git/commits", [
                'message' => $message,
                'tree'    => $newTree['sha'],
                'parents' => [$head],
            ]);

            try {
                $this->api('PATCH', "/repos/{$this->repo}/git/refs/heads/{$this->branch}", [
                    'sha'   => $commit['sha'],
                    'force' => false,
                ]);
                break;
            } catch (Exception $e) {
                if ($attempt >= 2) {
                    throw $e;
                }
            }
        }

        file_put_contents($this->stateFile, json_encode($current));

        return $commit['sha'];
    }

    protected function api(string $method, string $path, array|null $body = null): array
    {
        // GITHUB_API_URL allows GitHub Enterprise (and a local mock in tests)
        $base = rtrim(getenv('GITHUB_API_URL') ?: 'https://api.github.com', '/');
        $curl = curl_init($base . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $this->token,
                'User-Agent: inkwell-kirby-sync',
                'X-GitHub-Api-Version: 2022-11-28',
                'Content-Type: application/json',
            ],
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($curl);
        $status   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $data     = json_decode((string)$response, true) ?? [];

        if ($status < 200 || $status >= 300) {
            throw new Exception(sprintf(
                'GitHub sync failed (%s %s → HTTP %d): %s',
                $method,
                $path,
                $status,
                $data['message'] ?? 'no response'
            ));
        }

        return $data;
    }
}
