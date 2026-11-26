<?php

/**
 * Global Kirby configuration.
 * Host-specific overrides live in config.localhost.php.
 *
 * @see https://getkirby.com/docs/reference/system/options
 */
return [
    'debug' => getenv('KIRBY_DEBUG') === 'true',

    // German is the default language; English is a translation.
    // Both live under a prefix (/de/…, /en/…) and the bare domain sends
    // visitors to the language their browser prefers.
    'languages'        => true,
    'languages.detect' => true,

    // Never allow creating the first admin account through the browser
    // on a public server. Locally this is overridden in config.localhost.php,
    // on Vercel the admin is provisioned from environment variables.
    'panel' => [
        'install' => false,
        'slug'    => 'panel',
        'menu'    => [
            'site',
            'stories' => [
                'icon'  => 'text',
                'label' => 'Stories',
                'link'  => 'pages/stories',
                'current' => fn ($current) => str_contains(\Kirby\Cms\App::instance()->path(), 'pages/stories'),
            ],
            'papers' => [
                'icon'  => 'book',
                'label' => 'Papers',
                'link'  => 'pages/papers',
                'current' => fn ($current) => str_contains(\Kirby\Cms\App::instance()->path(), 'pages/papers'),
            ],
            'authors' => [
                'icon'  => 'users',
                'label' => 'Authors',
                'link'  => 'pages/authors',
                'current' => fn ($current) => str_contains(\Kirby\Cms\App::instance()->path(), 'pages/authors'),
            ],
            '-',
            'users',
            'system',
        ],
    ],

    // Publication-wide settings used by templates
    'inkwell' => [
        'storiesPerPage' => 9,
        'papersPerPage'  => 10,
        'wordsPerMinute' => 230,
    ],
];
