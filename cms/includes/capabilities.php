<?php
/**
 * Fixed capability list. Permission groups (stored in `permission_groups`)
 * are named bundles of these, assignable site-wide or scoped to one
 * category (+ everything under it) or one series. ADMIN always has every
 * capability and can't be granted through a group — only another ADMIN can
 * promote someone to ADMIN — to avoid a privilege-escalation hole via a
 * custom "manage_users" group.
 */

const CAPABILITIES = [
    'manage_categories' => 'Manage categories',
    'manage_series' => 'Manage series',
    'manage_videos' => 'Manage videos',
    'manage_files' => 'Manage files',
    'publish_content' => 'Publish/unpublish content',
    'moderate_comments' => 'Moderate comments',
    'manage_users' => 'Manage users',
    'manage_permissions' => 'Manage permissions',
    'manage_plugins' => 'Manage plugins',
    'view_audit_log' => 'View audit log',
    'view_analytics' => 'View analytics',
];

/**
 * user_can($user, $capability, $scopeType, $scopeId)
 * - ADMIN: always true.
 * - Otherwise: true if any of the user's group assignments grant this
 *   capability, either site-wide, or scoped to the requested category/
 *   series, or scoped to an ancestor category of the requested one.
 */
function user_can(array $user, string $capability, ?string $scopeType = null, $scopeId = null): bool
{
    if ($user['role'] === 'ADMIN') {
        return true;
    }

    $stmt = db()->prepare('SELECT uga.scope_type, uga.scope_id, pg.capabilities FROM user_group_assignments uga
                            JOIN permission_groups pg ON pg.id = uga.group_id WHERE uga.user_id = ?');
    $stmt->execute([$user['id']]);
    $assignments = $stmt->fetchAll();

    $ancestorCategoryIds = ($scopeType === 'category' && $scopeId) ? category_ancestor_ids((int)$scopeId) : [];

    foreach ($assignments as $a) {
        $caps = array_map('trim', explode(',', $a['capabilities']));
        if (!in_array($capability, $caps, true)) {
            continue;
        }
        if ($a['scope_type'] === 'site') {
            return true;
        }
        if ($scopeType === null) {
            continue; // this call is asking about a site-wide action; a scoped grant doesn't cover it
        }
        if ($a['scope_type'] === $scopeType && (int)$a['scope_id'] === (int)$scopeId) {
            return true;
        }
        if ($a['scope_type'] === 'category' && $scopeType === 'category' && in_array((int)$a['scope_id'], $ancestorCategoryIds, true)) {
            return true;
        }
    }
    return false;
}

/** Returns [scopeId, ...ancestor ids] for a category, self-inclusive. */
function category_ancestor_ids(int $categoryId): array
{
    $ids = [$categoryId];
    $stmt = db()->prepare('SELECT parent_id FROM categories WHERE id = ?');
    $current = $categoryId;
    $depth = 0;
    while ($depth < 20) { // guard against accidental cycles
        $stmt->execute([$current]);
        $row = $stmt->fetch();
        if (!$row || !$row['parent_id']) break;
        $ids[] = (int)$row['parent_id'];
        $current = (int)$row['parent_id'];
        $depth++;
    }
    return $ids;
}
