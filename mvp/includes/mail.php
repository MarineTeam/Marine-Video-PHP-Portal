<?php
/**
 * Email delivery via the Resend HTTP API (no SDK dependency).
 * Inert until RESEND_API_KEY is set — mirrors the original app's behavior:
 * without a key, admins simply copy share links and send them manually.
 */

function resend_is_configured(): bool
{
    return RESEND_API_KEY !== '';
}

/**
 * Send an email through Resend. Returns [true, null] on success or
 * [false, "error message"] on failure. Never throws — a mail failure must
 * never block share-link creation.
 */
function resend_send(string $toEmail, string $subject, string $html): array
{
    if (!resend_is_configured()) {
        return [false, 'Email delivery is not configured (RESEND_API_KEY unset).'];
    }

    $payload = [
        'from' => MAIL_FROM,
        'to' => [$toEmail],
        'subject' => $subject,
        'html' => $html,
    ];
    if (EMAIL_REPLY_TO !== '') {
        $payload['reply_to'] = EMAIL_REPLY_TO;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [false, 'Network error contacting Resend: ' . $curlErr];
    }
    if ($status >= 400) {
        $decoded = json_decode($body ?? '', true);
        $msg = $decoded['message'] ?? ('Resend returned HTTP ' . $status);
        return [false, $msg];
    }
    return [true, null];
}

/** Compose and send the "you've been shared a video" email for a share link. */
function send_share_link_email(string $toEmail, string $videoTitle, string $watchUrl, string $expiresAtHuman): array
{
    $siteName = htmlspecialchars(SITE_NAME);
    $title = htmlspecialchars($videoTitle);
    $subject = "You've been shared a video: {$videoTitle}";
    $html = <<<HTML
<div style="font-family:Inter,Arial,sans-serif;background:#0b0f14;color:#e7edf3;padding:32px;border-radius:12px;max-width:520px;margin:0 auto;">
  <h2 style="margin-top:0;color:#e7edf3;">{$siteName}</h2>
  <p>Someone shared a private video with you:</p>
  <p style="font-size:18px;font-weight:600;">{$title}</p>
  <p>
    <a href="{$watchUrl}" style="display:inline-block;padding:12px 20px;background:linear-gradient(90deg,#3ddad7,#5b8dee);color:#03121a;font-weight:700;border-radius:8px;text-decoration:none;">Watch video</a>
  </p>
  <p style="color:#9fb0c0;font-size:13px;">This link only works when you're signed in with this email address, and expires {$expiresAtHuman}.</p>
</div>
HTML;
    return resend_send($toEmail, $subject, $html);
}
