<?php

use Kirby\Cms\App as Kirby;
use Kirby\Session\FileSessionStore;

load([
    'Inkwell\\Vercel\\UpstashSessionStore' => __DIR__ . '/src/UpstashSessionStore.php',
    'Inkwell\\Vercel\\Redis'               => __DIR__ . '/src/Redis.php',
    'Inkwell\\Vercel\\AccountMirror'       => __DIR__ . '/src/AccountMirror.php',
]);

/**
 * Serverless compatibility layer.
 *
 * - Falls back to original images when the GD extension is unavailable
 *   (the vercel-php runtime does not ship GD), instead of failing thumbs.
 * - Stores Panel sessions in Upstash Redis when credentials are present,
 *   so logins survive across serverless instances. Without credentials,
 *   Kirby's default file-based store is used.
 * - Mirrors user accounts (e.g. sign-ups) to Redis so every serverless
 *   instance knows about them (see AccountMirror).
 */
$components = [];

if (extension_loaded('gd') === false && option('thumbs.driver', 'gd') === 'gd') {
    $components['file::version'] = fn (Kirby $kirby, $file, array $options = []) => $file;
}

$redisUrl   = getenv('KV_REST_API_URL') ?: getenv('UPSTASH_REDIS_REST_URL');
$redisToken = getenv('KV_REST_API_TOKEN') ?: getenv('UPSTASH_REDIS_REST_TOKEN');

$components['session::store'] = function (Kirby $kirby) use ($redisUrl, $redisToken) {
    if ($redisUrl && $redisToken) {
        return new Inkwell\Vercel\UpstashSessionStore($redisUrl, $redisToken);
    }

    return new FileSessionStore($kirby->root('sessions'));
};

/**
 * Upload account changes to Redis — only on serverless deployments, where
 * the accounts folder is a per-instance copy in /tmp.
 */
$mirrorAccounts = function () {
    $kirby = kirby();
    $redis = Inkwell\Vercel\Redis::fromEnv();

    if ($redis === null || $kirby->root('accounts') === $kirby->root('site') . '/accounts') {
        return;
    }

    $adminEmail = strtolower(trim((string)getenv('KIRBY_ADMIN_EMAIL')));
    $exclude    = $adminEmail !== '' ? [substr(hash('sha256', $adminEmail), 0, 8)] : [];

    try {
        (new Inkwell\Vercel\AccountMirror($redis, $kirby->root('accounts'), $exclude))->push();
    } catch (Throwable $e) {
        error_log('[inkwell] Could not save account changes to Redis: ' . $e->getMessage());
        throw new \Kirby\Exception\Exception('The account change could not be saved permanently. Please try again.');
    }
};

$userHooks = [];
foreach (['create', 'update', 'delete', 'changeEmail', 'changeName', 'changePassword', 'changeRole', 'changeLanguage'] as $action) {
    $userHooks['user.' . $action . ':after'] = $mirrorAccounts;
}

Kirby::plugin('inkwell/vercel', [
    'components' => $components,
    'hooks'      => $userHooks,
]);
