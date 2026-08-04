<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
verify_csrf();

$type = $_POST['content_type'] === 'video' ? 'video' : 'series';
$id = (int)$_POST['content_id'];
$reaction = $_POST['reaction'] === 'dislike' ? 'dislike' : 'like';

$stmt = db()->prepare('SELECT reaction FROM reactions WHERE content_type = ? AND content_id = ? AND user_id = ?');
$stmt->execute([$type, $id, $user['id']]);
$existing = $stmt->fetch();

if ($existing && $existing['reaction'] === $reaction) {
    db()->prepare('DELETE FROM reactions WHERE content_type = ? AND content_id = ? AND user_id = ?')->execute([$type, $id, $user['id']]);
} else {
    db()->prepare('INSERT INTO reactions (content_type, content_id, user_id, reaction) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE reaction = VALUES(reaction)')->execute([$type, $id, $user['id'], $reaction]);
}
redirect($_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php'));
