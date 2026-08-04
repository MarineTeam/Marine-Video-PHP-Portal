<?php
require_once __DIR__ . '/config.php';
$user = current_user();
$pageTitle = 'Home';

$topCategories = get_category_tree(null);
$uncategorizedStmt = db()->query("SELECT * FROM series WHERE category_id IS NULL AND published = 1 ORDER BY pinned DESC, position ASC LIMIT 24");
$uncategorized = array_filter($uncategorizedStmt->fetchAll(), fn($s) => can_view_series($s, $user));

// Featured hero (overrides recency-based default)
$heroStmt = db()->query("SELECT * FROM series WHERE featured = 1 AND published = 1 ORDER BY created_at DESC LIMIT 1");
$hero = $heroStmt->fetch();
if ($hero && !can_view_series($hero, $user)) $hero = null;

// Recently added
$recentStmt = db()->query("SELECT * FROM series WHERE published = 1 ORDER BY created_at DESC LIMIT 12");
$recent = array_filter($recentStmt->fetchAll(), fn($s) => can_view_series($s, $user));

// Continue watching (logged-in only)
$continueWatching = [];
if ($user) {
    $cwStmt = db()->prepare("SELECT wp.*, v.title AS video_title, v.slug AS video_slug, s.title AS series_title
                              FROM watch_progress wp JOIN videos v ON v.id = wp.video_id JOIN series s ON s.id = v.series_id
                              WHERE wp.user_id = ? AND wp.completed = 0 AND wp.position_seconds > 5
                              ORDER BY wp.updated_at DESC LIMIT 10");
    $cwStmt->execute([$user['id']]);
    $continueWatching = $cwStmt->fetchAll();
}

// Trending this week (from view_events, distinct from the simple view_count column)
$trendingStmt = db()->query("SELECT content_type, content_id, COUNT(*) c FROM view_events
                              WHERE viewed_at >= NOW() - INTERVAL 7 DAY GROUP BY content_type, content_id ORDER BY c DESC LIMIT 10");
$trendingRaw = $trendingStmt->fetchAll();
$trending = [];
foreach ($trendingRaw as $t) {
    if ($t['content_type'] === 'series') {
        $s = db()->prepare('SELECT * FROM series WHERE id = ? AND published = 1'); $s->execute([$t['content_id']]);
        if ($row = $s->fetch()) { if (can_view_series($row, $user)) $trending[] = ['type' => 'series', 'row' => $row]; }
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($hero): ?>
<section class="card" style="display:flex;gap:20px;align-items:center;">
  <div class="thumb" style="width:280px;flex-shrink:0;">
    <?php if ($hero['thumbnail']): ?><img src="uploads/thumbs/<?= h($hero['thumbnail']) ?>" alt=""><?php else: ?><div class="thumb-placeholder">★</div><?php endif; ?>
  </div>
  <div>
    <div class="badge">Featured</div>
    <h1><?= h($hero['title']) ?></h1>
    <p class="muted"><?= h(mb_strimwidth((string)$hero['description'], 0, 200, '…')) ?></p>
    <a class="btn" href="series.php?slug=<?= h($hero['slug']) ?>">View series</a>
  </div>
</section>
<?php endif; ?>

<?php if ($continueWatching): ?>
<section>
  <h2>Continue watching</h2>
  <div class="row-strip">
    <?php foreach ($continueWatching as $cw):
      $pct = $cw['duration_seconds'] > 0 ? min(100, round($cw['position_seconds'] / $cw['duration_seconds'] * 100)) : 0; ?>
      <a class="tile" href="video.php?slug=<?= h($cw['video_slug']) ?>&t=<?= (int)$cw['position_seconds'] ?>">
        <div class="thumb"><div class="thumb-placeholder">▶</div></div>
        <div class="tile-title"><?= h($cw['video_title']) ?></div>
        <div class="tile-meta"><?= h($cw['series_title']) ?> · <?= (int)$pct ?>%</div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section>
  <h2>Browse</h2>
  <div class="tile-grid">
    <?php foreach ($topCategories as $cat): ?>
      <a class="tile" href="category.php?slug=<?= h($cat['slug']) ?>">
        <div class="thumb"><div class="thumb-placeholder">▤</div></div>
        <div class="tile-title"><?= h($cat['name']) ?></div>
        <div class="tile-meta"><?= count($cat['children']) ?> subcategories</div>
      </a>
    <?php endforeach; ?>
    <?php foreach ($uncategorized as $s): ?>
      <a class="tile" href="series.php?slug=<?= h($s['slug']) ?>">
        <div class="thumb"><?php if ($s['thumbnail']): ?><img src="uploads/thumbs/<?= h($s['thumbnail']) ?>" alt=""><?php else: ?><div class="thumb-placeholder">▶</div><?php endif; ?></div>
        <div class="tile-title"><?= h($s['title']) ?><?= $s['pinned'] ? ' 📌' : '' ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($trending): ?>
<section>
  <h2>Trending this week</h2>
  <div class="row-strip">
    <?php foreach ($trending as $t): ?>
      <a class="tile" href="series.php?slug=<?= h($t['row']['slug']) ?>">
        <div class="thumb"><?php if ($t['row']['thumbnail']): ?><img src="uploads/thumbs/<?= h($t['row']['thumbnail']) ?>" alt=""><?php else: ?><div class="thumb-placeholder">▶</div><?php endif; ?></div>
        <div class="tile-title"><?= h($t['row']['title']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($recent): ?>
<section>
  <h2>Recently added</h2>
  <div class="row-strip">
    <?php foreach ($recent as $s): ?>
      <a class="tile" href="series.php?slug=<?= h($s['slug']) ?>">
        <div class="thumb"><?php if ($s['thumbnail']): ?><img src="uploads/thumbs/<?= h($s['thumbnail']) ?>" alt=""><?php else: ?><div class="thumb-placeholder">▶</div><?php endif; ?></div>
        <div class="tile-title"><?= h($s['title']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
