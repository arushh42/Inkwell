<?php
/** @var Kirby\Cms\Page $page */
/** @var Kirby\Cms\Pages $stories */
?>
<?php snippet('header') ?>

<div class="container section">
  <header class="page-head">
    <p class="kicker"><?= $tag ? 'Topic' : ($kind === 'blog' ? 'Blog posts' : ($kind === 'article' ? 'Articles' : 'The archive')) ?></p>
    <h1 class="page-head__title"><?= $tag ? esc($tag) : $page->title()->esc() ?></h1>
    <p class="page-head__sub">
      <?= $pagination->total() ?> <?= $pagination->total() === 1 ? 'story' : 'stories' ?>
      <?php if ($tag || $kind): ?> · <a href="<?= $page->url() ?>">Clear filters</a><?php endif ?>
    </p>
  </header>

  <nav class="filters" aria-label="Filter stories">
    <a class="tag <?= e(!$kind, 'is-active') ?>" href="<?= $page->url() ?>">All</a>
    <a class="tag <?= e($kind === 'article', 'is-active') ?>" href="<?= url('stories', ['params' => ['kind' => 'article']]) ?>">Articles</a>
    <a class="tag <?= e($kind === 'blog', 'is-active') ?>" href="<?= url('stories', ['params' => ['kind' => 'blog']]) ?>">Blog posts</a>
    <span class="filters__divider" aria-hidden="true"></span>
    <?php foreach ($allTags as $t): ?>
      <a class="tag <?= e($t === $tag, 'is-active') ?>" href="<?= url('stories', ['params' => ['tag' => urlencode($t)]]) ?>"><?= esc($t) ?></a>
    <?php endforeach ?>
  </nav>

  <?php if ($stories->isEmpty()): ?>
    <p class="empty">Nothing here yet.</p>
  <?php else: ?>
    <div class="card-grid">
      <?php foreach ($stories as $story): ?>
        <?php snippet('story-card', ['story' => $story]) ?>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <?php snippet('pagination', ['pagination' => $pagination]) ?>
</div>

<?php snippet('footer') ?>
