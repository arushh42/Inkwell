<?php
/** @var Kirby\Cms\Page $page */
/** @var Kirby\Cms\Site $site */
$description = $description ?? ($page->isHomePage() ? $site->tagline()->value() : $page->excerpt(160));
$ogImage     = $page->cover()->toFile();
$user        = $kirby->user();
$template    = $page->intendedTemplate()->name();
$isReading   = in_array($template, ['story', 'paper'], true);
$issue       = page('stories')?->children()->listed()->count() ?? 0;
$lang        = $kirby->language();
$virtual     = in_array($template, ['write', 'add-paper', 'login', 'signup'], true);
$versions    = $virtual ? ['de', 'en'] : inkwell_versions($page);
$fallback    = !$virtual && !in_array($lang->code(), $versions, true);
?>
<!doctype html>
<html lang="<?= $lang->code() ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $page->isHomePage() ? $site->title()->esc() . ' · ' . $site->tagline()->esc() : $page->title()->esc() . ' · ' . $site->title()->esc() ?></title>
  <meta name="description" content="<?= esc($description, 'attr') ?>">
  <meta name="theme-color" content="#F3EEE3" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#16140F" media="(prefers-color-scheme: dark)">
  <meta property="og:title" content="<?= $page->title()->esc('attr') ?>">
  <meta property="og:description" content="<?= esc($description, 'attr') ?>">
  <meta property="og:type" content="<?= $template === 'story' ? 'article' : 'website' ?>">
  <meta property="og:url" content="<?= $page->url() ?>">
  <meta property="og:locale" content="<?= $lang->code() === 'de' ? 'de_DE' : 'en_GB' ?>">
  <?php if ($ogImage): ?>
  <meta property="og:image" content="<?= $ogImage->url() ?>">
  <?php endif ?>
  <?php if ($fallback): ?>
  <meta name="robots" content="noindex">
  <link rel="canonical" href="<?= $page->url($versions[0]) ?>">
  <?php elseif (!$virtual): ?>
  <link rel="canonical" href="<?= $page->url() ?>">
  <?php foreach ($versions as $code): ?>
  <link rel="alternate" hreflang="<?= $code ?>" href="<?= $page->url($code) ?>">
  <?php endforeach ?>
  <link rel="alternate" hreflang="x-default" href="<?= $page->url($versions[0]) ?>">
  <?php endif ?>
  <link rel="alternate" type="application/rss+xml" title="<?= $site->title()->esc('attr') ?>" href="<?= lurl('feed.xml') ?>">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%23E0482B'/><text x='16' y='24' font-family='Georgia' font-style='italic' font-weight='700' font-size='22' text-anchor='middle' fill='%23F3EEE3'>i</text></svg>">
  <link rel="preload" href="<?= url('assets/fonts/fraunces.woff2') ?>" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="<?= url('assets/fonts/instrument-sans.woff2') ?>" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="<?= asset_url('assets/css/main.css') ?>">
</head>
<body class="template-<?= $template ?>">
  <a class="skip-link" href="#main"><?= t('skip') ?></a>

  <?php if ($isReading): ?>
    <div class="progress" aria-hidden="true"></div>
  <?php endif ?>

  <div class="strip">
    <div class="container strip__inner">
      <span><?= inkwell_date(time(), 'l, j F Y') ?></span>
      <span class="strip__issue"><?= tt('strip.issue', null, ['n' => str_pad((string)$issue, 2, '0', STR_PAD_LEFT)]) ?></span>
      <a href="<?= lurl('feed.xml') ?>">RSS ↗</a>
    </div>
  </div>

  <header class="site-header">
    <div class="container site-header__inner">
      <a class="wordmark" href="<?= $site->url() ?>" aria-label="<?= $site->title()->esc('attr') ?>">
        <span class="wordmark__dot" aria-hidden="true"></span><?= $site->title()->esc() ?>
      </a>

      <nav class="site-nav" aria-label="Main">
        <?php foreach (['stories', 'papers', 'authors', 'about', 'guides'] as $slug): ?>
          <?php if ($item = page($slug)): ?>
            <a href="<?= $item->url() ?>" <?= e($item->isOpen(), 'aria-current="page"') ?>><?= $item->title()->esc() ?></a>
          <?php endif ?>
        <?php endforeach ?>
      </nav>

      <div class="site-header__actions">
        <nav class="lang-switch" aria-label="<?= t('header.language') ?>">
          <?php foreach ($kirby->languages() as $language): ?>
            <a href="<?= inkwell_switch_url($language->code()) ?>" hreflang="<?= $language->code() ?>" lang="<?= $language->code() ?>"
               <?= e($language->code() === $lang->code(), 'aria-current="true"') ?>
               title="<?= esc(t('lang.' . $language->code()), 'attr') ?>"><?= strtoupper($language->code()) ?></a>
          <?php endforeach ?>
        </nav>
        <a class="icon-btn" href="<?= page('search')?->url() ?>" aria-label="<?= t('header.search') ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        </a>
        <?php if ($user): ?>
          <a class="btn btn--small header-btn" href="<?= lurl('add-paper') ?>" <?= e($template === 'add-paper', 'aria-current="page"') ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M12 11v6M9 14h6"/></svg>
            <span><?= t('header.addPaper') ?></span>
          </a>
          <a class="btn btn--small btn--accent header-btn" href="<?= lurl('write') ?>" <?= e($template === 'write', 'aria-current="page"') ?>>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4z"/></svg>
            <span><?= t('header.write') ?></span>
          </a>
          <a class="icon-btn" href="<?= lurl('logout', ['csrf' => csrf()]) ?>" aria-label="<?= t('header.logout') ?>" title="<?= t('header.logout') ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/></svg>
          </a>
        <?php else: ?>
          <a class="btn btn--small" href="<?= lurl('login') ?>"><?= t('header.signin') ?></a>
        <?php endif ?>
      </div>
    </div>
  </header>

  <main id="main">
  <?php if ($fallback): ?>
    <div class="lang-note container" role="note">
      <?= tt('fallback.note', null, ['language' => inkwell_lang_name($versions[0])]) ?>
      <a href="<?= $page->url($versions[0]) ?>" hreflang="<?= $versions[0] ?>"><?= t('lang.' . $versions[0]) ?> →</a>
    </div>
  <?php endif ?>
