<?php
/** @var Kirby\Cms\Page $page */
/** @var Kirby\Cms\Pages $stories */
$kicker = match (true) {
    (bool)$tag         => t('stories.kicker.topic'),
    $kind === 'blog'    => t('stories.kicker.blog'),
    $kind === 'article' => t('stories.kicker.article'),
    default             => t('stories.kicker.all'),
};
?>
<?php snippet('header') ?>

<div class="container section">
  <header class="page-head">
    <p class="kicker"><?= $kicker ?></p>
    <h1 class="page-head__title"><?= $tag ? esc($tag) : $page->title()->esc() ?></h1>
    <p class="page-head__sub">
      <?= inkwell_count('stories.count', $pagination->total()) ?>
      <?php if ($tag || $kind): ?> · <a href="<?= $page->url() ?>"><?= t('filters.clear') ?></a><?php endif ?>
    </p>
  </header>

  <nav class="filters" aria-label="<?= t('stories.kicker.topic') ?>">
    <a class="tag <?= e(!$kind && !$tag, 'is-active') ?>" href="<?= $page->url() ?>"><?= t('filters.all') ?></a>
    <a class="tag <?= e($kind === 'article', 'is-active') ?>" href="<?= url('stories', ['params' => ['kind' => 'article']]) ?>"><?= t('filters.articles') ?></a>
    <a class="tag <?= e($kind === 'blog', 'is-active') ?>" href="<?= url('stories', ['params' => ['kind' => 'blog']]) ?>"><?= t('filters.blog') ?></a>
    <span class="filters__divider" aria-hidden="true"></span>
    <?php foreach ($allTags as $t): ?>
      <a class="tag <?= e($t === $tag, 'is-active') ?>" href="<?= url('stories', ['params' => ['tag' => urlencode($t)]]) ?>"><?= esc($t) ?></a>
    <?php endforeach ?>
  </nav>

  <?php if ($stories->isEmpty()): ?>
    <p class="empty"><?= t('empty') ?></p>
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
