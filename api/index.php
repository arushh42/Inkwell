<?php

/**
 * Vercel entry point (vercel-php runtime).
 *
 * Vercel functions run on a read-only file system with an ephemeral,
 * per-instance /tmp directory. Kirby expects to write to a few folders
 * (media, sessions, cache, accounts, content), so this front controller:
 *
 *  1. serves already-generated media files and Panel assets directly,
 *  2. points Kirby's writable roots to /tmp,
 *  3. seeds /tmp with the content committed to the repository,
 *  4. provisions the admin account from environment variables,
 *  5. boots Kirby as usual.
 *
 * Locally, the regular /index.php is used and none of this applies.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$tmp  = rtrim(sys_get_temp_dir(), '/') . '/inkwell';

// Kirby derives its base path from SCRIPT_NAME. Without this, /api/*
// (Kirby's own REST API used by the Panel) would be treated as the site root.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';

// Behind Vercel's proxy every request appears to come from the same local
// address. Use the client IP that Vercel's edge sets (and overwrites, so it
// can't be spoofed) — otherwise Kirby's brute-force protection would lock
// out *all* editors after 10 failed logins by anyone.
if (getenv('VERCEL') && !empty($_SERVER['HTTP_X_REAL_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

/**
 * Streams a file from disk if it lives inside $base. Returns false otherwise.
 */
$serve = static function (string $base, string $relative, bool $immutable = true): bool {
    $base = realpath($base);
    $file = $base ? realpath($base . '/' . $relative) : false;

    if ($file === false || is_file($file) === false || str_starts_with($file, $base . '/') === false) {
        return false;
    }

    $types = [
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'text/javascript; charset=utf-8',
        'mjs'  => 'text/javascript; charset=utf-8',
        'json' => 'application/json',
        'map'  => 'application/json',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'pdf'  => 'application/pdf',
        'mp4'  => 'video/mp4',
    ];
    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $type = $types[$ext] ?? (mime_content_type($file) ?: 'application/octet-stream');

    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . ($immutable ? 'public, max-age=31536000, immutable' : 'public, max-age=3600'));

    if ($ext === 'svg') {
        // uploaded SVGs must never execute scripts in our origin
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:");
    }

    readfile($file);
    return true;
};

// 1a. Panel assets: /media/panel/<hash>/<file> → vendor/getkirby/cms/panel/dist/<file>
if (preg_match('#^/media/panel/[^/]+/(.+)$#', $path, $m) && $serve($root . '/vendor/getkirby/cms/panel/dist', $m[1])) {
    exit;
}

// 1b. Media files Kirby already published to /tmp on this instance
if (str_starts_with($path, '/media/') && $serve($tmp . '/media', substr($path, 7))) {
    exit;
}

// 1c. Static assets (normally served by Vercel's CDN, see vercel.json)
if (str_starts_with($path, '/assets/') && $serve($root . '/assets', substr($path, 8), false)) {
    exit;
}

// 2. Seed writable content on a cold start
if (is_dir($tmp . '/content') === false) {
    $copy = static function (string $from, string $to) use (&$copy): void {
        @mkdir($to, 0775, true);
        foreach (scandir($from) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            is_dir("$from/$item") ? $copy("$from/$item", "$to/$item") : copy("$from/$item", "$to/$item");
        }
    };

    $staging = $tmp . '/content.' . bin2hex(random_bytes(4));
    $copy($root . '/content', $staging);
    @rename($staging, $tmp . '/content');
}

// 3. Provision the admin account from environment variables.
//    Accepts a bcrypt/argon hash (KIRBY_ADMIN_PASSWORD_HASH) or a plain
//    password (KIRBY_ADMIN_PASSWORD, or a non-hash pasted into the hash
//    variable), which is hashed here. Values are trimmed, because stray
//    whitespace from copy & paste is the most common cause of failed logins.
$adminEmail    = strtolower(trim((string)getenv('KIRBY_ADMIN_EMAIL')));
$adminName     = trim((string)getenv('KIRBY_ADMIN_NAME')) ?: 'Admin';
$adminSecret   = trim((string)(getenv('KIRBY_ADMIN_PASSWORD_HASH') ?: getenv('KIRBY_ADMIN_PASSWORD')));
$adminSecret   = trim($adminSecret, "\"'");  // tolerate accidentally quoted values

