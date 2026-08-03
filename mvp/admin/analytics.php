<?php
require_once __DIR__ . '/../config.php';
require_admin();

$totalViews = (int)db()->query('SELECT COUNT(*) c FROM video_views')->fetch()['c'];
$views30 = (int)db()->query('SELECT COUNT(*) c FROM video_views WHERE viewed_at >= NOW() - INTERVAL 30 DAY')->fetch()['c'];
$totalWatchSeconds = (int)db()->query('SELECT COALESCE(SUM(watch_seconds),0) s FROM videos')->fetch()['s'];
$videoCount = (int)db()->query('SELECT COUNT(*) c FROM videos')->fetch()['c'];

$chartStmt = db()->query("SELECT DATE(viewed_at) d, COUNT(*) c FROM video_views
                           WHERE viewed_at >= NOW() - INTERVAL 30 DAY GROUP BY DATE(viewed_at)");
$byDay = [];
foreach ($chartStmt->fetchAll() as $row) {
    $byDay[$row['d']] = (int)$row['c'];
}
$chart = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $chart[] = ['date' => $d, 'count' => $byDay[$d] ?? 0];
}
$maxCount = max(1, max(array_column($chart, 'count')));

$mostWatched = db()->query('SELECT title, views, watch_seconds FROM videos ORDER BY views DESC LIMIT 10')->fetchAll();

$pageTitle = 'Admin · Analytics';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section class="stat-cards">
  <div class="stat-card"><div class="stat-num"><?= $totalViews ?></div><div class="stat-label">Total views</div></div>
  <div class="stat-card"><div class="stat-num"><?= $views30 ?></div><div class="stat-label">Views (30d)</div></div>
  <div class="stat-card"><div class="stat-num"><?= h(format_duration($totalWatchSeconds)) ?></div><div class="stat-label">Total watch time</div></div>
  <div class="stat-card"><div class="stat-num"><?= $videoCount ?></div><div class="stat-label">Videos</div></div>
</section>

<section>
  <h2>Views, last 30 days</h2>
  <div class="bar-chart">
    <?php foreach ($chart as $day): ?>
      <div class="bar" style="height:<?= max(2, round($day['count'] / $maxCount * 100)) ?>%" title="<?= h($day['date']) ?>: <?= $day['count'] ?>"></div>
    <?php endforeach; ?>
  </div>
</section>

<section>
  <h2>Most watched</h2>
  <table class="admin-table">
    <thead><tr><th>Title</th><th>Views</th><th>Watch time</th></tr></thead>
    <tbody>
      <?php foreach ($mostWatched as $m): ?>
        <tr><td><?= h($m['title']) ?></td><td><?= (int)$m['views'] ?></td><td><?= h(format_duration((int)$m['watch_seconds'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
