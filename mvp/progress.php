<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$user = current_user();
if (!$user || !$user['is_approved']) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$videoId = (int)($input['video_id'] ?? 0);
$position = max(0, (int)($input['position'] ?? 0));
$duration = max(0, (int)($input['duration'] ?? 0));

if (!$videoId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing video_id']);
    exit;
}

$completed = ($duration > 0 && $position / $duration >= 0.95) ? 1 : 0;

// Track a rough watch-time delta for analytics: the increase since last save.
$prevStmt = db()->prepare('SELECT position_seconds FROM watch_progress WHERE user_id = ? AND video_id = ?');
$prevStmt->execute([$user['id'], $videoId]);
$prev = $prevStmt->fetch();
$delta = $prev ? max(0, $position - (int)$prev['position_seconds']) : $position;
$delta = min($delta, 60); // clamp to avoid seek-jump inflation

db()->prepare('INSERT INTO watch_progress (user_id, video_id, position_seconds, duration_seconds, completed)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE position_seconds = VALUES(position_seconds),
                                        duration_seconds = VALUES(duration_seconds),
                                        completed = VALUES(completed)')
    ->execute([$user['id'], $videoId, $position, $duration, $completed]);

if ($delta > 0) {
    db()->prepare('UPDATE videos SET watch_seconds = watch_seconds + ? WHERE id = ?')->execute([$delta, $videoId]);
}

echo json_encode(['ok' => true]);
