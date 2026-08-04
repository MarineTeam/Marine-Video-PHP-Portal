<?php
/**
 * Same plain-cURL Auth0 OIDC integration as the video-portal engine (no
 * SDK/Composer dependency, full RS256 JWKS verification) — adapted so that
 * logging in only proves identity. Every login gets a `users` row; whether
 * they can actually see anything depends on `authorized`, checked separately
 * by require_authorized() in functions.php.
 */

function auth0_base_url(): string
{
    return 'https://' . AUTH0_DOMAIN;
}

function auth0_login_url(?string $returnTo = null): string
{
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['auth0_state'] = $state;
    $_SESSION['auth0_nonce'] = $nonce;
    $_SESSION['auth0_return_to'] = $returnTo ?: (SITE_URL . '/index.php');

    return auth0_base_url() . '/authorize?' . http_build_query([
        'response_type' => 'code',
        'client_id' => AUTH0_CLIENT_ID,
        'redirect_uri' => AUTH0_CALLBACK_URL,
        'scope' => 'openid profile email',
        'state' => $state,
        'nonce' => $nonce,
    ]);
}

function auth0_logout_url(): string
{
    return auth0_base_url() . '/v2/logout?' . http_build_query([
        'client_id' => AUTH0_CLIENT_ID,
        'returnTo' => SITE_URL . '/index.php',
    ]);
}

function base64url_decode(string $data): string
{
    $b64 = strtr($data, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) $b64 .= str_repeat('=', 4 - $pad);
    return base64_decode($b64);
}

function auth0_get_jwks(): array
{
    $cacheFile = sys_get_temp_dir() . '/mtcms_auth0_jwks.json';
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $body = file_get_contents($cacheFile);
    } else {
        $body = auth0_http_get(auth0_base_url() . '/.well-known/jwks.json');
        if ($body !== null) {
            @file_put_contents($cacheFile, $body);
        } elseif (is_file($cacheFile)) {
            $body = file_get_contents($cacheFile);
        }
    }
    return json_decode($body ?? '', true)['keys'] ?? [];
}

function auth0_http_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = curl_exec($ch);
    $ok = curl_errno($ch) === 0 && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400;
    curl_close($ch);
    return $ok ? $body : null;
}

function auth0_jwk_to_pem(array $jwk): ?string
{
    if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) return null;
    $n = base64url_decode($jwk['n']);
    $e = base64url_decode($jwk['e']);
    $modulus = pack('Ca*a*', 0x02, asn1_length(strlen($n) + ($n[0] > "\x7f" ? 1 : 0)), ($n[0] > "\x7f" ? "\x00" . $n : $n));
    $exponent = pack('Ca*a*', 0x02, asn1_length(strlen($e)), $e);
    $rsaPubSeq = pack('Ca*a*a*', 0x30, asn1_length(strlen($modulus) + strlen($exponent)), $modulus, $exponent);
    $bitString = pack('Ca*a*', 0x03, asn1_length(strlen($rsaPubSeq) + 1), "\x00" . $rsaPubSeq);
    $algId = hex2bin('300d06092a864886f70d0101010500');
    $spki = pack('Ca*a*a*', 0x30, asn1_length(strlen($algId) + strlen($bitString)), $algId, $bitString);
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function asn1_length(int $len): string
{
    if ($len <= 0x7f) return chr($len);
    $bytes = ltrim(pack('N', $len), "\x00");
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function auth0_verify_id_token(string $idToken): ?array
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) return null;
    [$headerB64, $payloadB64, $sigB64] = $parts;
    $header = json_decode(base64url_decode($headerB64), true);
    $payload = json_decode(base64url_decode($payloadB64), true);
    $signature = base64url_decode($sigB64);
    if (!$header || !$payload || ($header['alg'] ?? '') !== 'RS256') return null;

    $pem = null;
    foreach (auth0_get_jwks() as $jwk) {
        if (($jwk['kid'] ?? null) === ($header['kid'] ?? null)) { $pem = auth0_jwk_to_pem($jwk); break; }
    }
    if (!$pem) return null;
    if (openssl_verify($headerB64 . '.' . $payloadB64, $signature, $pem, OPENSSL_ALGO_SHA256) !== 1) return null;
    if (($payload['iss'] ?? '') !== auth0_base_url() . '/') return null;
    $aud = $payload['aud'] ?? null;
    $audOk = is_array($aud) ? in_array(AUTH0_CLIENT_ID, $aud, true) : $aud === AUTH0_CLIENT_ID;
    if (!$audOk || ($payload['exp'] ?? 0) < time()) return null;
    if (isset($_SESSION['auth0_nonce']) && ($payload['nonce'] ?? null) !== $_SESSION['auth0_nonce']) return null;
    return $payload;
}

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
    $email = normalize_email($claims['email'] ?? '');
    if ($email === '') {
        http_response_code(401);
        die('Your Auth0 account has no email address on file.');
    }

    unset($_SESSION['auth0_state'], $_SESSION['auth0_nonce']);
    $_SESSION['auth0_user'] = ['sub' => $claims['sub'] ?? '', 'email' => $email, 'name' => $claims['name'] ?? $email];
    sync_local_user_record($email, $claims['name'] ?? $email, $claims['sub'] ?? '');

    $returnTo = $_SESSION['auth0_return_to'] ?? (SITE_URL . '/index.php');
    unset($_SESSION['auth0_return_to']);
    redirect($returnTo);
}

/**
 * Every login attempt gets a `users` row (visible under Admin -> Users as
 * a "pending login attempt" until authorized). ADMIN_EMAILS self-authorize
 * as ADMIN on first login; everyone else needs an admin to grant access.
 */
function sync_local_user_record(string $email, string $name, string $sub): void
{
    $isAdminEmail = is_admin_email($email);
    $stmt = db()->prepare('SELECT id, authorized, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row) {
        if ($isAdminEmail && ($row['role'] !== 'ADMIN' || !$row['authorized'])) {
            db()->prepare("UPDATE users SET role = 'ADMIN', authorized = 1, auth0_sub = ?, name = ?, last_seen = NOW() WHERE id = ?")
                ->execute([$sub, $name, $row['id']]);
        } else {
            db()->prepare('UPDATE users SET auth0_sub = ?, name = ?, last_seen = NOW() WHERE id = ?')
                ->execute([$sub, $name, $row['id']]);
        }
    } else {
        db()->prepare('INSERT INTO users (email, auth0_sub, name, role, authorized, last_seen) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([$email, $sub, $name, $isAdminEmail ? 'ADMIN' : 'MEMBER', $isAdminEmail ? 1 : 0]);
    }
}
