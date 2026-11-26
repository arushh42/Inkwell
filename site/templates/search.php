<?php
/** @var Kirby\Cms\Page $page */
?>
<?php snippet('header') ?>

<div class="container section reading-wide">
  <header class="page-head">
    <p class="kicker">Find anything</p>
    <h1 class="page-head__title">Search</h1>
  </header>

  <form class="search-form" action="<?= $page->url() ?>" method="get" role="search">
    <label class="visually-hidden" for="q">Search stories, papers and authors</label>
    <input id="q" type="search" name="q" value="<?= esc($query, 'attr') ?>" placeholder="Search stories, papers and authors…" autofocus>
    <button class="btn" type="submit">Search</button>
  </form>

  <?php if ($results !== null): ?>
    <p class="page-head__sub"><?= $results->count() ?> result<?= $results->count() === 1 ? '' : 's' ?> for “<?= esc($query) ?>”</p>

    <ul class="results">
      <?php foreach ($results as $result): ?>
        <?php $type = $result->intendedTemplate()->name() ?>
        <li class="result">
          <span class="sticker sticker--<?= $type === 'story' ? $result->kind()->esc('attr') : $type ?>">
            <?= ['story' => 'Story', 'paper' => 'Paper', 'author' => 'Author'][$type] ?>
          </span>
          <a class="result__title" href="<?= $result->url() ?>"><?= $result->title()->esc() ?></a>
          <p class="result__excerpt"><?= esc($type === 'author' ? $result->bio()->excerpt(160) : $result->excerpt(160)) ?></p>
        </li>
      <?php endforeach ?>
    </ul>
  <?php endif ?>
</div>

<?php snippet('footer') ?>
