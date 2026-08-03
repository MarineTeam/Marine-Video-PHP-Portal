<?php
function plugin_notifications_register(): void
{
    // No direct hook points in the page body — the subscribe button lives in
    // the footer (assets/js/push-subscribe.js) so it can prompt for the
    // browser permission at the right time. This plugin exposes
    // plugin_notifications_notify_user() for other plugins (Subscriptions)
    // to call when new content publishes.
}

function plugin_notifications_notify_user(int $userId, string $title, string $body, string $url): void
{
    $stmt = db()->prepare('SELECT * FROM push_subscriptions WHERE user_id = ?');
    $stmt->execute([$userId]);
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);
    foreach ($stmt->fetchAll() as $sub) {
        send_web_push($sub, $payload);
    }
}
