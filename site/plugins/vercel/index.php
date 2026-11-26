<?php

use Kirby\Cms\App as Kirby;
use Kirby\Session\FileSessionStore;

load([
    'Inkwell\\Vercel\\UpstashSessionStore' => __DIR__ . '/src/UpstashSessionStore.php',
]);

/**
 * Serverless compatibility layer.
 *
 * - Falls back to original images when the GD extension is unavailable
 *   (the vercel-php runtime does not ship GD), instead of failing thumbs.
 * - Stores Panel sessions in Upstash Redis when credentials are present,
 *   so logins survive across serverless instances. Without credentials,
 *   Kirby's default file-based store is used.
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

Kirby::plugin('inkwell/vercel', [
    'components' => $components,
]);
