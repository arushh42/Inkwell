<?php

use Kirby\Cms\Blocks;
use Kirby\Data\Json;
use Kirby\Exception\Exception;
use Kirby\Filesystem\F;
use Kirby\Sane\Sane;
use Kirby\Toolkit\Str;

/**
 * Writing studio: create and edit stories in the site design.
 *
 * Two kinds of writers use it:
 *  - Editors/admins: write as any author, publish and unpublish anything.
 *    Their writes run with their own Kirby permissions.
 *  - Contributors (signed up on the site): always write as their own author
 *    profile, can only edit their own unpublished stories, and can submit
 *    for review ("unlisted") but never publish. Contributors have no Kirby
 *    permissions of their own, so after the checks below their writes run
 *    as the system user.
 *
 * Blueprint validation and hooks (incl. GitHub sync) apply in both cases.
 */
return function ($kirby, $page) {
    $user     = $kirby->user();
    $isEditor = inkwell_is_editor($user);
    $myAuthor = inkwell_author_for($user);
    $stories  = page('stories');
    // includes hidden profiles of new contributors (published on first story)
    $authors  = page('authors')->childrenAndDrafts();
    $slug     = $page->editSlug()->value();
    $story    = $slug ? $stories->childrenAndDrafts()->find($slug) : null;

    if ($slug && $story === null) {
        go(lurl('write'));
    }

    $ownsStory = fn ($s) => $myAuthor !== null && in_array($myAuthor->uuid()->toString(), $s->author()->yaml(), true);

    // contributors may only open their own stories
    if ($story && !$isEditor && !$ownsStory($story)) {
        go(lurl('write'));
    }

    // contributors can't change a story once it's live
    $readOnly = $story && !$isEditor && $story->isListed();

    // A brand-new member's author profile is committed to Git and reaches
    // every serverless instance with the next deploy (~1 min). Until then,
    // an instance may not know it yet. Show a friendly notice, not an error.
    $setupPending = !$isEditor && $myAuthor === null;

    // run a write with the right permissions
    $as = fn (callable $write) => $isEditor ? $write() : $kirby->impersonate('kirby', $write);

    $defaultAuthor = $isEditor
        ? ($story?->author()->toPages()->first() ?? $myAuthor ?? $authors->first())
        : $myAuthor;

    // language: which version is being edited, and does it exist yet?
    $lang        = $kirby->language()->code();
    $versions    = $story ? inkwell_versions($story) : [];
    $translating = $story !== null && !in_array($lang, $versions, true);

    $values = [
        'title'    => $story?->title()->value() ?? '',
        'subtitle' => $story?->subtitle()->value() ?? '',
        'kind'     => $story?->kind()->value() ?? 'article',
        'tags'     => $story?->tags()->value() ?? '',
        'author'   => $defaultAuthor?->id(),
        'text'     => $story ? $story->text()->toBlocks()->toHtml() : '',
    ];
    $error = null;

    if ($kirby->request()->is('POST')) {
        $values = array_merge($values, [
            'title'    => trim((string)get('title')),
            'subtitle' => trim((string)get('subtitle')),
            'kind'     => get('kind') === 'blog' ? 'blog' : 'article',
            'tags'     => (string)get('tags'),
            'author'   => $isEditor ? (string)get('author') : $myAuthor?->id(),
            'text'     => (string)get('text'),
        ]);
        $action = (string)get('action');

        try {
            if (csrf(get('csrf')) !== true) {
                throw new Exception(t('error.session'));
            }

            if ($setupPending) {
                throw new Exception(t('write.error.setup'));
            }

            if ($readOnly) {
                throw new Exception(t('write.readonly'));
            }

            $allowed = $isEditor
                ? ['draft', 'publish', 'unpublish', 'submit']
                : ['draft', 'submit', 'withdraw'];
            if (!in_array($action, $allowed, true)) {
                throw new Exception(t('write.error.action'));
            }

            if ($values['title'] === '') {
                throw new Exception(t('write.error.headline'));
            }

            $author = $isEditor ? $authors->find($values['author']) : $myAuthor;
            if ($author === null) {
                throw new Exception($isEditor ? t('write.error.author') : t('write.error.profile'));
            }

            // editor HTML → sanitized → Kirby blocks
            $html   = Sane::sanitize($values['text'], 'html');
            $blocks = Blocks::factory(Blocks::parse($html))->toArray();
            $tags   = array_values(array_unique(array_filter(array_map('trim', explode(',', $values['tags'])))));

            // translatable text vs. fields shared by both language versions
            $text = [
                'title'    => $values['title'],
                'subtitle' => $values['subtitle'],
                'tags'     => implode(', ', $tags),
                'text'     => Json::encode($blocks),
            ];
            $shared = [
                'kind'   => $values['kind'],
                'author' => [$author->uuid()->toString()],
            ];

            $story = $as(function () use ($story, $stories, $text, $shared, $values, $lang) {
                if ($story) {
                    return inkwell_save_version($story, $text, $shared, $lang);
                }

                $base    = Str::slug($values['title']) ?: 'untitled';
                $newSlug = $base;
                for ($i = 2; $stories->childrenAndDrafts()->find($newSlug); $i++) {
                    $newSlug = $base . '-' . $i;
                }

                return $stories->createChild([
                    'slug'     => $newSlug,
                    'template' => 'story',
                    'content'  => $text + $shared + ['date' => date('Y-m-d'), 'availableIn' => [$lang]],
                ]);
            });

            // optional cover upload
            $upload = $kirby->request()->file('cover');
            if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $story = $as(function () use ($story, $upload, $values) {
                    $file = $story->createFile([
                        'source'   => $upload['tmp_name'],
                        'filename' => F::safeName($upload['name']),
                        'template' => 'cover',
                        'content'  => ['alt' => $values['title']],
                    ]);
                    return $story->update(['cover' => [$file->uuid()->toString()]]);
                });
            } elseif ($upload && ($upload['error'] ?? 0) === UPLOAD_ERR_INI_SIZE) {
                throw new Exception(t('write.error.image'));
            }

            $target = match ($action) {
                'publish'   => 'listed',
                'submit'    => 'unlisted',
                'unpublish', 'withdraw' => 'draft',
                default     => null,
            };

            if ($target !== null && $story->status() !== $target) {
                $story = $as(fn () => $story->changeStatus($target));
            }

            go($action === 'publish'
                ? $story->url()
                : lurl('write/' . $story->slug(), ['saved' => match ($action) {
                    'submit'    => 'submitted',
                    'withdraw'  => 'withdrawn',
                    'unpublish' => 'unpublished',
                    default     => 'draft',
                }]));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // the "desk": editors see everything, contributors only their own work
    $all  = $stories->childrenAndDrafts();
    $mine = fn ($pages) => $isEditor ? $pages : $pages->filter($ownsStory);

    return [
        'story'     => $story,
        'isEditor'  => $isEditor,
        'readOnly'  => $readOnly,
        'setupPending' => $setupPending,
        'lang'      => $lang,
        'versions'  => $versions,
        'translating' => $translating,
        'myAuthor'  => $myAuthor,
        'values'    => $values,
        'error'     => $error,
        'saved'     => get('saved'),
        'welcome'   => get('welcome') !== null,
        'authors'   => $authors,
        'cover'     => $story?->cover()->toFile(),
        'drafts'    => $mine($all->drafts())->sortBy('modified', 'desc')->limit(8),
        'inReview'  => $mine($all->unlisted())->sortBy('modified', 'desc')->limit(8),
        'published' => $mine($all->listed())->sortBy('date', 'desc')->limit(5),
    ];
};
