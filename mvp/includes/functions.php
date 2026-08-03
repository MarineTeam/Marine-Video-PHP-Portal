<?php
/** ---------------------------------------------------------------------
 * Guards
 * ------------------------------------------------------------------ */

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: ' . auth0_login_url(current_url()));
        exit;
    }
    return $user;
}

function require_approved(): array
{
    $user = require_login();
    if (!$user['is_approved']) {
        render_not_approved();
        exit;
    }
    return $user;
}

/** Server-side admin gate — redirect (page) or 403 (API), mirrors lib/auth.js. */
function require_admin(bool $isApi = false): array
{
    $user = require_login();
    if (!$user['is_admin']) {
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        http_response_code(403);
        die('<!doctype html><meta charset="utf-8"><body style="font-family:sans-serif;background:#0b0f14;color:#e7edf3;padding:40px;">403 — Admins only.</body>');
    }
    return $user;
}

function render_not_approved(): void
{
    http_response_code(200);
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Not approved</title>
    <link rel="stylesheet" href="assets/css/style.css"></head>
    <body class="theme-ocean">
      <div class="center-card">
        <h1>Not approved yet</h1>
        <p>Your account isn't on the approved viewers list. Ask an admin to add <?= htmlspecialchars(current_user()['email'] ?? '') ?> to the portal.</p>
        <a class="btn" href="<?= htmlspecialchars(SITE_URL) ?>/logout.php">Sign out</a>
      </div>
    </body></html>
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
 * Rate limiting (sliding window, fails open on DB error)
 * ------------------------------------------------------------------ */

function rate_limit_check(string $bucket, int $max, int $windowSeconds = RATE_LIMIT_WINDOW_SECONDS): bool
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

        $windowStart = strtotime($row['window_start']);
        if (($now - $windowStart) > $windowSeconds) {
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
        return true; // fail open — an infra hiccup should never block real users
    }
}

/** ---------------------------------------------------------------------
 * Settings (key/value store, replaces the Redis-backed settings)
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
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

/** ---------------------------------------------------------------------
 * Activity log
 * ------------------------------------------------------------------ */

function log_activity(string $action, string $details = ''): void
{
    try {
        $actor = current_user()['email'] ?? 'system';
        db()->prepare('INSERT INTO activity_log (actor_email, action, details) VALUES (?, ?, ?)')
            ->execute([$actor, $action, $details]);
    } catch (Throwable $e) {
        // best-effort — never breaks the underlying action
    }
}

/** ---------------------------------------------------------------------
 * Signed, time-limited streaming tokens (replaces bunny.net embed tokens)
 * ------------------------------------------------------------------ */

function make_stream_token(int $videoId, string $ownerKey, int $ttl = STREAM_TOKEN_TTL): string
{
    $token = bin2hex(random_bytes(20));
    $expires = date('Y-m-d H:i:s', time() + $ttl);
    db()->prepare('INSERT INTO play_tokens (token, video_id, owner_key, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$token, $videoId, $ownerKey, $expires]);
    return $token;
}

function verify_stream_token(string $token, int $videoId): bool
{
    $stmt = db()->prepare('SELECT expires_at FROM play_tokens WHERE token = ? AND video_id = ?');
    $stmt->execute([$token, $videoId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    return strtotime($row['expires_at']) >= time();
}

/** ---------------------------------------------------------------------
 * Misc helpers
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

function format_duration(int $seconds): string
{
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return 'never';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return intdiv($diff, 60) . 'm ago';
    if ($diff < 86400) return intdiv($diff, 3600) . 'h ago';
    return intdiv($diff, 86400) . 'd ago';
}

const THEME_PRESETS = [
    'ocean'   => ['--accent1' => '#3ddad7', '--accent2' => '#5b8dee'],
    'sunset'  => ['--accent1' => '#ff8a65', '--accent2' => '#ff5e8f'],
    'violet'  => ['--accent1' => '#a78bfa', '--accent2' => '#6d5bd0'],
    'emerald' => ['--accent1' => '#34d399', '--accent2' => '#0ea5a3'],
    'gold'    => ['--accent1' => '#fbbf24', '--accent2' => '#f97316'],
    'rose'    => ['--accent1' => '#fb7185', '--accent2' => '#e11d48'],
    'mono'    => ['--accent1' => '#cbd5e1', '--accent2' => '#64748b'],
];

function current_theme_vars(): array
{
    $custom = get_setting('theme_custom', '');
    if ($custom) {
        $parts = explode(',', $custom);
        if (count($parts) === 2) {
            return ['--accent1' => trim($parts[0]), '--accent2' => trim($parts[1])];
        }
    }
    $preset = get_setting('theme_preset', 'ocean');
    return THEME_PRESETS[$preset] ?? THEME_PRESETS['ocean'];
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}
