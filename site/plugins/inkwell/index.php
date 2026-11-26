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

load(['Inkwell\\DoiLookup' => __DIR__ . '/src/DoiLookup.php']);

require_once __DIR__ . '/i18n.php';

/**
 * Editors and admins can publish and edit anything; everyone else
 * (contributors who signed up) only writes and submits their own stories.
 */
function inkwell_is_editor(\Kirby\Cms\User|null $user): bool
{
    return $user !== null && in_array($user->role()->id(), ['admin', 'editor'], true);
}

/**
 * The author profile page that belongs to a login account: linked via the
 * author's "account" field, falling back to a matching email or name.
 */
function inkwell_author_for(\Kirby\Cms\User|null $user): \Kirby\Cms\Page|null
{
    if ($user === null || ($authors = page('authors')) === null) {
        return null;
    }

    $all = $authors->childrenAndDrafts();

    return $all->filter(fn ($a) => $a->account()->toUsers()->has($user))->first()
        ?? $all->findBy('email', $user->email())
        ?? $all->findBy('title', $user->name()->value());
}

/**
 * Finds a paper (published or not) by DOI, case-insensitively.
 */
function inkwell_paper_by_doi(string $doi, \Kirby\Cms\Page|null $except = null): \Kirby\Cms\Page|null
{
    return page('papers')?->childrenAndDrafts()
        ->filter(fn ($p) => strtolower($p->doi()->value()) === strtolower($doi) && ($except === null || !$p->is($except)))
        ->first();
}

/**
 * Simple rate limit (per client IP) backed by Redis on Vercel, or Kirby's
 * cache locally. Returns false once $max attempts within $seconds are used.
 */
function inkwell_rate_limit(string $action, int $max, int $seconds): bool
{
    $key   = 'inkwell:rate:' . $action . ':' . kirby()->visitor()->ip(hash: true);
    $redis = class_exists('Inkwell\\Vercel\\Redis') ? Inkwell\Vercel\Redis::fromEnv() : null;

    if ($redis !== null) {
        try {
            return $redis->allow($key, $max, $seconds);
        } catch (Throwable) {
            return true; // never lock people out because Redis hiccuped
        }
    }

    $cache = kirby()->cache('inkwell.core');
    $count = (int)$cache->get($key, 0) + 1;
    $cache->set($key, $count, (int)ceil($seconds / 60));

    return $count <= $max;
}

/**
 * Inkwell core plugin: publication-specific helpers, routes and feeds.
 */