if ($adminEmail === '' || $adminSecret === '') {
    error_log('[inkwell] Admin account not provisioned: set KIRBY_ADMIN_EMAIL and KIRBY_ADMIN_PASSWORD_HASH (or KIRBY_ADMIN_PASSWORD).');
} else {
    $accounts = $tmp . '/accounts';
    $userDir  = $accounts . '/' . substr(hash('sha256', $adminEmail), 0, 8);
    $marker   = $accounts . '/.provisioned';
    $version  = hash('sha256', $adminEmail . "\0" . $adminName . "\0" . $adminSecret);

    // (re)write the account whenever the environment variables change
    $previous = json_decode((string)@file_get_contents($marker), true) ?: [];
    if (($previous['version'] ?? null) !== $version) {
        $isHash = password_get_info($adminSecret)['algo'] !== null;

        if (!$isHash) {
            if (str_starts_with($adminSecret, '$')) {
                error_log('[inkwell] KIRBY_ADMIN_PASSWORD_HASH looks like an incomplete hash; it is being used as a plain password instead. Regenerate it if login fails.');
            }
            if (mb_strlen($adminSecret) < 8) {
                error_log('[inkwell] The admin password is shorter than 8 characters; Kirby will reject it at login. Use a longer password.');
            }
            $adminSecret = password_hash($adminSecret, PASSWORD_DEFAULT);
        }

        // remove the admin account provisioned from previous values (e.g. a
        // changed email) — but never member accounts created by sign-ups
        $oldDir = $previous['dir'] ?? null;
        if ($oldDir && $oldDir !== basename($userDir) && preg_match('/^[a-f0-9]{8}$/', $oldDir)) {
            foreach (array_diff(scandir($accounts . '/' . $oldDir) ?: [], ['.', '..']) as $file) {
                @unlink($accounts . '/' . $oldDir . '/' . $file);
            }
            @rmdir($accounts . '/' . $oldDir);
        }

        @mkdir($userDir, 0775, true);
        file_put_contents($userDir . '/index.php', '<?php return ' . var_export([
            'email'    => $adminEmail,
            'name'     => $adminName,
            'role'     => 'admin',
            'language' => 'en',
        ], true) . ';');
        file_put_contents($userDir . '/.htpasswd', $adminSecret);
        // Kirby ends sessions that started before the password file's mtime.
        // Every new serverless instance rewrites this file, so give it a fixed
        // timestamp — otherwise each cold start would sign the admin out.
        touch($userDir . '/.htpasswd', 946684800);
        file_put_contents($marker, json_encode(['version' => $version, 'dir' => basename($userDir)]));
    }
}

// 3b. Accounts created at runtime (sign-ups) live in Redis; download them
//     before Kirby boots — only for requests that can involve a signed-in
//     user, so anonymous readers don't pay for an extra Redis round-trip.
require_once $root . '/site/plugins/vercel/src/Redis.php';
require_once $root . '/site/plugins/vercel/src/AccountMirror.php';

$redis = Inkwell\Vercel\Redis::fromEnv();
$needsAccounts = isset($_COOKIE['kirby_session'])
    || preg_match('#^/(?:(?:de|en)/)?(login|signup|write|add-paper|logout|panel|api)(/|$)#', $path) === 1;

if ($redis && $needsAccounts) {
    try {
        $adminDir = $adminEmail !== '' ? [substr(hash('sha256', $adminEmail), 0, 8)] : [];
        (new Inkwell\Vercel\AccountMirror($redis, $tmp . '/accounts', $adminDir))->hydrate();
    } catch (Throwable $e) {
        error_log('[inkwell] Could not load member accounts from Redis: ' . $e->getMessage());
    }
}

