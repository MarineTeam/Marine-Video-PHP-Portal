<?php
require_once __DIR__ . '/../config.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $count = max(1, (int)($_POST['homepage_count'] ?? 60));
        set_setting('homepage_count', (string)$count);

        $preset = $_POST['theme_preset'] ?? 'ocean';
        $custom1 = trim($_POST['custom1'] ?? '');
        $custom2 = trim($_POST['custom2'] ?? '');
        if ($preset === 'custom' && $custom1 && $custom2) {
            set_setting('theme_custom', $custom1 . ',' . $custom2);
        } else {
            set_setting('theme_custom', '');
            set_setting('theme_preset', $preset);
        }
        log_activity('settings.save', "count=$count preset=$preset");
        flash('success', 'Settings saved.');
        redirect('settings.php');
    }
}

$homepageCount = get_setting('homepage_count', '60');
$themePreset = get_setting('theme_preset', 'ocean');
$themeCustom = get_setting('theme_custom', '');

$pageTitle = 'Admin · Settings';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <h2>Homepage</h2>
  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_settings">
    <label>Videos shown on homepage (total, before pagination)
      <input type="number" name="homepage_count" min="1" value="<?= h($homepageCount) ?>">
    </label>

    <h3>Color palette</h3>
    <div class="palette-grid">
      <?php foreach (THEME_PRESETS as $name => $vars): ?>
        <label class="palette-swatch">
          <input type="radio" name="theme_preset" value="<?= h($name) ?>" <?= (!$themeCustom && $themePreset === $name) ? 'checked' : '' ?>>
          <span style="background:linear-gradient(90deg,<?= h($vars['--accent1']) ?>,<?= h($vars['--accent2']) ?>)"></span>
          <?= h(ucfirst($name)) ?>
        </label>
      <?php endforeach; ?>
      <label class="palette-swatch">
        <input type="radio" name="theme_preset" value="custom" <?= $themeCustom ? 'checked' : '' ?>>
        Custom:
        <input type="color" name="custom1" value="<?= h(explode(',', $themeCustom)[0] ?? '#3ddad7') ?>">
        <input type="color" name="custom2" value="<?= h(explode(',', $themeCustom)[1] ?? '#5b8dee') ?>">
      </label>
    </div>

    <button class="btn" type="submit">Save settings</button>
  </form>
</section>

<section>
  <h2>Content protection</h2>
  <div class="card">
    <p>Every play uses a signed, time-limited streaming token generated fresh per request (<?= (int)STREAM_TOKEN_TTL ?> seconds) — there is no permanent, guessable public URL to a video file.</p>
    <p>For extra hardening at the web-server level, consider: serving <code>/uploads</code> from outside the web root and proxying through <code>stream.php</code> only, or adding a signed-cookie/Referer check in front of your reverse proxy.</p>
    <p>bunny.net videos are currently <strong><?= bunny_is_configured() ? 'enabled' : 'disabled — set BUNNY_LIBRARY_ID/BUNNY_API_KEY/BUNNY_TOKEN_AUTH_KEY in config.php' ?></strong>. If enabled, direct bunny.net CDN file URLs are never used by this app — only signed, time-limited embed tokens are. For full lockdown, enable <strong>Block Direct URL File Access</strong> on the library's Security tab (and set <code>BUNNY_CDN_TOKEN_KEY</code> here if the pull zone's token key differs from the library's).</p>
    <p>Email delivery of share links is currently <strong><?= resend_is_configured() ? 'enabled (Resend)' : 'disabled — set RESEND_API_KEY in config.php' ?></strong>.</p>
  </div>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
