<?php
/** @var Kirby\Cms\Page $page */
$stories = $page->stories();
$papers  = $page->papers();
?>
<?php snippet('header', ['description' => $page->bio()->excerpt(160)]) ?>

<div class="container section">
  <header class="profile profile--<?= $page->swatch() ?>">
    <?php snippet('avatar', ['author' => $page, 'size' => 'lg']) ?>
    <div>
      <p class="kicker"><?= t('author.kicker') ?></p>
      <h1 class="profile__name"><?= $page->title()->esc() ?></h1>
      <p class="profile__role"><?= $page->role()->esc() ?></p>
      <p class="profile__bio"><?= $page->bio()->esc() ?></p>
      <p class="profile__links">
        <span class="profile__stat"><?= inkwell_count('stories.count', $stories->count()) ?></span>
        <span class="profile__stat"><?= inkwell_count('papers.count', $papers->count()) ?></span>
        <?php if ($page->website()->isNotEmpty()): ?><a class="btn btn--small" href="<?= $page->website()->esc('attr') ?>" rel="noopener"><?= t('author.website') ?></a><?php endif ?>
        <?php if ($page->email()->isNotEmpty()): ?><a class="btn btn--small" href="mailto:<?= $page->email()->esc('attr') ?>"><?= t('author.email') ?></a><?php endif ?>
      </p>
    </div>
  </header>

  <?php if ($stories->isNotEmpty()): ?>
    <header class="section-head">
      <h2 class="section-head__title"><?= t('author.stories') ?></h2>
      <span class="section-head__rule" aria-hidden="true"></span>
    </header>
    <div class="card-grid">
      <?php foreach ($stories as $story): ?>
        <?php snippet('story-card', ['story' => $story]) ?>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <?php if ($papers->isNotEmpty()): ?>
    <header class="section-head">
      <h2 class="section-head__title"><?= t('author.papers') ?></h2>
      <span class="section-head__rule" aria-hidden="true"></span>
    </header>
    <div class="reading-wide">
      <?php foreach ($papers as $paper): ?>
        <?php snippet('paper-item', ['paper' => $paper]) ?>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>

<?php snippet('footer') ?>
