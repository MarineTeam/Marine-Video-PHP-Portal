<?php
require_once __DIR__ . '/config.php';

$videoId = (int)($_GET['id'] ?? 0);
$token = (string)($_GET['token'] ?? '');
if (!$videoId || !$token || !verify_stream_token($token, $videoId)) {
    http_response_code(403);
    die('Invalid or expired playback token.');
}

$stmt = db()->prepare('SELECT filename FROM videos WHERE id = ?');
$stmt->execute([$videoId]);
$video = $stmt->fetch();
if (!$video || !$video['filename']) { http_response_code(404); die('Video file not found.'); }

$path = rtrim(__DIR__, '/') . '/uploads/videos/' . $video['filename'];
if (!is_file($path)) { http_response_code(404); die('Video file missing on disk.'); }

$size = filesize($path);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $ext === 'webm' ? 'video/webm' : ($ext === 'mov' ? 'video/quicktime' : 'video/mp4');

$start = 0; $end = $size - 1; $length = $size;
header('Accept-Ranges: bytes');
header('Content-Type: ' . $mime);
header('Cache-Control: private, no-store');

if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    $start = $m[1] === '' ? 0 : (int)$m[1];
    $end = min($m[2] === '' ? $size - 1 : (int)$m[2], $size - 1);
    $length = $end - $start + 1;
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
header('Content-Length: ' . $length);

$fh = fopen($path, 'rb');
fseek($fh, $start);
$bytesLeft = $length;
while ($bytesLeft > 0 && !feof($fh)) {
    $chunk = min(8192, $bytesLeft);
    echo fread($fh, $chunk);
    $bytesLeft -= $chunk;
    flush();
}
fclose($fh);
