<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header') ?>

<div class="container section reading-wide">
  <header class="page-head">
    <p class="kicker"><?= $keyword ? 'Keyword' : 'Research &amp; preprints' ?></p>
    <h1 class="page-head__title"><?= $keyword ? esc($keyword) : $page->title()->esc() ?></h1>
    <?php if ($keyword): ?>
      <p class="page-head__sub">Papers tagged “<?= esc($keyword) ?>” · <a href="<?= $page->url() ?>">Show all</a></p>
    <?php elseif ($page->intro()->isNotEmpty()): ?>
      <p class="page-head__sub"><?= $page->intro()->esc() ?></p>
    <?php endif ?>
  </header>

  <?php if ($papers->isEmpty()): ?>
    <p class="empty">No papers published yet.</p>
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
