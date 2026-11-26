<?php

/**
 * Stories listing: filter by tag (/stories/tag:php) or type (/stories/kind:blog).
 */
return function ($page) {
    $stories = $page->children()->listed()->sortBy('date', 'desc');

    $tag  = param('tag');
    $kind = param('kind');

    if ($tag) {
        $tag     = urldecode($tag);
        $stories = $stories->filterBy('tags', $tag, ',');
    }

    if (in_array($kind, ['article', 'blog'], true)) {
        $stories = $stories->filterBy('kind', $kind);
    }

    $stories    = $stories->paginate(option('inkwell.storiesPerPage', 9));
    $allTags    = $page->children()->listed()->pluck('tags', ',', true);
    sort($allTags);

    return [
        'stories'    => $stories,
        'pagination' => $stories->pagination(),
        'tag'        => $tag,
        'kind'       => $kind,
        'allTags'    => $allTags,
    ];
};
