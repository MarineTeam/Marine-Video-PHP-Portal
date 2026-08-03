<?php
/**
 * Marine Team CMS — web installer, modeled on WordPress's famous
 * "5-minute install": no manual file editing required. Delete this file
 * once setup is complete (it protects itself once installed, but leaving
 * server-side installers reachable indefinitely is bad practice).
 */

define('CONFIG_PATH', __DIR__ . '/config.php');
define('TEMPLATE_PATH', __DIR__ . '/config.sample.php');

function installer_layout(string $title, string $body): void
{
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>'
       . '<link rel="stylesheet" href="assets/css/style.css"></head><body class="installer">'
       . '<div class="install-card"><h1>Marine Team CMS — Setup</h1>' . $body . '</div></body></html>';
    exit;
}

function schema_is_installed(): bool
{
    try {
        require_once CONFIG_PATH;
        db()->query('SELECT 1 FROM settings LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$configExists = is_file(CONFIG_PATH);
$step = $configExists ? (schema_is_installed() ? 'done_already' : 'site_info') : 'db';
if (($_GET['step'] ?? '') === 'db' && $configExists) $step = 'db'; // allow retrying DB step

// ---------------------------------------------------------------------
// Step: already fully installed
// ---------------------------------------------------------------------
if ($step === 'done_already' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    installer_layout('Already installed', '
        <p class="flash flash-success">Marine Team CMS is already set up.</p>
        <p><strong>Delete install.php from your server now</strong> — leaving a working installer reachable is a security risk.</p>
        <a class="btn" href="index.php">Go to the site</a>
    ');
}

// ---------------------------------------------------------------------
// Step 1: database connection -> write config.php from the template
// ---------------------------------------------------------------------
if ($step === 'db') {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        $siteUrl = rtrim(trim($_POST['site_url'] ?? ''), '/');

        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo = null;

            $template = file_get_contents(TEMPLATE_PATH);
            $template = strtr($template, [
                '{{DB_HOST}}' => addslashes($dbHost),
                '{{DB_NAME}}' => addslashes($dbName),
                '{{DB_USER}}' => addslashes($dbUser),
                '{{DB_PASS}}' => addslashes($dbPass),
                '{{SITE_URL}}' => addslashes($siteUrl),
            ]);
            file_put_contents(CONFIG_PATH, $template);
            header('Location: install.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Could not connect: ' . $e->getMessage();
        }
    }

    $guessedUrl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    installer_layout('Database setup', '
        <p>First, tell me how to reach your MySQL database.</p>
        ' . ($error ? '<p class="flash flash-error">' . htmlspecialchars($error) . '</p>' : '') . '
        <form method="post" class="card">
          <label>Database host <input type="text" name="db_host" value="localhost" required></label>
          <label>Database name <input type="text" name="db_name" required></label>
          <label>Database user <input type="text" name="db_user" required></label>
          <label>Database password <input type="password" name="db_pass"></label>
          <label>Site URL (no trailing slash) <input type="url" name="site_url" value="' . htmlspecialchars($guessedUrl) . '" required></label>
          <button class="btn" type="submit">Connect</button>
        </form>
    ');
}

// ---------------------------------------------------------------------
// Step 2: site name, Auth0, bunny.net, admin emails -> finish install
// ---------------------------------------------------------------------
if ($step === 'site_info') {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $replacements = [
            '{{AUTH0_DOMAIN}}' => trim($_POST['auth0_domain'] ?? ''),
            '{{AUTH0_CLIENT_ID}}' => trim($_POST['auth0_client_id'] ?? ''),
            '{{AUTH0_CLIENT_SECRET}}' => trim($_POST['auth0_client_secret'] ?? ''),
            '{{ADMIN_EMAILS}}' => trim($_POST['admin_emails'] ?? ''),
            '{{APP_SECRET}}' => bin2hex(random_bytes(32)),
            '{{BUNNY_STREAM_LIBRARY_ID}}' => trim($_POST['bunny_stream_library_id'] ?? ''),
            '{{BUNNY_STREAM_API_KEY}}' => trim($_POST['bunny_stream_api_key'] ?? ''),
            '{{BUNNY_STREAM_TOKEN_AUTH_KEY}}' => trim($_POST['bunny_stream_token_auth_key'] ?? ''),
            '{{BUNNY_STREAM_CDN_HOSTNAME}}' => trim($_POST['bunny_stream_cdn_hostname'] ?? ''),
            '{{BUNNY_STORAGE_ZONE}}' => trim($_POST['bunny_storage_zone'] ?? ''),
            '{{BUNNY_STORAGE_API_KEY}}' => trim($_POST['bunny_storage_api_key'] ?? ''),
            '{{BUNNY_STORAGE_REGION_HOST}}' => trim($_POST['bunny_storage_region_host'] ?? ''),
            '{{BUNNY_STORAGE_PULL_ZONE}}' => trim($_POST['bunny_storage_pull_zone'] ?? ''),
            '{{RESEND_API_KEY}}' => trim($_POST['resend_api_key'] ?? ''),
            '{{MAIL_FROM}}' => trim($_POST['mail_from'] ?? 'Marine Team <onboarding@resend.dev>'),
            '{{VAPID_PUBLIC_KEY}}' => trim($_POST['vapid_public_key'] ?? ''),
            '{{VAPID_PRIVATE_KEY}}' => trim($_POST['vapid_private_key'] ?? ''),
        ];
        $configContents = file_get_contents(CONFIG_PATH);
        $configContents = strtr($configContents, array_map('addslashes', $replacements));
        file_put_contents(CONFIG_PATH, $configContents);

        try {
            require_once CONFIG_PATH;
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmtSql) {
                if ($stmtSql !== '') db()->exec($stmtSql);
            }
            if (!empty($_POST['site_name'])) {
                set_setting('site_name', trim($_POST['site_name']));
            }
            header('Location: install.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Database connected, but the schema import failed: ' . $e->getMessage();
        }
    }

    installer_layout('Site setup', '
        <p>Database connected. Now the rest — Auth0 is required; bunny.net, Resend, and Web Push are optional (leave blank to disable and configure later in config.php).</p>
        ' . ($error ? '<p class="flash flash-error">' . htmlspecialchars($error) . '</p>' : '') . '
        <form method="post" class="card">
          <label>Site name <input type="text" name="site_name" value="Marine Team"></label>

          <h3>Auth0 (required)</h3>
          <p class="muted small">Regular Web Application. Allowed Callback URL: <code>' . htmlspecialchars(defined('SITE_URL') ? SITE_URL : '') . '/auth/callback.php</code>, Allowed Logout URL: your site URL.</p>
          <label>Auth0 domain (no https://) <input type="text" name="auth0_domain" placeholder="your-tenant.us.auth0.com" required></label>
          <label>Auth0 client ID <input type="text" name="auth0_client_id" required></label>
          <label>Auth0 client secret <input type="password" name="auth0_client_secret" required></label>
          <label>Admin emails (comma-separated — auto-approved as ADMIN on first login) <input type="text" name="admin_emails" placeholder="you@example.com" required></label>

          <h3>bunny.net Stream (video, optional)</h3>
          <label>Library ID <input type="text" name="bunny_stream_library_id"></label>
          <label>API key <input type="text" name="bunny_stream_api_key"></label>
          <label>Token Authentication key (optional) <input type="text" name="bunny_stream_token_auth_key"></label>
          <label>CDN hostname (optional, thumbnails) <input type="text" name="bunny_stream_cdn_hostname" placeholder="vz-xxxx.b-cdn.net"></label>

          <h3>bunny.net Storage (downloadable files, optional)</h3>
          <label>Storage zone name <input type="text" name="bunny_storage_zone"></label>
          <label>Storage API key (zone password) <input type="text" name="bunny_storage_api_key"></label>
          <label>Storage region host <input type="text" name="bunny_storage_region_host" placeholder="storage.bunnycdn.com"></label>
          <label>Public pull zone hostname <input type="text" name="bunny_storage_pull_zone"></label>

          <h3>Resend (email, optional)</h3>
          <label>API key <input type="text" name="resend_api_key"></label>
          <label>From address <input type="text" name="mail_from" value="Marine Team &lt;onboarding@resend.dev&gt;"></label>

          <h3>Web Push (Notifications plugin, optional)</h3>
          <p class="muted small">Generate a VAPID keypair (e.g. with the <code>web-push</code> npm CLI) if you want the Notifications plugin.</p>
          <label>VAPID public key <input type="text" name="vapid_public_key"></label>
          <label>VAPID private key <input type="text" name="vapid_private_key"></label>

          <button class="btn" type="submit">Install</button>
        </form>
    ');
}
