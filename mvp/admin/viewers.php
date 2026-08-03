<?php
require_once __DIR__ . '/../config.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_single') {
        $email = normalize_email($_POST['email'] ?? '');
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($row = $stmt->fetch()) {
                db()->prepare('UPDATE users SET is_approved = 1 WHERE id = ?')->execute([$row['id']]);
            } else {
                db()->prepare('INSERT INTO users (email, password_hash, is_approved) VALUES (?, "", 1)')->execute([$email]);
            }
            log_activity('viewer.add', $email);
            flash('success', "Approved $email.");
        } else {
            flash('error', 'Enter a valid email.');
        }
        redirect('viewers.php');
    }

    if ($action === 'bulk_add') {
        $raw = $_POST['emails'] ?? '';
        $emails = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $added = 0;
        foreach (array_unique(array_map('normalize_email', $emails)) as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($row = $stmt->fetch()) {
                db()->prepare('UPDATE users SET is_approved = 1 WHERE id = ?')->execute([$row['id']]);
            } else {
                db()->prepare('INSERT INTO users (email, password_hash, is_approved) VALUES (?, "", 1)')->execute([$email]);
            }
            $added++;
        }
        log_activity('viewer.bulk_add', "$added emails");
        flash('success', "Approved $added viewer(s).");
        redirect('viewers.php');
    }

    if ($action === 'remove') {
        $id = (int)$_POST['id'];
        $stmt = db()->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $email = $stmt->fetch()['email'] ?? '';
        db()->prepare('UPDATE users SET is_approved = 0 WHERE id = ?')->execute([$id]);
        log_activity('viewer.remove', $email);
        flash('success', 'Viewer removed.');
        redirect('viewers.php');
    }
}

$viewers = db()->query("SELECT * FROM users WHERE is_approved = 1 ORDER BY email")->fetchAll();

$pageTitle = 'Admin · Viewers';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<section>
  <h2>Add a viewer</h2>
  <div class="card">
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_single">
      <input type="email" name="email" placeholder="viewer@example.com" required>
      <button class="btn" type="submit">Approve</button>
    </form>
    <h3>Bulk add</h3>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="bulk_add">
      <textarea name="emails" rows="4" placeholder="Paste emails separated by commas, spaces, or newlines"></textarea>
      <button class="btn" type="submit">Approve all</button>
    </form>
  </div>
</section>

<section>
  <h2>Approved viewers (<?= count($viewers) ?>)</h2>
  <table class="admin-table">
    <thead><tr><th>Email</th><th>Admin</th><th>Last seen</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($viewers as $v): ?>
        <tr>
          <td><?= h($v['email']) ?></td>
          <td><?= is_admin_email($v['email']) ? 'Yes' : '—' ?></td>
          <td><?= h(time_ago($v['last_seen'])) ?></td>
          <td>
            <?php if (!is_admin_email($v['email'])): ?>
              <form method="post" onsubmit="return confirm('Remove this viewer?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                <button class="link-btn danger" type="submit">Remove</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
