<?php
require_once __DIR__ . '/config.php';
$user = current_user();

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM videos WHERE slug = ?');
$stmt->execute([$slug]);
$video = $stmt->fetch();
if (!$video) { http_response_code(404); die('Video not found.'); }

$sStmt = db()->prepare('SELECT * FROM series WHERE id = ?');
$sStmt->execute([$video['series_id']]);
$series = $sStmt->fetch();

if (!can_view_video($video, $user, $series)) {
    if (!$user) redirect(auth0_login_url(current_url()));
    require_authorized();
    http_response_code(403);
    die('This video is restricted.');
}
if (is_video_locked($video, $series, $user)) {
    http_response_code(403);
    die('Finish the previous video in "' . h($series['title']) . '" first — this series requires watching in order.');
}

$pageTitle = $video['title'];
$token = ($video['filename'] && !$video['bunny_video_id']) ? make_stream_token($video['id'], $user ? $user['email'] : 'anon:' . session_id()) : null;
$bunnyEmbedUrl = $video['bunny_video_id'] ? bunny_embed_url($video['bunny_video_id']) : null;

$resumeAt = 0;
if ($user) {
    $pStmt = db()->prepare('SELECT position_seconds FROM watch_progress WHERE user_id = ? AND video_id = ?');
    $pStmt->execute([$user['id'], $video['id']]);
    $p = $pStmt->fetch();
    $resumeAt = $p ? (int)$p['position_seconds'] : (int)($_GET['t'] ?? 0);

    db()->prepare('INSERT INTO view_events (content_type, content_id, user_id) VALUES ("video", ?, ?)')->execute([$video['id'], $user['id']]);
}
if (is_plugin_active('view-counts', $series['category_id'] ?? null)) {
    db()->prepare('UPDATE videos SET view_count = view_count + 1 WHERE id = ?')->execute([$video['id']]);
}

include __DIR__ . '/includes/header.php';
?>
<h1><?= h($video['title']) ?></h1>
<p class="muted small"><a href="series.php?slug=<?= h($series['slug']) ?>">← <?= h($series['title']) ?></a></p>

<?php if ($bunnyEmbedUrl): ?>
  <div class="player-wrap">
    <iframe id="bunny-player" src="<?= h($bunnyEmbedUrl) ?>" allow="autoplay" allowfullscreen loading="lazy"
            data-video-id="<?= (int)$video['id'] ?>" data-resume="<?= (int)$resumeAt ?>"></iframe>
  </div>
<?php elseif ($video['embed_url']): ?>
  <div class="player-wrap"><iframe src="<?= h($video['embed_url']) ?>" allow="autoplay" allowfullscreen loading="lazy"></iframe></div>
<?php elseif ($token): ?>
  <div class="player-wrap">
    <video id="player" controls playsinline <?= $resumeAt > 5 ? '' : 'autoplay' ?> data-video-id="<?= (int)$video['id'] ?>" data-resume="<?= (int)$resumeAt ?>">
      <source src="stream.php?id=<?= (int)$video['id'] ?>&token=<?= h($token) ?>" type="video/mp4">
    </video>
  </div>
<?php else: ?>
  <p class="muted">No playable source configured for this video.</p>
<?php endif; ?>

<?php if ($video['description']): ?><p><?= nl2br(h($video['description'])) ?></p><?php endif; ?>

<div class="reaction-row">
  <?php do_action('video_actions', $video, $series, $user); ?>
</div>

<?php do_action('video_below_content', $video, $series, $user); ?>

<script>
  window.PROGRESS_ENDPOINT = <?= json_encode(SITE_URL . '/progress.php') ?>;
  window.VIDEO_ID = <?= (int)$video['id'] ?>;
</script>
<?php if ($bunnyEmbedUrl): ?><script src="<?= h(SITE_URL) ?>/assets/js/bunny-player.js"></script><?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
