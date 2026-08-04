<?php
/**
 * WordPress-style plugin loader. Each plugin lives in plugins/<slug>/plugin.php
 * and must define a function named plugin_<slug_with_underscores>_register()
 * that wires itself up purely through add_action()/add_filter(). The core
 * never references a specific plugin by name outside of this loader.
 */

function get_all_plugin_slugs(): array
{
    $dirs = glob(__DIR__ . '/../plugins/*', GLOB_ONLYDIR);
    return array_map('basename', $dirs);
}

function is_plugin_active_site_wide(string $slug): bool
{
    static $cache = null;
    if ($cache === null) {
        try {
            $rows = db()->query('SELECT slug, active FROM plugins')->fetchAll();
            $cache = array_column($rows, 'active', 'slug');
        } catch (Throwable $e) {
            $cache = []; // schema not installed yet — fail open to "no plugins active"
        }
    }
    return !empty($cache[$slug]);
}

/** Nearest-ancestor category override wins; falls back to the site-wide default. */
function is_plugin_active(string $slug, ?int $categoryId = null): bool
{
    $default = is_plugin_active_site_wide($slug);
    if ($categoryId === null) {
        return $default;
    }
    $stmt = db()->prepare('SELECT active FROM plugin_category_overrides WHERE plugin_slug = ? AND category_id = ?');
    foreach (category_ancestor_ids($categoryId) as $catId) {
        $stmt->execute([$slug, $catId]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return (bool)$row['active'];
        }
    }
    return $default;
}

function load_active_plugins(): void
{
    foreach (get_all_plugin_slugs() as $slug) {
        $activeAnywhere = is_plugin_active_site_wide($slug);
        if (!$activeAnywhere) {
            try {
                $stmt = db()->prepare('SELECT 1 FROM plugin_category_overrides WHERE plugin_slug = ? AND active = 1 LIMIT 1');
                $stmt->execute([$slug]);
                $activeAnywhere = (bool)$stmt->fetch();
            } catch (Throwable $e) {
                $activeAnywhere = false; // schema not installed yet
            }
        }
        if (!$activeAnywhere) {
            continue; // never active anywhere — skip loading its code at all
        }
        $file = __DIR__ . "/../plugins/$slug/plugin.php";
        if (is_file($file)) {
            require_once $file;
            $fn = 'plugin_' . str_replace('-', '_', $slug) . '_register';
            if (function_exists($fn)) {
                $fn();
            }
        }
    }
}

function plugin_display_name(string $slug): string
{
    $names = [
        'favorites' => 'Favorites', 'watch-later' => 'Watch Later', 'comments' => 'Comments',
        'related-content' => 'Related Content', 'ratings' => 'Ratings', 'view-counts' => 'View Counts',
        'social-share' => 'Social Share', 'announcements' => 'Announcements', 'notifications' => 'Notifications',
        'subscriptions' => 'Subscriptions', 'playlists' => 'Playlists', 'likes-dislikes' => 'Likes / Dislikes',
    ];
    return $names[$slug] ?? ucwords(str_replace('-', ' ', $slug));
}
