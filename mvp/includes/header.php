<?php
/** Include after config.php. Set $pageTitle before including. */
$themeVars = current_theme_vars();
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle ?? SITE_NAME) ?> · <?= h(SITE_NAME) ?></title>
<link rel="manifest" href="<?= h(SITE_URL) ?>/manifest.json">
<link rel="apple-touch-icon" href="<?= h(SITE_URL) ?>/assets/icons/icon-180.png">
<meta name="theme-color" content="#0b0f14">
<link rel="stylesheet" href="<?= h(SITE_URL) ?>/assets/css/style.css">
<style>
  :root { <?php foreach ($themeVars as $k => $v) echo h($k) . ':' . h($v) . ';'; ?> }
</style>
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= h(SITE_URL) ?>/index.php"><?= h(SITE_NAME) ?></a>
  <nav>
    <?php if ($user): ?>
      <?php if ($user['is_admin']): ?><a href="<?= h(SITE_URL) ?>/admin/index.php">Admin</a><?php endif; ?>
      <span class="muted"><?= h($user['email']) ?></span>
      <a href="<?= h(SITE_URL) ?>/logout.php">Sign out</a>
    <?php else: ?>
      <a href="<?= h(SITE_URL) ?>/login.php">Sign in</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
<?php foreach (pop_flashes() as $f): ?>
  <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['message']) ?></div>
<?php endforeach; ?>
