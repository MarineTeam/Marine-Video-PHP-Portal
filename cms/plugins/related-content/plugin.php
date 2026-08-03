<?php
function plugin_related_content_register(): void
{
    add_action('series_below_content', 'plugin_related_content_render', 5);
    add_action('video_below_content', 'plugin_related_content_render_video', 5);
}

function plugin_related_content_for_series(array $series, int $limit = 6): array
{
    $stmt = db()->prepare('SELECT DISTINCT s2.* FROM series s2
        LEFT JOIN series_tags st2 ON st2.series_id = s2.id
        LEFT JOIN series_tags st1 ON st1.tag_id = st2.tag_id AND st1.series_id = ?
        WHERE s2.id != ? AND s2.published = 1 AND (s2.category_id = ? OR st1.series_id IS NOT NULL)
        ORDER BY s2.view_count DESC LIMIT ?');
    $stmt->bindValue(1, $series['id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $series['id'], PDO::PARAM_INT);
    $stmt->bindValue(3, $series['category_id'], PDO::PARAM_INT);
    $stmt->bindValue(4, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function plugin_related_content_render(array $series, ?array $user): void
{
    $related = array_filter(plugin_related_content_for_series($series), fn($s) => can_view_series($s, $user));
    if (!$related) return;
    echo '<section><h2>Related</h2><div class="row-strip">';
    foreach ($related as $r) {
        echo '<a class="tile" href="series.php?slug=' . h($r['slug']) . '"><div class="thumb"><div class="thumb-placeholder">▤</div></div><div class="tile-title">' . h($r['title']) . '</div></a>';
    }
    echo '</div></section>';
}

function plugin_related_content_render_video(array $video, array $series, ?array $user): void
{
    plugin_related_content_render($series, $user);
}
