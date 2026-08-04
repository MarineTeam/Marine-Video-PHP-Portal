<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('view_analytics');

$totalViews = (int)db()->query('SELECT COUNT(*) c FROM view_events')->fetch()['c'];
$views30 = (int)db()->query('SELECT COUNT(*) c FROM view_events WHERE viewed_at >= NOW() - INTERVAL 30 DAY')->fetch()['c'];
$totalWatchSeconds = (int)db()->query('SELECT COALESCE(SUM(watch_seconds),0) s FROM videos')->fetch()['s'];
$seriesCount = (int)db()->query('SELECT COUNT(*) c FROM series')->fetch()['c'];

$byDay = array_column(db()->query("SELECT DATE(viewed_at) d, COUNT(*) c FROM view_events WHERE viewed_at >= NOW() - INTERVAL 30 DAY GROUP BY DATE(viewed_at)")->fetchAll(), 'c', 'd');
$chart = [];
for ($i = 29; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $chart[] = ['date' => $d, 'count' => (int)($byDay[$d] ?? 0)]; }
$maxCount = max(1, max(array_column($chart, 'count')));

$mostWatchedSeries = db()->query("SELECT s.title, s.view_count, COALESCE(SUM(v.watch_seconds),0) AS watch_seconds
                                   FROM series s LEFT JOIN videos v ON v.series_id = s.id
                                   GROUP BY s.id ORDER BY s.view_count DESC LIMIT 10")->fetchAll();
$mostWatchedVideos = db()->query('SELECT title, view_count, watch_seconds FROM videos ORDER BY view_count DESC LIMIT 10')->fetchAll();

$pageTitle = 'Admin · Analytics';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section class="stat-cards">
  <div class="stat-card"><div class="stat-num"><?= $totalViews ?></div><div class="tile-meta">Total views</div></div>
  <div class="stat-card"><div class="stat-num"><?= $views30 ?></div><div class="tile-meta">Views (30d)</div></div>
  <div class="stat-card"><div class="stat-num"><?= h(format_duration($totalWatchSeconds)) ?></div><div class="tile-meta">Total watch time</div></div>
  <div class="stat-card"><div class="stat-num"><?= $seriesCount ?></div><div class="tile-meta">Series</div></div>
</section>
<section><h2>Views, last 30 days</h2>
  <div class="bar-chart"><?php foreach ($chart as $day): ?><div class="bar" style="height:<?= max(2, round($day['count'] / $maxCount * 100)) ?>%" title="<?= h($day['date']) ?>: <?= $day['count'] ?>"></div><?php endforeach; ?></div>
</section>
<section><h2>Most-watched series</h2>
  <table class="admin-table"><?php foreach ($mostWatchedSeries as $m): ?><tr><td><?= h($m['title']) ?></td><td><?= (int)$m['view_count'] ?> views</td><td><?= h(format_duration((int)$m['watch_seconds'])) ?></td></tr><?php endforeach; ?></table>
</section>
<section><h2>Most-watched videos</h2>
  <table class="admin-table"><?php foreach ($mostWatchedVideos as $m): ?><tr><td><?= h($m['title']) ?></td><td><?= (int)$m['view_count'] ?> views</td><td><?= h(format_duration((int)$m['watch_seconds'])) ?></td></tr><?php endforeach; ?></table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
