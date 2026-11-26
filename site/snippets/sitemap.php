<?= '<?xml version="1.0" encoding="utf-8"?>' . "\n" ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <?php foreach ($pages as $p): ?>
  <?php $versions = inkwell_versions($p) ?>
  <?php foreach ($versions as $code): ?>
  <url>
    <loc><?= $p->url($code) ?></loc>
    <lastmod><?= $p->modified('Y-m-d') ?></lastmod>
    <?php foreach ($versions as $alt): ?>
    <xhtml:link rel="alternate" hreflang="<?= $alt ?>" href="<?= $p->url($alt) ?>"/>
    <?php endforeach ?>
  </url>
  <?php endforeach ?>
  <?php endforeach ?>
</urlset>
