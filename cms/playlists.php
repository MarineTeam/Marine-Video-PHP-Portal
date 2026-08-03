<?php
require_once __DIR__ . '/config.php';
$user = require_authorized();

$playlistId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_playlist') {
        db()->prepare('DELETE FROM playlists WHERE id = ? AND user_id = ?')->execute([(int)$_POST['id'], $user['id']]);
        redirect('playlists.php');
    }
    if ($action === 'remove_item') {
        db()->prepare('DELETE FROM playlist_items WHERE id = ? AND playlist_id IN (SELECT id FROM playlists WHERE user_id = ?)')
            ->execute([(int)$_POST['item_id'], $user['id']]);
        redirect('playlists.php?id=' . $playlistId);
    }
}

if ($playlistId) {
    $pStmt = db()->prepare('SELECT * FROM playlists WHERE id = ? AND user_id = ?'); $pStmt->execute([$playlistId, $user['id']]);
    $playlist = $pStmt->fetch();
    if (!$playlist) { http_response_code(404); die('Playlist not found.'); }
    $itemsStmt = db()->prepare('SELECT pi.*, v.title, v.slug FROM playlist_items pi JOIN videos v ON v.id = pi.video_id WHERE pi.playlist_id = ? ORDER BY pi.position ASC');
    $itemsStmt->execute([$playlistId]);
    $items = $itemsStmt->fetchAll();
    $pageTitle = $playlist['name'];
} else {
    $playlist = null;
    $listStmt = db()->prepare('SELECT * FROM playlists WHERE user_id = ? ORDER BY created_at DESC'); $listStmt->execute([$user['id']]);
    $playlists = $listStmt->fetchAll();
    $pageTitle = 'Playlists';
}

include __DIR__ . '/includes/header.php';
?>
<?php if ($playlist): ?>
  <p class="muted"><a href="playlists.php">← All playlists</a></p>
  <h1><?= h($playlist['name']) ?></h1>
  <div class="tile-grid">
    <?php foreach ($items as $it): ?>
      <div class="tile">
        <a href="video.php?slug=<?= h($it['slug']) ?>"><div class="thumb"><div class="thumb-placeholder">▶</div></div><div class="tile-title"><?= h($it['title']) ?></div></a>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove_item"><input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>"><button class="link-btn danger" type="submit">Remove</button></form>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <h1>Playlists</h1>
  <?php if (!$playlists): ?><p class="muted">No playlists yet — add a video to a playlist from its watch page.</p><?php endif; ?>
  <div class="tile-grid">
    <?php foreach ($playlists as $p): ?>
      <div class="tile">
        <a href="playlists.php?id=<?= (int)$p['id'] ?>"><div class="thumb"><div class="thumb-placeholder">☰</div></div><div class="tile-title"><?= h($p['name']) ?></div></a>
        <form method="post" onsubmit="return confirm('Delete this playlist?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_playlist"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="link-btn danger" type="submit">Delete</button></form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
