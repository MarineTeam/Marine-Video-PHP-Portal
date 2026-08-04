<?php
require_once __DIR__ . '/../config.php';
require_login();
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, series_id, bunny_video_id, status, title FROM videos WHERE id = ?');
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video) { http_response_code(404); echo json_encode(['error' => 'Not found.']); exit; }
require_capability('manage_videos', 'series', (int)$video['series_id']);

if (!$video['bunny_video_id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Not a bunny.net video.']);
    exit;
}

$info = bunny_get_video($video['bunny_video_id']);
if (!$info) {
    echo json_encode(['status' => $video['status'], 'error' => 'Could not reach bunny.net to check this video\'s status. Check BUNNY_STREAM_API_KEY / BUNNY_STREAM_LIBRARY_ID in config.php.']);
    exit;
}

$bunnyStatusCode = (int)($info['status'] ?? -1);
$newStatus = bunny_status_to_local($bunnyStatusCode);
$duration = (int)($info['length'] ?? 0);
if ($newStatus !== $video['status']) {
    db()->prepare('UPDATE videos SET status = ?, duration_seconds = ? WHERE id = ?')->execute([$newStatus, $duration, $id]);
    if ($newStatus === 'ready') {
        audit_log('video.bunny_ready', $video['title']);
        do_action('content_published', 'video', $id);
    }
}

echo json_encode(['status' => $newStatus, 'bunny_status_code' => $bunnyStatusCode, 'error' => null]);
