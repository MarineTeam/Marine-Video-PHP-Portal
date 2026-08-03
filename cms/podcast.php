<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/rss+xml; charset=utf-8');

$slug = $_GET['series'] ?? '';
$stmt = db()->prepare('SELECT * FROM series WHERE slug = ? AND published = 1'); $stmt->execute([$slug]);
$series = $stmt->fetch();
if (!$series || $series['member_only'] || has_viewer_grants('series', $series['id'])) {
    http_response_code(404);
    die('Feed not available for this series (private or restricted content cannot be published as a podcast feed).');
}

$videosStmt = db()->prepare("SELECT * FROM videos WHERE series_id = ? AND published = 1 AND member_only = 0 ORDER BY position DESC");
$videosStmt->execute([$series['id']]);
$videos = $videosStmt->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?' . ">\n";
?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
<channel>
  <title><?= h($series['title']) ?></title>
  <link><?= h(SITE_URL) ?>/series.php?slug=<?= h($series['slug']) ?></link>
  <description><?= h($series['description'] ?? '') ?></description>
  <itunes:author><?= h(get_setting('site_name', 'Marine Team')) ?></itunes:author>
  <?php foreach ($videos as $v):
    if ($v['bunny_video_id'] || $v['embed_url']) continue; // enclosures need a direct, stable file URL — local files only
    $fileUrl = SITE_URL . '/uploads/videos/' . $v['filename'];
    $size = is_file(__DIR__ . '/uploads/videos/' . $v['filename']) ? filesize(__DIR__ . '/uploads/videos/' . $v['filename']) : 0;
  ?>
  <item>
    <title><?= h($v['title']) ?></title>
    <guid><?= h(SITE_URL) ?>/video.php?slug=<?= h($v['slug']) ?></guid>
    <pubDate><?= h(date(DATE_RSS, strtotime($v['created_at']))) ?></pubDate>
    <description><?= h($v['description'] ?? '') ?></description>
    <enclosure url="<?= h($fileUrl) ?>" length="<?= (int)$size ?>" type="video/mp4"/>
    <itunes:duration><?= (int)$v['duration_seconds'] ?></itunes:duration>
  </item>
  <?php endforeach; ?>
</channel>
</rss>
