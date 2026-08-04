<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('manage_series'); // sharing content requires the same capability as managing it

define('SHARE_DEFAULT_HOURS', 72);
define('SHARE_MAX_HOURS', 720);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $contentType = $_POST['content_type'] === 'video' ? 'video' : 'series';
        $contentId = (int)$_POST['content_id'];
        $email = normalize_email($_POST['recipient_email'] ?? '');
        $hours = min(SHARE_MAX_HOURS, max(1, (int)($_POST['hours'] ?? SHARE_DEFAULT_HOURS)));
        $sendEmail = !empty($_POST['send_email']);

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid recipient email.');
            redirect('shares.php');
        }
        if (!rate_limit_check('share:' . $admin['email'], 20)) {
            flash('error', 'Too many share links created recently. Try again shortly.');
            redirect('shares.php');
        }

        $table = $contentType === 'video' ? 'videos' : 'series';
        $col = 'title';
        $cStmt = db()->prepare("SELECT $col FROM `$table` WHERE id = ?"); $cStmt->execute([$contentId]);
        $contentTitle = $cStmt->fetch()[$col] ?? null;
        if (!$contentTitle) { flash('error', 'Content not found.'); redirect('shares.php'); }

        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', time() + $hours * 3600);
        db()->prepare('INSERT INTO share_links (token, content_type, content_id, recipient_email, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$token, $contentType, $contentId, $email, $expiresAt, $admin['email']]);
        audit_log('share.create', "$contentType #$contentId -> $email");

        $watchUrl = SITE_URL . '/share.php?t=' . $token;
        if ($sendEmail && resend_is_configured()) {
            [$ok, $err] = resend_send($email, "You've been shared: $contentTitle", "
                <div style=\"font-family:Inter,Arial,sans-serif;background:#0b0f14;color:#e7edf3;padding:32px;border-radius:12px;max-width:520px;margin:0 auto;\">
                  <h2>" . h(get_setting('site_name', 'Marine Team')) . "</h2>
                  <p>Someone shared this with you: <strong>" . h($contentTitle) . "</strong></p>
                  <p><a href=\"" . h($watchUrl) . "\" style=\"display:inline-block;padding:12px 20px;background:linear-gradient(90deg,#3ddad7,#5b8dee);color:#03121a;font-weight:700;border-radius:8px;text-decoration:none;\">View</a></p>
                  <p style=\"color:#9fb0c0;font-size:13px;\">This link only works when signed in with this email address, and expires " . h(date('M j, Y g:ia', strtotime($expiresAt))) . " UTC.</p>
                </div>");
            if ($ok) {
                db()->prepare('UPDATE share_links SET emailed_at = NOW() WHERE token = ?')->execute([$token]);
                audit_log('share.email', $email);
                flash('success', "Share link created and emailed to $email.");
            } else {
                flash('error', "Share link created, but the email failed to send: $err. Copy the link below instead.");
            }
        } else {
            flash('success', 'Share link created. Copy it below to send manually.');
        }
        redirect('shares.php');
    }

    if ($action === 'revoke') {
        db()->prepare('UPDATE share_links SET revoked = 1 WHERE id = ?')->execute([(int)$_POST['id']]);
        audit_log('share.revoke', '#' . (int)$_POST['id']);
        flash('success', 'Link revoked.');
        redirect('shares.php');
    }

    if ($action === 'resend') {
        $id = (int)$_POST['id'];
        $stmt = db()->prepare('SELECT * FROM share_links WHERE id = ?'); $stmt->execute([$id]);
        $s = $stmt->fetch();
        if ($s && !$s['revoked'] && resend_is_configured()) {
            $table = $s['content_type'] === 'video' ? 'videos' : 'series';
            $tStmt = db()->prepare("SELECT title FROM `$table` WHERE id = ?"); $tStmt->execute([$s['content_id']]);
            $title = $tStmt->fetch()['title'] ?? 'shared content';
            $watchUrl = SITE_URL . '/share.php?t=' . $s['token'];
            [$ok, $err] = resend_send($s['recipient_email'], "You've been shared: $title", "<p>" . h($title) . "</p><p><a href=\"" . h($watchUrl) . "\">View</a></p>");
            if ($ok) {
                db()->prepare('UPDATE share_links SET emailed_at = NOW() WHERE id = ?')->execute([$id]);
                audit_log('share.resend', $s['recipient_email']);
                flash('success', 'Resent.');
            } else {
                flash('error', "Resend failed: $err");
            }
        }
        redirect('shares.php');
    }
}

$shares = db()->query("SELECT * FROM share_links WHERE revoked = 0 AND expires_at > NOW() ORDER BY created_at DESC")->fetchAll();
foreach ($shares as &$s) {
    $table = $s['content_type'] === 'video' ? 'videos' : 'series';
    $tStmt = db()->prepare("SELECT title FROM `$table` WHERE id = ?"); $tStmt->execute([$s['content_id']]);
    $s['content_title'] = $tStmt->fetch()['title'] ?? '(deleted)';
}
unset($s);

$pageTitle = 'Admin · Shares';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <h2>Active private links (<?= count($shares) ?>)</h2>
  <p class="muted small">Create new share links directly from the Series or Videos admin pages ("Share" action on each row) — this tab is for reviewing, resending, and revoking existing ones.</p>
  <table class="admin-table">
    <thead><tr><th>Content</th><th>Recipient</th><th>Expires</th><th>Viewed</th><th>Emailed</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($shares as $s): ?>
      <tr>
        <td><?= h(ucfirst($s['content_type'])) ?>: <?= h($s['content_title']) ?></td>
        <td><?= h($s['recipient_email']) ?></td>
        <td><?= h(date('M j, g:ia', strtotime($s['expires_at']))) ?></td>
        <td><?= $s['viewed_at'] ? 'Yes, ' . h(time_ago($s['viewed_at'])) : 'Not yet' ?></td>
        <td><?= $s['emailed_at'] ? 'Yes, ' . h(time_ago($s['emailed_at'])) : '—' ?></td>
        <td>
          <?php if (resend_is_configured()): ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="link-btn" type="submit">Resend</button></form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Revoke this link immediately?')"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="link-btn danger" type="submit">Revoke</button></form>
          <button class="link-btn" onclick="navigator.clipboard.writeText('<?= h(SITE_URL) ?>/share.php?t=<?= h($s['token']) ?>');this.textContent='Copied!'">Copy link</button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
