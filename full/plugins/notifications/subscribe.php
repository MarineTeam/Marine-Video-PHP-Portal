<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'subscribe';

if ($action === 'subscribe') {
    $sub = $input['subscription'] ?? [];
    if (empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
        http_response_code(400); echo json_encode(['error' => 'Invalid subscription payload.']); exit;
    }
    db()->prepare('INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth_key) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth_key = VALUES(auth_key)')
        ->execute([$user['id'], $sub['endpoint'], $sub['keys']['p256dh'], $sub['keys']['auth']]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'unsubscribe') {
    $endpoint = $input['endpoint'] ?? '';
    db()->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?')->execute([$user['id'], $endpoint]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action.']);
