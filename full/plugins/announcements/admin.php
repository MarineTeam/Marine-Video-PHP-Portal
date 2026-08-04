<?php
/** Included by admin/plugin-admin.php after capability + active checks. */
$admin = $GLOBALS['__plugin_admin_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $msg = trim($_POST['message'] ?? '');
        if ($msg !== '') {
            db()->prepare('UPDATE announcements SET active = 0')->execute(); // only one active at a time
            db()->prepare('INSERT INTO announcements (message, active) VALUES (?, 1)')->execute([$msg]);
            audit_log('announcement.create', $msg);
        }
    }
    if ($action === 'toggle') {
        db()->prepare('UPDATE announcements SET active = 1 - active WHERE id = ?')->execute([(int)$_POST['id']]);
    }
    if ($action === 'delete') {
        db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int)$_POST['id']]);
    }
    redirect('plugin-admin.php?slug=announcements');
}

$all = db()->query('SELECT * FROM announcements ORDER BY created_at DESC')->fetchAll();
?>
<h2>Announcements</h2>
<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <label>New announcement (becomes the active banner) <textarea name="message" rows="2" required></textarea></label>
  <button class="btn" type="submit">Publish</button>
</form>
<table class="admin-table">
  <?php foreach ($all as $a): ?>
    <tr><td><?= h($a['message']) ?></td><td><?= $a['active'] ? 'Active' : 'Inactive' ?></td>
    <td><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="link-btn" type="submit">Toggle</button></form>
    <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="link-btn danger" type="submit">Delete</button></form></td></tr>
  <?php endforeach; ?>
</table>
