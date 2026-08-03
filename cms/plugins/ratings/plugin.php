<?php
function plugin_ratings_register(): void
{
    add_action('series_actions', 'plugin_ratings_widget');
    add_action('video_actions', 'plugin_ratings_video_widget');
}

function plugin_ratings_avg(string $type, int $id): array
{
    $stmt = db()->prepare('SELECT AVG(stars) avg_stars, COUNT(*) c FROM ratings WHERE content_type = ? AND content_id = ?');
    $stmt->execute([$type, $id]);
    $row = $stmt->fetch();
    return ['avg' => $row['avg_stars'] ? round($row['avg_stars'], 1) : null, 'count' => (int)$row['c']];
}

function plugin_ratings_widget(array $series, ?array $user): void
{
    plugin_ratings_render('series', $series['id'], $user);
}
function plugin_ratings_video_widget(array $video, array $series, ?array $user): void
{
    plugin_ratings_render('video', $video['id'], $user);
}

function plugin_ratings_render(string $type, int $id, ?array $user): void
{
    $stats = plugin_ratings_avg($type, $id);
    $mine = 0;
    if ($user) {
        $stmt = db()->prepare('SELECT stars FROM ratings WHERE content_type = ? AND content_id = ? AND user_id = ?');
        $stmt->execute([$type, $id, $user['id']]);
        $row = $stmt->fetch();
        $mine = $row ? (int)$row['stars'] : 0;
    }
    echo '<span class="tile-meta">' . ($stats['avg'] ? "★ {$stats['avg']} ({$stats['count']})" : 'No ratings yet') . '</span> ';
    if ($user && $user['authorized']) {
        echo '<form method="post" action="' . h(SITE_URL) . '/plugins/ratings/submit.php" style="display:inline">' . csrf_field()
           . '<input type="hidden" name="content_type" value="' . h($type) . '"><input type="hidden" name="content_id" value="' . (int)$id . '">';
        for ($i = 1; $i <= 5; $i++) {
            echo '<button class="link-btn" name="stars" value="' . $i . '" type="submit" style="font-size:16px;">' . ($i <= $mine ? '★' : '☆') . '</button>';
        }
        echo '</form>';
    }
}
