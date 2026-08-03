<?php
/**
 * bunny.net Stream webhook receiver — configure this URL under your
 * Stream library's Security tab -> Webhook URL, and encoding status
 * changes update instantly instead of waiting for an admin to load a page
 * that happens to poll.
 *
 * Payload (per docs.bunny.net/stream/webhooks):
 *   { "VideoLibraryId": 133, "VideoGuid": "...", "Status": 3 }
 */
require_once __DIR__ . '/config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['VideoGuid']) || !isset($data['Status'])) {
    http_response_code(400);
    exit('Bad payload');
}

// Reject payloads for a different library — the only verification available
// without bunny.net's paid signature-verification feature. Combined with
// this URL not being linked from anywhere public, that's reasonable, but if
// you enable HMAC signature verification on the library, verify it here too.
if (!empty($data['VideoLibraryId']) && (string)$data['VideoLibraryId'] !== (string)BUNNY_LIBRARY_ID) {
    http_response_code(403);
    exit('Library mismatch');
}

$guid = $data['VideoGuid'];
$newStatus = bunny_status_to_local((int)$data['Status']);

$stmt = db()->prepare("UPDATE videos SET status = ? WHERE bunny_video_id = ?");
$stmt->execute([$newStatus, $guid]);

if ($newStatus === 'ready') {
    $tStmt = db()->prepare('SELECT title FROM videos WHERE bunny_video_id = ?');
    $tStmt->execute([$guid]);
    $title = $tStmt->fetch()['title'] ?? $guid;
    log_activity('video.bunny_webhook_ready', $title);
}

http_response_code(200);
echo 'ok';
