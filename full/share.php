<?php
require_once __DIR__ . '/config.php';

$token = (string)($_GET['t'] ?? '');
$stmt = db()->prepare('SELECT * FROM share_links WHERE token = ?');
$stmt->execute([$token]);
$share = $stmt->fetch();

$pageTitle = 'Shared content';

function render_share_message(string $msg): void
{
    global $pageTitle;
    include __DIR__ . '/includes/header.php';
    echo '<div class="center-card"><p>' . h($msg) . '</p></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if (!$share || $share['revoked'] || strtotime($share['expires_at']) < time()) {
    render_share_message("This link has expired or doesn't exist.");
}

// Forced login — recipients authenticate via Auth0 like anyone else.
$user = current_user();
if (!$user) {
    redirect(auth0_login_url(SITE_URL . '/share.php?t=' . urlencode($token)));
}

// Email must match the intended recipient. Deliberately generic message —
// never reveal the intended recipient's address to a mismatched account.
if (normalize_email($user['email']) !== normalize_email($share['recipient_email'])) {
    render_share_message("This link is for a different email address than the one you're signed in with.");
}

if (!$share['viewed_at']) {
    db()->prepare('UPDATE share_links SET viewed_at = NOW() WHERE id = ?')->execute([$share['id']]);
}

if ($share['content_type'] === 'series') {
    $sStmt = db()->prepare('SELECT * FROM series WHERE id = ?'); $sStmt->execute([$share['content_id']]);
    $series = $sStmt->fetch();
    if (!$series) render_share_message('The shared content no longer exists.');

    // Clicked into one video from within a shared series.
    $videoId = (int)($_GET['video'] ?? 0);
    if ($videoId) {
        $vStmt = db()->prepare('SELECT * FROM videos WHERE id = ? AND series_id = ? AND published = 1');
        $vStmt->execute([$videoId, $series['id']]);
        $video = $vStmt->fetch();
        if (!$video) render_share_message('The shared content no longer exists.');
        render_shared_video($video, $share, $user, $token);
        exit;
    }

    $videosStmt = db()->prepare('SELECT * FROM videos WHERE series_id = ? AND published = 1 ORDER BY position ASC');
    $videosStmt->execute([$series['id']]);
    $videos = $videosStmt->fetchAll();

    $pageTitle = $series['title'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section>
      <h1><?= h($series['title']) ?></h1>
      <p class="muted">Shared privately with <?= h($user['email']) ?> · expires <?= h(date('M j, Y g:ia', strtotime($share['expires_at']))) ?> UTC</p>
      <?php if ($series['description']): ?><p><?= nl2br(h($series['description'])) ?></p><?php endif; ?>
      <div class="tile-grid">
        <?php foreach ($videos as $v): ?>
          <a class="tile" href="share.php?t=<?= h($token) ?>&video=<?= (int)$v['id'] ?>">
            <div class="thumb"><div class="thumb-placeholder">▶</div></div>
            <div class="tile-title"><?= h($v['title']) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
} else {
    $vStmt = db()->prepare('SELECT * FROM videos WHERE id = ?'); $vStmt->execute([$share['content_id']]);
    $video = $vStmt->fetch();
    if (!$video) render_share_message('The shared content no longer exists.');
    render_shared_video($video, $share, $user, $token);
}

/** Shared rendering for a single shared video, used both by a direct video
 * share and by clicking into a video from within a shared series above. */
function render_shared_video(array $video, array $share, array $user, string $token): void
{
    global $pageTitle;
    $streamToken = ($video['filename'] && !$video['bunny_video_id']) ? make_stream_token((int)$video['id'], 'share:' . $token) : null;
    $bunnyEmbedUrl = $video['bunny_video_id'] ? bunny_embed_url($video['bunny_video_id']) : null;

    $pageTitle = $video['title'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="watch-page">
      <h1><?= h($video['title']) ?></h1>
      <p class="muted">Shared privately with <?= h($user['email']) ?> · expires <?= h(date('M j, Y g:ia', strtotime($share['expires_at']))) ?> UTC</p>

      <?php if ($bunnyEmbedUrl): ?>
        <div class="player-wrap"><iframe src="<?= h($bunnyEmbedUrl) ?>" allow="autoplay" allowfullscreen loading="lazy"></iframe></div>
      <?php elseif ($video['embed_url']): ?>
        <div class="player-wrap"><iframe src="<?= h($video['embed_url']) ?>" allow="autoplay" allowfullscreen loading="lazy"></iframe></div>
      <?php elseif ($streamToken): ?>
        <div class="player-wrap"><video controls playsinline><source src="stream.php?id=<?= (int)$video['id'] ?>&token=<?= h($streamToken) ?>" type="video/mp4"></video></div>
      <?php else: ?>
        <p class="muted">This video has no playable source configured.</p>
      <?php endif; ?>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
}
