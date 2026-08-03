<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('manage_series');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
        $memberOnly = !empty($_POST['member_only']) ? 1 : 0;
        $featured = !empty($_POST['featured']) ? 1 : 0;
        $pinned = !empty($_POST['pinned']) ? 1 : 0;
        $requireSequential = !empty($_POST['require_sequential']) ? 1 : 0;
        $published = !empty($_POST['published']) ? 1 : 0;
        $publishAt = $_POST['publish_at'] ?? '' ? date('Y-m-d H:i:s', strtotime($_POST['publish_at'])) : null;
        $unpublishAt = $_POST['unpublish_at'] ?? '' ? date('Y-m-d H:i:s', strtotime($_POST['unpublish_at'])) : null;
        $tagNames = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

        if ($title === '') { flash('error', 'Title is required.'); redirect('series.php'); }

        if ($action === 'create') {
            $slug = unique_slug('series', $title);
            db()->prepare('INSERT INTO series (title, slug, description, category_id, member_only, featured, pinned, require_sequential, published, publish_at, unpublish_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$title, $slug, $description, $categoryId, $memberOnly, $featured, $pinned, $requireSequential, $published, $publishAt, $unpublishAt]);
            $seriesId = (int)db()->lastInsertId();
            audit_log('series.create', $title);
        } else {
            $seriesId = (int)$_POST['id'];
            db()->prepare('UPDATE series SET title=?, description=?, category_id=?, member_only=?, featured=?, pinned=?, require_sequential=?, published=?, publish_at=?, unpublish_at=? WHERE id=?')
                ->execute([$title, $description, $categoryId, $memberOnly, $featured, $pinned, $requireSequential, $published, $publishAt, $unpublishAt, $seriesId]);
            audit_log('series.update', $title);
            db()->prepare('DELETE FROM series_tags WHERE series_id = ?')->execute([$seriesId]);
        }

        foreach ($tagNames as $tagName) {
            db()->prepare('INSERT INTO tags (name) VALUES (?) ON DUPLICATE KEY UPDATE name = name')->execute([$tagName]);
            $tStmt = db()->prepare('SELECT id FROM tags WHERE name = ?'); $tStmt->execute([$tagName]);
            $tagId = $tStmt->fetch()['id'];
            db()->prepare('INSERT IGNORE INTO series_tags (series_id, tag_id) VALUES (?, ?)')->execute([$seriesId, $tagId]);
        }

        if (!empty($_FILES['thumbnail']['name'])) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $fname = bin2hex(random_bytes(16)) . '.' . $ext;
                @mkdir(__DIR__ . '/../uploads/thumbs', 0755, true);
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], __DIR__ . '/../uploads/thumbs/' . $fname);
                db()->prepare('UPDATE series SET thumbnail = ? WHERE id = ?')->execute([$fname, $seriesId]);
            }
        }

        flash('success', 'Saved.');
        redirect('series.php');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db()->prepare('DELETE FROM series WHERE id = ?')->execute([$id]);
        audit_log('series.delete', "#$id");
        flash('success', 'Series deleted.');
        redirect('series.php');
    }
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT s.*, c.name AS category_name FROM series s LEFT JOIN categories c ON c.id = s.category_id';
$params = [];
if ($search !== '') { $sql .= ' WHERE s.title LIKE ?'; $params[] = "%$search%"; }
$sql .= ' ORDER BY s.position ASC, s.id DESC';
$stmt = db()->prepare($sql); $stmt->execute($params);
$allSeries = $stmt->fetchAll();
$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
if ($editId) {
    $eStmt = db()->prepare('SELECT * FROM series WHERE id = ?'); $eStmt->execute([$editId]); $editing = $eStmt->fetch();
    if ($editing) {
        $tStmt = db()->prepare('SELECT t.name FROM series_tags st JOIN tags t ON t.id = st.tag_id WHERE st.series_id = ?');
        $tStmt->execute([$editId]);
        $editing['tag_names'] = implode(', ', array_column($tStmt->fetchAll(), 'name'));
    }
}

$pageTitle = 'Admin · Series';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <h2><?= $editing ? 'Edit series' : 'New series' ?></h2>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
    <label>Title <input type="text" name="title" value="<?= h($editing['title'] ?? '') ?>" required></label>
    <label>Description <textarea name="description" rows="3"><?= h($editing['description'] ?? '') ?></textarea></label>
    <label>Category
      <select name="category_id"><option value="">Uncategorized</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($editing['category_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Tags (comma-separated) <input type="text" name="tags" value="<?= h($editing['tag_names'] ?? '') ?>"></label>
    <label>Thumbnail <input type="file" name="thumbnail" accept="image/*"></label>
    <label><input type="checkbox" name="published" <?= ($editing['published'] ?? 1) ? 'checked' : '' ?>> Published</label>
    <label><input type="checkbox" name="member_only" <?= !empty($editing['member_only']) ? 'checked' : '' ?>> Members only</label>
    <label><input type="checkbox" name="featured" <?= !empty($editing['featured']) ? 'checked' : '' ?>> Featured (homepage hero)</label>
    <label><input type="checkbox" name="pinned" <?= !empty($editing['pinned']) ? 'checked' : '' ?>> Pinned to top of its category</label>
    <label><input type="checkbox" name="require_sequential" <?= !empty($editing['require_sequential']) ? 'checked' : '' ?>> Require watching videos in order</label>
    <label>Publish at (blank = now) <input type="datetime-local" name="publish_at" value="<?= !empty($editing['publish_at']) ? h(date('Y-m-d\TH:i', strtotime($editing['publish_at']))) : '' ?>"></label>
    <label>Unpublish at (blank = never) <input type="datetime-local" name="unpublish_at" value="<?= !empty($editing['unpublish_at']) ? h(date('Y-m-d\TH:i', strtotime($editing['unpublish_at']))) : '' ?>"></label>
    <button class="btn" type="submit"><?= $editing ? 'Save' : 'Create' ?></button>
    <?php if ($editing): ?><a class="link-btn" href="series.php">Cancel</a><?php endif; ?>
  </form>
</section>

<section>
  <h2>All series (<?= count($allSeries) ?>)</h2>
  <form method="get" class="toolbar"><input type="search" name="q" value="<?= h($search) ?>" placeholder="Search…"><button class="btn" type="submit">Search</button></form>
  <table class="admin-table">
    <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($allSeries as $s): ?>
      <tr>
        <td><?= h($s['title']) ?><?= $s['featured'] ? ' ★' : '' ?><?= $s['pinned'] ? ' 📌' : '' ?></td>
        <td><?= h($s['category_name'] ?? '—') ?></td>
        <td><?= $s['published'] ? 'Published' : 'Draft' ?><?= $s['member_only'] ? ' · Members' : '' ?></td>
        <td><?= (int)$s['view_count'] ?></td>
        <td>
          <a class="link-btn" href="series.php?edit=<?= (int)$s['id'] ?>">Edit</a>
          <a class="link-btn" href="videos.php?series_id=<?= (int)$s['id'] ?>">Videos</a>
          <a class="link-btn" href="files.php?series_id=<?= (int)$s['id'] ?>">Files</a>
          <a class="link-btn" href="permissions.php?scope_type=series&scope_id=<?= (int)$s['id'] ?>">Access</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this series and all its videos/files?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="link-btn danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
