<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header') ?>

<article class="container section">
  <header class="page-head reading">
    <h1 class="page-head__title"><?= $page->title()->esc() ?></h1>
  </header>
  <div class="prose prose--story reading">
    <?= $page->text()->toBlocks() ?>
  </div>
</article>

<?php snippet('footer') ?>
