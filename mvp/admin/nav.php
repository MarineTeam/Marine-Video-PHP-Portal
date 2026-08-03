<?php
$tabs = [
    'videos.php' => 'Videos',
    'viewers.php' => 'Viewers',
    'shares.php' => 'Shares',
    'settings.php' => 'Settings',
    'activity.php' => 'Activity',
    'analytics.php' => 'Analytics',
];
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="admin-tabs">
  <?php foreach ($tabs as $file => $label): ?>
    <a href="<?= h($file) ?>" class="<?= $current === $file ? 'active' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>
