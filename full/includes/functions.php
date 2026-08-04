<?php
/** ---------------------------------------------------------------------
 * Guards
 * ------------------------------------------------------------------ */

function current_user(): ?array
{
    if (empty($_SESSION['auth0_user']['email'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$_SESSION['auth0_user']['email']]);
    $row = $stmt->fetch();
    $cached = $row ?: null;
    return $cached;
}

/** Auth0 login proves identity only — this checks the `authorized` flag too. */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . auth0_login_url(current_url()));
        exit;
    }
    return $user;
}

function require_authorized(): array
{
    $user = require_login();
    if (!$user['authorized']) {
        render_not_authorized();
        exit;
    }
    return $user;
}

function require_capability(string $capability, ?string $scopeType = null, $scopeId = null): array
{
    $user = require_authorized();
    if (!user_can($user, $capability, $scopeType, $scopeId)) {
        http_response_code(403);
        die('<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;background:#0b0f14;color:#e7edf3;padding:40px;">403 — you do not have the "' . h($capability) . '" capability here.</body>');
    }
    return $user;
}

function render_not_authorized(): void
{
    http_response_code(403);
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Access not authorized</title>
    <link rel="stylesheet" href="<?= h(SITE_URL) ?>/assets/css/style.css"></head>
    <body><div class="center-card">
      <h1>Access not authorized</h1>
      <p>You're signed in as <?= h(current_user()['email'] ?? '') ?>, but an admin hasn't granted you access yet.</p>
      <a class="btn" href="<?= h(SITE_URL) ?>/logout.php">Sign out</a>
    </div></body></html>
    <?php
}

function current_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/** ---------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sessionToken = $_SESSION['csrf'] ?? '';
    $postedToken = $_POST['csrf'] ?? '';
    if ($sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        http_response_code(400);
        die('Invalid or expired form submission (CSRF check failed). Go back and try again.');
    }
}

/** ---------------------------------------------------------------------
 * Rate limiting
 * ------------------------------------------------------------------ */

function rate_limit_check(string $bucket, int $max, int $windowSeconds = 60): bool
{
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT hit_count, window_start FROM rate_limits WHERE bucket_key = ? FOR UPDATE');
        $stmt->execute([$bucket]);
        $row = $stmt->fetch();
        $now = time();
        if (!$row) {
            $pdo->prepare('INSERT INTO rate_limits (bucket_key, hit_count, window_start) VALUES (?, 1, NOW())')->execute([$bucket]);
            $pdo->commit();
            return true;
        }
        if (($now - strtotime($row['window_start'])) > $windowSeconds) {
            $pdo->prepare('UPDATE rate_limits SET hit_count = 1, window_start = NOW() WHERE bucket_key = ?')->execute([$bucket]);
            $pdo->commit();
            return true;
        }
        if ($row['hit_count'] >= $max) {
            $pdo->commit();
            return false;
        }
        $pdo->prepare('UPDATE rate_limits SET hit_count = hit_count + 1 WHERE bucket_key = ?')->execute([$bucket]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        return true;
    }
}

/** ---------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------ */

function get_setting(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function set_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
        ->execute([$key, $value]);
}

/** ---------------------------------------------------------------------
 * Audit log
 * ------------------------------------------------------------------ */

function audit_log(string $action, string $target = '', string $details = ''): void
{
    try {
        $actor = current_user()['email'] ?? 'system';
        db()->prepare('INSERT INTO audit_log (actor_email, action, target, details) VALUES (?, ?, ?, ?)')
            ->execute([$actor, $action, $target, $details]);
    } catch (Throwable $e) {
        // best-effort
    }
}

/** ---------------------------------------------------------------------
 * Streaming tokens (local video files, mirrors the video-portal engine)
 * ------------------------------------------------------------------ */

function make_stream_token(int $videoId, string $ownerKey, int $ttl = STREAM_TOKEN_TTL): string
{
    $token = bin2hex(random_bytes(20));
    db()->prepare('INSERT INTO play_tokens (token, video_id, owner_key, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$token, $videoId, $ownerKey, date('Y-m-d H:i:s', time() + $ttl)]);
    return $token;
}

function verify_stream_token(string $token, int $videoId): bool
{
    $stmt = db()->prepare('SELECT expires_at FROM play_tokens WHERE token = ? AND video_id = ?');
    $stmt->execute([$token, $videoId]);
    $row = $stmt->fetch();
    return $row && strtotime($row['expires_at']) >= time();
}

/** ---------------------------------------------------------------------
 * Misc
 * ------------------------------------------------------------------ */

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pop_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function slugify(string $text): string
{
    $text = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    return $text !== '' ? $text : 'item';
}

function unique_slug(string $table, string $base): string
{
    $slug = slugify($base);
    $original = $slug;
    $i = 2;
    $stmt = db()->prepare("SELECT COUNT(*) c FROM `$table` WHERE slug = ?");
    while (true) {
        $stmt->execute([$slug]);
        if ((int)$stmt->fetch()['c'] === 0) {
            return $slug;
        }
        $slug = $original . '-' . $i;
        $i++;
    }
}

function format_duration(int $seconds): string
{
    $hh = intdiv($seconds, 3600);
    $mm = intdiv($seconds % 3600, 60);
    $ss = $seconds % 60;
    return $hh > 0 ? sprintf('%d:%02d:%02d', $hh, $mm, $ss) : sprintf('%d:%02d', $mm, $ss);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) return 'never';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return intdiv($diff, 60) . 'm ago';
    if ($diff < 86400) return intdiv($diff, 3600) . 'h ago';
    return intdiv($diff, 86400) . 'd ago';
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function is_admin_email(string $email): bool
{
    $list = array_map(fn($e) => strtolower(trim($e)), explode(',', ADMIN_EMAILS));
    return in_array(strtolower($email), $list, true);
}

const THEME_PRESETS = [
    'ocean'   => ['--accent1' => '#3ddad7', '--accent2' => '#5b8dee'],
    'sunset'  => ['--accent1' => '#ff8a65', '--accent2' => '#ff5e8f'],
    'violet'  => ['--accent1' => '#a78bfa', '--accent2' => '#6d5bd0'],
    'emerald' => ['--accent1' => '#34d399', '--accent2' => '#0ea5a3'],
];

function current_theme_vars(): array
{
    $custom = get_setting('theme_custom', '');
    if ($custom && count($parts = explode(',', $custom)) === 2) {
        return ['--accent1' => trim($parts[0]), '--accent2' => trim($parts[1])];
    }
    return THEME_PRESETS[get_setting('theme_preset', 'ocean')] ?? THEME_PRESETS['ocean'];
}
