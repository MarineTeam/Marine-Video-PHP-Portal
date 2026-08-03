<?php
require_once __DIR__ . '/config.php';
$user = require_approved();

$pageTitle = 'Home';
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['q'] ?? '');
$collectionId = isset($_GET['collection']) && $_GET['collection'] !== '' ? (int)$_GET['collection'] : null;
$homepageCount = max(1, (int)get_setting('homepage_count', '60'));

// Build the filtered, capped video set
$where = ["status = 'ready'"];
$params = [];
if ($search !== '') {
    $where[] = 'title LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($collectionId) {
    $where[] = 'collection_id = ?';
    $params[] = $collectionId;
}
$whereSql = implode(' AND ', $where);

$totalStmt = db()->prepare("SELECT COUNT(*) c FROM (SELECT id FROM videos WHERE $whereSql ORDER BY sort_order ASC, id DESC LIMIT $homepageCount) t");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetch()['c'];
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT * FROM (SELECT * FROM videos WHERE $whereSql ORDER BY sort_order ASC, id DESC LIMIT $homepageCount) capped
        ORDER BY sort_order ASC, id DESC LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll();

$collections = db()->query('SELECT * FROM collections ORDER BY name')->fetchAll();

// Continue watching strip
$cwStmt = db()->prepare("SELECT wp.*, v.title, v.thumbnail, v.bunny_video_id FROM watch_progress wp
                          JOIN videos v ON v.id = wp.video_id
                          WHERE wp.user_id = ? AND wp.completed = 0 AND wp.position_seconds > 5
                          ORDER BY wp.updated_at DESC LIMIT 10");
$cwStmt->execute([$user['id']]);
$continueWatching = $cwStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<form class="toolbar" method="get">
  <input type="search" name="q" placeholder="Search videos…" value="<?= h($search) ?>">
  <select name="collection" onchange="this.form.submit()">
    <option value="">All collections</option>
    <?php foreach ($collections as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $collectionId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Search</button>
</form>

<?php if ($continueWatching): ?>
<section>
  <h2>Continue watching</h2>
  <div class="cw-strip">
    <?php foreach ($continueWatching as $cw):
      $pct = $cw['duration_seconds'] > 0 ? min(100, round($cw['position_seconds'] / $cw['duration_seconds'] * 100)) : 0;
      $cwThumb = $cw['thumbnail'] ? 'uploads/thumbs/' . $cw['thumbnail'] : ($cw['bunny_video_id'] ? bunny_thumbnail_url($cw['bunny_video_id']) : null);
    ?>
      <a class="cw-card" href="watch.php?id=<?= (int)$cw['video_id'] ?>">
        <div class="thumb">
          <?php if ($cwThumb): ?><img src="<?= h($cwThumb) ?>" alt="" loading="lazy"><?php endif; ?>
          <div class="progress"><span style="width:<?= (int)$pct ?>%"></span></div>
        </div>
        <div class="cw-title"><?= h($cw['title']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section>
  <h2>Library</h2>
  <?php if (!$videos): ?>
    <p class="muted">No videos yet.</p>
  <?php else: ?>
    <div class="video-grid">
      <?php foreach ($videos as $v):
        $thumbSrc = $v['thumbnail'] ? 'uploads/thumbs/' . $v['thumbnail'] : ($v['bunny_video_id'] ? bunny_thumbnail_url($v['bunny_video_id']) : null);
      ?>
        <a class="video-card" href="watch.php?id=<?= (int)$v['id'] ?>">
          <div class="thumb">
            <?php if ($thumbSrc): ?>
              <img src="<?= h($thumbSrc) ?>" alt="" loading="lazy">
            <?php else: ?>
              <div class="thumb-placeholder">▶</div>
            <?php endif; ?>
            <div class="play-overlay">▶</div>
          </div>
          <div class="video-title"><?= h($v['title']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="pagination">
      <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&collection=<?= (int)$collectionId ?>">Previous</a><?php endif; ?>
      <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&collection=<?= (int)$collectionId ?>">Next</a><?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
