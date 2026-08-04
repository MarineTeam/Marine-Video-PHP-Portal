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

$stmt = db()->prepare('SELECT id, title FROM videos WHERE bunny_video_id = ?');
$stmt->execute([$guid]);
$video = $stmt->fetch();

if (!$video) {
    // Never seen this bunny.net video before — most likely uploaded
    // directly via bunny.net's own dashboard rather than through this app.
    // Auto-create it so it shows up without a manual Import step.
    $info = bunny_get_video($guid);
    $title = $info['title'] ?? $guid;
    try {
        $minOrder = (int)(db()->query('SELECT COALESCE(MIN(sort_order), 0) m FROM videos')->fetch()['m']);
        db()->prepare('INSERT INTO videos (title, bunny_video_id, sort_order, status) VALUES (?, ?, ?, ?)')
            ->execute([$title, $guid, $minOrder - 1, $newStatus]);
        log_activity('video.bunny_auto_imported', $title);
    } catch (PDOException $e) {
        // uniq_bunny_video_id race — another webhook call for this same new
        // video already inserted it a moment ago; fall through to update.
    }
    $stmt->execute([$guid]);
    $video = $stmt->fetch();
}

if ($video) {
    db()->prepare('UPDATE videos SET status = ? WHERE id = ?')->execute([$newStatus, $video['id']]);
    if ($newStatus === 'ready') {
        log_activity('video.bunny_webhook_ready', $video['title']);
    }
}

http_response_code(200);
echo 'ok';
