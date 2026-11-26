<?php
/** @var Kirby\Cms\Page $page */
/** @var Kirby\Cms\Page|null $paper */
$status = match (true) {
    $paper === null      => ['new', t('status.newPaper')],
    $paper->isListed()   => ['published', t('status.published')],
    $paper->isUnlisted() => ['in-review', t('status.review')],
    default              => ['draft', t('status.draft')],
};
$action   = $paper ? lurl('add-paper/' . $paper->slug()) : lurl('add-paper');
$ro       = $readOnly ? 'readonly' : '';
$original = $versions[0] ?? null;
?>
<?php snippet('header', ['description' => t('ap.intro')]) ?>

<form class="writer paper-form" method="post" action="<?= $action ?>" data-paper-form data-lookup="<?= lurl('add-paper/lookup') ?>">
  <input type="hidden" name="csrf" value="<?= csrf() ?>">

  <div class="container writer__layout">
    <div class="writer__main">
      <p class="writer__status">
        <span class="writer__dot writer__dot--<?= $status[0] ?>"></span>
        <?= $status[1] ?>
        <?php if ($paper): ?> · <?= tt('status.saved', null, ['date' => inkwell_date($paper->modified(), 'j M, H:i')]) ?><?php endif ?>
      </p>

      <h1 class="paper-form__title"><?= $paper ? t('ap.edit') : kti(t('ap.title')) ?></h1>
      <p class="paper-form__intro"><?= t('ap.intro') ?></p>

      <?php if ($paper): ?>
        <nav class="lang-bar" aria-label="<?= t('lang.version') ?>">
          <span class="lang-bar__label"><?= t('lang.version') ?>:</span>
          <?php foreach ($kirby->languages() as $language): ?>
            <?php $code = $language->code(); $exists = in_array($code, $versions, true) ?>
            <a href="<?= lurl('add-paper/' . $paper->slug(), [], $code) ?>" class="lang-bar__item <?= e($code === $lang, 'is-current') ?> <?= e(!$exists, 'is-missing') ?>" <?= e($code === $lang, 'aria-current="true"') ?>>
              <?= t('lang.' . $code) ?><?php if (!$exists): ?> <small>(<?= t('lang.missing') ?>)</small><?php endif ?>
            </a>
          <?php endforeach ?>
        </nav>
      <?php endif ?>

      <div data-messages>
        <?php if ($error): ?>
          <div class="notice notice--error" role="alert"><?= esc($error) ?></div>
        <?php elseif ($notice): ?>
          <div class="notice" role="status"><?= esc($notice) ?></div>
        <?php elseif ($readOnly): ?>
          <div class="notice" role="status"><?= t('ap.readonly') ?></div>
        <?php elseif ($saved === 'draft'): ?>
          <div class="notice" role="status"><?= t('saved.draft') ?></div>
        <?php elseif ($saved === 'submitted'): ?>
          <div class="notice" role="status"><?= $isEditor ? t('saved.submitted.editor') : t('ap.saved.submitted') ?></div>
        <?php elseif ($saved === 'withdrawn'): ?>
          <div class="notice" role="status"><?= t('ap.saved.withdrawn') ?></div>
        <?php elseif ($saved === 'unpublished'): ?>
          <div class="notice" role="status"><?= t('saved.unpublished') ?></div>
        <?php elseif ($translating && $original): ?>
          <div class="notice notice--soft" role="status"><?= esc(tt('lang.translating', null, ['language' => inkwell_lang_name($lang), 'original' => inkwell_lang_name($original)])) ?></div>
        <?php endif ?>
      </div>

      <div class="doi-box">
        <label class="field__label" for="doi">DOI</label>
        <div class="doi-box__row">
          <input class="input input--doi" id="doi" name="doi" value="<?= esc($values['doi'], 'attr') ?>" placeholder="<?= esc(t('ap.doi.placeholder'), 'attr') ?>" autocomplete="off" spellcheck="false" required <?= $ro ?> <?= e($paper === null, 'autofocus') ?>>
          <?php if (!$readOnly): ?>
            <button class="btn btn--accent" type="submit" name="action" value="lookup" formnovalidate data-fetch><?= t('ap.fetch') ?></button>
          <?php endif ?>
        </div>
        <p class="field__help"><?= t('ap.doi.help') ?></p>
      </div>

      <fieldset class="paper-fields" <?= e($readOnly, 'disabled') ?> data-fields>
        <div class="field">
          <label class="field__label" for="title"><?= t('ap.f.title') ?></label>
          <textarea class="input input--title" id="title" name="title" rows="2" required data-autogrow><?= esc($values['title']) ?></textarea>
        </div>

        <div class="field">
          <label class="field__label" for="authors"><?= t('ap.f.authors') ?> <span class="field__count" data-author-count></span></label>
          <textarea class="input" id="authors" name="authors" rows="4" placeholder="<?= esc(t('ap.f.authors.placeholder'), 'attr') ?>" required><?= esc($values['authors']) ?></textarea>
          <p class="field__help"><?= t('ap.f.authors.help') ?></p>
        </div>
        <input type="hidden" name="authorTotal" value="<?= esc($values['authorTotal'], 'attr') ?>">

        <div class="field-grid">
          <div class="field">
            <label class="field__label" for="venue"><?= t('ap.f.venue') ?></label>
            <input class="input" id="venue" name="venue" value="<?= esc($values['venue'], 'attr') ?>">
          </div>
          <div class="field">
            <label class="field__label" for="date"><?= t('ap.f.date') ?></label>
            <input class="input" id="date" name="date" type="date" value="<?= esc($values['date'], 'attr') ?>" required>
          </div>
          <div class="field">
            <label class="field__label" for="volume"><?= t('ap.f.volume') ?></label>
            <input class="input" id="volume" name="volume" value="<?= esc($values['volume'], 'attr') ?>">
          </div>
          <div class="field">
            <label class="field__label" for="pages"><?= t('ap.f.pages') ?></label>
            <input class="input" id="pages" name="pages" value="<?= esc($values['pages'], 'attr') ?>">
          </div>
          <div class="field">
            <label class="field__label" for="arxiv"><?= t('ap.f.arxiv') ?> <span class="field__optional"><?= t('optional') ?></span></label>
            <input class="input" id="arxiv" name="arxiv" value="<?= esc($values['arxiv'], 'attr') ?>" placeholder="2501.12948">
          </div>
          <div class="field">
            <label class="field__label" for="keywords"><?= t('ap.f.keywords') ?></label>
            <input class="input" id="keywords" name="keywords" value="<?= esc($values['keywords'], 'attr') ?>" placeholder="<?= esc(t('ap.f.keywords.placeholder'), 'attr') ?>">
          </div>
        </div>

        <div class="field">
          <label class="field__label" for="abstract"><?= t('ap.f.abstract') ?></label>
          <div class="notice notice--soft" data-license-note <?= e($values['abstract'] !== '' || $values['doi'] === '', 'hidden') ?>><?= t('ap.licence') ?></div>
          <textarea class="input input--abstract" id="abstract" name="abstract" rows="9" required lang="en"><?= esc($values['abstract']) ?></textarea>
          <input type="hidden" name="abstractSource" value="<?= esc($values['abstractSource'], 'attr') ?>" data-abstract-source>
          <p class="field__help" data-source-label><?= $values['abstractSource'] !== '' ? esc(tt('paper.source', null, ['source' => inkwell_source_label($values['abstractSource'])])) : t('ap.source.you') ?></p>
        </div>

        <div class="field">
          <label class="field__label" for="summary"><?= t('ap.f.summary') ?> <span class="field__optional"><?= t('optional') ?></span></label>
          <textarea class="input" id="summary" name="summary" rows="5" placeholder="<?= esc(t('ap.f.summary.placeholder'), 'attr') ?>"><?= esc($values['summary']) ?></textarea>
        </div>
      </fieldset>
    </div>

    <aside class="writer__aside">
      <div class="publish-card">
        <p class="kicker"><?= t('pub.kicker') ?></p>

        <div class="publish-card__actions">
          <?php if ($readOnly): ?>
            <a class="btn btn--block" href="<?= $paper->url() ?>"><?= t('btn.viewPaper') ?></a>
          <?php elseif ($isEditor && $paper && $paper->isListed()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish"><?= t('btn.update') ?></button>
            <button class="btn btn--block" type="submit" name="action" value="unpublish"><?= t('btn.unpublish') ?></button>
          <?php elseif ($isEditor): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish"><?= $paper && $paper->isUnlisted() ? t('btn.approve') : t('btn.publish') ?></button>
            <button class="btn btn--block" type="submit" name="action" value="draft"><?= $paper && $paper->isUnlisted() ? t('btn.save') : t('btn.saveDraft') ?></button>
          <?php elseif ($paper && $paper->isUnlisted()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="submit"><?= t('btn.saveChanges') ?></button>
            <button class="btn btn--block" type="submit" name="action" value="withdraw"><?= t('btn.withdraw') ?></button>
          <?php else: ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="submit"><?= t('btn.submit') ?></button>
            <button class="btn btn--block" type="submit" name="action" value="draft"><?= t('btn.saveDraft') ?></button>
          <?php endif ?>
        </div>

        <?php if (!$isEditor && !$readOnly): ?>
          <p class="field__help publish-card__note"><?= t('pub.note.paper') ?></p>
        <?php endif ?>
        <?php if ($guide = page('guides/add-a-paper')): ?>
          <p class="publish-card__guide"><?= t('pub.guide.q') ?> <a href="<?= $guide->url() ?>"><?= t('pub.guide.link') ?></a></p>
        <?php endif ?>

        <?php if ($paper): ?>
          <p class="publish-card__links">
            <a href="<?= $paper->isDraft() ? $paper->previewUrl() : $paper->url() ?>" target="_blank" rel="noopener"><?= $paper->isListed() ? t('pub.live') : t('pub.preview') ?></a>
            <?php if ($isEditor): ?>
              <a href="<?= $paper->panel()->url() ?>"><?= t('pub.advanced') ?></a>
            <?php endif ?>
          </p>
        <?php endif ?>
      </div>

      <div class="drafts-card">
        <div class="drafts-card__head">
          <p class="kicker"><?= $isEditor ? t('desk.papers.editor') : t('desk.papers') ?></p>
          <a href="<?= lurl('add-paper') ?>" class="drafts-card__new"><?= t('desk.new') ?></a>
        </div>
        <?php
        $groups = [
            ['label' => $isEditor ? t('desk.awaiting') : t('desk.submitted'), 'items' => $inReview, 'count' => $isEditor, 'date' => 'modified'],
            ['label' => t('desk.drafts'), 'items' => $drafts, 'count' => false, 'date' => 'modified'],
            ['label' => $isEditor ? t('desk.recent') : t('desk.published'), 'items' => $published, 'count' => false, 'date' => 'date'],
        ];
        $empty = true;
        ?>
        <?php foreach ($groups as $group): ?>
          <?php if ($group['items']->isEmpty()) continue; $empty = false; ?>
          <p class="drafts-card__label"><?= $group['label'] ?><?php if ($group['count']): ?> <span class="count"><?= $group['items']->count() ?></span><?php endif ?></p>
          <ul class="drafts-card__list">
            <?php foreach ($group['items'] as $item): ?>
              <li <?= e($paper && $item->is($paper), 'aria-current="true"') ?>>
                <a href="<?= lurl('add-paper/' . $item->slug()) ?>"><?= $item->title()->esc() ?></a>
                <span><?= $group['date'] === 'date' ? $item->date()->toDate('Y') : inkwell_date($item->modified(), 'j M') ?></span>
              </li>
            <?php endforeach ?>
          </ul>
        <?php endforeach ?>
        <?php if ($empty): ?>
          <p class="drafts-card__empty"><?= t('desk.empty.papers') ?></p>
        <?php endif ?>
      </div>
    </aside>
  </div>
</form>

<script>window.INKWELL_I18N = <?= json_encode([
    'fetch'      => t('ap.fetch'),
    'fetching'   => t('ap.fetching'),
    'looking'    => t('ap.js.looking'),
    'fetched'    => t('ap.js.fetched'),
    'preprint'   => t('ap.js.preprint'),
    'check'      => t('ap.js.check'),
    'ownWords'   => t('ap.js.ownWords'),
    'continue'   => t('ap.js.continue'),
    'view'       => t('ap.js.view'),
    'network'    => t('ap.js.network'),
    'unexpected' => t('ap.js.unexpected'),
    'source'     => t('paper.source'),
    'sourceYou'  => t('ap.source.you'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="<?= asset_url('assets/js/paper.js') ?>" defer></script>

<?php snippet('footer', ['cta' => false]) ?>
