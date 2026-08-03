<?php
/**
 * One-time setup script. Run this once after configuring config.php, then
 * DELETE this file (or at least its confirm=1 won't work without the flag,
 * but deleting is safest — it's excluded from HTTP access via .htaccess
 * only for config.php/schema.sql, not for itself).
 */
require_once __DIR__ . '/config.php';

$done = false;
$error = null;

if (($_GET['confirm'] ?? '') === 'yes') {
    try {
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        // naive splitter on ";\n" — fine for this schema (no stored procedures)
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        foreach ($statements as $stmt) {
            if ($stmt === '') continue;
            db()->exec($stmt);
        }
        $done = true;

        // Make sure at least the configured admin emails have an approved row.
        foreach (explode(',', ADMIN_EMAILS) as $email) {
            $email = normalize_email($email);
            if (!$email) continue;
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if (!$stmt->fetch()) {
                db()->prepare('INSERT INTO users (email, password_hash, is_admin, is_approved) VALUES (?, "", 1, 1)')
                    ->execute([$email]);
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Install</title>
<link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<div class="center-card">
  <h1>Marine Video Portal — Setup</h1>
  <?php if ($error): ?>
    <p class="flash flash-error"><?= h($error) ?></p>
  <?php elseif ($done): ?>
    <p class="flash flash-success">Database tables created and admin account(s) seeded.</p>
    <p><strong>Now delete install.php from the server.</strong></p>
    <a class="btn" href="index.php">Go to the portal</a>
  <?php else: ?>
    <p>This will create all required tables in <code><?= h(DB_NAME) ?></code> and mark the emails in <code>ADMIN_EMAILS</code> as approved admins.</p>
    <a class="btn" href="?confirm=yes">Run setup</a>
  <?php endif; ?>
</div>
</body></html>
