<?php
// Step 1 of turning on 2FA: caller must already be logged in (valid token).
// Generates a secret, stores it un-confirmed, and returns the otpauth:// URL
// to scan. Nothing takes effect until confirm_2fa.php verifies a real code.
require __DIR__ . '/../lib/totp.php';

$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') v7_error('POST only', 405);

$db = v7_db($C);
$user = v7_require_user($db);

$secret = v7_totp_generate_secret();
$db->prepare('UPDATE users SET totp_secret = ?, totp_enabled = 0 WHERE id = ?')->execute([$secret, $user['id']]);

v7_json(['ok' => true, 'secret' => $secret, 'otpauth_url' => v7_totp_otpauth_url($secret, $user['email'])]);
