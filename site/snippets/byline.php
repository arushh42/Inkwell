<?php
/** @var Kirby\Cms\Page $page */
/** @var Kirby\Cms\Page|null $author */
?>
<div class="byline">
  <?php if ($author): ?>
    <a href="<?= $author->url() ?>" tabindex="-1" aria-hidden="true"><?php snippet('avatar', ['author' => $author]) ?></a>
  <?php endif ?>
  <div class="byline__text">
    <?php if ($author): ?>
      <a class="byline__name" href="<?= $author->url() ?>"><?= $author->title()->esc() ?></a>
    <?php endif ?>
    <span class="byline__meta">
      <time datetime="<?= $page->date()->toDate('Y-m-d') ?>"><?= inkwell_date($page->date(), 'M j, Y') ?></time>
      <span aria-hidden="true">·</span> <?= tt('minRead', null, ['n' => $page->readingTime()]) ?>
    </span>
  </div>
</div>
