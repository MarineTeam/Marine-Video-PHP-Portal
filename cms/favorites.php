<?php
require_once __DIR__ . '/config.php';
$user = require_authorized();
$pageTitle = 'Favorites';

$stmt = db()->prepare('SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$favs = $stmt->fetchAll();

$items = [];
foreach ($favs as $f) {
    $table = $f['content_type'] === 'video' ? 'videos' : 'series';
    $s = db()->prepare("SELECT * FROM `$table` WHERE id = ?"); $s->execute([$f['content_id']]);
    if ($row = $s->fetch()) $items[] = ['type' => $f['content_type'], 'row' => $row];
}
include __DIR__ . '/includes/header.php';
?>
<h1>Favorites</h1>
<?php if (!$items): ?><p class="muted">Nothing favorited yet.</p><?php endif; ?>
<div class="tile-grid">
  <?php foreach ($items as $it): $r = $it['row']; ?>
    <a class="tile" href="<?= $it['type'] === 'series' ? 'series.php?slug=' . h($r['slug']) : 'video.php?slug=' . h($r['slug']) ?>">
      <div class="thumb"><div class="thumb-placeholder"><?= $it['type'] === 'series' ? '▤' : '▶' ?></div></div>
      <div class="tile-title"><?= h($r['title']) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
