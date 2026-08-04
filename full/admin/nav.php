<?php
$tabs = [
    'index.php' => 'Dashboard', 'categories.php' => 'Categories', 'series.php' => 'Series',
    'videos.php' => 'Videos', 'files.php' => 'Files', 'shares.php' => 'Shares', 'users.php' => 'Users',
    'permissions.php' => 'Permissions', 'plugins.php' => 'Plugins',
    'audit.php' => 'Audit Log', 'analytics.php' => 'Analytics', 'settings.php' => 'Settings',
];
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="admin-tabs">
  <?php foreach ($tabs as $file => $label): ?>
    <a href="<?= h($file) ?>" class="<?= $current === $file ? 'active' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>
