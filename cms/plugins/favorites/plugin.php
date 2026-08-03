<?php
function plugin_favorites_register(): void
{
    add_action('series_actions', 'plugin_favorites_button');
    add_action('video_actions', 'plugin_favorites_video_button');
}

function plugin_favorites_is_favorited(int $userId, string $type, int $id): bool
{
    $stmt = db()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND content_type = ? AND content_id = ?');
    $stmt->execute([$userId, $type, $id]);
    return (bool)$stmt->fetch();
}

function plugin_favorites_button(array $series, ?array $user): void
{
    if (!$user) return;
    $fav = plugin_favorites_is_favorited($user['id'], 'series', $series['id']);
    echo '<form method="post" action="' . h(SITE_URL) . '/plugins/favorites/toggle.php" style="display:inline">'
       . csrf_field() . '<input type="hidden" name="content_type" value="series"><input type="hidden" name="content_id" value="' . (int)$series['id'] . '">'
       . '<button class="btn small" type="submit">' . ($fav ? '★ Favorited' : '☆ Add to Favorites') . '</button></form>';
}

function plugin_favorites_video_button(array $video, array $series, ?array $user): void
{
    if (!$user) return;
    $fav = plugin_favorites_is_favorited($user['id'], 'video', $video['id']);
    echo '<form method="post" action="' . h(SITE_URL) . '/plugins/favorites/toggle.php" style="display:inline">'
       . csrf_field() . '<input type="hidden" name="content_type" value="video"><input type="hidden" name="content_id" value="' . (int)$video['id'] . '">'
       . '<button class="btn small" type="submit">' . ($fav ? '★ Favorited' : '☆ Add to Favorites') . '</button></form>';
}
