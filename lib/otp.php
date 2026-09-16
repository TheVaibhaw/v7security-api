<?php
// Emailed one-time codes — separate from the TOTP app-based flow in totp.php.
// The code itself is never stored in plain text, only its hash, same as a
// password would be.
if (defined('V7_OTP_LOADED')) return;
define('V7_OTP_LOADED', 1);

function v7_otp_generate(int $digits = 6): string
{
    return str_pad((string) random_int(0, (10 ** $digits) - 1), $digits, '0', STR_PAD_LEFT);
}

function v7_otp_hash(string $code): string
{
    return password_hash($code, PASSWORD_DEFAULT);
}

function v7_otp_verify(string $code, ?string $hash): bool
{
    return $hash && password_verify(trim($code), $hash);
}
