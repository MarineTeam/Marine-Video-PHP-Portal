<?php
require_once __DIR__ . '/config.php';

$token = (string)($_GET['t'] ?? '');
$stmt = db()->prepare('SELECT sl.*, v.title, v.filename, v.embed_url, v.bunny_video_id FROM share_links sl
                        JOIN videos v ON v.id = sl.video_id WHERE sl.token = ?');
$stmt->execute([$token]);
$share = $stmt->fetch();

$pageTitle = 'Shared video';

function render_share_message(string $msg): void
{
    global $pageTitle;
    include __DIR__ . '/includes/header.php';
    echo '<div class="center-card"><p>' . h($msg) . '</p></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

if (!$share || $share['revoked'] || strtotime($share['expires_at']) < time()) {
    render_share_message('This link has expired or doesn\'t exist.');
}

// Forced login — recipients must authenticate via Auth0 like anyone else.
$user = current_user();
if (!$user) {
    redirect(auth0_login_url(SITE_URL . '/share.php?t=' . urlencode($token)));
}

// Email must match the intended recipient. Deliberately generic message —
// never reveal the intended recipient's address to a mismatched account.
if (normalize_email($user['email']) !== normalize_email($share['recipient_email'])) {
    render_share_message('This link is for a different email address than the one you\'re signed in with.');
}

if (!$share['viewed_at']) {
    db()->prepare('UPDATE share_links SET viewed_at = NOW() WHERE id = ?')->execute([$share['id']]);
}

$streamToken = ($share['filename'] && !$share['bunny_video_id']) ? make_stream_token((int)$share['video_id'], 'share:' . $token) : null;
$bunnyEmbedUrl = $share['bunny_video_id'] ? bunny_embed_url($share['bunny_video_id']) : null;
$pageTitle = $share['title'];
include __DIR__ . '/includes/header.php';
?>
<section class="watch-page">
  <h1><?= h($share['title']) ?></h1>
  <p class="muted">Shared privately with <?= h($user['email']) ?> · expires <?= h(date('M j, Y g:ia', strtotime($share['expires_at']))) ?> UTC</p>

  <?php if ($bunnyEmbedUrl): ?>
    <div class="player-wrap">
      <iframe src="<?= h($bunnyEmbedUrl) ?>" allow="autoplay" allowfullscreen loading="lazy"></iframe>
    </div>
  <?php elseif ($share['embed_url']): ?>
    <div class="player-wrap">
      <iframe src="<?= h($share['embed_url']) ?>" allow="autoplay" allowfullscreen loading="lazy"></iframe>
    </div>
  <?php elseif ($streamToken): ?>
    <div class="player-wrap">
      <video controls playsinline>
        <source src="stream.php?id=<?= (int)$share['video_id'] ?>&token=<?= h($streamToken) ?>" type="video/mp4">
      </video>
    </div>
  <?php else: ?>
    <p class="muted">This video has no playable source configured.</p>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
