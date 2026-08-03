<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('manage_categories');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $parentId = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;
        if ($name !== '') {
            $slug = unique_slug('categories', $name);
            db()->prepare('INSERT INTO categories (name, slug, parent_id) VALUES (?, ?, ?)')->execute([$name, $slug, $parentId]);
            audit_log('category.create', $name);
            flash('success', 'Category created.');
        }
        redirect('categories.php');
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $parentId = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;
        $memberOnly = !empty($_POST['member_only']) ? 1 : 0;
        $published = !empty($_POST['published']) ? 1 : 0;
        $publishAt = $_POST['publish_at'] ?? '' ? date('Y-m-d H:i:s', strtotime($_POST['publish_at'])) : null;
        $unpublishAt = $_POST['unpublish_at'] ?? '' ? date('Y-m-d H:i:s', strtotime($_POST['unpublish_at'])) : null;
        if ($parentId === $id) $parentId = null; // can't be its own parent
        db()->prepare('UPDATE categories SET name=?, parent_id=?, member_only=?, published=?, publish_at=?, unpublish_at=? WHERE id=?')
            ->execute([$name, $parentId, $memberOnly, $published, $publishAt, $unpublishAt, $id]);
        audit_log('category.update', $name);
        flash('success', 'Saved.');
        redirect('categories.php');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        audit_log('category.delete', "#$id");
        flash('success', 'Category deleted (series inside were not deleted — they became uncategorized).');
        redirect('categories.php');
    }

    if ($action === 'move') {
        $id = (int)$_POST['id']; $dir = $_POST['dir'] === 'up' ? 'up' : 'down';
        $stmt = db()->prepare('SELECT id, position, parent_id FROM categories WHERE parent_id <=> (SELECT parent_id FROM categories WHERE id = ?) ORDER BY position ASC, id ASC');
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll();
        $idx = null;
        foreach ($rows as $i => $r) if ((int)$r['id'] === $id) { $idx = $i; break; }
        if ($idx !== null) {
            $swap = $dir === 'up' ? $idx - 1 : $idx + 1;
            if (isset($rows[$swap])) {
                db()->prepare('UPDATE categories SET position = ? WHERE id = ?')->execute([$rows[$swap]['position'], $rows[$idx]['id']]);
                db()->prepare('UPDATE categories SET position = ? WHERE id = ?')->execute([$rows[$idx]['position'], $rows[$swap]['id']]);
            }
        }
        redirect('categories.php');
    }
}

$allCategories = db()->query('SELECT * FROM categories ORDER BY parent_id, position ASC, name ASC')->fetchAll();
$tree = get_category_tree(null);

$pageTitle = 'Admin · Categories';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';

function render_categories_recursive(array $nodes, int $depth = 0): void {
    foreach ($nodes as $c) {
        echo '<tr><td style="padding-left:' . ($depth * 24) . 'px">' . h($c['name']) . '</td>';
        echo '<td>' . ($c['published'] ? 'Published' : 'Draft') . ($c['member_only'] ? ' · Members' : '') . '</td>';
        echo '<td>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="' . (int)$c['id'] . '"><input type="hidden" name="dir" value="up"><button class="link-btn" type="submit">↑</button></form>
            <form method="post" style="display:inline"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="' . (int)$c['id'] . '"><input type="hidden" name="dir" value="down"><button class="link-btn" type="submit">↓</button></form>
            <a class="link-btn" href="category.php?slug=' . h($c['slug']) . '" target="_blank">View</a>
        </td>
        <td><details><summary>Edit</summary>' . render_category_edit_form($c) . '</details></td>
        <td><form method="post" onsubmit="return confirm(\'Delete this category?\')"><input type="hidden" name="csrf" value="' . h(csrf_token()) . '"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . (int)$c['id'] . '"><button class="link-btn danger" type="submit">Delete</button></form></td>
        </tr>';
        if ($c['children']) render_categories_recursive($c['children'], $depth + 1);
    }
}
function render_category_edit_form(array $c): string {
    return '<form method="post" class="card">
        <input type="hidden" name="csrf" value="' . h(csrf_token()) . '">
        <input type="hidden" name="action" value="update"><input type="hidden" name="id" value="' . (int)$c['id'] . '">
        <label>Name <input type="text" name="name" value="' . h($c['name']) . '"></label>
        <label>Published <input type="checkbox" name="published" ' . ($c['published'] ? 'checked' : '') . '></label>
        <label>Members only <input type="checkbox" name="member_only" ' . ($c['member_only'] ? 'checked' : '') . '></label>
        <label>Publish at (blank = now) <input type="datetime-local" name="publish_at" value="' . ($c['publish_at'] ? h(date('Y-m-d\TH:i', strtotime($c['publish_at']))) : '') . '"></label>
        <label>Unpublish at (blank = never) <input type="datetime-local" name="unpublish_at" value="' . ($c['unpublish_at'] ? h(date('Y-m-d\TH:i', strtotime($c['unpublish_at']))) : '') . '"></label>
        <button class="btn small" type="submit">Save</button>
    </form>';
}
?>
<section>
  <h2>New category</h2>
  <form method="post" class="card inline-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <input type="text" name="name" placeholder="Category name" required>
    <select name="parent_id"><option value="">Top level</option>
      <?php foreach ($allCategories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Create</button>
  </form>
</section>
<section>
  <h2>All categories</h2>
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Status</th><th>Reorder</th><th>Edit</th><th></th></tr></thead>
    <tbody><?php render_categories_recursive($tree); ?></tbody>
  </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
