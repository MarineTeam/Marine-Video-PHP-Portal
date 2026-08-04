<?php
function plugin_view_counts_register(): void
{
    add_action('series_actions', 'plugin_view_counts_series');
    add_action('video_actions', 'plugin_view_counts_video');
}

function plugin_view_counts_series(array $series, ?array $user): void
{
    echo '<span class="tile-meta">' . (int)$series['view_count'] . ' views</span>';
}
function plugin_view_counts_video(array $video, array $series, ?array $user): void
{
    echo '<span class="tile-meta">' . (int)$video['view_count'] . ' views</span>';
}
