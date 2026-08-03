<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
verify_csrf();
$id = (int)$_POST['id'];

$stmt = db()->prepare('SELECT user_id FROM comments WHERE id = ?'); $stmt->execute([$id]);
$c = $stmt->fetch();
if ($c && ($c['user_id'] == $user['id'] || user_can($user, 'moderate_comments'))) {
    db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
    audit_log('comment.delete', "#$id");
}
redirect($_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php'));
