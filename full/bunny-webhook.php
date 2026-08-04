<?php
/**
 * bunny.net Stream webhook receiver — configure this URL under your
 * Stream library's Security tab -> Webhook URL, and encoding status
 * changes update instantly instead of waiting for an admin to load the
 * Videos tab for that series (which only polls reactively).
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
if (!empty($data['VideoLibraryId']) && (string)$data['VideoLibraryId'] !== (string)BUNNY_STREAM_LIBRARY_ID) {
    http_response_code(403);
    exit('Library mismatch');
}

$guid = $data['VideoGuid'];
$newStatus = bunny_status_to_local((int)$data['Status']);

$stmt = db()->prepare('SELECT id, series_id, title, status AS old_status FROM videos WHERE bunny_video_id = ?');
$stmt->execute([$guid]);
$video = $stmt->fetch();

if (!$video) {
    // Never seen this bunny.net video before — most likely uploaded
    // directly via bunny.net's own dashboard rather than through this app.
    // The content model requires every video to belong to a series, and
    // bunny.net's webhook has no way to tell us which one you'd want — so
    // auto-created videos land in a find-or-create "Unsorted" series for
    // you to re-file later, rather than guessing.
    $info = bunny_get_video($guid);
    $title = $info['title'] ?? $guid;

    $sStmt = db()->prepare("SELECT id FROM series WHERE slug = 'unsorted'");
    $sStmt->execute();
    $unsorted = $sStmt->fetch();
    if (!$unsorted) {
        db()->prepare("INSERT INTO series (title, slug, published) VALUES ('Unsorted', 'unsorted', 1)")->execute();
        $unsortedId = (int)db()->lastInsertId();
    } else {
        $unsortedId = (int)$unsorted['id'];
    }

    try {
        $maxPos = (int)(db()->query("SELECT COALESCE(MAX(position),0) m FROM videos WHERE series_id = $unsortedId")->fetch()['m']);
        $slug = unique_slug('videos', 'unsorted-' . $title . '-' . substr($guid, 0, 8));
        db()->prepare('INSERT INTO videos (series_id, title, slug, bunny_video_id, position, status) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$unsortedId, $title, $slug, $guid, $maxPos + 1, $newStatus]);
        audit_log('video.bunny_auto_imported', $title);
    } catch (PDOException $e) {
        // uniq_bunny_video_id race — another webhook call for this same new
        // video already inserted it a moment ago; fall through to update.
    }
    $stmt->execute([$guid]);
    $video = $stmt->fetch();
}

if ($video) {
    db()->prepare('UPDATE videos SET status = ? WHERE id = ?')->execute([$newStatus, $video['id']]);
    if ($newStatus === 'ready' && $video['old_status'] !== 'ready') {
        audit_log('video.bunny_webhook_ready', $video['title']);
        do_action('content_published', 'video', (int)$video['id']);
    }
}

http_response_code(200);
echo 'ok';
