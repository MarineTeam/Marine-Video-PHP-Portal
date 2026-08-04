<?php
require_once __DIR__ . '/config.php';
$user = current_user();

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM series WHERE slug = ?');
$stmt->execute([$slug]);
$series = $stmt->fetch();
if (!$series) { http_response_code(404); die('Series not found.'); }
if (!can_view_series($series, $user)) {
    if (!$user) redirect(auth0_login_url(current_url()));
    require_authorized();
    http_response_code(403);
    die('This series is restricted.');
}

$pageTitle = $series['title'];
$videosStmt = db()->prepare('SELECT * FROM videos WHERE series_id = ? ORDER BY position ASC');
$videosStmt->execute([$series['id']]);
$allVideos = $videosStmt->fetchAll();
$videos = array_filter($allVideos, fn($v) => can_view_video($v, $user, $series));

$filesStmt = db()->prepare('SELECT * FROM files WHERE series_id = ? AND published = 1 ORDER BY position ASC');
$filesStmt->execute([$series['id']]);
$files = array_filter($filesStmt->fetchAll(), fn($f) => !$f['member_only'] || ($user && $user['authorized']));

$tagsStmt = db()->prepare('SELECT t.name FROM series_tags st JOIN tags t ON t.id = st.tag_id WHERE st.series_id = ?');
$tagsStmt->execute([$series['id']]);
$tags = array_column($tagsStmt->fetchAll(), 'name');

if ($user) {
    db()->prepare('INSERT INTO view_events (content_type, content_id, user_id) VALUES ("series", ?, ?)')->execute([$series['id'], $user['id']]);
}
if (is_plugin_active('view-counts', $series['category_id'])) {
    db()->prepare('UPDATE series SET view_count = view_count + 1 WHERE id = ?')->execute([$series['id']]);
}

include __DIR__ . '/includes/header.php';
?>
<h1><?= h($series['title']) ?><?= $series['member_only'] ? ' <span class="badge">Members only</span>' : '' ?></h1>
<?php if ($series['description']): ?><p class="muted"><?= nl2br(h($series['description'])) ?></p><?php endif; ?>
<?php if ($tags): ?>
  <ul class="chip-list"><?php foreach ($tags as $t): ?><li class="chip"><a href="search.php?q=<?= urlencode($t) ?>"><?= h($t) ?></a></li><?php endforeach; ?></ul>
<?php endif; ?>

<div class="reaction-row">
  <?php do_action('series_actions', $series, $user); ?>
</div>

<section>
  <h2>Videos</h2>
  <?php if (!$videos): ?>
    <p class="muted">No videos yet.</p>
  <?php else: ?>
    <div class="tile-grid">
      <?php foreach ($videos as $v): $locked = is_video_locked($v, $series, $user); ?>
        <?php if ($locked): ?>
          <div class="tile" style="opacity:.5;cursor:not-allowed;">
            <div class="thumb"><div class="thumb-placeholder">🔒</div></div>
            <div class="tile-title"><?= h($v['title']) ?> <span class="badge locked">Locked</span></div>
          </div>
        <?php else: ?>
          <a class="tile" href="video.php?slug=<?= h($v['slug']) ?>">
            <div class="thumb"><div class="thumb-placeholder">▶</div></div>
            <div class="tile-title"><?= h($v['title']) ?><?= $v['member_only'] ? ' <span class="badge">Members</span>' : '' ?></div>
            <div class="tile-meta"><?= $v['duration_seconds'] ? h(format_duration((int)$v['duration_seconds'])) : '' ?></div>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($files): ?>
<section>
  <h2>Files</h2>
  <table class="admin-table">
    <?php foreach ($files as $f): ?>
      <tr><td><?= h($f['title']) ?></td><td class="muted small"><?= number_format($f['size_bytes'] / 1048576, 1) ?> MB</td>
      <td><a class="btn small" href="<?= h($f['storage_url']) ?>" target="_blank" rel="noopener">Download</a></td></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php endif; ?>

<?php do_action('series_below_content', $series, $user); ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
