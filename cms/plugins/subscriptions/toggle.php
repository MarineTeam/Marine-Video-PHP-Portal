<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
verify_csrf();

$type = $_POST['content_type'] === 'category' ? 'category' : 'series';
$id = (int)$_POST['content_id'];

$stmt = db()->prepare('SELECT 1 FROM subscriptions WHERE user_id = ? AND content_type = ? AND content_id = ?');
$stmt->execute([$user['id'], $type, $id]);
if ($stmt->fetch()) {
    db()->prepare('DELETE FROM subscriptions WHERE user_id = ? AND content_type = ? AND content_id = ?')->execute([$user['id'], $type, $id]);
} else {
    db()->prepare('INSERT INTO subscriptions (user_id, content_type, content_id) VALUES (?, ?, ?)')->execute([$user['id'], $type, $id]);
}
redirect($_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php'));
