<?php
/** @var Kirby\Cms\Page $story */
$size   = $size ?? 'default';
$index  = $index ?? null;
$cover  = $story->cover()->toFile();
$author = $story->author()->toPages()->first();
$tag    = $story->tags()->split(',')[0] ?? null;
$other  = !inkwell_is_translated($story) ? inkwell_versions($story)[0] : null;
?>
<article class="card card--<?= $size ?>">
  <?php if ($index !== null): ?>
    <span class="card__index" aria-hidden="true"><?= str_pad((string)$index, 2, '0', STR_PAD_LEFT) ?></span>
  <?php endif ?>
  <?php if ($cover && !in_array($size, ['compact', 'side'], true)): ?>
    <a class="card__media" href="<?= $story->url() ?>" tabindex="-1" aria-hidden="true">
      <img src="<?= $cover->resize($size === 'large' ? 1400 : 800)->url() ?>"
           alt="<?= $cover->alt()->esc('attr') ?>"
           width="<?= $cover->width() ?>" height="<?= $cover->height() ?>"
           loading="lazy">
    </a>
  <?php endif ?>
  <div class="card__body">
    <p class="eyebrow">
      <span class="sticker sticker--<?= $story->kind()->esc('attr') ?>"><?= $story->kind()->value() === 'blog' ? t('kind.blog.short') : t('kind.article') ?></span>
      <?php if ($tag): ?>
        <a class="eyebrow__tag" href="<?= url('stories', ['params' => ['tag' => urlencode($tag)]]) ?>"><?= esc($tag) ?></a>
      <?php endif ?>
      <?php if ($other): ?>
        <span class="lang-badge" lang="<?= $other ?>" title="<?= esc(tt('fallback.note', null, ['language' => inkwell_lang_name($other)]), 'attr') ?>"><?= strtoupper($other) ?></span>
      <?php endif ?>
    </p>
    <h3 class="card__title"><a href="<?= $story->url() ?>" <?= $other ? 'lang="' . $other . '"' : '' ?>><?= $story->title()->esc() ?></a></h3>
    <?php if ($size !== 'compact'): ?>
      <p class="card__excerpt" <?= $other ? 'lang="' . $other . '"' : '' ?>><?= esc($story->excerpt(150)) ?></p>
    <?php endif ?>
    <?php snippet('byline', ['page' => $story, 'author' => $author]) ?>
  </div>
</article>
