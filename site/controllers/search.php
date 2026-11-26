<?php

/**
 * Full-text search across stories, papers and authors.
 */
return function ($site) {
    $query = trim((string)get('q'));
    $results = null;

    if ($query !== '') {
        $results = $site->index()
            ->listed()
            ->filter(fn ($p) => in_array($p->intendedTemplate()->name(), ['story', 'paper', 'author'], true))
            ->search($query, 'title|subtitle|text|abstract|tags|keywords|bio|venue');
    }

    return [
        'query'   => $query,
        'results' => $results,
    ];
};
