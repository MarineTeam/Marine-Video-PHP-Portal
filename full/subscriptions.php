<?php
require_once __DIR__ . '/config.php';
$user = require_authorized();
$pageTitle = 'Subscriptions';

$stmt = db()->prepare('SELECT * FROM subscriptions WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$subs = $stmt->fetchAll();
$items = [];
foreach ($subs as $s) {
    $table = $s['content_type'] === 'category' ? 'categories' : 'series';
    $q = db()->prepare("SELECT * FROM `$table` WHERE id = ?"); $q->execute([$s['content_id']]);
    if ($row = $q->fetch()) $items[] = ['type' => $s['content_type'], 'row' => $row];
}
include __DIR__ . '/includes/header.php';
?>
<h1>Subscriptions</h1>
<?php if (!$items): ?><p class="muted">Not subscribed to anything yet — look for the 🔕 Subscribe button on a series page.</p><?php endif; ?>
<div class="tile-grid">
  <?php foreach ($items as $it): $r = $it['row']; ?>
    <a class="tile" href="<?= $it['type'] === 'category' ? 'category.php?slug=' . h($r['slug']) : 'series.php?slug=' . h($r['slug']) ?>">
      <div class="thumb"><div class="thumb-placeholder">▤</div></div>
      <div class="tile-title"><?= h($r['name'] ?? $r['title']) ?></div>
      <div class="tile-meta"><?= h(ucfirst($it['type'])) ?></div>
    </a>
  <?php endforeach; ?>
</div>
<?php if (is_plugin_active('notifications')): ?>
  <p><button class="btn" id="enable-notifications">Enable push notifications</button></p>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
