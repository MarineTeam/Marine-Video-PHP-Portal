<?php
$themeVars = current_theme_vars();
$navUser = current_user();
$siteName = get_setting('site_name', 'Marine Team');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? $siteName) ?> · <?= h($siteName) ?></title>
<link rel="manifest" href="<?= h(SITE_URL) ?>/manifest.json">
<meta name="theme-color" content="#0b0f14">
<link rel="stylesheet" href="<?= h(SITE_URL) ?>/assets/css/style.css">
<style>:root { <?php foreach ($themeVars as $k => $v) echo h($k) . ':' . h($v) . ';'; ?> }</style>
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= h(SITE_URL) ?>/index.php"><?= h($siteName) ?></a>
  <form class="nav-search" method="get" action="<?= h(SITE_URL) ?>/search.php">
    <input type="search" name="q" placeholder="Search…" value="<?= h($_GET['q'] ?? '') ?>">
  </form>
  <nav>
    <?php if ($navUser): ?>
      <?php if (is_plugin_active('favorites')): ?><a href="<?= h(SITE_URL) ?>/favorites.php">Favorites</a><?php endif; ?>
      <?php if (is_plugin_active('watch-later')): ?><a href="<?= h(SITE_URL) ?>/watch-later.php">Watch Later</a><?php endif; ?>
      <?php if (is_plugin_active('playlists')): ?><a href="<?= h(SITE_URL) ?>/playlists.php">Playlists</a><?php endif; ?>
      <?php if (is_plugin_active('subscriptions')): ?><a href="<?= h(SITE_URL) ?>/subscriptions.php">Subscriptions</a><?php endif; ?>
      <?php if ($navUser['role'] === 'ADMIN' || user_can($navUser, 'manage_series') || user_can($navUser, 'manage_videos')): ?>
        <a href="<?= h(SITE_URL) ?>/admin/index.php">Admin</a>
      <?php endif; ?>
      <span class="muted small"><?= h($navUser['email']) ?><?= $navUser['authorized'] ? '' : ' (pending)' ?></span>
      <a href="<?= h(SITE_URL) ?>/logout.php">Sign out</a>
    <?php else: ?>
      <a href="<?= h(SITE_URL) ?>/login.php">Sign in</a>
    <?php endif; ?>
  </nav>
</header>
<?php if (is_plugin_active('announcements')): plugin_announcements_render_banner(); endif; ?>
<main class="container">
<?php foreach (pop_flashes() as $f): ?>
  <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['message']) ?></div>
<?php endforeach; ?>
