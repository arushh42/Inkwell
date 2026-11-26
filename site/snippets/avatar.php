<?php
/** @var Kirby\Cms\Page $author */
$size  = $size ?? 'sm';
$px    = ['sm' => 80, 'md' => 160, 'lg' => 320][$size] ?? 80;
$photo = $author->avatar()->toFile();
?>
<span class="avatar avatar--<?= $size ?> swatch--<?= $author->swatch() ?>" aria-hidden="true">
  <?php if ($photo): ?>
    <img src="<?= $photo->crop($px, $px)->url() ?>" alt="" width="<?= $px / 2 ?>" height="<?= $px / 2 ?>" loading="lazy">
  <?php else: ?>
    <?= esc($author->initials()) ?>
  <?php endif ?>
</span>
