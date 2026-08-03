<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('view_audit_log');
$log = db()->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 300')->fetchAll();
$pageTitle = 'Admin · Audit Log';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <h2>Recent admin activity</h2>
  <table class="admin-table">
    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th></tr></thead>
    <tbody>
      <?php foreach ($log as $l): ?>
        <tr><td><?= h(time_ago($l['created_at'])) ?></td><td><?= h($l['actor_email']) ?></td><td><?= h($l['action']) ?></td><td><?= h($l['target'] ?? '') ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
