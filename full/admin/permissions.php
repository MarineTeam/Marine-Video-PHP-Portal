<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('manage_permissions');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_group') {
        $name = trim($_POST['name'] ?? '');
        $caps = implode(',', array_intersect((array)($_POST['capabilities'] ?? []), array_keys(CAPABILITIES)));
        if ($name !== '') {
            db()->prepare('INSERT INTO permission_groups (name, capabilities) VALUES (?, ?)')->execute([$name, $caps]);
            audit_log('permission_group.create', $name);
            flash('success', 'Group created.');
        }
        redirect('permissions.php');
    }

    if ($action === 'delete_group') {
        db()->prepare('DELETE FROM permission_groups WHERE id = ?')->execute([(int)$_POST['id']]);
        audit_log('permission_group.delete', "#" . (int)$_POST['id']);
        redirect('permissions.php');
    }

    if ($action === 'assign_group') {
        $userId = (int)$_POST['user_id'];
        $groupId = (int)$_POST['group_id'];
        $scopeType = in_array($_POST['scope_type'] ?? 'site', ['site', 'category', 'series'], true) ? $_POST['scope_type'] : 'site';
        $scopeId = $scopeType !== 'site' ? (int)$_POST['scope_id'] : null;
        db()->prepare('INSERT INTO user_group_assignments (user_id, group_id, scope_type, scope_id) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $groupId, $scopeType, $scopeId]);
        audit_log('permission.assign', "user #$userId -> group #$groupId ($scopeType)");
        flash('success', 'Assigned.');
        redirect('permissions.php');
    }

    if ($action === 'unassign') {
        db()->prepare('DELETE FROM user_group_assignments WHERE id = ?')->execute([(int)$_POST['id']]);
        redirect('permissions.php');
    }

    if ($action === 'add_grant') {
        $contentType = $_POST['content_type'] === 'video' ? 'video' : 'series';
        $contentId = (int)$_POST['content_id'];
        $grantType = $_POST['grant_type'] === 'group' ? 'group' : 'email';
        if ($grantType === 'group') {
            db()->prepare('INSERT INTO viewer_grants (content_type, content_id, grant_type, group_id) VALUES (?, ?, "group", ?)')
                ->execute([$contentType, $contentId, (int)$_POST['group_id']]);
        } else {
            db()->prepare('INSERT INTO viewer_grants (content_type, content_id, grant_type, email) VALUES (?, ?, "email", ?)')
                ->execute([$contentType, $contentId, normalize_email($_POST['email'] ?? '')]);
        }
        audit_log('viewer_grant.add', "$contentType #$contentId");
        flash('success', 'Grant added. Once any grant exists on an item, member-only is ignored — only grantees (and admins) can view it.');
        redirect('permissions.php?scope_type=' . $contentType . '&scope_id=' . $contentId);
    }

    if ($action === 'remove_grant') {
        db()->prepare('DELETE FROM viewer_grants WHERE id = ?')->execute([(int)$_POST['id']]);
        redirect('permissions.php');
    }
}

