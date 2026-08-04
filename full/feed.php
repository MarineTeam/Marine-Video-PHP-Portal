<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/rss+xml; charset=utf-8');

$siteName = get_setting('site_name', 'Marine Team');
$series = db()->query("SELECT * FROM series WHERE published = 1 ORDER BY created_at DESC LIMIT 30")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
?>
<rss version="2.0">
<channel>
  <title><?= h($siteName) ?></title>
  <link><?= h(SITE_URL) ?>/index.php</link>
  <description><?= h($siteName) ?> — recently added series</description>
  <?php foreach ($series as $s): if ($s['member_only'] || has_viewer_grants('series', $s['id'])) continue; ?>
  <item>
    <title><?= h($s['title']) ?></title>
    <link><?= h(SITE_URL) ?>/series.php?slug=<?= h($s['slug']) ?></link>
    <guid><?= h(SITE_URL) ?>/series.php?slug=<?= h($s['slug']) ?></guid>
    <pubDate><?= h(date(DATE_RSS, strtotime($s['created_at']))) ?></pubDate>
    <description><?= h($s['description'] ?? '') ?></description>
  </item>
  <?php endforeach; ?>
</channel>
</rss>
