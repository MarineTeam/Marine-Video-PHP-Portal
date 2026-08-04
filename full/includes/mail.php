<?php
function resend_is_configured(): bool
{
    return RESEND_API_KEY !== '';
}

function resend_send(string $toEmail, string $subject, string $html): array
{
    if (!resend_is_configured()) {
        return [false, 'Email delivery is not configured (RESEND_API_KEY unset).'];
    }
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . RESEND_API_KEY, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['from' => MAIL_FROM, 'to' => [$toEmail], 'subject' => $subject, 'html' => $html]),
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return [false, "Network error contacting Resend: $err"];
    if ($status >= 400) {
        $decoded = json_decode($body ?? '', true);
        return [false, $decoded['message'] ?? "Resend returned HTTP $status"];
    }
    return [true, null];
}
