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
$doiUrl = $page->doi()->isNotEmpty() ? 'https://doi.org/' . $page->doi()->value() : null;
?>
<?php snippet('header') ?>

<article class="paper">
  <header class="paper__head reading-wide">
    <p class="eyebrow">
      <span class="sticker sticker--paper">Research paper</span>
      <span class="eyebrow__tag"><?= $page->date()->toDate('F Y') ?></span>
    </p>
    <h1 class="paper__title"><?= $page->title()->esc() ?></h1>
    <p class="paper__authors">
      <?= implode('<span class="sep">, </span>', $names) ?>
      <?php if ($page->hasMoreAuthors()): ?>
        <span class="paper__etal">et al. · <?= $page->authorTotal()->toInt() ?> authors</span>
      <?php endif ?>
    </p>
  </header>

  <div class="paper__layout reading-wide">
    <div>
      <section class="index-card">
        <p class="index-card__label">Abstract</p>
        <p class="index-card__text"><?= $page->abstract()->esc() ?></p>
        <?php if ($page->abstractSource()->isNotEmpty()): ?>
          <p class="index-card__source">Abstract: <?= $page->abstractSource()->esc() ?></p>
        <?php endif ?>
      </section>

      <?php if ($page->text()->isNotEmpty()): ?>
        <section class="prose">
          <?= $page->text()->toBlocks() ?>
        </section>
      <?php endif ?>

      <details class="cite">
        <summary>Cite this paper <span aria-hidden="true">+</span></summary>
        <pre><code><?= esc($page->bibtex()) ?></code></pre>
      </details>
    </div>

    <aside class="paper__aside">
      <dl class="facts">
        <?php if ($page->venue()->isNotEmpty()): ?>
          <div><dt>Published in</dt><dd><?= $page->venue()->esc() ?><?php if ($page->volume()->isNotEmpty()): ?> <span class="facts__muted"><?= $page->volume()->esc() ?><?= $page->pages()->isNotEmpty() ? ', ' . $page->pages()->esc() : '' ?></span><?php endif ?></dd></div>
        <?php endif ?>
        <div><dt>Date</dt><dd><?= $page->date()->toDate('j F Y') ?></dd></div>
        <?php if ($page->doi()->isNotEmpty()): ?>
          <div><dt>DOI</dt><dd><a href="<?= esc($doiUrl, 'attr') ?>" rel="noopener"><?= $page->doi()->esc() ?></a></dd></div>
        <?php endif ?>
        <?php if ($page->arxiv()->isNotEmpty()): ?>
          <div><dt>Preprint</dt><dd><a href="https://arxiv.org/abs/<?= $page->arxiv()->esc('attr') ?>" rel="noopener">arXiv:<?= $page->arxiv()->esc() ?></a></dd></div>
        <?php endif ?>
        <div><dt>Reading time</dt><dd><?= $page->readingTime() ?> min</dd></div>
      </dl>

      <div class="paper__actions">
        <?php if ($pdf): ?>
          <a class="btn btn--accent btn--block" href="<?= $pdf->url() ?>" download>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
            Download PDF<?= $pdf->pages()->isNotEmpty() ? ' · ' . $pdf->pages()->esc() . ' pp.' : '' ?>
          </a>
        <?php elseif ($doiUrl): ?>
          <a class="btn btn--accent btn--block" href="<?= esc($doiUrl, 'attr') ?>" rel="noopener">
            Read in <?= $page->venue()->or('the journal')->esc() ?> ↗
          </a>
        <?php endif ?>
        <a class="btn btn--block" href="<?= $page->url() ?>.bib">Download BibTeX</a>
      </div>

      <?php if ($page->keywords()->isNotEmpty()): ?>
        <p class="kicker">Keywords</p>
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
