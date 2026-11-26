<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header') ?>

<div class="container section reading-wide">
  <header class="page-head">
    <p class="kicker"><?= t('search.kicker') ?></p>
    <h1 class="page-head__title"><?= $page->title()->esc() ?></h1>
  </header>

  <form class="search-form" action="<?= $page->url() ?>" method="get" role="search">
    <label class="visually-hidden" for="q"><?= t('search.label') ?></label>
    <input id="q" type="search" name="q" value="<?= esc($query, 'attr') ?>" placeholder="<?= esc(t('search.placeholder'), 'attr') ?>" autofocus>
    <button class="btn" type="submit"><?= t('search.button') ?></button>
  </form>

  <?php if ($results !== null): ?>
    <p class="page-head__sub"><?= esc(inkwell_count('search.results', $results->count(), ['query' => $query])) ?></p>

    <ul class="results">
      <?php foreach ($results as $result): ?>
        <?php $type = $result->intendedTemplate()->name() ?>
        <li class="result">
          <span class="sticker sticker--<?= $type === 'story' ? $result->kind()->esc('attr') : $type ?>">
            <?= t('search.type.' . $type) ?>
          </span>
          <a class="result__title" href="<?= $result->url() ?>"><?= $result->title()->esc() ?></a>
          <p class="result__excerpt"><?= esc($type === 'author' ? $result->bio()->excerpt(160) : $result->excerpt(160)) ?></p>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</div>

<?php snippet('footer') ?>
