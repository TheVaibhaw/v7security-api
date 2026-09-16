<?php
// Accepts EITHER a TOTP app code OR an emailed OTP code — whichever the
// account has enabled. If both are enabled, either one is accepted.
require __DIR__ . '/../lib/totp.php';
require __DIR__ . '/../lib/otp.php';
require __DIR__ . '/../lib/activity.php';
require __DIR__ . '/../lib/rate_limit.php';

$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') v7_error('POST only', 405);
if (v7_rate_exceeded($C, 'verify_2fa', 10, 60)) v7_error('too many attempts, try again shortly', 429);

$db = v7_db($C);
$body = v7_body();
$tempToken = (string) ($body['temp_token'] ?? '');
$code = (string) ($body['code'] ?? '');

$stmt = $db->prepare('SELECT p.user_id, p.expires_at, p.otp_code_hash, u.email, u.totp_secret, u.totp_enabled, r.name AS role
    FROM pending_2fa p JOIN users u ON u.id = p.user_id JOIN roles r ON r.id = u.role_id
    WHERE p.temp_token = ?');
$stmt->execute([$tempToken]);
$row = $stmt->fetch() ?: null;

if (!$row || strtotime($row['expires_at']) < time()) v7_error('expired or invalid 2FA session', 401);

$validTotp = $row['totp_enabled'] && v7_totp_verify($row['totp_secret'], $code);
$validOtp = $row['otp_code_hash'] && v7_otp_verify($code, $row['otp_code_hash']);

if (!$validTotp && !$validOtp) {
    v7_log_activity($db, $C, $row['user_id'], $row['email'], '2fa_failed');
    v7_error('invalid code', 401);
}

$db->prepare('DELETE FROM pending_2fa WHERE temp_token = ?')->execute([$tempToken]);

$token = v7_issue_token($db, $C, $row['user_id']);
v7_log_activity($db, $C, $row['user_id'], $row['email'], '2fa_success');

v7_json(['ok' => true, 'token' => $token, 'role' => $row['role'], 'email' => $row['email']]);
