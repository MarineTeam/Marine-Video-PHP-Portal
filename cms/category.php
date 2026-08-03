<?php
require_once __DIR__ . '/config.php';
$user = current_user();

$slug = $_GET['slug'] ?? '';
$category = get_category_by_slug($slug);
if (!$category) { http_response_code(404); die('Category not found.'); }
if (!is_within_schedule($category['publish_at'], $category['unpublish_at']) || !$category['published']) {
    if (!$user || $user['role'] !== 'ADMIN') { http_response_code(404); die('Category not found.'); }
}
if ($category['member_only'] && !($user && $user['authorized'])) {
    require_authorized();
}

$pageTitle = $category['name'];
$subcategories = get_category_tree((int)$category['id']);
$seriesStmt = db()->prepare('SELECT * FROM series WHERE category_id = ? AND published = 1 ORDER BY pinned DESC, position ASC');
$seriesStmt->execute([$category['id']]);
$seriesList = array_filter($seriesStmt->fetchAll(), fn($s) => can_view_series($s, $user));

include __DIR__ . '/includes/header.php';
?>
<h1><?= h($category['name']) ?></h1>

<?php if ($subcategories): ?>
<section>
  <h2>Subcategories</h2>
  <div class="tile-grid">
    <?php foreach ($subcategories as $sub): ?>
      <a class="tile" href="category.php?slug=<?= h($sub['slug']) ?>">
        <div class="thumb"><div class="thumb-placeholder">▤</div></div>
        <div class="tile-title"><?= h($sub['name']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section>
  <h2>Series</h2>
  <?php if (!$seriesList): ?>
    <p class="muted">Nothing here yet.</p>
  <?php else: ?>
    <div class="tile-grid">
      <?php foreach ($seriesList as $s): ?>
        <a class="tile" href="series.php?slug=<?= h($s['slug']) ?>">
          <div class="thumb"><?php if ($s['thumbnail']): ?><img src="uploads/thumbs/<?= h($s['thumbnail']) ?>" alt=""><?php else: ?><div class="thumb-placeholder">▶</div><?php endif; ?></div>
          <div class="tile-title"><?= h($s['title']) ?><?= $s['pinned'] ? ' 📌' : '' ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
