<?php
/** @var Kirby\Cms\Page $page */
$pdf = $page->pdf()->toFile();

$names = [];
foreach ($page->authors()->toPages() as $author) {
    $names[] = '<a href="' . $author->url() . '">' . $author->title()->esc() . '</a>';
}
foreach ($page->coauthors()->split(',') as $name) {
    $names[] = esc($name);
}
$doiUrl   = $page->doi()->isNotEmpty() ? 'https://doi.org/' . $page->doi()->value() : null;
$textLang = inkwell_is_translated($page) ? null : inkwell_versions($page)[0];
?>
<?php snippet('header') ?>

<article class="paper">
  <header class="paper__head reading-wide">
    <p class="eyebrow">
      <span class="sticker sticker--paper"><?= t('paper.label') ?></span>
      <span class="eyebrow__tag"><?= inkwell_date($page->date(), 'F Y') ?></span>
    </p>
    <h1 class="paper__title" lang="en"><?= $page->title()->esc() ?></h1>
    <p class="paper__authors">
      <?= implode('<span class="sep">, </span>', $names) ?>
      <?php if ($page->hasMoreAuthors()): ?>
        <span class="paper__etal"><?= tt('paper.etal.count', null, ['count' => $page->authorTotal()->toInt()]) ?></span>
      <?php endif ?>
    </p>
  </header>

  <div class="paper__layout reading-wide">
    <div>
      <section class="index-card">
        <p class="index-card__label"><?= t('paper.abstract') ?></p>
        <p class="index-card__text" lang="en"><?= $page->abstract()->esc() ?></p>
        <?php if ($page->abstractSource()->isNotEmpty()): ?>
          <p class="index-card__source"><?= esc(tt('paper.source', null, ['source' => inkwell_source_label($page->abstractSource()->value())])) ?></p>
        <?php endif ?>
      </section>

      <?php if ($page->text()->isNotEmpty()): ?>
        <section class="prose" <?= $textLang ? 'lang="' . $textLang . '"' : '' ?>>
          <?= $page->text()->toBlocks() ?>
        </section>
      <?php endif ?>

      <details class="cite">
        <summary><?= t('paper.cite') ?> <span aria-hidden="true">+</span></summary>
        <pre><code><?= esc($page->bibtex()) ?></code></pre>
      </details>
    </div>

    <aside class="paper__aside">
      <dl class="facts">
        <?php if ($page->venue()->isNotEmpty()): ?>
          <div><dt><?= t('paper.publishedIn') ?></dt><dd><?= $page->venue()->esc() ?><?php if ($page->volume()->isNotEmpty()): ?> <span class="facts__muted"><?= $page->volume()->esc() ?><?= $page->pages()->isNotEmpty() ? ', ' . $page->pages()->esc() : '' ?></span><?php endif ?></dd></div>
        <?php endif ?>
        <div><dt><?= t('paper.date') ?></dt><dd><?= inkwell_date($page->date(), 'j F Y') ?></dd></div>
        <?php if ($page->doi()->isNotEmpty()): ?>
          <div><dt>DOI</dt><dd><a href="<?= esc($doiUrl, 'attr') ?>" rel="noopener"><?= $page->doi()->esc() ?></a></dd></div>
        <?php endif ?>
        <?php if ($page->arxiv()->isNotEmpty()): ?>
          <div><dt><?= t('paper.preprint') ?></dt><dd><a href="https://arxiv.org/abs/<?= $page->arxiv()->esc('attr') ?>" rel="noopener">arXiv:<?= $page->arxiv()->esc() ?></a></dd></div>
        <?php endif ?>
        <div><dt><?= t('paper.readingTime') ?></dt><dd><?= tt('paper.minutes', null, ['n' => $page->readingTime()]) ?></dd></div>
      </dl>

      <div class="paper__actions">
        <?php if ($pdf): ?>
          <a class="btn btn--accent btn--block" href="<?= $pdf->url() ?>" download>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
            <?= t('paper.pdf') ?><?= $pdf->pages()->isNotEmpty() ? ' · ' . tt('paper.pdf.pages', null, ['n' => $pdf->pages()->value()]) : '' ?>
          </a>
        <?php elseif ($doiUrl): ?>
          <a class="btn btn--accent btn--block" href="<?= esc($doiUrl, 'attr') ?>" rel="noopener">
            <?= $page->venue()->isNotEmpty() ? esc(tt('paper.read', null, ['venue' => $page->venue()->value()])) : t('paper.read.generic') ?>
          </a>
        <?php endif ?>
        <a class="btn btn--block" href="<?= lurl('papers/' . $page->slug() . '.bib') ?>"><?= t('paper.bibtex') ?></a>
      </div>

      <?php if ($page->keywords()->isNotEmpty()): ?>
        <p class="kicker"><?= t('paper.keywords') ?></p>
        <div class="tag-cloud">
          <?php foreach ($page->keywords()->split(',') as $kw): ?>
            <a class="tag" href="<?= url('papers', ['params' => ['keyword' => urlencode($kw)]]) ?>"><?= esc($kw) ?></a>
          <?php endforeach ?>
        </div>
      <?php endif ?>
    </aside>
  </div>
</article>

<?php snippet('footer') ?>
