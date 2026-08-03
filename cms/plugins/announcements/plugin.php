<?php
function plugin_announcements_register(): void
{
    // Banner is rendered directly from includes/header.php via the guarded
    // is_plugin_active('announcements') check, calling this function.
}

function plugin_announcements_render_banner(): void
{
    $stmt = db()->query('SELECT * FROM announcements WHERE active = 1 ORDER BY created_at DESC LIMIT 1');
    $a = $stmt->fetch();
    if (!$a) return;
    echo '<div class="announcement-banner" id="announcement-banner" data-id="' . (int)$a['id'] . '">'
       . '<span>' . h($a['message']) . '</span><button aria-label="Dismiss">×</button></div>';
}
