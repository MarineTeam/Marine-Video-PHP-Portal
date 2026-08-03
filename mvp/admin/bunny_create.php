<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
$admin = require_admin(true);

if (!bunny_is_configured()) {
    http_response_code(400);
    echo json_encode(['error' => 'bunny.net is not configured on this server.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!hash_equals($_SESSION['csrf'] ?? '__none__', $input['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid form session, refresh the page and try again.']);
    exit;
}

if (!rate_limit_check('upload:' . $admin['email'], RATE_LIMIT_UPLOAD_MAX)) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many uploads in a short time. Try again shortly.']);
    exit;
}

$title = trim($input['title'] ?? '');
$collectionId = ($input['collection_id'] ?? '') !== '' ? (int)$input['collection_id'] : null;
if ($title === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required.']);
    exit;
}

[$guid, $err] = bunny_create_video($title);
if (!$guid) {
    http_response_code(502);
    echo json_encode(['error' => $err ?? 'bunny.net video creation failed.']);
    exit;
}

$minOrder = (int)(db()->query('SELECT COALESCE(MIN(sort_order), 0) m FROM videos')->fetch()['m']);
db()->prepare('INSERT INTO videos (title, bunny_video_id, collection_id, sort_order, status)
                VALUES (?, ?, ?, ?, "processing")')
    ->execute([$title, $guid, $collectionId, $minOrder - 1]);
$videoRowId = (int)db()->lastInsertId();

$ticket = bunny_sign_tus_upload($guid);
log_activity('video.upload_bunny', $title);

echo json_encode(['ok' => true, 'video_row_id' => $videoRowId, 'upload' => $ticket, 'title' => $title]);
