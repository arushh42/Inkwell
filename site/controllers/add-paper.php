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
        go(lurl('add-paper'));
    }

    $isMine = fn ($p) => $p->submittedBy()->toUsers()->has($user);

    if ($paper && !$isEditor && !$isMine($paper)) {
        go(lurl('add-paper'));
    }

    $readOnly = $paper && !$isEditor && $paper->isListed();

    $lang        = $kirby->language()->code();
    $versions    = $paper ? inkwell_versions($paper) : [];
    $translating = $paper !== null && !in_array($lang, $versions, true);
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
                    throw new Exception(t('error.session'));
                }
                $doi = Inkwell\DoiLookup::normalize($values['doi']);
                if ($doi === null) {
                    throw new Exception(t('doi.error.invalid'));
                }
                if ($dupe = inkwell_paper_by_doi($doi, $paper)) {
                    throw new Exception(tt('doi.error.exists.named', null, ['title' => $dupe->title()->value()]));
                }
                try {
                    $found = (new Inkwell\DoiLookup())->lookup($doi);
                } catch (RuntimeException $e) {
                    throw new Exception($e->getCode() === 404 ? t('doi.error.notFound') : t('doi.error.unavailable'));
                }
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
                $notice = tt($found['abstractOpen'] ? 'ap.notice.fetched' : 'ap.notice.fetched.own', null, ['registry' => $found['registry']]);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            try {
                if (csrf(get('csrf')) !== true) {
                    throw new Exception(t('error.session'));
                }

                if ($readOnly) {
                    throw new Exception(t('ap.readonly'));
                }

                $allowed = $isEditor ? ['draft', 'publish', 'unpublish'] : ['draft', 'submit', 'withdraw'];
                if (!in_array($action, $allowed, true)) {
                    throw new Exception(t('write.error.action'));
                }

                // validation
                $doi = Inkwell\DoiLookup::normalize($values['doi']);
                if ($doi === null) {
                    throw new Exception(t('ap.error.doi'));
                }
                if ($dupe = inkwell_paper_by_doi($doi, $paper)) {
                    throw new Exception(tt('doi.error.exists.named', null, ['title' => $dupe->title()->value()]));
                }
                if ($values['title'] === '') {
                    throw new Exception(t('ap.error.title'));
                }
                $names = array_values(array_filter(array_map('trim', preg_split('/\R/', $values['authors']))));
                if ($names === []) {
                    throw new Exception(t('ap.error.authors'));
                }
                if (V::date($values['date']) === false) {
                    throw new Exception(t('ap.error.date'));
                }
                if ($values['abstract'] === '') {
                    throw new Exception(t('ap.error.abstract'));
                }
                if ($values['arxiv'] !== '' && preg_match('/^\d{4}\.\d{4,5}$/', $values['arxiv']) !== 1) {
                    throw new Exception(t('ap.error.arxiv'));
                }

                // a description written by the member is credited to them
                $source = $values['abstractSource'] !== ''
                    ? $values['abstractSource']
                    : 'Description written by ' . $user->name()->or($user->email()) . ' for ' . $kirby->site()->title();

                $total   = max(count($names), (int)$values['authorTotal']);
                $summary = array_filter(array_map('trim', preg_split('/\R{2,}/', $values['summary'])));
                $blocks  = $summary === [] ? [] : Blocks::factory(array_merge(
                    [['type' => 'heading', 'content' => ['level' => 'h2', 'text' => t('ap.f.summary')]]],
                    array_map(fn ($p) => ['type' => 'text', 'content' => ['text' => '<p>' . Str::esc($p) . '</p>']], $summary)
                ))->toArray();

                // only the plain-language summary differs between languages;
                // the paper's own metadata (title, authors, abstract…) is shared
                $text    = ['text' => Json::encode($blocks)];
                $content = [
                    'title'          => $values['title'],
                    'abstract'       => $values['abstract'],
                    'abstractSource' => $source,
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

                $paper = $as(function () use ($paper, $papers, $content, $text, $user, $lang) {
                    if ($paper) {
                        return inkwell_save_version($paper, $text, $content, $lang);
                    }

                    $base = Str::slug(Str::short($content['title'], 70, '')) ?: 'paper';
                    $slug = $base;
                    for ($i = 2; $papers->childrenAndDrafts()->find($slug); $i++) {
                        $slug = $base . '-' . $i;
                    }

                    return $papers->createChild([
                        'slug'     => $slug,
                        'template' => 'paper',
                        'content'  => $text + $content + ['submittedBy' => [$user->uuid()->toString()], 'availableIn' => [$lang]],
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
                    : lurl('add-paper/' . $paper->slug(), ['saved' => match ($action) {
                        'submit'    => 'submitted',
                        'withdraw'  => 'withdrawn',
                        'unpublish' => 'unpublished',
                        default     => 'draft',
                    }]));
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
        'lang'      => $lang,
        'versions'  => $versions,
        'translating' => $translating,
        'values'    => $values,
        'error'     => $error,
        'notice'    => $notice,
        'saved'     => get('saved'),
        'drafts'    => $mine($all->drafts())->sortBy('modified', 'desc')->limit(8),
        'inReview'  => $mine($all->unlisted())->sortBy('modified', 'desc')->limit(8),
        'published' => $mine($all->listed())->sortBy('date', 'desc')->limit(5),
    ];
};
