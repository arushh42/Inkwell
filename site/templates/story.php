<?php
/** @var Kirby\Cms\Page $page */
$author = $page->author()->toPages()->first();
$cover  = $page->cover()->toFile();
$more   = $author
    ? $author->stories()->not($page)->limit(2)
    : $page->siblings()->listed()->not($page)->limit(2);
?>
<?php snippet('header') ?>

<article class="story">
  <header class="story__head">
    <p class="eyebrow eyebrow--center">
      <span class="sticker sticker--<?= $page->kind()->esc('attr') ?>"><?= $page->kind()->value() === 'blog' ? 'Blog post' : 'Article' ?></span>
      <?php foreach (array_slice($page->tags()->split(','), 0, 2) as $tag): ?>
        <a class="eyebrow__tag" href="<?= url('stories', ['params' => ['tag' => urlencode($tag)]]) ?>"><?= esc($tag) ?></a>
      <?php endforeach ?>
    </p>
    <h1 class="story__title"><?= $page->title()->esc() ?></h1>
    <?php if ($page->subtitle()->isNotEmpty()): ?>
      <p class="story__subtitle"><?= $page->subtitle()->esc() ?></p>
    <?php endif ?>
    <?php snippet('byline', ['page' => $page, 'author' => $author]) ?>
    <?php if ($kirby->user()): ?>
      <a class="btn btn--small story__edit" href="<?= url('write/' . $page->slug()) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/></svg>
        Edit this story
      </a>
    <?php endif ?>
  </header>

  <?php if ($cover): ?>
    <figure class="story__cover">
      <img src="<?= $cover->resize(1600)->url() ?>"
           srcset="<?= $cover->srcset([800, 1200, 1600]) ?>"
           sizes="(min-width: 1180px) 1120px, 100vw"
           alt="<?= $cover->alt()->esc('attr') ?>"
           width="<?= $cover->width() ?>" height="<?= $cover->height() ?>">
      <?php if ($cover->caption()->isNotEmpty()): ?>
        <figcaption><?= $cover->caption()->esc() ?></figcaption>
      <?php endif ?>
    </figure>
  <?php endif ?>

  <div class="prose prose--story reading">
    <?= $page->text()->toBlocks() ?>
    <span class="end-mark" aria-hidden="true"></span>
  </div>

  <footer class="story__foot reading">
    <?php if ($page->tags()->isNotEmpty()): ?>
      <div class="tag-cloud">
        <?php foreach ($page->tags()->split(',') as $tag): ?>
          <a class="tag" href="<?= url('stories', ['params' => ['tag' => urlencode($tag)]]) ?>">#<?= esc($tag) ?></a>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <?php if ($author): ?>
      <div class="author-box">
        <?php snippet('avatar', ['author' => $author, 'size' => 'md']) ?>
        <div>
          <p class="kicker">Written by</p>
          <h2 class="author-box__name"><a href="<?= $author->url() ?>"><?= $author->title()->esc() ?></a></h2>
          <p class="author-box__role"><?= $author->role()->esc() ?></p>
          <p class="author-box__bio"><?= $author->bio()->esc() ?></p>
        </div>
      </div>
    <?php endif ?>
  </footer>
</article>

<?php if ($more->isNotEmpty()): ?>
<section class="container section">
  <header class="section-head">
    <h2 class="section-head__title">More from <?= $author ? $author->title()->esc() : $site->title()->esc() ?></h2>
    <span class="section-head__rule" aria-hidden="true"></span>
  </header>
  <div class="card-grid card-grid--2">
    <?php foreach ($more as $story): ?>
      <?php snippet('story-card', ['story' => $story]) ?>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<?php snippet('footer') ?>
