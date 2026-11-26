<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header') ?>

<div class="container section">
  <header class="page-head">
    <p class="kicker">Help &amp; how-to</p>
    <h1 class="page-head__title"><?= $page->title()->esc() ?></h1>
    <p class="page-head__sub"><?= $page->intro()->esc() ?></p>
  </header>

  <div class="guide-cards">
    <?php foreach ($page->children()->listed() as $guide): ?>
      <?php $first = $guide->steps()->toStructure()->first()?->image()->toFile() ?>
      <a class="guide-card" href="<?= $guide->url() ?>">
        <?php if ($first): ?>
          <span class="guide-card__media">
            <img src="<?= $first->resize(900)->url() ?>" alt="" width="<?= $first->width() ?>" height="<?= $first->height() ?>" loading="lazy">
          </span>
        <?php endif ?>
        <span class="guide-card__body">
          <span class="kicker"><?= $guide->steps()->toStructure()->count() ?> steps · <?= $guide->duration()->esc() ?></span>
          <span class="guide-card__title"><?= $guide->title()->esc() ?></span>
          <span class="guide-card__intro"><?= $guide->intro()->esc() ?></span>
          <span class="guide-card__cta">Start the guide →</span>
        </span>
      </a>
    <?php endforeach ?>
  </div>

  <?php if ($page->faq()->isNotEmpty()): ?>
    <section class="faq">
      <header class="section-head">
        <h2 class="section-head__title">Good to know</h2>
        <span class="section-head__rule" aria-hidden="true"></span>
      </header>
      <?php foreach ($page->faq()->toStructure() as $item): ?>
        <details class="faq__item">
          <summary><?= $item->question()->esc() ?><span aria-hidden="true">+</span></summary>
          <p><?= $item->answer()->esc() ?></p>
        </details>
      <?php endforeach ?>
    </section>
  <?php endif ?>
</div>

<?php snippet('footer') ?>
