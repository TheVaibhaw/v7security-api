<?php
// Turns on/off emailed-OTP 2FA for the logged-in user. Unlike TOTP this needs
// no confirmation step — the "proof" is that the caller already holds a valid
// session token, and every future login still requires the mailbox anyway.
$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') v7_error('POST only', 405);

$db = v7_db($C);
$user = v7_require_user($db);
$body = v7_body();
$enabled = !empty($body['enabled']) ? 1 : 0;

$db->prepare('UPDATE users SET email_otp_enabled = ? WHERE id = ?')->execute([$enabled, $user['id']]);

v7_json(['ok' => true, 'email_otp_enabled' => (bool) $enabled]);