$groups = db()->query('SELECT * FROM permission_groups ORDER BY name')->fetchAll();
$assignments = db()->query('SELECT uga.*, u.email, pg.name AS group_name FROM user_group_assignments uga
                             JOIN users u ON u.id = uga.user_id JOIN permission_groups pg ON pg.id = uga.group_id
                             ORDER BY u.email')->fetchAll();
$users = db()->query('SELECT id, email FROM users ORDER BY email')->fetchAll();

$scopeType = $_GET['scope_type'] ?? null;
$scopeId = isset($_GET['scope_id']) ? (int)$_GET['scope_id'] : null;
$grants = [];
$scopeLabel = null;
if ($scopeType && $scopeId) {
    $stmt = db()->prepare('SELECT vg.*, pg.name AS group_name FROM viewer_grants vg LEFT JOIN permission_groups pg ON pg.id = vg.group_id WHERE content_type = ? AND content_id = ?');
    $stmt->execute([$scopeType, $scopeId]);
    $grants = $stmt->fetchAll();
    $table = $scopeType === 'video' ? 'videos' : 'series';
    $col = $scopeType === 'video' ? 'title' : 'title';
    $lStmt = db()->prepare("SELECT $col FROM `$table` WHERE id = ?"); $lStmt->execute([$scopeId]);
    $scopeLabel = $lStmt->fetch()[$col] ?? "#$scopeId";
}

$pageTitle = 'Admin · Permissions';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <h2>Permission groups</h2>
  <p class="muted small">A group bundles capabilities. Assign a group to a user site-wide, or scoped to one category (covers everything under it) or one series.</p>
  <form method="post" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="create_group">
    <label>Group name <input type="text" name="name" required></label>
    <label>Capabilities</label>
    <?php foreach (CAPABILITIES as $key => $label): ?>
      <label style="display:inline-block;margin-right:16px;"><input type="checkbox" name="capabilities[]" value="<?= h($key) ?>"> <?= h($label) ?></label>
    <?php endforeach; ?>
    <button class="btn" type="submit">Create group</button>
  </form>
  <ul class="chip-list">
    <?php foreach ($groups as $g): ?>
      <li class="chip"><?= h($g['name']) ?> (<?= h($g['capabilities']) ?>)
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete_group"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="link-btn danger" onclick="return confirm('Delete this group?')">×</button></form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section>
  <h2>Assign a group to a user</h2>
  <form method="post" class="card inline-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="assign_group">
    <select name="user_id"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= h($u['email']) ?></option><?php endforeach; ?></select>
    <select name="group_id"><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= h($g['name']) ?></option><?php endforeach; ?></select>
    <select name="scope_type"><option value="site">Site-wide</option><option value="category">Category ID</option><option value="series">Series ID</option></select>
    <input type="number" name="scope_id" placeholder="ID (if scoped)">
    <button class="btn small" type="submit">Assign</button>
  </form>
  <table class="admin-table">
    <thead><tr><th>User</th><th>Group</th><th>Scope</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr><td><?= h($a['email']) ?></td><td><?= h($a['group_name']) ?></td>
        <td><?= h($a['scope_type']) ?><?= $a['scope_id'] ? ' #' . (int)$a['scope_id'] : '' ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unassign"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="link-btn danger" type="submit">Remove</button></form></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section>
  <h2>Restricted viewing grants<?= $scopeLabel ? ' — ' . h($scopeLabel) : '' ?></h2>
  <p class="muted small">Grant specific groups or exact emails access to one series/video. As soon as any grant exists on an item, it becomes fully restricted — member-only no longer applies, and only grantees (plus admins) can view it.</p>
  <form method="get" class="inline-form">
    <select name="scope_type"><option value="series" <?= $scopeType === 'series' ? 'selected' : '' ?>>Series</option><option value="video" <?= $scopeType === 'video' ? 'selected' : '' ?>>Video</option></select>
    <input type="number" name="scope_id" placeholder="ID" value="<?= h((string)$scopeId) ?>">
    <button class="btn small" type="submit">Load grants</button>
  </form>
  <?php if ($scopeType && $scopeId): ?>
    <form method="post" class="card inline-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_grant">
      <input type="hidden" name="content_type" value="<?= h($scopeType) ?>"><input type="hidden" name="content_id" value="<?= (int)$scopeId ?>">
      <select name="grant_type"><option value="email">Email</option><option value="group">Group</option></select>
      <input type="email" name="email" placeholder="person@example.com">
      <select name="group_id"><option value="">— or pick a group —</option><?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= h($g['name']) ?></option><?php endforeach; ?></select>
      <button class="btn small" type="submit">Add grant</button>
    </form>
    <table class="admin-table">
      <?php foreach ($grants as $g): ?>
        <tr><td><?= $g['grant_type'] === 'email' ? h($g['email']) : 'Group: ' . h($g['group_name']) ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove_grant"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="link-btn danger" type="submit">Remove</button></form></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
