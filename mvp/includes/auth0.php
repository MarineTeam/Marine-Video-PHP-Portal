<?php
/**
 * Minimal Auth0 integration using the OIDC Authorization Code flow.
 * No Composer/SDK dependency — plain cURL + JWKS (RS256) signature
 * verification, matching what @auth0/nextjs-auth0 does under the hood.
 *
 * Session shape after login:
 *   $_SESSION['auth0_user'] = ['sub' => ..., 'email' => ..., 'name' => ...]
 */

function auth0_base_url(): string
{
    return 'https://' . AUTH0_DOMAIN;
}

/** Build the /authorize URL and stash CSRF state + nonce in the session. */
function auth0_login_url(?string $returnTo = null): string
{
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['auth0_state'] = $state;
    $_SESSION['auth0_nonce'] = $nonce;
    $_SESSION['auth0_return_to'] = $returnTo ?: (SITE_URL . '/index.php');

    $params = [
        'response_type' => 'code',
        'client_id' => AUTH0_CLIENT_ID,
        'redirect_uri' => AUTH0_CALLBACK_URL,
        'scope' => 'openid profile email',
        'state' => $state,
        'nonce' => $nonce,
    ];
    return auth0_base_url() . '/authorize?' . http_build_query($params);
}

/** Logout of the local session AND the Auth0 tenant session. */
function auth0_logout_url(): string
{
    $params = [
        'client_id' => AUTH0_CLIENT_ID,
        'returnTo' => SITE_URL . '/index.php',
    ];
    return auth0_base_url() . '/v2/logout?' . http_build_query($params);
}

function base64url_decode(string $data): string
{
    $b64 = strtr($data, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) {
        $b64 .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($b64);
}

/** Fetch (and lightly file-cache) the tenant's JWKS. */
function auth0_get_jwks(): array
{
    $cacheFile = sys_get_temp_dir() . '/mvp_auth0_jwks.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $body = file_get_contents($cacheFile);
    } else {
        $body = auth0_http_get(auth0_base_url() . '/.well-known/jwks.json');
        if ($body !== null) {
            @file_put_contents($cacheFile, $body);
        } elseif (is_file($cacheFile)) {
            $body = file_get_contents($cacheFile); // stale but usable
        }
    }
    $json = json_decode($body ?? '', true);
    return $json['keys'] ?? [];
}

function auth0_http_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $ok = curl_errno($ch) === 0 && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400;
    curl_close($ch);
    return $ok ? $body : null;
}

/** Build a PEM public key from a JWK's RSA modulus/exponent. */
function auth0_jwk_to_pem(array $jwk): ?string
{
    if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
        return null;
    }
    $n = base64url_decode($jwk['n']);
    $e = base64url_decode($jwk['e']);

    $modulus = pack('Ca*a*', 0x02, asn1_length(strlen($n) + ($n[0] > "\x7f" ? 1 : 0)), ($n[0] > "\x7f" ? "\x00" . $n : $n));
    $exponent = pack('Ca*a*', 0x02, asn1_length(strlen($e)), $e);
    $rsaPubSeq = pack('Ca*a*a*', 0x30, asn1_length(strlen($modulus) + strlen($exponent)), $modulus, $exponent);
    $bitString = pack('Ca*a*', 0x03, asn1_length(strlen($rsaPubSeq) + 1), "\x00" . $rsaPubSeq);
    $algId = hex2bin('300d06092a864886f70d0101010500'); // rsaEncryption OID + NULL
    $spki = pack('Ca*a*a*', 0x30, asn1_length(strlen($algId) + strlen($bitString)), $algId, $bitString);

    $der = base64_encode($spki);
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($der, 64, "\n") . "-----END PUBLIC KEY-----\n";
    return $pem;
}

function asn1_length(int $len): string
{
    if ($len <= 0x7f) {
        return chr($len);
    }
    $bytes = ltrim(pack('N', $len), "\x00");
    return chr(0x80 | strlen($bytes)) . $bytes;
}

/**
 * Decode + verify an RS256 ID token's signature against the tenant JWKS.
 * Returns the claims array, or null if verification fails.
 */
