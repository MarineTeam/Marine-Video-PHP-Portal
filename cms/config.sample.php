<?php
/**
 * Marine Team CMS — configuration TEMPLATE.
 * install.php generates the real config.php from this file — you normally
 * never need to edit this one directly. config.php is gitignored so it's
 * never overwritten by a future `git pull`.
 */

// ---- Database -------------------------------------------------------------
define('DB_HOST', '{{DB_HOST}}');
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASS', '{{DB_PASS}}');

// ---- Site -------------------------------------------------------------
define('SITE_URL', '{{SITE_URL}}'); // no trailing slash

// ---- Auth0 -------------------------------------------------------------
// Regular Web Application. Allowed Callback URL: SITE_URL/auth/callback.php
// Allowed Logout URL: SITE_URL
define('AUTH0_DOMAIN', '{{AUTH0_DOMAIN}}');
define('AUTH0_CLIENT_ID', '{{AUTH0_CLIENT_ID}}');
define('AUTH0_CLIENT_SECRET', '{{AUTH0_CLIENT_SECRET}}');
define('AUTH0_CALLBACK_URL', SITE_URL . '/auth/callback.php');

// Comma-separated emails that self-authorize as ADMIN on first login.
define('ADMIN_EMAILS', '{{ADMIN_EMAILS}}');

// ---- Security -------------------------------------------------------------
define('APP_SECRET', '{{APP_SECRET}}');
define('IDLE_TIMEOUT_SECONDS', 1800);

// ---- bunny.net Stream (video) -------------------------------------------------------------
define('BUNNY_STREAM_LIBRARY_ID', '{{BUNNY_STREAM_LIBRARY_ID}}');
define('BUNNY_STREAM_API_KEY', '{{BUNNY_STREAM_API_KEY}}');
define('BUNNY_STREAM_TOKEN_AUTH_KEY', '{{BUNNY_STREAM_TOKEN_AUTH_KEY}}'); // optional
define('BUNNY_STREAM_CDN_HOSTNAME', '{{BUNNY_STREAM_CDN_HOSTNAME}}');     // optional, thumbnails

// ---- bunny.net Storage (downloadable files) -------------------------------------------------------------
define('BUNNY_STORAGE_ZONE', '{{BUNNY_STORAGE_ZONE}}');
define('BUNNY_STORAGE_API_KEY', '{{BUNNY_STORAGE_API_KEY}}');
define('BUNNY_STORAGE_REGION_HOST', '{{BUNNY_STORAGE_REGION_HOST}}'); // e.g. storage.bunnycdn.com
define('BUNNY_STORAGE_PULL_ZONE', '{{BUNNY_STORAGE_PULL_ZONE}}');    // public CDN hostname for downloads
define('LOCAL_FILE_UPLOAD_LIMIT', 4.5 * 1024 * 1024); // files <= this go server-side; bigger ones need Storage TUS-less PUT from admin

// ---- Email (Resend, optional — used by Notifications/Subscriptions plugins as a fallback) --------------
define('RESEND_API_KEY', '{{RESEND_API_KEY}}');
define('MAIL_FROM', '{{MAIL_FROM}}');

// ---- Web Push (Notifications plugin) -------------------------------------------------------------
define('VAPID_PUBLIC_KEY', '{{VAPID_PUBLIC_KEY}}');
define('VAPID_PRIVATE_KEY', '{{VAPID_PRIVATE_KEY}}');
define('VAPID_SUBJECT', 'mailto:admin@example.com');

// ---- Streaming tokens -------------------------------------------------------------
define('STREAM_TOKEN_TTL', 21600);

// ---- Timezone -------------------------------------------------------------
date_default_timezone_set('UTC');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/hooks.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth0.php';
require_once __DIR__ . '/includes/bunny.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/webpush.php';
require_once __DIR__ . '/includes/capabilities.php';
require_once __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/plugins.php';

load_active_plugins();
