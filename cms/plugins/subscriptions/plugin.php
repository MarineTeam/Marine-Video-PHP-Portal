<?php
function plugin_subscriptions_register(): void
{
    add_action('series_actions', 'plugin_subscriptions_button');
    // Core fires do_action('content_published', $type, $id) when an admin
    // publishes something — see admin/series.php / admin/videos.php.
    add_action('content_published', 'plugin_subscriptions_notify');
}

function plugin_subscriptions_button(array $series, ?array $user): void
{
    if (!$user) return;
    $stmt = db()->prepare('SELECT 1 FROM subscriptions WHERE user_id = ? AND content_type = "series" AND content_id = ?');
    $stmt->execute([$user['id'], $series['id']]);
    $subbed = (bool)$stmt->fetch();
    echo '<form method="post" action="' . h(SITE_URL) . '/plugins/subscriptions/toggle.php" style="display:inline">' . csrf_field()
       . '<input type="hidden" name="content_type" value="series"><input type="hidden" name="content_id" value="' . (int)$series['id'] . '">'
       . '<button class="btn small" type="submit">' . ($subbed ? '🔔 Subscribed' : '🔕 Subscribe') . '</button></form>';
}

function plugin_subscriptions_notify(string $type, int $id): void
{
    if ($type !== 'video') return;
    $vStmt = db()->prepare('SELECT v.*, s.title AS series_title, s.category_id FROM videos v JOIN series s ON s.id = v.series_id WHERE v.id = ?');
    $vStmt->execute([$id]);
    $video = $vStmt->fetch();
    if (!$video) return;

    $subs = db()->prepare('SELECT DISTINCT user_id FROM subscriptions WHERE
        (content_type = "series" AND content_id = ?) OR (content_type = "category" AND content_id = ?)');
    $subs->execute([$video['series_id'], $video['category_id']]);

    foreach ($subs->fetchAll() as $row) {
        $title = 'New video: ' . $video['title'];
        $body = 'In ' . $video['series_title'];
        $url = SITE_URL . '/video.php?slug=' . $video['slug'];
        if (is_plugin_active('notifications')) {
            plugin_notifications_notify_user((int)$row['user_id'], $title, $body, $url);
        } elseif (resend_is_configured()) {
            $uStmt = db()->prepare('SELECT email FROM users WHERE id = ?'); $uStmt->execute([$row['user_id']]);
            $email = $uStmt->fetch()['email'] ?? null;
            if ($email) resend_send($email, $title, "<p>$body</p><p><a href=\"$url\">Watch now</a></p>");
        }
    }
}