function auth0_verify_id_token(string $idToken): ?array
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        return null;
    }
    [$headerB64, $payloadB64, $sigB64] = $parts;
    $header = json_decode(base64url_decode($headerB64), true);
    $payload = json_decode(base64url_decode($payloadB64), true);
    $signature = base64url_decode($sigB64);
    if (!$header || !$payload || ($header['alg'] ?? '') !== 'RS256') {
        return null;
    }

    $pem = null;
    foreach (auth0_get_jwks() as $jwk) {
        if (($jwk['kid'] ?? null) === ($header['kid'] ?? null)) {
            $pem = auth0_jwk_to_pem($jwk);
            break;
        }
    }
    if (!$pem) {
        return null;
    }

    $signedInput = $headerB64 . '.' . $payloadB64;
    $ok = openssl_verify($signedInput, $signature, $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        return null;
    }

    // Standard claim checks
    if (($payload['iss'] ?? '') !== auth0_base_url() . '/') {
        return null;
    }
    $aud = $payload['aud'] ?? null;
    $audOk = is_array($aud) ? in_array(AUTH0_CLIENT_ID, $aud, true) : $aud === AUTH0_CLIENT_ID;
    if (!$audOk) {
        return null;
    }
    if (($payload['exp'] ?? 0) < time()) {
        return null;
    }
    if (isset($_SESSION['auth0_nonce']) && ($payload['nonce'] ?? null) !== $_SESSION['auth0_nonce']) {
        return null;
    }

    return $payload;
}

/** Exchange the authorization code for tokens and establish the local session. */
function auth0_handle_callback(): void
{
    if (($_GET['state'] ?? '') !== ($_SESSION['auth0_state'] ?? '__none__')) {
        http_response_code(400);
        die('Invalid OAuth state.');
    }
    if (empty($_GET['code'])) {
        http_response_code(400);
        die('Missing authorization code. Error: ' . htmlspecialchars($_GET['error_description'] ?? 'unknown'));
    }

    $ch = curl_init(auth0_base_url() . '/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'authorization_code',
            'client_id' => AUTH0_CLIENT_ID,
            'client_secret' => AUTH0_CLIENT_SECRET,
            'code' => $_GET['code'],
            'redirect_uri' => AUTH0_CALLBACK_URL,
        ]),
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $tokens = json_decode($body ?? '', true);
    if ($status >= 400 || empty($tokens['id_token'])) {
        http_response_code(502);
        die('Auth0 token exchange failed.');
    }

    $claims = auth0_verify_id_token($tokens['id_token']);
    if (!$claims) {
        http_response_code(401);
        die('Could not verify Auth0 session. Please try logging in again.');
    }

    $email = strtolower(trim($claims['email'] ?? ''));
    if ($email === '') {
        http_response_code(401);
        die('Your Auth0 account has no email address on file.');
    }

    unset($_SESSION['auth0_state'], $_SESSION['auth0_nonce']);
    $_SESSION['auth0_user'] = [
        'sub' => $claims['sub'] ?? '',
        'email' => $email,
        'name' => $claims['name'] ?? $email,
    ];

    sync_local_user_record($email);

    $returnTo = $_SESSION['auth0_return_to'] ?? (SITE_URL . '/index.php');
    unset($_SESSION['auth0_return_to']);
    header('Location: ' . $returnTo);
    exit;
}

/** Ensure a row exists in `users` for this email and apply ADMIN_EMAILS + last_seen. */
function sync_local_user_record(string $email): void
{
    $isAdmin = is_admin_email($email) ? 1 : 0;
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row) {
        db()->prepare('UPDATE users SET last_seen = NOW(), is_admin = GREATEST(is_admin, ?) WHERE id = ?')
            ->execute([$isAdmin, $row['id']]);
    } else {
        db()->prepare('INSERT INTO users (email, password_hash, is_admin, is_approved, last_seen) VALUES (?, ?, ?, ?, NOW())')
            ->execute([$email, '', $isAdmin, $isAdmin]); // admins are auto-approved; others need admin approval
    }
}

function is_admin_email(string $email): bool
{
    $list = array_map(fn($e) => strtolower(trim($e)), explode(',', ADMIN_EMAILS));
    return in_array(strtolower($email), $list, true);
}

/** Return the current logged-in user's app record, or null if not logged in. */
function current_user(): ?array
{
    if (empty($_SESSION['auth0_user']['email'])) {
        return null;
    }
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $email = $_SESSION['auth0_user']['email'];
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row) {
        $row['is_admin'] = (bool)($row['is_admin'] || is_admin_email($email));
        $row['is_approved'] = (bool)($row['is_approved'] || $row['is_admin']);
    }
    $cached = $row ?: null;
    return $cached;
}
