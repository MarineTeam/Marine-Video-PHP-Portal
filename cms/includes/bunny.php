<?php
/**
 * bunny.net Stream (video hosting) + Storage (downloadable files).
 * Same signed-URL/TUS-ticket pattern as the video-portal engine's
 * includes/bunny.php, plus Storage zone upload/delete for the Files feature.
 */

function bunny_stream_configured(): bool
{
    return BUNNY_STREAM_LIBRARY_ID !== '' && BUNNY_STREAM_API_KEY !== '';
}

function bunny_storage_configured(): bool
{
    return BUNNY_STORAGE_ZONE !== '' && BUNNY_STORAGE_API_KEY !== '' && BUNNY_STORAGE_REGION_HOST !== '';
}

function bunny_stream_api(string $method, string $path, ?array $jsonBody = null): array
{
    $ch = curl_init('https://video.bunnycdn.com' . $path);
    $headers = ['AccessKey: ' . BUNNY_STREAM_API_KEY, 'Accept: application/json'];
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20];
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
    if ($err) return [false, null, "Network error contacting bunny.net: $err"];
    $decoded = json_decode($body ?? '', true);
    if ($status >= 400) return [false, $decoded, "bunny.net returned HTTP $status: " . ($decoded['message'] ?? $body)];
    return [true, $decoded, null];
}

function bunny_create_video(string $title): array
{
    [$ok, $data, $err] = bunny_stream_api('POST', '/library/' . BUNNY_STREAM_LIBRARY_ID . '/videos', ['title' => $title]);
    return $ok ? [$data['guid'] ?? null, null] : [null, $err];
}

function bunny_get_video(string $guid): ?array
{
    [$ok, $data] = bunny_stream_api('GET', '/library/' . BUNNY_STREAM_LIBRARY_ID . '/videos/' . $guid);
    return $ok ? $data : null;
}

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
    [$ok, $data, $err] = bunny_stream_api('GET', '/library/' . BUNNY_STREAM_LIBRARY_ID . '/videos?page=' . $page . '&itemsPerPage=' . $itemsPerPage . '&orderBy=date');
    if (!$ok) return ['items' => [], 'totalItems' => 0, 'error' => $err];
    return ['items' => $data['items'] ?? [], 'totalItems' => (int)($data['totalItems'] ?? 0), 'error' => null];
}

function bunny_delete_video(string $guid): bool
{
    [$ok] = bunny_stream_api('DELETE', '/library/' . BUNNY_STREAM_LIBRARY_ID . '/videos/' . $guid);
    return $ok;
}

function bunny_sign_tus_upload(string $guid, int $ttlSeconds = 3600): array
{
    $expire = time() + $ttlSeconds;
    return [
        'endpoint' => 'https://video.bunnycdn.com/tusupload',
        'library_id' => BUNNY_STREAM_LIBRARY_ID,
        'video_id' => $guid,
        'signature' => hash('sha256', BUNNY_STREAM_LIBRARY_ID . BUNNY_STREAM_API_KEY . $expire . $guid),
        'expire' => $expire,
    ];
}

function bunny_embed_url(string $guid, int $ttlSeconds = STREAM_TOKEN_TTL): string
{
    $expires = time() + $ttlSeconds;
    $token = hash('sha256', BUNNY_STREAM_TOKEN_AUTH_KEY . $guid . $expires);
    return sprintf('https://iframe.mediadelivery.net/embed/%s/%s?token=%s&expires=%d&autoplay=false',
        BUNNY_STREAM_LIBRARY_ID, $guid, $token, $expires);
}

function bunny_thumbnail_url(string $guid, int $ttlSeconds = 21600): ?string
{
    if (BUNNY_STREAM_CDN_HOSTNAME === '') return null;
    $path = '/' . $guid . '/thumbnail.jpg';
    $plain = 'https://' . BUNNY_STREAM_CDN_HOSTNAME . $path;
    if (BUNNY_STREAM_TOKEN_AUTH_KEY === '') return $plain;
    $expires = time() + $ttlSeconds;
    $token = rtrim(strtr(base64_encode(md5(BUNNY_STREAM_TOKEN_AUTH_KEY . $path . $expires, true)), '+/', '-_'), '=');
    return $plain . '?token=' . $token . '&expires=' . $expires;
}

/** ---------------------------------------------------------------------
 * bunny.net Storage — downloadable files
 * ------------------------------------------------------------------ */

function bunny_storage_upload(string $localPath, string $remotePath): array
{
    $url = 'https://' . BUNNY_STORAGE_REGION_HOST . '/' . BUNNY_STORAGE_ZONE . '/' . ltrim($remotePath, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_PUT => true,
        CURLOPT_INFILE => fopen($localPath, 'rb'),
        CURLOPT_INFILESIZE => filesize($localPath),
        CURLOPT_HTTPHEADER => ['AccessKey: ' . BUNNY_STORAGE_API_KEY, 'Content-Type: application/octet-stream'],
        CURLOPT_TIMEOUT => 300,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return [false, "Network error contacting bunny.net Storage: $err"];
    if ($status >= 400) return [false, "bunny.net Storage returned HTTP $status: $body"];
    $publicUrl = BUNNY_STORAGE_PULL_ZONE !== '' ? 'https://' . BUNNY_STORAGE_PULL_ZONE . '/' . ltrim($remotePath, '/') : null;
    return [true, $publicUrl];
}

function bunny_storage_delete(string $remotePath): bool
{
    $url = 'https://' . BUNNY_STORAGE_REGION_HOST . '/' . BUNNY_STORAGE_ZONE . '/' . ltrim($remotePath, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => ['AccessKey: ' . BUNNY_STORAGE_API_KEY],
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status < 400;
}
