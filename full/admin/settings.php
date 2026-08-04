<?php
require_once __DIR__ . '/../config.php';
$admin = require_login();
if ($admin['role'] !== 'ADMIN') { http_response_code(403); die('Admins only.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    set_setting('site_name', trim($_POST['site_name'] ?? 'Marine Team'));
    $preset = $_POST['theme_preset'] ?? 'ocean';
    if ($preset === 'custom' && !empty($_POST['custom1']) && !empty($_POST['custom2'])) {
        set_setting('theme_custom', trim($_POST['custom1']) . ',' . trim($_POST['custom2']));
    } else {
        set_setting('theme_custom', '');
        set_setting('theme_preset', $preset);
    }
    audit_log('settings.save');
    flash('success', 'Settings saved.');
    redirect('settings.php');
}

$siteName = get_setting('site_name', 'Marine Team');
$themePreset = get_setting('theme_preset', 'ocean');
$themeCustom = get_setting('theme_custom', '');

$pageTitle = 'Admin · Settings';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <label>Site name <input type="text" name="site_name" value="<?= h($siteName) ?>"></label>
    <h3>Color palette</h3>
    <div class="palette-grid">
      <?php foreach (THEME_PRESETS as $name => $vars): ?>
        <label style="display:flex;align-items:center;gap:6px;">
          <input type="radio" name="theme_preset" value="<?= h($name) ?>" <?= (!$themeCustom && $themePreset === $name) ? 'checked' : '' ?>>
          <span style="width:24px;height:24px;border-radius:50%;display:inline-block;background:linear-gradient(90deg,<?= h($vars['--accent1']) ?>,<?= h($vars['--accent2']) ?>)"></span> <?= h(ucfirst($name)) ?>
        </label>
      <?php endforeach; ?>
      <label>Custom: <input type="radio" name="theme_preset" value="custom" <?= $themeCustom ? 'checked' : '' ?>>
        <input type="color" name="custom1" value="<?= h(explode(',', $themeCustom)[0] ?? '#3ddad7') ?>">
        <input type="color" name="custom2" value="<?= h(explode(',', $themeCustom)[1] ?? '#5b8dee') ?>">
      </label>
    </div>
    <button class="btn" type="submit">Save</button>
  </form>
</section>
<section>
  <h2>Integration status</h2>
  <div class="card">
    <p>Auth0: <strong><?= AUTH0_DOMAIN !== '' && AUTH0_DOMAIN !== '{{AUTH0_DOMAIN}}' ? 'Configured' : 'Not configured'  ?></strong></p>
    <p>bunny.net Stream: <strong><?= bunny_stream_configured() ? 'Configured' : 'Not configured' ?></strong></p>
    <p>bunny.net Storage: <strong><?= bunny_storage_configured() ? 'Configured' : 'Not configured — files upload locally up to ' . round(LOCAL_FILE_UPLOAD_LIMIT/1048576,1) . 'MB' ?></strong></p>
    <p>Resend: <strong><?= resend_is_configured() ? 'Configured' : 'Not configured' ?></strong></p>
    <p>Web Push (VAPID): <strong><?= (VAPID_PUBLIC_KEY !== '' && VAPID_PUBLIC_KEY !== '{{VAPID_PUBLIC_KEY}}') ? 'Configured' : 'Not configured' ?></strong></p>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
