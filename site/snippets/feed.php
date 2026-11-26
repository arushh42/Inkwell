<?= '<?xml version="1.0" encoding="utf-8"?>' . "\n" ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?= site()->title()->escape('xml') ?></title>
    <link><?= site()->url() ?></link>
    <description><?= site()->tagline()->escape('xml') ?></description>
    <atom:link href="<?= url('feed.xml') ?>" rel="self" type="application/rss+xml" />
    <?php foreach ($items as $item): ?>
    <item>
      <title><?= $item->title()->escape('xml') ?></title>
      <link><?= $item->url() ?></link>
      <guid isPermaLink="true"><?= $item->url() ?></guid>
      <pubDate><?= $item->date()->toDate(DATE_RSS) ?></pubDate>
      <description><?= Kirby\Toolkit\Xml::encode($item->excerpt(300)) ?></description>
    </item>
    <?php endforeach ?>
  </channel>
</rss>
