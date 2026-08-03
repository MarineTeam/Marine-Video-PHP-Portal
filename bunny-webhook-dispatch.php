<?php
/**
 * bunny.net only allows ONE webhook URL per Stream library. If both the
 * mvp/ and cms/ engines share a library (or you just don't want to manage
 * two libraries), point bunny.net's Webhook URL at THIS file instead, and
 * it fans the payload out to both engines' own bunny-webhook.php receivers.
 *
 * Why a separate dispatcher instead of just including both engines' logic
 * here directly: mvp/config.php and cms/config.php each define same-named
 * global functions (db(), h(), bunny_status_to_local(), etc.) — requiring
 * both in one PHP process would fatal with "cannot redeclare function".
 * Forwarding as two independent HTTP requests avoids that entirely, and
 * reuses the exact receiver code already in each engine, so there's only
 * one place the update logic lives per engine.
 *
 * Deploy this file anywhere reachable by a single URL — e.g. your domain
 * root, as a sibling to the mvp/ and cms/ folders — and set the two target
 * URLs below to match wherever you actually put those folders.
 */

// ---- Fill these in to match your actual deployment ------------------------
$targets = [
    'https://marine-video.devinl.net/mvp/bunny-webhook.php',
    'https://marine-video.devinl.net/cms/bunny-webhook.php',
];
// -----------------------------------------------------------------------

$rawBody = file_get_contents('php://input');
if ($rawBody === '' || json_decode($rawBody) === null) {
    http_response_code(400);
    exit('Bad payload');
}

$results = [];
foreach ($targets as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $rawBody,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $results[$url] = $status;
}

// A given video only ever belongs to whichever engine actually created it,
// so exactly one target will find a matching row and update it — the other
// will just no-op (200, nothing changed). That's expected, not an error.
http_response_code(200);
echo json_encode(['ok' => true, 'forwarded_to' => $results]);
