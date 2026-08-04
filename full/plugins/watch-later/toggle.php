<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
verify_csrf();
$videoId = (int)$_POST['video_id'];

$stmt = db()->prepare('SELECT 1 FROM watch_later WHERE user_id = ? AND video_id = ?');
$stmt->execute([$user['id'], $videoId]);
if ($stmt->fetch()) {
    db()->prepare('DELETE FROM watch_later WHERE user_id = ? AND video_id = ?')->execute([$user['id'], $videoId]);
} else {
    $maxPos = (int)(db()->query('SELECT COALESCE(MAX(position),0) m FROM watch_later WHERE user_id = ' . (int)$user['id'])->fetch()['m']);
    db()->prepare('INSERT INTO watch_later (user_id, video_id, position) VALUES (?, ?, ?)')->execute([$user['id'], $videoId, $maxPos + 1]);
}
redirect($_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php'));
