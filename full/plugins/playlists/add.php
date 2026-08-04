<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
verify_csrf();

$videoId = (int)$_POST['video_id'];
$playlistId = $_POST['playlist_id'] ?? '';

if ($playlistId === 'new') {
    $name = trim($_POST['new_name'] ?? '') ?: 'My Playlist';
    db()->prepare('INSERT INTO playlists (user_id, name) VALUES (?, ?)')->execute([$user['id'], $name]);
    $playlistId = (int)db()->lastInsertId();
} else {
    $playlistId = (int)$playlistId;
    $stmt = db()->prepare('SELECT 1 FROM playlists WHERE id = ? AND user_id = ?'); $stmt->execute([$playlistId, $user['id']]);
    if (!$stmt->fetch()) { flash('error', 'Playlist not found.'); redirect($_SERVER['HTTP_REFERER'] ?? SITE_URL . '/index.php'); }
}

$maxPos = (int)(db()->query('SELECT COALESCE(MAX(position),0) m FROM playlist_items WHERE playlist_id = ' . (int)$playlistId)->fetch()['m']);
db()->prepare('INSERT INTO playlist_items (playlist_id, video_id, position) VALUES (?, ?, ?)')->execute([$playlistId, $videoId, $maxPos + 1]);
flash('success', 'Added to playlist.');
redirect($_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php'));
