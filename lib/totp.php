<?php
// Time-based one-time passwords (RFC 6238) — plain PHP, no extension needed.
// hash_hmac() and pack()/unpack() are core PHP, always available.
if (defined('V7_TOTP_LOADED')) return;
define('V7_TOTP_LOADED', 1);

const V7_BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function v7_totp_generate_secret(int $bytes = 20): string
{
    return v7_base32_encode(random_bytes($bytes));
}

function v7_base32_encode(string $data): string
{
    $bits = '';
    foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    $bits = str_pad($bits, (int) (ceil(strlen($bits) / 5) * 5), '0', STR_PAD_RIGHT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= V7_BASE32_ALPHABET[bindec($chunk)];
    return $out;
}

function v7_base32_decode(string $b32): string
{
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $char) {
        $pos = strpos(V7_BASE32_ALPHABET, $char);
        if ($pos === false) continue;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) < 8) continue;
        $bytes .= chr(bindec($byte));
    }
    return $bytes;
}

function v7_totp_code(string $secret, ?int $timestamp = null, int $step = 30, int $digits = 6): string
{
    $timestamp ??= time();
    $counter = pack('N*', 0, (int) floor($timestamp / $step));
    $hash = hash_hmac('sha1', $counter, v7_base32_decode($secret), true);
    $offset = ord($hash[19]) & 0xf;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/** Accepts the current and adjacent time windows to tolerate clock drift. */
function v7_totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = trim($code);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(v7_totp_code($secret, time() + $i * 30), $code)) return true;
    }
    return false;
}

function v7_totp_otpauth_url(string $secret, string $email, string $issuer = 'v7Security'): string
{
    return 'otpauth://totp/' . rawurlencode("$issuer:$email") . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
}
