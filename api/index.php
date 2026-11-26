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
    if (!is_file($marker) || file_get_contents($marker) !== $version) {
        $isHash = password_get_info($adminSecret)['algo'] !== null;

        if (!$isHash) {
            if (str_starts_with($adminSecret, '$')) {
                error_log('[inkwell] KIRBY_ADMIN_PASSWORD_HASH looks like an incomplete hash; it is being used as a plain password instead. Regenerate it if login fails.');
            }
            $adminSecret = password_hash($adminSecret, PASSWORD_DEFAULT);
        }

        // remove accounts provisioned from previous values (e.g. a changed email)
        foreach (glob($accounts . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $file) {
                @unlink($dir . '/' . $file);
            }
            @rmdir($dir);
        }

        @mkdir($userDir, 0775, true);
        file_put_contents($userDir . '/index.php', '<?php return ' . var_export([
            'email'    => $adminEmail,
            'name'     => $adminName,
            'role'     => 'admin',
            'language' => 'en',
        ], true) . ';');
        file_put_contents($userDir . '/.htpasswd', $adminSecret);
        file_put_contents($marker, $version);
    }
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
