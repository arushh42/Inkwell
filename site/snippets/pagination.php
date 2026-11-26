<?php
/** @var Kirby\Cms\Pagination $pagination */
if ($pagination->hasPages()): ?>
<nav class="pagination" aria-label="Pagination">
  <?php if ($pagination->hasPrevPage()): ?>
    <a class="btn btn--ghost" href="<?= $pagination->prevPageUrl() ?>" rel="prev">← Newer</a>
  <?php else: ?>
    <span></span>
  <?php endif ?>
  <span class="pagination__info">Page <?= $pagination->page() ?> of <?= $pagination->pages() ?></span>
  <?php if ($pagination->hasNextPage()): ?>
    <a class="btn btn--ghost" href="<?= $pagination->nextPageUrl() ?>" rel="next">Older →</a>
  <?php else: ?>
    <span></span>
  <?php endif ?>
</nav>
<?php endif ?>
