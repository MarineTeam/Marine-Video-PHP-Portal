<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('manage_users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'authorize') {
        db()->prepare('UPDATE users SET authorized = 1 WHERE id = ?')->execute([$id]);
        audit_log('user.authorize', "#$id");
        flash('success', 'User authorized.');
    }
    if ($action === 'revoke') {
        $stmt = db()->prepare('SELECT email FROM users WHERE id = ?'); $stmt->execute([$id]);
        $email = $stmt->fetch()['email'] ?? '';
        if (is_admin_email($email)) { flash('error', 'Cannot revoke a configured ADMIN_EMAILS account.'); redirect('users.php'); }
        db()->prepare('UPDATE users SET authorized = 0, role = "MEMBER" WHERE id = ?')->execute([$id]);
        audit_log('user.revoke', "#$id");
        flash('success', 'Access revoked.');
    }
    if ($action === 'promote') {
        db()->prepare('UPDATE users SET role = "ADMIN", authorized = 1 WHERE id = ?')->execute([$id]);
        audit_log('user.promote_admin', "#$id");
        flash('success', 'Promoted to ADMIN.');
    }
    if ($action === 'demote') {
        $stmt = db()->prepare('SELECT email FROM users WHERE id = ?'); $stmt->execute([$id]);
        $email = $stmt->fetch()['email'] ?? '';
        if (is_admin_email($email)) { flash('error', 'Cannot demote a configured ADMIN_EMAILS account.'); redirect('users.php'); }
        db()->prepare('UPDATE users SET role = "MEMBER" WHERE id = ?')->execute([$id]);
        audit_log('user.demote', "#$id");
    }
    redirect('users.php');
}

$users = db()->query('SELECT * FROM users ORDER BY authorized ASC, last_seen DESC')->fetchAll();
$pageTitle = 'Admin · Users';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <h2>Users (<?= count($users) ?>)</h2>
  <p class="muted small">Anyone who signs in via Auth0 gets a row here automatically — but only <strong>authorized</strong> users (or ADMINs) can see member-only content. Grant granular access per-series/category on the <a href="permissions.php">Permissions</a> tab.</p>
  <table class="admin-table">
    <thead><tr><th>Email</th><th>Role</th><th>Status</th><th>Last seen</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['email']) ?></td>
          <td><?= h($u['role']) ?></td>
          <td><?= $u['authorized'] ? 'Authorized' : '<span style="color:var(--danger)">Pending</span>' ?></td>
          <td><?= h(time_ago($u['last_seen'])) ?></td>
          <td>
            <?php if (!$u['authorized']): ?>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="authorize"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="link-btn" type="submit">Authorize</button></form>
            <?php else: ?>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="link-btn danger" type="submit">Revoke</button></form>
            <?php endif; ?>
            <?php if ($u['role'] !== 'ADMIN'): ?>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="promote"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="link-btn" type="submit">Make Admin</button></form>
            <?php else: ?>
              <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="demote"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="link-btn" type="submit">Remove Admin</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
