<?php
/**
 * bunny.net Stream integration.
 * Mirrors lib/bunny.js from the original Next.js app: server-side video
 * lifecycle calls via the Stream API, a signed ticket for browser->bunny
 * TUS resumable uploads (video bytes never touch this server), signed
 * time-limited embed URLs for playback, and signed CDN thumbnail URLs.
 */

function bunny_is_configured(): bool
{
    return BUNNY_LIBRARY_ID !== '' && BUNNY_API_KEY !== '';
}

function bunny_api_request(string $method, string $path, ?array $jsonBody = null): array
{
    $ch = curl_init('https://video.bunnycdn.com' . $path);
    $headers = [
        'AccessKey: ' . BUNNY_API_KEY,
        'Accept: application/json',
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
    ];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($jsonBody);
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return [false, null, "Network error contacting bunny.net: $err"];
    }
    $decoded = json_decode($body ?? '', true);
    if ($status >= 400) {
        $msg = is_array($decoded) ? ($decoded['message'] ?? $body) : $body;
        return [false, $decoded, "bunny.net returned HTTP $status: $msg"];
    }
    return [true, $decoded, null];
}

/** Create a new (empty) video entry in the library. Returns [guid, error]. */
function bunny_create_video(string $title): array
{
    [$ok, $data, $err] = bunny_api_request('POST', '/library/' . BUNNY_LIBRARY_ID . '/videos', ['title' => $title]);
    if (!$ok) {
        return [null, $err];
    }
    return [$data['guid'] ?? null, null];
}

/** Fetch a video's current status from bunny (for encoding-status badges). */
function bunny_get_video(string $guid): ?array
{
    [$ok, $data] = bunny_api_request('GET', '/library/' . BUNNY_LIBRARY_ID . '/videos/' . $guid);
    return $ok ? $data : null;
}

/** bunny status codes: 0 created, 1 uploaded, 2 processing, 3 transcoding, 4 finished, 5 error, 6 upload failed. */
/** bunny.net status codes (confirmed against docs.bunny.net/stream/webhooks):
 *  0 Queued, 1 Processing, 2 Encoding, 3 Finished (fully available),
 *  4 Resolution finished (one resolution done — NOT the final state, more
 *  may still be encoding), 5 Failed. */
function bunny_status_to_local(int $bunnyStatus): string
{
    if ($bunnyStatus === 3) return 'ready';
    if ($bunnyStatus === 5) return 'failed';
    return 'processing';
}

function bunny_list_videos(int $page = 1, int $itemsPerPage = 100): array
{
    [$ok, $data, $err] = bunny_api_request('GET', '/library/' . BUNNY_LIBRARY_ID . '/videos?page=' . $page . '&itemsPerPage=' . $itemsPerPage . '&orderBy=date');
    if (!$ok) return ['items' => [], 'totalItems' => 0, 'error' => $err];
    return ['items' => $data['items'] ?? [], 'totalItems' => (int)($data['totalItems'] ?? 0), 'error' => null];
}

function bunny_delete_video(string $guid): bool
{
    [$ok] = bunny_api_request('DELETE', '/library/' . BUNNY_LIBRARY_ID . '/videos/' . $guid);
    return $ok;
}

/**
 * Sign a TUS upload ticket for a pre-created video so the browser can upload
 * directly to bunny.net over the resumable TUS protocol. The API key never
 * reaches the client — only this short-lived signature does.
 */
function bunny_sign_tus_upload(string $guid, int $ttlSeconds = 3600): array
{
    $expire = time() + $ttlSeconds;
    $signature = hash('sha256', BUNNY_LIBRARY_ID . BUNNY_API_KEY . $expire . $guid);
    return [
        'endpoint' => 'https://video.bunnycdn.com/tusupload',
        'library_id' => BUNNY_LIBRARY_ID,
        'video_id' => $guid,
        'signature' => $signature,
        'expire' => $expire,
    ];
}

/** Signed, time-limited embed URL — regenerated fresh on every watch request. */
function bunny_embed_url(string $guid, int $ttlSeconds = STREAM_TOKEN_TTL): string
{
    $expires = time() + $ttlSeconds;
    $token = hash('sha256', BUNNY_TOKEN_AUTH_KEY . $guid . $expires);
    return sprintf(
        'https://iframe.mediadelivery.net/embed/%s/%s?token=%s&expires=%d&autoplay=false',
        BUNNY_LIBRARY_ID,
        $guid,
        $token,
        $expires
    );
}

/**
 * Signed CDN thumbnail URL. Only needed if "Block Direct URL File Access" is
 * enabled on the library's Security tab; otherwise the plain URL works too.
 * Bunny's CDN token-auth scheme: base64url(md5_raw(key + path + expires)).
 */
function bunny_thumbnail_url(string $guid, int $ttlSeconds = 21600): ?string
{
    if (BUNNY_CDN_HOSTNAME === '') {
        return null;
    }
    $path = '/' . $guid . '/thumbnail.jpg';
    $plain = 'https://' . BUNNY_CDN_HOSTNAME . $path;

    $tokenKey = BUNNY_CDN_TOKEN_KEY !== '' ? BUNNY_CDN_TOKEN_KEY : BUNNY_TOKEN_AUTH_KEY;
    if ($tokenKey === '') {
        return $plain; // no token auth configured on the pull zone
    }
    $expires = time() + $ttlSeconds;
    $hash = md5($tokenKey . $path . $expires, true);
    $token = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    return $plain . '?token=' . $token . '&expires=' . $expires;
}
