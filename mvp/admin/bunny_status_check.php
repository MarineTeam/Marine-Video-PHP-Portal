<?php
require_once __DIR__ . '/../config.php';
require_admin(true);
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, bunny_video_id, status FROM videos WHERE id = ?');
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video || !$video['bunny_video_id']) {
    http_response_code(404);
    echo json_encode(['error' => 'Not a bunny.net video.']);
    exit;
}

$info = bunny_get_video($video['bunny_video_id']);
if (!$info) {
    // Don't silently no-op — this usually means the API key or library ID
    // is wrong, or bunny.net is unreachable from this server right now.
    echo json_encode(['status' => $video['status'], 'error' => 'Could not reach bunny.net to check this video\'s status. Check BUNNY_API_KEY / BUNNY_LIBRARY_ID in config.php.']);
    exit;
}

$bunnyStatusCode = (int)($info['status'] ?? -1);
$newStatus = bunny_status_to_local($bunnyStatusCode);
if ($newStatus !== $video['status']) {
    db()->prepare('UPDATE videos SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
}

echo json_encode(['status' => $newStatus, 'bunny_status_code' => $bunnyStatusCode, 'error' => null]);
