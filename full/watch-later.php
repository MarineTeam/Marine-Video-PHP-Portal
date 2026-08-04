<?php
require_once __DIR__ . '/config.php';
$user = require_authorized();
$pageTitle = 'Watch Later';

$stmt = db()->prepare('SELECT wl.*, v.title, v.slug FROM watch_later wl JOIN videos v ON v.id = wl.video_id WHERE wl.user_id = ? ORDER BY wl.position ASC');
$stmt->execute([$user['id']]);
$items = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<h1>Watch Later</h1>
<?php if (!$items): ?><p class="muted">Nothing queued yet.</p><?php endif; ?>
<div class="tile-grid">
  <?php foreach ($items as $it): ?>
    <a class="tile" href="video.php?slug=<?= h($it['slug']) ?>">
      <div class="thumb"><div class="thumb-placeholder">▶</div></div>
      <div class="tile-title"><?= h($it['title']) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
