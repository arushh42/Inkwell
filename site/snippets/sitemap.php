<?= '<?xml version="1.0" encoding="utf-8"?>' . "\n" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <?php foreach ($pages as $p): ?>
  <url>
    <loc><?= $p->url() ?></loc>
    <lastmod><?= $p->modified('Y-m-d') ?></lastmod>
  </url>
  <?php endforeach ?>
</urlset>
