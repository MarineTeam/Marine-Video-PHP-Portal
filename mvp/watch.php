<?php
require_once __DIR__ . '/config.php';
$user = require_approved();

$videoId = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM videos WHERE id = ? AND status = 'ready'");
$stmt->execute([$videoId]);
$video = $stmt->fetch();
if (!$video) {
    http_response_code(404);
    die('Video not found.');
}

// Every play uses a signed, time-limited token generated fresh per request —
// never a permanent public URL. Local files use our own stream.php token;
// bunny.net videos get a freshly signed embed URL each time instead.
$token = $video['bunny_video_id'] ? null : make_stream_token($video['id'], $user['email']);
$bunnyEmbedUrl = $video['bunny_video_id'] ? bunny_embed_url($video['bunny_video_id']) : null;

db()->prepare('UPDATE videos SET views = views + 1 WHERE id = ?')->execute([$video['id']]);
db()->prepare('INSERT INTO video_views (video_id, viewer_email) VALUES (?, ?)')->execute([$video['id'], $user['email']]);

$progStmt = db()->prepare('SELECT * FROM watch_progress WHERE user_id = ? AND video_id = ?');
$progStmt->execute([$user['id'], $video['id']]);
$progress = $progStmt->fetch();
$resumeAt = $progress ? (int)$progress['position_seconds'] : 0;

$pageTitle = $video['title'];
include __DIR__ . '/includes/header.php';
?>

<section class="watch-page">
  <h1><?= h($video['title']) ?></h1>

  <?php if ($bunnyEmbedUrl): ?>
    <div class="player-wrap">
      <iframe id="bunny-player" src="<?= h($bunnyEmbedUrl) ?>" allow="autoplay" allowfullscreen loading="lazy"
              data-video-id="<?= (int)$video['id'] ?>" data-resume="<?= (int)$resumeAt ?>"></iframe>
    </div>
    <?php if ($resumeAt > 5): ?>
      <p class="muted">Resuming from <?= h(format_duration($resumeAt)) ?>.</p>
    <?php endif; ?>
  <?php elseif ($video['embed_url']): ?>
    <div class="player-wrap">
      <iframe src="<?= h($video['embed_url']) ?>" allow="autoplay" allowfullscreen loading="lazy"></iframe>
    </div>
  <?php else: ?>
    <div class="player-wrap">
      <video id="player" controls playsinline <?= $resumeAt > 5 ? '' : 'autoplay' ?>
             data-video-id="<?= (int)$video['id'] ?>" data-resume="<?= (int)$resumeAt ?>">
        <source src="stream.php?id=<?= (int)$video['id'] ?>&token=<?= h($token) ?>" type="video/mp4">
        Your browser doesn't support HTML5 video.
      </video>
    </div>
    <?php if ($resumeAt > 5): ?>
      <p class="muted">Resuming from <?= h(format_duration($resumeAt)) ?>. <a href="#" id="start-over">Start over instead</a>.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>

<script>
  window.PROGRESS_ENDPOINT = <?= json_encode(SITE_URL . '/progress.php') ?>;
  window.VIDEO_ID = <?= (int)$video['id'] ?>;
</script>
<?php if ($bunnyEmbedUrl): ?>
<script src="<?= h(SITE_URL) ?>/assets/js/bunny-player.js"></script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
