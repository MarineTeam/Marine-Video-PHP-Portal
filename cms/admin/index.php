<?php
require_once __DIR__ . '/../config.php';
$admin = require_login();
if (!$admin['authorized']) { render_not_authorized(); exit; }

$counts = [
    'Categories' => (int)db()->query('SELECT COUNT(*) c FROM categories')->fetch()['c'],
    'Series' => (int)db()->query('SELECT COUNT(*) c FROM series')->fetch()['c'],
    'Videos' => (int)db()->query('SELECT COUNT(*) c FROM videos')->fetch()['c'],
    'Files' => (int)db()->query('SELECT COUNT(*) c FROM files')->fetch()['c'],
    'Users' => (int)db()->query('SELECT COUNT(*) c FROM users')->fetch()['c'],
    'Pending users' => (int)db()->query("SELECT COUNT(*) c FROM users WHERE authorized = 0")->fetch()['c'],
];

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section class="stat-cards">
  <?php foreach ($counts as $label => $n): ?>
    <div class="stat-card"><div class="stat-num"><?= $n ?></div><div class="tile-meta"><?= h($label) ?></div></div>
  <?php endforeach; ?>
</section>
<?php if ($counts['Pending users'] > 0): ?>
  <p class="flash flash-error"><?= $counts['Pending users'] ?> user(s) are signed in but not yet authorized. Review them on the <a href="users.php">Users</a> tab.</p>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
