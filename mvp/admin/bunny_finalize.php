<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
$admin = require_admin(true);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!hash_equals($_SESSION['csrf'] ?? '__none__', $input['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid form session, refresh the page and try again.']);
    exit;
}

$videoRowId = (int)($input['video_row_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM videos WHERE id = ?');
$stmt->execute([$videoRowId]);
$video = $stmt->fetch();
if (!$video || !$video['bunny_video_id']) {
    http_response_code(404);
    echo json_encode(['error' => 'Video not found.']);
    exit;
}

$info = bunny_get_video($video['bunny_video_id']);
if (!$info) {
    echo json_encode(['ok' => true, 'status' => 'processing']); // bunny not reachable yet — try again shortly
    exit;
}

$localStatus = bunny_status_to_local((int)($info['status'] ?? 0));
db()->prepare('UPDATE videos SET status = ? WHERE id = ?')->execute([$localStatus, $videoRowId]);
if ($localStatus === 'ready') {
    log_activity('video.bunny_ready', $video['title']);
}

echo json_encode(['ok' => true, 'status' => $localStatus]);
