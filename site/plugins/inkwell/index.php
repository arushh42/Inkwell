<?php

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Cms\Response;
use Kirby\Toolkit\Str;

/**
 * URL for a file in /assets with a content-based version, e.g.
 * assets/css/main.css?v=3f2a9c01. Changes whenever the file changes, so
 * browsers never keep stale CSS/JS. (File mtimes can't be used: Vercel
 * gives every deployed file the same timestamp.)
 */
function asset_url(string $path): string
{
    static $versions = [];
    $file = kirby()->root('index') . '/' . ltrim($path, '/');
    $versions[$path] ??= is_file($file) ? substr(hash_file('xxh3', $file), 0, 10) : '0';

    return url($path) . '?v=' . $versions[$path];
}

/**
 * Inkwell core plugin: publication-specific helpers, routes and feeds.
 */
Kirby::plugin('inkwell/core', [

    'pageMethods' => [
        /**
         * A stable signature colour per page (used for author avatars).
         */
        'swatch' => function (): string {
            $swatches = ['red', 'blue', 'green', 'yellow'];
            return $swatches[crc32($this->slug()) % count($swatches)];
        },

        /**
         * Initials for avatar placeholders, e.g. "Mira Lang" → "ML".
         */
        'initials' => function (): string {
            $parts = preg_split('/\s+/', trim($this->title()->value()));
            $first = mb_substr($parts[0] ?? '', 0, 1);
            $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
            return mb_strtoupper($first . $last);
        },

        /**
         * Estimated reading time in minutes (Medium-style).
         */
        'readingTime' => function (): int {
            $html  = $this->text()->toBlocks()->toHtml() . ' ' . $this->abstract()->value();
            $words = str_word_count(strip_tags($html));
            $wpm   = option('inkwell.wordsPerMinute', 230);

            return max(1, (int)ceil($words / $wpm));
        },

        /**
         * Plain-text excerpt for cards and meta descriptions.
         */
        'excerpt' => function (int $length = 160): string {
            if ($this->subtitle()->isNotEmpty()) {
                return $this->subtitle()->value();
            }

            $source = $this->abstract()->isNotEmpty()
                ? $this->abstract()->value()
                : $this->text()->toBlocks()->toHtml();

            return Str::excerpt(strip_tags($source), $length);
        },

        /**
         * All listed stories written by this author page.
         */
        'stories' => function (): Pages {
            return site()->find('stories')->children()->listed()
                ->filter(fn ($story) => $story->author()->toPages()->has($this))
                ->sortBy('date', 'desc');
        },

        /**
         * All listed papers this author page contributed to.
         */
        'papers' => function (): Pages {
            return site()->find('papers')->children()->listed()
                ->filter(fn ($paper) => $paper->authors()->toPages()->has($this))
                ->sortBy('date', 'desc');
        },

        /**
         * Full author line for papers (site authors + external co-authors).
         */
        'authorNames' => function (): array {
            $names = $this->authors()->toPages()->pluck('title');

            return array_merge($names, $this->coauthors()->split(','));
        },

        /**
         * BibTeX citation for a paper.
         */
        'bibtex' => function (): string {
            $authors = implode(' and ', $this->authorNames());
            $year    = $this->date()->toDate('Y');
            $first   = Str::slug(explode(' ', $authors)[0] ?? 'paper', '');
            $key     = $first . $year . Str::slug(explode(' ', $this->title()->value())[0], '');

            $fields = array_filter([
                'title'   => $this->title()->value(),
                'author'  => $authors,
                'journal' => $this->venue()->value(),
                'year'    => $year,
                'doi'     => $this->doi()->value(),
                'url'     => $this->url(),
            ]);

            $lines = [];
            foreach ($fields as $name => $value) {
                $lines[] = sprintf('  %-7s = {%s}', $name, $value);
            }

            return '@article{' . $key . ",\n" . implode(",\n", $lines) . "\n}";
        },
    ],

    'routes' => [
        /**
         * Front-end writing studio: /write (new story) and /write/<slug> (edit).
         * Only for signed-in editors; everyone else is sent to the login.
         */
        [
            'pattern' => ['write', 'write/(:any)'],
            'method'  => 'GET|POST',
            'action'  => function (string|null $slug = null) {
                if (kirby()->user() === null) {
                    go(url('login', ['query' => ['redirect' => $slug ? 'write/' . $slug : 'write']]));
                }

                return Page::factory([
                    'slug'     => 'write',
                    'template' => 'write',
                    'content'  => [
                        'title'     => $slug ? 'Edit story' : 'Write a story',
                        'editSlug'  => $slug ?? '',
                    ],
                ]);
            },
        ],

        /**
         * Editor sign-in in the site's own design (instead of the Panel's).
         */
        [
            'pattern' => 'login',
            'method'  => 'GET|POST',
            'action'  => function () {
                return Page::factory([
                    'slug'     => 'login',
                    'template' => 'login',
                    'content'  => ['title' => 'Sign in'],
                ]);
            },
        ],

        /**
         * Sign out (CSRF-protected so other sites can't log editors out).
         */
        [
            'pattern' => 'logout',
            'action'  => function () {
                if (csrf(get('csrf')) === true) {
                    kirby()->user()?->logout();
                }

                go('/');
            },
        ],

        /**
         * RSS 2.0 feed of the latest stories and papers.
         */
        [
            'pattern' => 'feed.xml',
            'action'  => function () {
                $items = site()->find('stories')->children()->listed()
                    ->add(site()->find('papers')->children()->listed())
                    ->sortBy('date', 'desc')
                    ->limit(20);

                $xml = snippet('feed', ['items' => $items], true);

                return new Response($xml, 'application/rss+xml');
            },
        ],

        /**
         * XML sitemap for search engines.
         */
        [
            'pattern' => 'sitemap.xml',
            'action'  => function () {
                $pages = site()->index()->listed()
                    ->filter(fn ($p) => $p->intendedTemplate()->name() !== 'search');

                $xml = snippet('sitemap', ['pages' => $pages], true);

                return new Response($xml, 'application/xml');
            },
        ],

        /**
         * Download a paper's BibTeX entry: /papers/<slug>.bib
         */
        [
            'pattern' => 'papers/(:any).bib',
            'action'  => function (string $slug) {
                $paper = page('papers/' . $slug);

                if (!$paper || $paper->isListed() === false) {
                    return false;
                }

                return new Response($paper->bibtex(), 'application/x-bibtex', 200, [
                    'Content-Disposition' => 'attachment; filename="' . $slug . '.bib"',
                ]);
            },
        ],
    ],
]);
