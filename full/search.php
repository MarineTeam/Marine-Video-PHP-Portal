<?php
require_once __DIR__ . '/config.php';
$user = current_user();
$q = trim($_GET['q'] ?? '');
$pageTitle = $q !== '' ? "Search: $q" : 'Search';

$results = [];
if ($q !== '') {
    foreach (search_content($q) as $r) {
        if ($r['type'] === 'series') {
            $sStmt = db()->prepare('SELECT * FROM series WHERE id = ?'); $sStmt->execute([$r['id']]);
            $row = $sStmt->fetch();
            if ($row && can_view_series($row, $user)) $results[] = $r;
        } else {
            $vStmt = db()->prepare('SELECT * FROM videos WHERE id = ?'); $vStmt->execute([$r['id']]);
            $row = $vStmt->fetch();
            if ($row && can_view_video($row, $user)) $results[] = $r;
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<h1><?= $q !== '' ? 'Search results for "' . h($q) . '"' : 'Search' ?></h1>
<?php if ($q !== '' && !$results): ?><p class="muted">No matches.</p><?php endif; ?>
<div class="tile-grid">
  <?php foreach ($results as $r): ?>
    <a class="tile" href="<?= $r['type'] === 'series' ? 'series.php?slug=' . h($r['slug']) : 'video.php?slug=' . h($r['slug']) ?>">
      <div class="thumb"><div class="thumb-placeholder"><?= $r['type'] === 'series' ? '▤' : '▶' ?></div></div>
      <div class="tile-title"><?= h($r['name']) ?></div>
      <div class="tile-meta"><?= h(ucfirst($r['type'])) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
