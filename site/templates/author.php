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
      <p class="kicker">Contributor</p>
      <h1 class="profile__name"><?= $page->title()->esc() ?></h1>
      <p class="profile__role"><?= $page->role()->esc() ?></p>
      <p class="profile__bio"><?= $page->bio()->esc() ?></p>
      <p class="profile__links">
        <span class="profile__stat"><b><?= $stories->count() ?></b> stories</span>
        <span class="profile__stat"><b><?= $papers->count() ?></b> papers</span>
        <?php if ($page->website()->isNotEmpty()): ?><a class="btn btn--small" href="<?= $page->website()->esc('attr') ?>" rel="noopener">Website ↗</a><?php endif ?>
        <?php if ($page->email()->isNotEmpty()): ?><a class="btn btn--small" href="mailto:<?= $page->email()->esc('attr') ?>">Email</a><?php endif ?>
      </p>
    </div>
  </header>

  <?php if ($stories->isNotEmpty()): ?>
    <header class="section-head">
      <h2 class="section-head__title">Stories</h2>
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
      <h2 class="section-head__title">Papers</h2>
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
