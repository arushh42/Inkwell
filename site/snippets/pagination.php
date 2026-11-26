<?php
/** @var Kirby\Cms\Pagination $pagination */
if ($pagination->hasPages()): ?>
<nav class="pagination" aria-label="<?= t('pagination.label') ?>">
  <?php if ($pagination->hasPrevPage()): ?>
    <a class="btn btn--ghost" href="<?= $pagination->prevPageUrl() ?>" rel="prev"><?= t('pagination.newer') ?></a>
  <?php else: ?>
    <span></span>
  <?php endif ?>
  <span class="pagination__info"><?= tt('pagination.info', null, ['page' => $pagination->page(), 'pages' => $pagination->pages()]) ?></span>
  <?php if ($pagination->hasNextPage()): ?>
    <a class="btn btn--ghost" href="<?= $pagination->nextPageUrl() ?>" rel="next"><?= t('pagination.older') ?></a>
  <?php else: ?>
    <span></span>
  <?php endif ?>
</nav>
<?php endif ?>
