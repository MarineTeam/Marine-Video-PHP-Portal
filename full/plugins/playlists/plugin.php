<?php
function plugin_playlists_register(): void
{
    add_action('video_actions', 'plugin_playlists_add_button');
}

function plugin_playlists_add_button(array $video, array $series, ?array $user): void
{
    if (!$user) return;
    static $instance = 0;
    $instance++;
    $stmt = db()->prepare('SELECT * FROM playlists WHERE user_id = ? ORDER BY name'); $stmt->execute([$user['id']]);
    $playlists = $stmt->fetchAll();
    $selectId = 'playlist-select-' . $instance;
    $nameId = 'new-playlist-name-' . $instance;
    echo '<form method="post" action="' . h(SITE_URL) . '/plugins/playlists/add.php" style="display:inline">' . csrf_field()
       . '<input type="hidden" name="video_id" value="' . (int)$video['id'] . '">'
       . '<select name="playlist_id" id="' . $selectId . '" onchange="document.getElementById(\'' . $nameId . '\').style.display = this.value === \'new\' ? \'inline-block\' : \'none\';">';
    foreach ($playlists as $p) echo '<option value="' . (int)$p['id'] . '">' . h($p['name']) . '</option>';
    echo '<option value="new">+ New playlist…</option></select>'
       . '<input type="text" name="new_name" placeholder="Playlist name" style="display:none" id="' . $nameId . '">'
       . '<button class="btn small" type="submit">Add to playlist</button></form>';
}
