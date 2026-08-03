<?php
function plugin_likes_dislikes_register(): void
{
    add_action('series_actions', 'plugin_likes_dislikes_widget');
    add_action('video_actions', 'plugin_likes_dislikes_video_widget');
}

function plugin_likes_dislikes_counts(string $type, int $id): array
{
    $stmt = db()->prepare("SELECT reaction, COUNT(*) c FROM reactions WHERE content_type = ? AND content_id = ? GROUP BY reaction");
    $stmt->execute([$type, $id]);
    $counts = ['like' => 0, 'dislike' => 0];
    foreach ($stmt->fetchAll() as $row) $counts[$row['reaction']] = (int)$row['c'];
    return $counts;
}

function plugin_likes_dislikes_render(string $type, int $id, ?array $user): void
{
    $counts = plugin_likes_dislikes_counts($type, $id);
    $mine = null;
    if ($user) {
        $stmt = db()->prepare('SELECT reaction FROM reactions WHERE content_type = ? AND content_id = ? AND user_id = ?');
        $stmt->execute([$type, $id, $user['id']]);
        $row = $stmt->fetch();
        $mine = $row['reaction'] ?? null;
    }
    if ($user && $user['authorized']) {
        echo '<form method="post" action="' . h(SITE_URL) . '/plugins/likes-dislikes/react.php" style="display:inline">' . csrf_field()
           . '<input type="hidden" name="content_type" value="' . h($type) . '"><input type="hidden" name="content_id" value="' . (int)$id . '">'
           . '<button class="link-btn" name="reaction" value="like" type="submit">' . ($mine === 'like' ? '👍 ' : '👍 ') . $counts['like'] . '</button> '
           . '<button class="link-btn" name="reaction" value="dislike" type="submit">' . ($mine === 'dislike' ? '👎 ' : '👎 ') . $counts['dislike'] . '</button></form>';
    } else {
        echo '<span class="tile-meta">👍 ' . $counts['like'] . ' · 👎 ' . $counts['dislike'] . '</span>';
    }
}

function plugin_likes_dislikes_widget(array $series, ?array $user): void { plugin_likes_dislikes_render('series', $series['id'], $user); }
function plugin_likes_dislikes_video_widget(array $video, array $series, ?array $user): void { plugin_likes_dislikes_render('video', $video['id'], $user); }
