<?php
/** @var Kirby\Cms\Page $paper */
?>
<article class="paper-item">
  <h3 class="paper-item__title"><a href="<?= $paper->url() ?>" lang="en"><?= $paper->title()->esc() ?></a></h3>
  <p class="paper-item__authors"><?= esc($paper->byline(6)) ?></p>
  <p class="paper-item__meta">
    <?php if ($paper->venue()->isNotEmpty()): ?><em><?= $paper->venue()->esc() ?></em> · <?php endif ?>
    <?= $paper->date()->toDate('Y') ?>
    <?php if ($paper->pdf()->toFile()): ?> · <a href="<?= $paper->pdf()->toFile()->url() ?>">PDF</a><?php endif ?>
    <?php if ($paper->arxiv()->isNotEmpty()): ?> · <a href="https://arxiv.org/abs/<?= $paper->arxiv()->esc('attr') ?>" rel="noopener">arXiv</a><?php endif ?>
    <?php if ($paper->doi()->isNotEmpty()): ?> · <a href="https://doi.org/<?= $paper->doi()->esc('attr') ?>" rel="noopener">DOI</a><?php endif ?>
  </p>
</article>
