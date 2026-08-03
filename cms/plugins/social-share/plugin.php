<?php
function plugin_social_share_register(): void
{
    add_action('series_actions', 'plugin_social_share_buttons_series');
    add_action('video_actions', 'plugin_social_share_buttons_video');
}

function plugin_social_share_render(string $url, string $title): void
{
    $encUrl = urlencode($url);
    $encTitle = urlencode($title);
    echo '<button class="link-btn" onclick="navigator.clipboard.writeText(\'' . h($url) . '\');this.textContent=\'Copied!\'">Copy link</button> '
       . '<a class="link-btn" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url=' . $encUrl . '&text=' . $encTitle . '">Share on X</a> '
       . '<a class="link-btn" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=' . $encUrl . '">Share on Facebook</a> '
       . '<a class="link-btn" href="mailto:?subject=' . $encTitle . '&body=' . $encUrl . '">Email</a>';
}

function plugin_social_share_buttons_series(array $series, ?array $user): void
{
    plugin_social_share_render(SITE_URL . '/series.php?slug=' . $series['slug'], $series['title']);
}
function plugin_social_share_buttons_video(array $video, array $series, ?array $user): void
{
    plugin_social_share_render(SITE_URL . '/video.php?slug=' . $video['slug'], $video['title']);
}
