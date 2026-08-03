<?php
/**
 * Pure-PHP Web Push sender (RFC 8291 message encryption + RFC 8292 VAPID),
 * using only openssl_* and hash_hkdf() — both built into PHP 8.1+, so no
 * Composer package is required. This is the most intricate piece of the
 * whole app: real elliptic-curve crypto with no external library to lean
 * on. It's implemented carefully to spec, but — unlike everything else in
 * this project — it could not be exercised against a real browser push
 * subscription in the sandbox this was built in. Test it against a real
 * subscription before relying on it; the venerable `minishlink/web-push`
 * Composer package is a solid fallback if anything here misbehaves.
 */

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** DER-encoded ECDSA signature -> raw r||s (64 bytes), as JWS/VAPID requires. */
function der_ecdsa_to_raw(string $der): string
{
    $offset = 2; // skip SEQUENCE tag + length
    $readInt = function (string $der, int &$offset): string {
        $offset++; // skip INTEGER tag (0x02)
        $len = ord($der[$offset]); $offset++;
        $bytes = substr($der, $offset, $len);
        $offset += $len;
        return ltrim($bytes, "\x00"); // strip any leading sign-padding zero
    };
    $r = $readInt($der, $offset);
    $s = $readInt($der, $offset);
    return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}

function vapid_jwt(string $audience): string
{
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = base64url_encode(json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => VAPID_SUBJECT]));
    $signingInput = "$header.$payload";

    $privateKey = openssl_pkey_get_private(vapid_private_key_pem());
    openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $rawSig = base64url_encode(der_ecdsa_to_raw($signature));
    return "$signingInput.$rawSig";
}

/** Reconstructs a usable EC private-key PEM from the raw base64url d-value stored in config. */
function vapid_private_key_pem(): string
{
    $d = base64url_decode_local(VAPID_PRIVATE_KEY);
    // Build an ASN.1 SEC1 EC private key structure for the P-256 curve.
    $version = "\x02\x01\x01";
    $privKey = "\x04\x20" . str_pad($d, 32, "\x00", STR_PAD_LEFT);
    $curveOid = "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // P-256 (prime256v1) OID 1.2.840.10045.3.1.7
    $pubKey = "\xa1\x44\x03\x42\x00\x04" . base64url_decode_local(VAPID_PUBLIC_KEY);
    $seqBody = $version . $privKey . $curveOid . $pubKey;
    $seq = "\x30" . asn1_len(strlen($seqBody)) . $seqBody;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($seq), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

function asn1_len(int $len): string
{
    if ($len <= 0x7f) return chr($len);
    $bytes = ltrim(pack('N', $len), "\x00");
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function base64url_decode_local(string $data): string
{
    $b64 = strtr($data, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) $b64 .= str_repeat('=', 4 - $pad);
    return base64_decode($b64);
}

/**
 * Encrypts $payload for one subscription per RFC 8291 (aes128gcm) and POSTs
 * it to the push service. Returns [true, null] or [false, "error"].
 */
function send_web_push(array $subscription, string $payload): array
{
    if (VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') {
        return [false, 'VAPID keys are not configured.'];
    }
    try {
        $endpoint = $subscription['endpoint'];
        $uaPublicRaw = base64url_decode_local($subscription['p256dh']); // 65-byte uncompressed EC point
        $authSecret = base64url_decode_local($subscription['auth_key']); // 16 bytes

        // Ephemeral local EC keypair for this message.
        $localKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $localDetails = openssl_pkey_get_details($localKey);
        $asPublicRaw = "\x04" . $localDetails['ec']['x'] . $localDetails['ec']['y'];

        // Reconstruct the client's (UA) EC public key as an openssl resource to derive ECDH with it.
        $uaPublicPem = ec_point_to_pem($uaPublicRaw);
        $uaPublicKey = openssl_pkey_get_public($uaPublicPem);
        $sharedSecret = openssl_pkey_derive($uaPublicKey, $localKey, 256);
        if (!$sharedSecret) return [false, 'ECDH derivation failed — check the subscription keys.'];

        $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;
        $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $authSecret);

        $salt = random_bytes(16);
        $prkInfoCek = "Content-Encoding: aes128gcm\x00";
        $cek = hash_hkdf('sha256', $ikm, 16, $prkInfoCek, $salt);
        $nonceInfo = "Content-Encoding: nonce\x00";
        $nonce = hash_hkdf('sha256', $ikm, 12, $nonceInfo, $salt);

        $paddedPayload = $payload . "\x02"; // delimiter byte, no extra padding
        $ciphertext = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) return [false, 'AES-GCM encryption failed.'];

        $header = $salt . pack('N', 4096) . chr(strlen($asPublicRaw)) . $asPublicRaw;
        $body = $header . $ciphertext . $tag;

        $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
        $jwt = vapid_jwt($audience);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Encoding: aes128gcm',
                'Content-Type: application/octet-stream',
                'TTL: 86400',
                'Authorization: vapid t=' . $jwt . ', k=' . VAPID_PUBLIC_KEY,
            ],
        ]);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return [false, "Network error: $err"];
        if ($status >= 400) return [false, "Push service returned HTTP $status"];
        return [true, null];
    } catch (Throwable $e) {
        return [false, 'Exception: ' . $e->getMessage()];
    }
}

function ec_point_to_pem(string $rawPoint): string
{
    $algId = hex2bin('301306072a8648ce3d020106082a8648ce3d030107'); // id-ecPublicKey + prime256v1 OID
    $bitString = "\x03" . asn1_len(strlen($rawPoint) + 1) . "\x00" . $rawPoint;
    $seq = "\x30" . asn1_len(strlen($algId) + strlen($bitString)) . $algId . $bitString;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($seq), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
