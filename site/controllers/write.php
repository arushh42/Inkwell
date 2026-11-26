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
        go('write');
    }

    $ownsStory = fn ($s) => $myAuthor !== null && in_array($myAuthor->uuid()->toString(), $s->author()->yaml(), true);

    // contributors may only open their own stories
    if ($story && !$isEditor && !$ownsStory($story)) {
        go('write');
    }

    // contributors can't change a story once it's live
    $readOnly = $story && !$isEditor && $story->isListed();

    // A brand-new member's author profile is committed to Git and reaches
    // every serverless instance with the next deploy (~1 min). Until then,
    // an instance may not know it yet — show a friendly notice, not an error.
    $setupPending = !$isEditor && $myAuthor === null;

    // run a write with the right permissions
    $as = fn (callable $write) => $isEditor ? $write() : $kirby->impersonate('kirby', $write);

    $defaultAuthor = $isEditor
        ? ($story?->author()->toPages()->first() ?? $myAuthor ?? $authors->first())
        : $myAuthor;

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
                throw new Exception('Your session expired. Reload the page and try again.');
            }

            if ($setupPending) {
                throw new Exception('Your account is still being set up. Please wait a minute and try again — your text is still in the editor.');
            }

            if ($readOnly) {
                throw new Exception('This story is already published. Ask an editor if it needs changes.');
            }

            $allowed = $isEditor
                ? ['draft', 'publish', 'unpublish', 'submit']
                : ['draft', 'submit', 'withdraw'];
            if (!in_array($action, $allowed, true)) {
                throw new Exception('That action isn’t available to you.');
            }

            if ($values['title'] === '') {
                throw new Exception('Give your story a headline first.');
            }

            $author = $isEditor ? $authors->find($values['author']) : $myAuthor;
            if ($author === null) {
                throw new Exception($isEditor ? 'Please choose an author.' : 'Your author profile is missing. Please contact an editor.');
            }

            // editor HTML → sanitized → Kirby blocks
            $html   = Sane::sanitize($values['text'], 'html');
            $blocks = Blocks::factory(Blocks::parse($html))->toArray();
            $tags   = array_values(array_unique(array_filter(array_map('trim', explode(',', $values['tags'])))));

            $content = [
                'title'    => $values['title'],
                'subtitle' => $values['subtitle'],
                'kind'     => $values['kind'],
                'tags'     => implode(', ', $tags),
                'author'   => [$author->uuid()->toString()],
                'text'     => Json::encode($blocks),
            ];

            $story = $as(function () use ($story, $stories, $content, $values) {
                if ($story) {
                    return $story->update($content);
                }

                $base    = Str::slug($values['title']) ?: 'untitled';
                $newSlug = $base;
                for ($i = 2; $stories->childrenAndDrafts()->find($newSlug); $i++) {
                    $newSlug = $base . '-' . $i;
                }

                return $stories->createChild([
                    'slug'     => $newSlug,
                    'template' => 'story',
                    'content'  => $content + ['date' => date('Y-m-d')],
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
                throw new Exception('That image is too large. Please use one under 4 MB.');
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
                : url('write/' . $story->slug(), ['query' => ['saved' => match ($action) {
                    'submit'    => 'submitted',
                    'withdraw'  => 'withdrawn',
                    'unpublish' => 'unpublished',
                    default     => 'draft',
                }]]));
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
