<?php
/** @var Kirby\Cms\Page $page */
/** @var Kirby\Cms\Page|null $paper */
$status = match (true) {
    $paper === null      => 'New paper',
    $paper->isListed()   => 'Published',
    $paper->isUnlisted() => 'In review',
    default              => 'Draft',
};
$action = $paper ? url('add-paper/' . $paper->slug()) : url('add-paper');
$ro     = $readOnly ? 'readonly' : '';
?>
<?php snippet('header', ['description' => 'Add a research paper to ' . $site->title()]) ?>

<form class="writer paper-form" method="post" action="<?= $action ?>" data-paper-form data-lookup="<?= url('add-paper/lookup') ?>">
  <input type="hidden" name="csrf" value="<?= csrf() ?>">

  <div class="container writer__layout">
    <div class="writer__main">
      <p class="writer__status">
        <span class="writer__dot writer__dot--<?= Kirby\Toolkit\Str::slug($status) ?>"></span>
        <?= $status ?>
        <?php if ($paper): ?> · last saved <?= $paper->modified('j M, H:i') ?><?php endif ?>
      </p>

      <h1 class="paper-form__title"><?= $paper ? 'Edit paper' : 'Add a <em>paper</em>' ?></h1>
      <p class="paper-form__intro">Paste a DOI and we’ll fill in the title, authors, journal and abstract for you. Check the details, add a plain-language summary, and <?= $isEditor ? 'publish' : 'submit it for review' ?>.</p>

      <div data-messages>
        <?php if ($error): ?>
          <div class="notice notice--error" role="alert"><?= esc($error) ?></div>
        <?php elseif ($notice): ?>
          <div class="notice" role="status"><?= esc($notice) ?></div>
        <?php elseif ($readOnly): ?>
          <div class="notice" role="status">This paper is published. If it needs changes, ask an editor.</div>
        <?php elseif ($saved === 'draft'): ?>
          <div class="notice" role="status">Draft saved. Only you and the editors can see it.</div>
        <?php elseif ($saved === 'submitted'): ?>
          <div class="notice" role="status">Submitted for review! An editor will check it and publish it.</div>
        <?php elseif ($saved === 'withdrawn'): ?>
          <div class="notice" role="status">Submission withdrawn. The paper is a draft again.</div>
        <?php elseif ($saved === 'unpublished'): ?>
          <div class="notice" role="status">Paper unpublished and moved back to drafts.</div>
        <?php endif ?>
      </div>

      <div class="doi-box">
        <label class="field__label" for="doi">DOI</label>
        <div class="doi-box__row">
          <input class="input input--doi" id="doi" name="doi" value="<?= esc($values['doi'], 'attr') ?>" placeholder="10.1038/s41586-025-09422-z or https://doi.org/…" autocomplete="off" spellcheck="false" required <?= $ro ?> <?= e($paper === null, 'autofocus') ?>>
          <?php if (!$readOnly): ?>
            <button class="btn btn--accent" type="submit" name="action" value="lookup" formnovalidate data-fetch>Fetch details</button>
          <?php endif ?>
        </div>
        <p class="field__help">Works for journal articles, conference papers and arXiv preprints. Pasting a DOI fetches the details automatically.</p>
      </div>

      <fieldset class="paper-fields" <?= e($readOnly, 'disabled') ?> data-fields>
        <div class="field">
          <label class="field__label" for="title">Title</label>
          <textarea class="input input--title" id="title" name="title" rows="2" required data-autogrow><?= esc($values['title']) ?></textarea>
        </div>

        <div class="field">
          <label class="field__label" for="authors">Authors <span class="field__count" data-author-count></span></label>
          <textarea class="input" id="authors" name="authors" rows="4" placeholder="One author per line, in byline order" required><?= esc($values['authors']) ?></textarea>
          <p class="field__help">One per line. Long lists are shown as the first 8 names + “et al.”</p>
        </div>
        <input type="hidden" name="authorTotal" value="<?= esc($values['authorTotal'], 'attr') ?>">

        <div class="field-grid">
          <div class="field">
            <label class="field__label" for="venue">Journal / conference</label>
            <input class="input" id="venue" name="venue" value="<?= esc($values['venue'], 'attr') ?>">
          </div>
          <div class="field">
            <label class="field__label" for="date">Published</label>
            <input class="input" id="date" name="date" type="date" value="<?= esc($values['date'], 'attr') ?>" required>
          </div>
          <div class="field">
            <label class="field__label" for="volume">Volume</label>
            <input class="input" id="volume" name="volume" value="<?= esc($values['volume'], 'attr') ?>">
          </div>
          <div class="field">
            <label class="field__label" for="pages">Pages</label>
            <input class="input" id="pages" name="pages" value="<?= esc($values['pages'], 'attr') ?>">
          </div>
          <div class="field">
            <label class="field__label" for="arxiv">arXiv ID <span class="field__optional">optional</span></label>
            <input class="input" id="arxiv" name="arxiv" value="<?= esc($values['arxiv'], 'attr') ?>" placeholder="2501.12948">
          </div>
          <div class="field">
            <label class="field__label" for="keywords">Keywords</label>
            <input class="input" id="keywords" name="keywords" value="<?= esc($values['keywords'], 'attr') ?>" placeholder="Machine learning, Reasoning">
          </div>
        </div>

        <div class="field">
          <label class="field__label" for="abstract">Abstract</label>
          <div class="notice notice--soft" data-license-note <?= e($values['abstract'] !== '' || $values['doi'] === '', 'hidden') ?>>
            This paper’s abstract isn’t openly licensed, so we can’t copy it. Please describe the paper in a few sentences of your own.
          </div>
          <textarea class="input input--abstract" id="abstract" name="abstract" rows="9" required><?= esc($values['abstract']) ?></textarea>
          <input type="hidden" name="abstractSource" value="<?= esc($values['abstractSource'], 'attr') ?>" data-abstract-source>
          <p class="field__help" data-source-label><?= $values['abstractSource'] !== '' ? 'Source: ' . esc($values['abstractSource']) : 'Written by you — it will be credited to you.' ?></p>
        </div>

        <div class="field">
          <label class="field__label" for="summary">In plain language <span class="field__optional">optional</span></label>
          <textarea class="input" id="summary" name="summary" rows="5" placeholder="What did they find, and why does it matter? Two or three sentences for a curious non-expert."><?= esc($values['summary']) ?></textarea>
        </div>
      </fieldset>
    </div>

    <aside class="writer__aside">
      <div class="publish-card">
        <p class="kicker">Publishing</p>

        <div class="publish-card__actions">
          <?php if ($readOnly): ?>
            <a class="btn btn--block" href="<?= $paper->url() ?>">View published paper</a>
          <?php elseif ($isEditor && $paper && $paper->isListed()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish">Update paper</button>
            <button class="btn btn--block" type="submit" name="action" value="unpublish">Unpublish</button>
          <?php elseif ($isEditor): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="publish"><?= $paper && $paper->isUnlisted() ? 'Approve &amp; publish' : 'Publish' ?></button>
            <button class="btn btn--block" type="submit" name="action" value="draft">Save <?= $paper && $paper->isUnlisted() ? '' : 'draft' ?></button>
          <?php elseif ($paper && $paper->isUnlisted()): ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="submit">Save changes</button>
            <button class="btn btn--block" type="submit" name="action" value="withdraw">Withdraw submission</button>
          <?php else: ?>
            <button class="btn btn--accent btn--block" type="submit" name="action" value="submit">Submit for review</button>
            <button class="btn btn--block" type="submit" name="action" value="draft">Save draft</button>
          <?php endif ?>
        </div>

        <?php if (!$isEditor && !$readOnly): ?>
          <p class="field__help publish-card__note">An editor reviews every paper before it goes live.</p>
        <?php endif ?>

        <?php if ($paper): ?>
          <p class="publish-card__links">
            <a href="<?= $paper->isDraft() ? $paper->previewUrl() : $paper->url() ?>" target="_blank" rel="noopener"><?= $paper->isListed() ? 'View live ↗' : 'Preview ↗' ?></a>
            <?php if ($isEditor): ?>
              <a href="<?= $paper->panel()->url() ?>">Advanced settings (classic admin) ↗</a>
            <?php endif ?>
          </p>
        <?php endif ?>
      </div>

      <div class="drafts-card">
        <div class="drafts-card__head">
          <p class="kicker"><?= $isEditor ? 'Papers' : 'Your papers' ?></p>
          <a href="<?= url('add-paper') ?>" class="drafts-card__new">+ New</a>
        </div>
        <?php
        $groups = [
            ($isEditor ? 'Awaiting review' : 'Submitted for review') => [$inReview, 'modified'],
            'Drafts' => [$drafts, 'modified'],
            ($isEditor ? 'Recently published' : 'Published') => [$published, 'date'],
        ];
        $empty = true;
        ?>
        <?php foreach ($groups as $label => [$items, $dateField]): ?>
          <?php if ($items->isEmpty()) continue; $empty = false; ?>
          <p class="drafts-card__label"><?= $label ?><?php if ($label === 'Awaiting review'): ?> <span class="count"><?= $items->count() ?></span><?php endif ?></p>
          <ul class="drafts-card__list">
            <?php foreach ($items as $item): ?>
              <li <?= e($paper && $item->is($paper), 'aria-current="true"') ?>>
                <a href="<?= url('add-paper/' . $item->slug()) ?>"><?= $item->title()->esc() ?></a>
                <span><?= $dateField === 'date' ? $item->date()->toDate('Y') : $item->modified('j M') ?></span>
              </li>
            <?php endforeach ?>
          </ul>
        <?php endforeach ?>
        <?php if ($empty): ?>
          <p class="drafts-card__empty">Papers you add will show up here.</p>
        <?php endif ?>
      </div>
    </aside>
  </div>
</form>

<script src="<?= asset_url('assets/js/paper.js') ?>" defer></script>

<?php snippet('footer', ['cta' => false]) ?>