// Diagnostics: /__health shows how this instance is configured.
// Only yes/no checks and a masked email — never secrets.
if ($path === '/__health') {
    $raw      = getenv('KIRBY_ADMIN_PASSWORD_HASH') !== false && getenv('KIRBY_ADMIN_PASSWORD_HASH') !== ''
        ? ['KIRBY_ADMIN_PASSWORD_HASH', (string)getenv('KIRBY_ADMIN_PASSWORD_HASH')]
        : ['KIRBY_ADMIN_PASSWORD', (string)getenv('KIRBY_ADMIN_PASSWORD')];
    $secret   = trim(trim($raw[1]), "\"'");
    $algo     = password_get_info($secret)['algoName'] ?? 'unknown';
    $format   = match (true) {
        $secret === ''                    => 'missing',
        $algo !== 'unknown'               => $algo . ' hash (' . strlen($secret) . ' chars)',
        str_starts_with($secret, '$')     => 'INCOMPLETE hash (' . strlen($secret) . ' chars, bcrypt needs 60)',
        default                           => 'plain password',
    };
    $rawEmail = (string)getenv('KIRBY_ADMIN_EMAIL');
    $mask     = static fn (string $e) => preg_replace('/^(.)[^@]*(@.*)$/', '$1***$2', $e);

    $blocked = ['ips' => 0, 'emails' => 0];
    $logins  = @json_decode((string)@file_get_contents($tmp . '/accounts/.logins'), true) ?: [];
    foreach (['by-ip' => 'ips', 'by-email' => 'emails'] as $key => $label) {
        foreach ($logins[$key] ?? [] as $entry) {
            if (($entry['trials'] ?? 0) >= 10 && ($entry['time'] ?? 0) > time() - 3600) {
                $blocked[$label]++;
            }
        }
    }

    $redisUrl   = getenv('KV_REST_API_URL') ?: getenv('UPSTASH_REDIS_REST_URL');
    $redisToken = getenv('KV_REST_API_TOKEN') ?: getenv('UPSTASH_REDIS_REST_TOKEN');
    $redis      = 'not configured';
    if ($redisUrl && $redisToken) {
        $c = curl_init(rtrim($redisUrl, '/'));
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '["PING"]', CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $redisToken, 'Content-Type: application/json']]);
        $pong  = json_decode((string)curl_exec($c), true)['result'] ?? null;
        $redis = $pong === 'PONG' ? 'connected' : 'configured but NOT reachable (HTTP ' . curl_getinfo($c, CURLINFO_HTTP_CODE) . ')';
    }

    if (!is_file($tmp . '/instance')) {
        file_put_contents($tmp . '/instance', bin2hex(random_bytes(3)) . ' started ' . gmdate('c'));
    }

    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode([
        'php'                 => PHP_VERSION,
        'instance'            => file_get_contents($tmp . '/instance'),
        'admin_email'         => $rawEmail === '' ? 'MISSING' : $mask(strtolower(trim($rawEmail))),
        'admin_email_trimmed' => $rawEmail !== trim($rawEmail) ? 'yes (had extra spaces — fixed automatically)' : 'no',
        'password_variable'   => $secret === '' ? 'MISSING' : $raw[0],
        'password_format'     => $format,
        'plain_password_long_enough' => $format === 'plain password' ? (mb_strlen($secret) >= 8 ? 'yes' : 'NO — Kirby requires at least 8 characters') : 'n/a',
        'account_provisioned' => count(glob($tmp . '/accounts/*/index.php') ?: []) > 0,
        'accounts_on_instance' => count(glob($tmp . '/accounts/*/index.php') ?: []),
        'login_blocks'        => $blocked,
        'sessions'            => $redis,
        'github_sync'         => getenv('GITHUB_SYNC_TOKEN') ? 'token set' : 'not configured',
        'gd'                  => extension_loaded('gd'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 4. Boot Kirby
require $root . '/vendor/autoload.php';

$siteUrl = getenv('SITE_URL') ?: (
    ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https') . '://' .
    ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? getenv('VERCEL_URL'))
);

echo (new Kirby([
    'roots' => [
        'index'    => $root,
        'content'  => $tmp . '/content',
        'media'    => $tmp . '/media',
        'accounts' => $tmp . '/accounts',
        'sessions' => $tmp . '/sessions',
        'cache'    => $tmp . '/cache',
        'logs'     => $tmp . '/logs',
    ],
    'urls' => [
        'index' => rtrim($siteUrl, '/'),
    ],
    'options' => [
        'url' => rtrim($siteUrl, '/'),
    ],
]))->render();
