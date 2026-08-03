<?php
require_once __DIR__ . '/../config.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'revoke') {
        db()->prepare('UPDATE share_links SET revoked = 1 WHERE id = ?')->execute([$id]);
        log_activity('share.revoke', "#$id");
        flash('success', 'Link revoked.');
        redirect('shares.php');
    }

    if ($action === 'resend') {
        if (!rate_limit_check('share_resend:' . $admin['email'], RATE_LIMIT_SHARE_MAX)) {
            flash('error', 'Too many resends recently. Try again shortly.');
            redirect('shares.php');
        }
        $stmt = db()->prepare('SELECT sl.*, v.title FROM share_links sl JOIN videos v ON v.id = sl.video_id WHERE sl.id = ?');
        $stmt->execute([$id]);
        $s = $stmt->fetch();
        if ($s && !$s['revoked']) {
            $watchUrl = SITE_URL . '/share.php?t=' . $s['token'];
            [$ok, $err] = send_share_link_email($s['recipient_email'], $s['title'], $watchUrl, date('M j, Y g:ia', strtotime($s['expires_at'])) . ' UTC');
            if ($ok) {
                db()->prepare('UPDATE share_links SET emailed_at = NOW() WHERE id = ?')->execute([$id]);
                log_activity('share.resend', $s['recipient_email']);
                flash('success', 'Resent.');
            } else {
                flash('error', "Resend failed: $err");
            }
        }
        redirect('shares.php');
    }
}

$shares = db()->query("SELECT sl.*, v.title FROM share_links sl JOIN videos v ON v.id = sl.video_id
                        WHERE sl.revoked = 0 AND sl.expires_at > NOW() ORDER BY sl.created_at DESC")->fetchAll();

$pageTitle = 'Admin · Shares';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <h2>Active private links (<?= count($shares) ?>)</h2>
  <table class="admin-table">
    <thead><tr><th>Video</th><th>Recipient</th><th>Expires</th><th>Viewed</th><th>Emailed</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($shares as $s): ?>
      <tr>
        <td><?= h($s['title']) ?></td>
        <td><?= h($s['recipient_email']) ?></td>
        <td><?= h(date('M j, g:ia', strtotime($s['expires_at']))) ?></td>
        <td><?= $s['viewed_at'] ? 'Yes, ' . h(time_ago($s['viewed_at'])) : 'Not yet' ?></td>
        <td><?= $s['emailed_at'] ? 'Yes, ' . h(time_ago($s['emailed_at'])) : '—' ?></td>
        <td>
          <?php if (resend_is_configured()): ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="link-btn" type="submit">Resend</button>
            </form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Revoke this link immediately?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="link-btn danger" type="submit">Revoke</button>
          </form>
          <button class="link-btn" onclick="navigator.clipboard.writeText('<?= h(SITE_URL) ?>/share.php?t=<?= h($s['token']) ?>');this.textContent='Copied!'">Copy link</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
