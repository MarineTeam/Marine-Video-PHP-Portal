<?php
require_once __DIR__ . '/../config.php';
$admin = require_capability('manage_plugins');
$GLOBALS['__plugin_admin_user'] = $admin;

$slug = $_GET['slug'] ?? '';
$file = __DIR__ . "/../plugins/$slug/admin.php";
if (!in_array($slug, get_all_plugin_slugs(), true) || !is_file($file)) {
    http_response_code(404);
    die('This plugin has no admin page.');
}

$pageTitle = 'Admin · ' . plugin_display_name($slug);
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
require $file;
include __DIR__ . '/../includes/footer.php';
