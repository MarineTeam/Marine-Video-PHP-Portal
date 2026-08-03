<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('manage_plugins');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_site_wide') {
        $slug = $_POST['slug'] ?? '';
        db()->prepare('UPDATE plugins SET active = 1 - active WHERE slug = ?')->execute([$slug]);
        audit_log('plugin.toggle', $slug);
        redirect('plugins.php');
    }
    if ($action === 'set_category_override') {
        $slug = $_POST['slug'] ?? '';
        $categoryId = (int)$_POST['category_id'];
        $value = $_POST['value']; // 'inherit' | '1' | '0'
        db()->prepare('DELETE FROM plugin_category_overrides WHERE plugin_slug = ? AND category_id = ?')->execute([$slug, $categoryId]);
        if ($value !== 'inherit') {
            db()->prepare('INSERT INTO plugin_category_overrides (plugin_slug, category_id, active) VALUES (?, ?, ?)')
                ->execute([$slug, $categoryId, (int)$value]);
        }
        audit_log('plugin.category_override', "$slug / category #$categoryId = $value");
        redirect('plugins.php');
    }
}

$slugs = get_all_plugin_slugs();
$active = array_column(db()->query('SELECT slug, active FROM plugins')->fetchAll(), 'active', 'slug');
$categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$overrides = db()->query('SELECT * FROM plugin_category_overrides')->fetchAll();
$overrideMap = [];
foreach ($overrides as $o) $overrideMap[$o['plugin_slug']][$o['category_id']] = $o['active'];

$pageTitle = 'Admin · Plugins';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<section>
  <h2>Plugins</h2>
  <p class="muted small">Site-wide toggle sets the default. Per-category overrides let one category behave differently (e.g. turn comments off in a kids' category).</p>
  <?php foreach ($slugs as $slug): ?>
    <div class="card">
      <div class="inline-form">
        <strong><?= h(plugin_display_name($slug)) ?></strong>
        <?php if (is_file(__DIR__ . "/../plugins/$slug/admin.php")): ?>
          <a class="link-btn" href="plugin-admin.php?slug=<?= h($slug) ?>">Manage</a>
        <?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_site_wide"><input type="hidden" name="slug" value="<?= h($slug) ?>">
          <button class="btn small" type="submit"><?= !empty($active[$slug]) ? 'Active — turn off' : 'Inactive — turn on' ?></button>
        </form>
      </div>
      <?php if ($categories): ?>
      <details>
        <summary>Per-category overrides</summary>
        <?php foreach ($categories as $c): $cur = $overrideMap[$slug][$c['id']] ?? null; ?>
          <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="set_category_override">
            <input type="hidden" name="slug" value="<?= h($slug) ?>"><input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
            <span class="muted small"><?= h($c['name']) ?></span>
            <select name="value" onchange="this.form.submit()">
              <option value="inherit" <?= $cur === null ? 'selected' : '' ?>>Inherit site default</option>
              <option value="1" <?= $cur === 1 ? 'selected' : '' ?>>Force ON</option>
              <option value="0" <?= $cur === 0 ? 'selected' : '' ?>>Force OFF</option>
            </select>
          </form>
        <?php endforeach; ?>
      </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
