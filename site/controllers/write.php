<?php

use Kirby\Cms\Blocks;
use Kirby\Data\Json;
use Kirby\Exception\Exception;
use Kirby\Filesystem\F;
use Kirby\Sane\Sane;
use Kirby\Toolkit\Str;

/**
 * Writing studio: create and edit stories without leaving the site design.
 *
 * Every write goes through Kirby's own page/file actions, so the current
 * user's permissions, blueprint validation and hooks (incl. GitHub sync)
 * apply exactly as they do in the Panel.
 */
return function ($kirby, $page) {
    $user    = $kirby->user();
    $stories = page('stories');
    $authors = page('authors')->children();
    $slug    = $page->editSlug()->value();
    $story   = $slug ? $stories->childrenAndDrafts()->find($slug) : null;

    if ($slug && $story === null) {
        go('write');
    }

    // pick a sensible default author: the story's, or one matching the editor
    $defaultAuthor =
        $story?->author()->toPages()->first() ??
        $authors->findBy('email', $user->email()) ??
        $authors->findBy('title', $user->name()->value()) ??
        $authors->first();

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
            'author'   => (string)get('author'),
            'text'     => (string)get('text'),
        ]);

        try {
            if (csrf(get('csrf')) !== true) {
                throw new Exception('Your session expired. Reload the page and try again.');
            }

            if ($values['title'] === '') {
                throw new Exception('Give your story a headline first.');
            }

            $author = $authors->find($values['author']);
            if ($author === null) {
                throw new Exception('Please choose an author.');
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

            if ($story) {
                $story = $story->update($content);
            } else {
                $base   = Str::slug($values['title']) ?: 'untitled';
                $newSlug = $base;
                for ($i = 2; $stories->childrenAndDrafts()->find($newSlug); $i++) {
                    $newSlug = $base . '-' . $i;
                }

                $story = $stories->createChild([
                    'slug'     => $newSlug,
                    'template' => 'story',
                    'content'  => $content + ['date' => date('Y-m-d')],
                ]);
            }

            // optional cover upload
            $upload = $kirby->request()->file('cover');
            if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $file = $story->createFile([
                    'source'   => $upload['tmp_name'],
                    'filename' => F::safeName($upload['name']),
                    'template' => 'cover',
                    'content'  => ['alt' => $values['title']],
                ]);
                $story = $story->update(['cover' => [$file->uuid()->toString()]]);
            } elseif ($upload && ($upload['error'] ?? 0) === UPLOAD_ERR_INI_SIZE) {
                throw new Exception('That image is too large. Please use one under 4 MB.');
            }

            $action = get('action');
            if ($action === 'publish' && $story->isListed() === false) {
                $story = $story->changeStatus('listed');
            } elseif ($action === 'unpublish' && $story->isDraft() === false) {
                $story = $story->changeStatus('draft');
            }

            go($action === 'publish'
                ? $story->url()
                : url('write/' . $story->slug(), ['query' => ['saved' => $action === 'unpublish' ? 'unpublished' : 'draft']]));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    return [
        'story'   => $story,
        'values'  => $values,
        'error'   => $error,
        'saved'   => get('saved'),
        'authors' => $authors,
        'cover'   => $story?->cover()->toFile(),
        'drafts'  => $stories->drafts()->sortBy('modified', 'desc')->limit(8),
        'mine'    => $stories->children()->listed()->sortBy('date', 'desc')->limit(5),
    ];
};
