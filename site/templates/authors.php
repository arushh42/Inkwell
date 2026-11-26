<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header') ?>

<div class="container section">
  <header class="page-head">
    <p class="kicker"><?= t('authors.kicker') ?></p>
    <h1 class="page-head__title"><?= $page->title()->esc() ?></h1>
    <p class="page-head__sub"><?= t('authors.sub') ?></p>
  </header>

  <div class="author-grid">
    <?php foreach ($page->children()->listed() as $author): ?>
      <a class="author-card author-card--<?= $author->swatch() ?>" href="<?= $author->url() ?>">
        <?php snippet('avatar', ['author' => $author, 'size' => 'lg']) ?>
        <h2 class="author-card__name"><?= $author->title()->esc() ?></h2>
        <p class="author-card__role"><?= $author->role()->esc() ?></p>
        <p class="author-card__count">
          <span><?= inkwell_count('stories.count', $author->stories()->count()) ?></span>
          <span><?= inkwell_count('papers.count', $author->papers()->count()) ?></span>
        </p>
      </a>
    <?php endforeach ?>
  </div>
</div>

<?php snippet('footer') ?>
