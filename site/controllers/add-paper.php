<?php

use Kirby\Cms\Blocks;
use Kirby\Data\Json;
use Kirby\Exception\Exception;
use Kirby\Toolkit\Str;
use Kirby\Toolkit\V;

/**
 * "Add a paper": paste a DOI, metadata is fetched (see DoiLookup and the
 * add-paper/lookup route), review it, then submit for review (contributors)
 * or publish (editors). Same permission model as the writing studio:
 * contributors only edit papers they added and that aren't live yet.
 */
return function ($kirby, $page) {
    $user     = $kirby->user();
    $isEditor = inkwell_is_editor($user);
    $papers   = page('papers');
    $slug     = $page->editSlug()->value();
    $paper    = $slug ? $papers->childrenAndDrafts()->find($slug) : null;
    $maxNames = 8; // longer author lists are stored as "first 8 … et al. (total)"

    if ($slug && $paper === null) {
        go('add-paper');
    }

    $isMine = fn ($p) => $p->submittedBy()->toUsers()->has($user);

    if ($paper && !$isEditor && !$isMine($paper)) {
        go('add-paper');
    }

    $readOnly = $paper && !$isEditor && $paper->isListed();
    $as       = fn (callable $write) => $isEditor ? $write() : $kirby->impersonate('kirby', $write);

    // existing plain-language summary (text blocks) → plain text for the form
    $summary = '';
    if ($paper) {
        foreach ($paper->text()->toBlocks() as $block) {
            if ($block->type() === 'text') {
                $summary .= trim(html_entity_decode(strip_tags(str_replace('</p>', "\n\n", $block->text()->value())))) . "\n\n";
            }
        }
    }

    $values = [
        'doi'            => $paper?->doi()->value() ?? '',
        'title'          => $paper?->title()->value() ?? '',
        'authors'        => $paper ? implode("\n", $paper->coauthors()->split(',')) : '',
        'authorTotal'    => $paper?->authorTotal()->value() ?? '',
        'date'           => $paper?->date()->toDate('Y-m-d') ?? '',
        'venue'          => $paper?->venue()->value() ?? '',
        'volume'         => $paper?->volume()->value() ?? '',
        'pages'          => $paper?->pages()->value() ?? '',
        'arxiv'          => $paper?->arxiv()->value() ?? '',
        'abstract'       => $paper?->abstract()->value() ?? '',
        'abstractSource' => $paper?->abstractSource()->value() ?? '',
        'keywords'       => $paper?->keywords()->value() ?? '',
        'summary'        => trim($summary),
    ];
    $error  = null;
    $notice = null;

    if ($kirby->request()->is('POST')) {
        foreach (array_keys($values) as $key) {
            $values[$key] = trim((string)get($key));
        }
        $action = (string)get('action');

        // no-JavaScript fallback for the "Fetch details" button
        if ($action === 'lookup') {
            try {
                if (csrf(get('csrf')) !== true) {
                    throw new Exception('Your session expired. Reload the page and try again.');
                }
                $doi = Inkwell\DoiLookup::normalize($values['doi']);
                if ($doi === null) {
                    throw new Exception('That doesn’t look like a DOI. It should start with “10.”');
                }
                if ($dupe = inkwell_paper_by_doi($doi, $paper)) {
                    throw new Exception('This paper is already on ' . $kirby->site()->title() . ': “' . $dupe->title() . '”.');
                }
                $found  = (new Inkwell\DoiLookup())->lookup($doi);
                $values = array_merge($values, [
                    'doi'            => $found['doi'],
                    'title'          => $found['title'],
                    'authors'        => implode("\n", $found['authors']),
                    'authorTotal'    => '',
                    'date'           => $found['date'],
                    'venue'          => $found['venue'],
                    'volume'         => $found['volume'],
                    'pages'          => $found['pages'],
                    'arxiv'          => $found['arxiv'],
                    'abstract'       => $found['abstract'],
                    'abstractSource' => $found['abstractSource'],
                    'keywords'       => implode(', ', $found['keywords']),
                ]);
                $notice = $found['abstractOpen']
                    ? 'Details fetched from ' . $found['registry'] . '. Please check them before saving.'
                    : 'Details fetched from ' . $found['registry'] . '. The abstract isn’t openly licensed, so please describe the paper in your own words.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            try {
                if (csrf(get('csrf')) !== true) {
                    throw new Exception('Your session expired. Reload the page and try again.');
                }

                if ($readOnly) {
                    throw new Exception('This paper is already published. Ask an editor if it needs changes.');
                }

                $allowed = $isEditor ? ['draft', 'publish', 'unpublish'] : ['draft', 'submit', 'withdraw'];
                if (!in_array($action, $allowed, true)) {
                    throw new Exception('That action isn’t available to you.');
                }

                // validation
                $doi = Inkwell\DoiLookup::normalize($values['doi']);
                if ($doi === null) {
                    throw new Exception('Please enter a valid DOI (it starts with “10.”).');
                }
                if ($dupe = inkwell_paper_by_doi($doi, $paper)) {
                    throw new Exception('This paper is already on ' . $kirby->site()->title() . ': “' . $dupe->title() . '”.');
                }
                if ($values['title'] === '') {
                    throw new Exception('The paper needs a title.');
                }
                $names = array_values(array_filter(array_map('trim', preg_split('/\R/', $values['authors']))));
                if ($names === []) {
                    throw new Exception('Please list at least one author.');
                }
                if (V::date($values['date']) === false) {
                    throw new Exception('Please enter the publication date as YYYY-MM-DD.');
                }
                if ($values['abstract'] === '') {
                    throw new Exception('Please add the abstract, or a short description in your own words.');
                }
                if ($values['arxiv'] !== '' && preg_match('/^\d{4}\.\d{4,5}$/', $values['arxiv']) !== 1) {
                    throw new Exception('The arXiv ID should look like 2501.12948.');
                }

                // a description written by the member is credited to them
                $source = $values['abstractSource'] !== ''
                    ? $values['abstractSource']
                    : 'Description written by ' . $user->name()->or($user->email()) . ' for ' . $kirby->site()->title();

                $total   = max(count($names), (int)$values['authorTotal']);
                $summary = array_filter(array_map('trim', preg_split('/\R{2,}/', $values['summary'])));
                $blocks  = $summary === [] ? [] : Blocks::factory(array_merge(
                    [['type' => 'heading', 'content' => ['level' => 'h2', 'text' => 'In plain language']]],
                    array_map(fn ($p) => ['type' => 'text', 'content' => ['text' => '<p>' . Str::esc($p) . '</p>']], $summary)
                ))->toArray();

                $content = [
                    'title'          => $values['title'],
                    'abstract'       => $values['abstract'],
                    'abstractSource' => $source,
                    'text'           => Json::encode($blocks),
                    'coauthors'      => implode(', ', array_map(fn ($n) => str_replace(',', '', $n), array_slice($names, 0, $maxNames))),
                    'authorTotal'    => $total > min(count($names), $maxNames) ? $total : '',
                    'date'           => $values['date'],
                    'venue'          => $values['venue'],
                    'volume'         => $values['volume'],
                    'pages'          => $values['pages'],
                    'doi'            => $doi,
                    'arxiv'          => $values['arxiv'],
                    'keywords'       => implode(', ', array_unique(array_filter(array_map('trim', explode(',', $values['keywords']))))),
                ];

                $paper = $as(function () use ($paper, $papers, $content, $user) {
                    if ($paper) {
                        return $paper->update($content);
                    }

                    $base = Str::slug(Str::short($content['title'], 70, '')) ?: 'paper';
                    $slug = $base;
                    for ($i = 2; $papers->childrenAndDrafts()->find($slug); $i++) {
                        $slug = $base . '-' . $i;
                    }

                    return $papers->createChild([
                        'slug'     => $slug,
                        'template' => 'paper',
                        'content'  => $content + ['submittedBy' => [$user->uuid()->toString()]],
                    ]);
                });

                $target = match ($action) {
                    'publish'  => 'listed',
                    'submit'   => 'unlisted',
                    'unpublish', 'withdraw' => 'draft',
                    default    => null,
                };
                if ($target !== null && $paper->status() !== $target) {
                    $paper = $as(fn () => $paper->changeStatus($target));
                }

                go($action === 'publish'
                    ? $paper->url()
                    : url('add-paper/' . $paper->slug(), ['query' => ['saved' => match ($action) {
                        'submit'    => 'submitted',
                        'withdraw'  => 'withdrawn',
                        'unpublish' => 'unpublished',
                        default     => 'draft',
                    }]]));
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }

    // the desk: editors see everything, contributors the papers they added
    $all  = $papers->childrenAndDrafts();
    $mine = fn ($pages) => $isEditor ? $pages : $pages->filter($isMine);

    return [
        'paper'     => $paper,
        'isEditor'  => $isEditor,
        'readOnly'  => $readOnly,
        'values'    => $values,
        'error'     => $error,
        'notice'    => $notice,
        'saved'     => get('saved'),
        'drafts'    => $mine($all->drafts())->sortBy('modified', 'desc')->limit(8),
        'inReview'  => $mine($all->unlisted())->sortBy('modified', 'desc')->limit(8),
        'published' => $mine($all->listed())->sortBy('date', 'desc')->limit(5),
    ];
};
