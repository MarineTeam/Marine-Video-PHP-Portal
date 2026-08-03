<?php
/**
 * Marine Video Portal (PHP/MySQL edition) — configuration TEMPLATE
 *
 * This file is tracked in git. The real config.php (with your actual
 * secrets) is NOT — it's gitignored so `git pull` can never overwrite it.
 *
 * First-time setup:
 *   cp config.sample.php config.php
 * then edit config.php with your real values. Never commit config.php.
 */

// ---- Database -------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'marine_video_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Site -------------------------------------------------------------
define('SITE_URL', 'http://localhost/marine-video-portal'); // no trailing slash
define('SITE_NAME', 'Marine Video Portal');

// ---- Auth0 -------------------------------------------------------------
// Create a Regular Web Application in Auth0.
//   Allowed Callback URL : SITE_URL . '/auth0-callback.php'
//   Allowed Logout URL   : SITE_URL
// Disable Sign Ups under Authentication -> Database, then add approved
// people manually under User Management -> Users. Access here is by email
// identity, so this is the primary guard against self-registration.
define('AUTH0_DOMAIN', 'your-tenant.us.auth0.com');   // no https://, no trailing slash
define('AUTH0_CLIENT_ID', 'CHANGE-ME');
define('AUTH0_CLIENT_SECRET', 'CHANGE-ME');
define('AUTH0_CALLBACK_URL', SITE_URL . '/auth0-callback.php');

// ---- bunny.net Stream (optional third video source, alongside local upload
// and generic embed URLs) -------------------------------------------------------------
// Create a Stream library at bunny.net, enable "Embed View Token
// Authentication" on its Security tab, and fill these in. Leave
// BUNNY_LIBRARY_ID empty to hide the bunny.net upload option entirely.
define('BUNNY_LIBRARY_ID', '');          // Stream library ID
define('BUNNY_API_KEY', '');             // Stream library API key (server-side only, never sent to the browser)
define('BUNNY_TOKEN_AUTH_KEY', '');      // Library Security tab -> "Embed View Token Authentication" key
define('BUNNY_CDN_HOSTNAME', '');        // Pull zone host, e.g. vz-xxxx-xxx.b-cdn.net — needed for thumbnails
// Only set this if your CDN pull zone's URL Token Authentication key differs
// from BUNNY_TOKEN_AUTH_KEY (only relevant if you enabled "Block Direct URL
// File Access" on the library's Security tab).
define('BUNNY_CDN_TOKEN_KEY', '');

// ---- Security -------------------------------------------------------------
// Random 32+ byte secret used to sign streaming/share tokens and the OAuth
// "state"/"nonce" values. Generate with: php -r "echo bin2hex(random_bytes(32));"
define('APP_SECRET', 'CHANGE-ME-TO-A-LONG-RANDOM-STRING');

// Comma-separated admin emails (case-insensitive). Auto-promoted to admin on
// every login regardless of the DB flag — mirrors the original ADMIN_EMAILS.
define('ADMIN_EMAILS', 'admin@example.com');

// Auto sign-out after this many seconds of inactivity (30 min, matches spec).
define('IDLE_TIMEOUT_SECONDS', 1800);

// ---- Uploads -------------------------------------------------------------
define('UPLOAD_VIDEO_DIR', __DIR__ . '/uploads/videos');
define('UPLOAD_THUMB_DIR', __DIR__ . '/uploads/thumbs');
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024 * 1024); // 2GB, tune php.ini too

// ---- Streaming tokens -------------------------------------------------------------
define('STREAM_TOKEN_TTL', 21600); // 6h, generated fresh per watch session — never a permanent URL

// ---- Share links -------------------------------------------------------------
define('SHARE_DEFAULT_HOURS', 72);
define('SHARE_MAX_HOURS', 720); // 30 days

// ---- Email (Resend) -------------------------------------------------------------
// Leave RESEND_API_KEY empty to disable automatic email delivery of share
// links; admins can still copy links manually from the Shares tab either way
// (inert-until-configured, same as the original app).
define('RESEND_API_KEY', '');
define('MAIL_FROM', 'Marine Video Portal <onboarding@resend.dev>'); // must be a Resend-verified sender in production
define('EMAIL_REPLY_TO', '');

// ---- Rate limiting -------------------------------------------------------------
define('RATE_LIMIT_WINDOW_SECONDS', 60);
define('RATE_LIMIT_VIDEOS_MAX', 60);
define('RATE_LIMIT_SHARE_MAX', 20);
define('RATE_LIMIT_UPLOAD_MAX', 30);

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
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth0.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/bunny.php';
