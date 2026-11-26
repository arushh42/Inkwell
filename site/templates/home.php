<?php
/** @var Kirby\Cms\Page $page */
$stories  = page('stories')->children()->listed()->sortBy('date', 'desc');
$featured = $page->featured()->toPages()->listed();

if ($featured->isEmpty()) {
    $featured = $stories->filterBy('featured', true)->limit(3);
}
if ($featured->isEmpty()) {
    $featured = $stories->limit(3);
}

$lead    = $featured->first();
$side    = $featured->not($lead);
$latest  = $stories->not($featured)->limit(6);
$papers  = page('papers')->children()->listed()->sortBy('date', 'desc');
$authors = page('authors')->children()->listed();
$tags    = page('stories')->children()->listed()->pluck('tags', ',', true);
?>
<?php snippet('header') ?>

<section class="hero container">
  <div class="hero__text">
    <p class="kicker"><?= t('home.kicker') ?></p>
    <h1 class="hero__title"><?= $page->headline()->kti() ?></h1>
    <?php if ($page->intro()->isNotEmpty()): ?>
      <p class="hero__intro"><?= $page->intro()->esc() ?></p>
    <?php endif ?>
    <div class="hero__actions">
      <a class="btn btn--accent" href="<?= page('stories')->url() ?>"><?= t('home.read') ?> →</a>
      <a class="btn" href="<?= page('papers')->url() ?>"><?= t('home.research') ?></a>
    </div>
  </div>

  <aside class="contents" aria-label="<?= t('home.issue') ?>">
    <p class="contents__title"><?= t('home.issue') ?></p>
    <ol class="contents__list">
      <li><a href="<?= page('stories')->url() ?>"><span><?= t('home.count.stories') ?></span><b><?= $stories->count() ?></b></a></li>
      <li><a href="<?= url('stories', ['params' => ['kind' => 'blog']]) ?>"><span><?= t('home.count.blog') ?></span><b><?= $stories->filterBy('kind', 'blog')->count() ?></b></a></li>
      <li><a href="<?= page('papers')->url() ?>"><span><?= t('home.count.papers') ?></span><b><?= $papers->count() ?></b></a></li>
      <li><a href="<?= page('authors')->url() ?>"><span><?= t('home.count.authors') ?></span><b><?= $authors->count() ?></b></a></li>
    </ol>
    <svg class="contents__stamp" viewBox="0 0 120 120" aria-hidden="true">
      <defs><path id="stamp-circle" d="M60 60m-44 0a44 44 0 1 1 88 0a44 44 0 1 1-88 0"/></defs>
      <circle class="contents__stamp-bg" cx="60" cy="60" r="58"/>
      <text><textPath href="#stamp-circle" textLength="272" lengthAdjust="spacing"><?= t('home.stamp') ?></textPath></text>
      <circle class="contents__stamp-dot" cx="60" cy="60" r="16"/>
    </svg>
  </aside>
</section>

<?php if (count($tags) > 0): ?>
<div class="ticker" aria-label="<?= t('stories.kicker.topic') ?>">
  <div class="ticker__track">
    <?php for ($copy = 0; $copy < 2; $copy++): ?>
      <div class="ticker__group" <?= e($copy === 1, 'aria-hidden="true"') ?>>
        <?php foreach ($tags as $tag): ?>
          <a href="<?= url('stories', ['params' => ['tag' => urlencode($tag)]]) ?>" <?= e($copy === 1, 'tabindex="-1"') ?>><?= esc($tag) ?></a>
          <span class="ticker__star" aria-hidden="true">✳</span>
        <?php endforeach ?>
      </div>
    <?php endfor ?>
  </div>
</div>
<?php endif ?>

<?php if ($lead): ?>
<section class="container section">
  <header class="section-head">
    <h2 class="section-head__title"><?= t('home.featured') ?></h2>
    <span class="section-head__rule" aria-hidden="true"></span>
    <a class="section-head__link" href="<?= page('stories')->url() ?>"><?= t('home.allStories') ?></a>
  </header>
  <div class="featured-grid">
    <?php snippet('story-card', ['story' => $lead, 'size' => 'large']) ?>
    <div class="featured-grid__side">
      <?php foreach ($side->values() as $i => $story): ?>
        <?php snippet('story-card', ['story' => $story, 'size' => 'side', 'index' => $i + 2]) ?>
      <?php endforeach ?>
    </div>
  </div>
</section>
<?php endif ?>

<div class="container section split">
  <section>
    <header class="section-head">
      <h2 class="section-head__title"><?= t('home.latest') ?></h2>
      <span class="section-head__rule" aria-hidden="true"></span>
    </header>
    <div class="story-list">
      <?php foreach ($latest->values() as $i => $story): ?>
        <?php snippet('story-card', ['story' => $story, 'size' => 'row', 'index' => $i + 1]) ?>
      <?php endforeach ?>
    </div>
  </section>

  <?php if ($page->showPapers()->toBool() && $papers->isNotEmpty()): ?>
  <aside class="catalogue">
    <header class="catalogue__head">
      <p class="kicker"><?= t('home.lab.kicker') ?></p>
      <h2 class="catalogue__title"><?= t('home.lab.title') ?></h2>
    </header>
    <?php foreach ($papers->limit(3) as $paper): ?>
      <a class="catalogue__card" href="<?= $paper->url() ?>">
        <span class="catalogue__meta"><?= $paper->date()->toDate('Y') ?> · <?= esc(Kirby\Toolkit\Str::short($paper->venue()->value(), 38)) ?></span>
        <span class="catalogue__name"><?= $paper->title()->esc() ?></span>
        <span class="catalogue__authors"><?= esc($paper->byline(3)) ?></span>
      </a>
    <?php endforeach ?>
    <a class="btn btn--block" href="<?= page('papers')->url() ?>"><?= t('home.allPapers') ?></a>
  </aside>
  <?php endif ?>
</div>

<?php if ($authors->isNotEmpty()): ?>
<section class="container section">
  <header class="section-head">
    <h2 class="section-head__title"><?= t('home.authors') ?></h2>
    <span class="section-head__rule" aria-hidden="true"></span>
    <a class="section-head__link" href="<?= page('authors')->url() ?>"><?= t('home.allAuthors') ?></a>
  </header>
  <div class="contributors">
    <?php foreach ($authors as $author): ?>
      <a class="contributor" href="<?= $author->url() ?>">
        <?php snippet('avatar', ['author' => $author, 'size' => 'md']) ?>
        <span class="contributor__name"><?= $author->title()->esc() ?></span>
        <span class="contributor__role"><?= $author->role()->esc() ?></span>
      </a>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<?php snippet('footer') ?>
