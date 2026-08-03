<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
verify_csrf();
if (!rate_limit_check('comment:' . $user['email'], 20)) { flash('error', 'Slow down a bit.'); redirect($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/index.php'); }

$type = $_POST['content_type'] === 'video' ? 'video' : 'series';
$id = (int)$_POST['content_id'];
$body = trim($_POST['body'] ?? '');
if ($body !== '') {
    db()->prepare('INSERT INTO comments (content_type, content_id, user_id, body) VALUES (?, ?, ?, ?)')->execute([$type, $id, $user['id'], $body]);
    audit_log('comment.post', "$type #$id");
}
redirect($_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php'));
