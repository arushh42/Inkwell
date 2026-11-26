<?php

/**
 * Local / traditional-hosting entry point.
 * On Vercel, requests go through api/index.php instead.
 */
require __DIR__ . '/vendor/autoload.php';

echo (new Kirby([
    'roots' => [
        'index' => __DIR__,
    ],
]))->render();
