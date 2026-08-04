<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
$admin = require_capability('manage_videos');

if (!bunny_stream_configured()) { http_response_code(400); echo json_encode(['error' => 'bunny.net is not configured.']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!hash_equals($_SESSION['csrf'] ?? '__none__', $input['csrf'] ?? '')) {
    http_response_code(400); echo json_encode(['error' => 'Invalid form session, refresh and try again.']); exit;
}
if (!rate_limit_check('bunny_upload:' . $admin['email'], 30)) {
    http_response_code(429); echo json_encode(['error' => 'Too many uploads recently.']); exit;
}

$title = trim($input['title'] ?? '');
$seriesId = (int)($input['series_id'] ?? 0);
$memberOnly = !empty($input['member_only']) ? 1 : 0;
if ($title === '' || !$seriesId) { http_response_code(400); echo json_encode(['error' => 'Title and series are required.']); exit; }

[$guid, $err] = bunny_create_video($title);
if (!$guid) { http_response_code(502); echo json_encode(['error' => $err ?? 'bunny.net video creation failed.']); exit; }

$maxPos = (int)(db()->query("SELECT COALESCE(MAX(position),0) m FROM videos WHERE series_id = " . (int)$seriesId)->fetch()['m']);
$slug = unique_slug('videos', $title . '-' . bin2hex(random_bytes(3)));
db()->prepare('INSERT INTO videos (series_id, title, slug, bunny_video_id, member_only, position, status) VALUES (?, ?, ?, ?, ?, ?, "processing")')
    ->execute([$seriesId, $title, $slug, $guid, $memberOnly, $maxPos + 1]);
$videoRowId = (int)db()->lastInsertId();

audit_log('video.upload_bunny', $title);
echo json_encode(['ok' => true, 'video_row_id' => $videoRowId, 'upload' => bunny_sign_tus_upload($guid), 'title' => $title]);
