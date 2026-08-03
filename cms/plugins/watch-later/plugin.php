<?php
function plugin_watch_later_register(): void
{
    add_action('video_actions', 'plugin_watch_later_button');
}

function plugin_watch_later_button(array $video, array $series, ?array $user): void
{
    if (!$user) return;
    $stmt = db()->prepare('SELECT 1 FROM watch_later WHERE user_id = ? AND video_id = ?');
    $stmt->execute([$user['id'], $video['id']]);
    $inList = (bool)$stmt->fetch();
    echo '<form method="post" action="' . h(SITE_URL) . '/plugins/watch-later/toggle.php" style="display:inline">'
       . csrf_field() . '<input type="hidden" name="video_id" value="' . (int)$video['id'] . '">'
       . '<button class="btn small" type="submit">' . ($inList ? '✓ In Watch Later' : '+ Watch Later') . '</button></form>';
}
