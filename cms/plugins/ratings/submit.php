<?php
require_once __DIR__ . '/../../config.php';
$user = require_authorized();
verify_csrf();

$type = $_POST['content_type'] === 'video' ? 'video' : 'series';
$id = (int)$_POST['content_id'];
$stars = max(1, min(5, (int)$_POST['stars']));

db()->prepare('INSERT INTO ratings (content_type, content_id, user_id, stars) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE stars = VALUES(stars)')
    ->execute([$type, $id, $user['id'], $stars]);

redirect($_SERVER['HTTP_REFERER'] ?? (SITE_URL . '/index.php'));
