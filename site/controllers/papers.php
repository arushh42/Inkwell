<?php

/**
 * Papers listing: filter by keyword (/papers/keyword:nlp), grouped by year.
 */
return function ($page) {
    $papers  = $page->children()->listed()->sortBy('date', 'desc');
    $keyword = param('keyword');

    if ($keyword) {
        $keyword = urldecode($keyword);
        $papers  = $papers->filterBy('keywords', $keyword, ',');
    }

    $papers = $papers->paginate(option('inkwell.papersPerPage', 10));

    return [
        'papers'     => $papers,
        'byYear'     => $papers->group(fn ($p) => $p->date()->toDate('Y')),
        'pagination' => $papers->pagination(),
        'keyword'    => $keyword,
    ];
};
