<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
verify_csrf();

$type = $_POST['content_type'] === 'video' ? 'video' : 'series';
$id = (int)$_POST['content_id'];

if (plugin_favorites_is_favorited($user['id'], $type, $id)) {
    db()->prepare('DELETE FROM favorites WHERE user_id = ? AND content_type = ? AND content_id = ?')->execute([$user['id'], $type, $id]);
} else {
    db()->prepare('INSERT INTO favorites (user_id, content_type, content_id) VALUES (?, ?, ?)')->execute([$user['id'], $type, $id]);
}

$referer = $_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php');
redirect($referer);
