<?php
/**
 * Central visibility logic. A series/video is publicly visible only if:
 *   - published = 1, AND
 *   - publish_at is null or in the past, AND
 *   - unpublish_at is null or in the future, AND
 *   - not member-only (or the viewer is logged in + authorized), AND
 *   - if viewer_grants exist for this item, the viewer matches one of them
 *     (by permission-group or by exact email) — this OVERRIDES member_only
 *     entirely per spec: once any grant exists, member_only is ignored and
 *     only grantees (+ admins) can view it.
 */

function is_within_schedule(?string $publishAt, ?string $unpublishAt): bool
{
    $now = time();
    if ($publishAt && strtotime($publishAt) > $now) return false;
    if ($unpublishAt && strtotime($unpublishAt) <= $now) return false;
    return true;
}

function has_viewer_grants(string $contentType, int $contentId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) c FROM viewer_grants WHERE content_type = ? AND content_id = ?');
    $stmt->execute([$contentType, $contentId]);
    return (int)$stmt->fetch()['c'] > 0;
}

function viewer_matches_grant(?array $user, string $contentType, int $contentId): bool
{
    if (!$user) return false;
    if ($user['role'] === 'ADMIN') return true;

    $stmt = db()->prepare('SELECT * FROM viewer_grants WHERE content_type = ? AND content_id = ?');
    $stmt->execute([$contentType, $contentId]);
    $grants = $stmt->fetchAll();

    $userGroupIds = null;
    foreach ($grants as $g) {
        if ($g['grant_type'] === 'email' && normalize_email($g['email']) === normalize_email($user['email'])) {
            return true;
        }
        if ($g['grant_type'] === 'group') {
            if ($userGroupIds === null) {
                $gStmt = db()->prepare('SELECT group_id FROM user_group_assignments WHERE user_id = ?');
                $gStmt->execute([$user['id']]);
                $userGroupIds = array_column($gStmt->fetchAll(), 'group_id');
            }
            if (in_array((int)$g['group_id'], array_map('intval', $userGroupIds), true)) {
                return true;
            }
        }
    }
    return false;
}

function can_view_series(array $series, ?array $user): bool
{
    if ($user && $user['role'] === 'ADMIN') return true;
    if (!is_within_schedule($series['publish_at'], $series['unpublish_at']) || !$series['published']) {
        return $user && $user['role'] === 'ADMIN';
    }
    if (has_viewer_grants('series', $series['id'])) {
        return viewer_matches_grant($user, 'series', $series['id']);
    }
    if ($series['member_only']) {
        return $user && $user['authorized'];
    }
    return true;
}

function can_view_video(array $video, ?array $user, ?array $parentSeries = null): bool
{
    if ($user && $user['role'] === 'ADMIN') return true;
    if ($parentSeries && !can_view_series($parentSeries, $user)) return false;
    if (!is_within_schedule($video['publish_at'], $video['unpublish_at']) || !$video['published']) {
        return false;
    }
    if (has_viewer_grants('video', $video['id'])) {
        return viewer_matches_grant($user, 'video', $video['id']);
    }
    if ($video['member_only']) {
        return $user && $user['authorized'];
    }
    return true;
}

/** ---------------------------------------------------------------------
 * Category tree
 * ------------------------------------------------------------------ */

function get_category_tree(?int $parentId = null): array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE ' . ($parentId === null ? 'parent_id IS NULL' : 'parent_id = ?') . ' ORDER BY position ASC, name ASC');
    $parentId === null ? $stmt->execute() : $stmt->execute([$parentId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['children'] = get_category_tree((int)$row['id']);
    }
    return $rows;
}

function get_category_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/** ---------------------------------------------------------------------
 * Sequential unlock
 * ------------------------------------------------------------------ */

function is_video_locked(array $video, array $series, ?array $user): bool
{
    if (!$series['require_sequential'] || !$user) {
        return false; // anonymous viewers are never locked out by this
    }
    $stmt = db()->prepare('SELECT id, position FROM videos WHERE series_id = ? AND position < ? ORDER BY position DESC LIMIT 1');
    $stmt->execute([$series['id'], $video['position']]);
    $previous = $stmt->fetch();
    if (!$previous) return false; // first video in the series
    $pStmt = db()->prepare('SELECT completed FROM watch_progress WHERE user_id = ? AND video_id = ?');
    $pStmt->execute([$user['id'], $previous['id']]);
    $progress = $pStmt->fetch();
    return !$progress || !$progress['completed'];
}

/** ---------------------------------------------------------------------
 * Search (relevance-ranked: title/prefix match outranks description-only)
 * ------------------------------------------------------------------ */

function search_content(string $query, int $limit = 30): array
{
    $like = '%' . $query . '%';
    $prefixLike = $query . '%';

    $stmt = db()->prepare("
        (SELECT 'series' AS type, id, title AS name, slug, description,
                (CASE WHEN title LIKE ? THEN 3 WHEN title LIKE ? THEN 2 ELSE 1 END) AS rank_score
         FROM series WHERE published = 1 AND (title LIKE ? OR description LIKE ?))
        UNION ALL
        (SELECT 'video' AS type, id, title AS name, slug, description,
                (CASE WHEN title LIKE ? THEN 3 WHEN title LIKE ? THEN 2 ELSE 1 END) AS rank_score
         FROM videos WHERE published = 1 AND (title LIKE ? OR description LIKE ?))
        ORDER BY rank_score DESC LIMIT $limit
    ");
    $stmt->execute([$prefixLike, $like, $like, $like, $prefixLike, $like, $like, $like]);
    return $stmt->fetchAll();
}
