<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header') ?>

<div class="container section reading-wide">
  <header class="page-head">
    <p class="kicker"><?= $keyword ? t('papers.kicker.keyword') : t('papers.kicker') ?></p>
    <h1 class="page-head__title"><?= $keyword ? esc($keyword) : $page->title()->esc() ?></h1>
    <?php if ($keyword): ?>
      <p class="page-head__sub"><?= esc(tt('papers.keyword.sub', null, ['keyword' => $keyword])) ?> · <a href="<?= $page->url() ?>"><?= t('filters.clear') ?></a></p>
    <?php elseif ($page->intro()->isNotEmpty()): ?>
      <p class="page-head__sub"><?= $page->intro()->esc() ?></p>
    <?php endif ?>
  </header>

  <?php if ($papers->isEmpty()): ?>
    <p class="empty"><?= t('papers.empty') ?></p>
  <?php endif ?>

  <?php foreach ($byYear as $year => $group): ?>
    <section class="year-group">
      <h2 class="year-group__year"><?= $year ?></h2>
      <div>
        <?php foreach ($group as $paper): ?>
          <?php snippet('paper-item', ['paper' => $paper]) ?>
        <?php endforeach ?>
      </div>
    </section>
  <?php endforeach ?>

  <?php snippet('pagination', ['pagination' => $pagination]) ?>
</div>

<?php snippet('footer') ?>