Kirby::plugin('inkwell/core', [

    'options' => [
        'cache' => true,
    ],

    'hooks' => [
        /**
         * When a contributor's story goes live, make their author
         * profile public too (it's created hidden on sign-up).
         */
        'page.changeStatus:after' => function ($newPage) {
            if ($newPage->intendedTemplate()->name() !== 'story' || $newPage->isListed() === false) {
                return;
            }

            $refs = $newPage->author()->yaml(); // e.g. ["page://abc123"]

            foreach (page('authors')?->drafts() ?? [] as $draft) {
                if (in_array($draft->uuid()->toString(), $refs, true)) {
                    kirby()->impersonate('kirby', fn () => $draft->changeStatus('listed'));
                }
            }
        },
    ],

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
         * True when only the first few of a long author list are stored.
         */
        'hasMoreAuthors' => function (): bool {
            return $this->authorTotal()->toInt() > count($this->authorNames());
        },

        /**
         * Display byline, e.g. "Daya Guo, Dejian Yang, Haowei Zhang et al."
         */
        'byline' => function (int|null $max = null): string {
            $names     = $this->authorNames();
            $truncated = $this->hasMoreAuthors() || ($max !== null && count($names) > $max);
            $names     = $max !== null ? array_slice($names, 0, $max) : $names;

            return implode(', ', $names) . ($truncated ? ' ' . t('paper.etal') : '');
        },

        /**
         * BibTeX citation for a paper.
         */
        'bibtex' => function (): string {
            $names   = $this->authorNames();
            $authors = implode(' and ', $names) . ($this->hasMoreAuthors() ? ' and others' : '');
            $year    = $this->date()->toDate('Y');
            $family  = explode(' ', $names[0] ?? 'paper');
            $key     = Str::slug(end($family), '') . $year . Str::slug(explode(' ', $this->title()->value())[0], '');

            $fields = array_filter([
                'title'   => $this->title()->value(),
                'author'  => $authors,
                'journal' => $this->venue()->value(),
                'volume'  => $this->volume()->value(),
                'pages'   => str_replace('-', '--', (string)$this->pages()->value()),
                'year'    => $year,
                'doi'     => $this->doi()->value(),
                'url'     => $this->doi()->isNotEmpty() ? 'https://doi.org/' . $this->doi()->value() : $this->url(),
            ]);

            $lines = [];
            foreach ($fields as $name => $value) {
                $lines[] = sprintf('  %-7s = {%s}', $name, $value);
            }

            return '@article{' . $key . ",\n" . implode(",\n", $lines) . "\n}";
        },
    ],

    'routes' => [
        /*
         * All of the site's own pages live under the language prefix
         * (/de/write, /en/write, …). Kirby calls language routes with the
         * Language object first and makes it the current language.
         */

        /**
         * DOI lookup for "Add a paper" (JSON). Signed-in members only,
         * rate-limited, results cached for a day.
         */
        [
            'pattern'  => 'add-paper/lookup',
            'language' => '*',
            'action'   => function ($language) {
                if (kirby()->user() === null) {
                    return Response::json(['error' => t('doi.error.login')], 401);
                }

                $doi = Inkwell\DoiLookup::normalize(get('doi'));
                if ($doi === null) {
                    return Response::json(['error' => t('doi.error.invalid')], 422);
                }

                if ($existing = inkwell_paper_by_doi($doi)) {
                    $user    = kirby()->user();
                    $canEdit = inkwell_is_editor($user) || $existing->submittedBy()->toUsers()->has($user);

                    return Response::json([
                        'error'     => t('doi.error.exists'),
                        'duplicate' => [
                            'title' => $existing->title()->value(),
                            'url'   => $existing->isListed() ? $existing->url() : null,
                            'edit'  => $canEdit && !$existing->isListed() ? lurl('add-paper/' . $existing->slug()) : null,
                        ],
                    ], 409);
                }

                if (inkwell_rate_limit('doi-lookup', 20, 60) === false) {
                    return Response::json(['error' => t('doi.error.rate')], 429);
                }

                $cache = kirby()->cache('inkwell.core');
                $key   = 'doi.' . md5(strtolower($doi));

                try {
                    $paper = $cache->get($key) ?? (new Inkwell\DoiLookup())->lookup($doi);
                    $cache->set($key, $paper, 60 * 24);
                    $paper['abstractSourceLabel'] = $paper['abstractSource'] !== '' ? inkwell_source_label($paper['abstractSource']) : '';
                    return Response::json(['paper' => $paper]);
                } catch (RuntimeException $e) {
                    $message = $e->getCode() === 404 ? t('doi.error.notFound') : t('doi.error.unavailable');
                    return Response::json(['error' => $message], $e->getCode() === 404 ? 404 : 502);
                }
            },
        ],

        /**
         * Add a paper (/add-paper) or edit one you added (/add-paper/<slug>).
         */
        [
            'pattern'  => ['add-paper', 'add-paper/(:any)'],
            'language' => '*',
            'method'   => 'GET|POST',
            'action'   => function ($language, string|null $slug = null) {
                if (kirby()->user() === null) {
                    go(lurl('login', ['redirect' => $slug ? 'add-paper/' . $slug : 'add-paper']));
                }

                return Page::factory([
                    'slug'     => 'add-paper',
                    'template' => 'add-paper',
                    'content'  => [
                        'title'    => strip_tags(kti($slug ? t('ap.edit') : t('ap.title'))),
                        'editSlug' => $slug ?? '',
                    ],
                ]);
            },
        ],

        /**
         * Sign up as an author.
         */
        [
            'pattern'  => 'signup',
            'language' => '*',
            'method'   => 'GET|POST',
            'action'   => function ($language) {
                return Page::factory([
                    'slug'     => 'signup',
                    'template' => 'signup',
                    'content'  => ['title' => strip_tags(kti(t('signup.title')))],
                ]);
            },
        ],

        /**
         * Writing studio: /write (new story) and /write/<slug> (edit).
         */
        [
            'pattern'  => ['write', 'write/(:any)'],
            'language' => '*',
            'method'   => 'GET|POST',
            'action'   => function ($language, string|null $slug = null) {
                if (kirby()->user() === null) {
                    go(lurl('login', ['redirect' => $slug ? 'write/' . $slug : 'write']));
                }

                return Page::factory([
                    'slug'     => 'write',
                    'template' => 'write',
                    'content'  => [
                        'title'    => $slug ? t('story.edit') : t('header.write'),
                        'editSlug' => $slug ?? '',
                    ],
                ]);
            },
        ],

        /**
         * Sign in, in the site's own design (instead of the Panel's).
         */
        [
            'pattern'  => 'login',
            'language' => '*',
            'method'   => 'GET|POST',
            'action'   => function ($language) {
                return Page::factory([
                    'slug'     => 'login',
                    'template' => 'login',
                    'content'  => ['title' => t('login.submit')],
                ]);
            },
        ],

        /**
         * Sign out (CSRF-protected so other sites can't log editors out).
         */
        [
            'pattern'  => 'logout',
            'language' => '*',
            'action'   => function ($language) {
                if (csrf(get('csrf')) === true) {
                    kirby()->user()?->logout();
                }

                go(lurl());
            },
        ],

        /**
         * RSS 2.0 feed per language: /de/feed.xml, /en/feed.xml.
         */
        [
            'pattern'  => 'feed.xml',
            'language' => '*',
            'action'   => function ($language) {
                $items = site()->find('stories')->children()->listed()
                    ->add(site()->find('papers')->children()->listed())
                    ->filter(fn ($p) => inkwell_is_translated($p))
                    ->sortBy('date', 'desc')
                    ->limit(20);

                $xml = snippet('feed', ['items' => $items], true);

                return new Response($xml, 'application/rss+xml');
            },
        ],

        /**
         * BibTeX for a paper: /de/papers/<slug>.bib
         */
        [
            'pattern'  => 'papers/(:any).bib',
            'language' => '*',
            'action'   => function ($language, string $slug) {
                $paper = page('papers/' . $slug);

                if (!$paper || $paper->isListed() === false) {
                    return false;
                }

                return new Response($paper->bibtex(), 'application/x-bibtex', 200, [
                    'Content-Disposition' => 'attachment; filename="' . $slug . '.bib"',
                ]);
            },
        ],

        /**
         * One sitemap for both languages, with hreflang alternates.
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
         * Old links without a language prefix (/stories/…, /write) are sent
         * to the same page in the visitor's language, so nothing shared
         * before the site became bilingual breaks.
         */
        [
            'pattern' => '(:all)',
            'action'  => function (string $path = '') {
                $first = explode('/', $path)[0];
                $skip  = ['', 'de', 'en', 'panel', 'api', 'media', 'assets', 'sitemap.xml'];

                if (in_array($first, $skip, true) || str_starts_with($path, '@')) {
                    /** @var \Kirby\Http\Route $this */
                    return $this->next();
                }

                $kirby    = kirby();
                $language = $kirby->detectedLanguage() ?? $kirby->defaultLanguage();
                $query    = $kirby->request()->query()->toArray();

                go(lurl($path, $query, $language->code()), 301);
            },
        ],
    ],
]);
