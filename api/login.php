<?php
require __DIR__ . '/../lib/activity.php';
require __DIR__ . '/../lib/otp.php';
require __DIR__ . '/../lib/mailer.php';
require __DIR__ . '/../lib/rate_limit.php';

$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') v7_error('POST only', 405);
if (v7_rate_exceeded($C, 'login', 10, 60)) v7_error('too many attempts, try again shortly', 429);

$body = v7_body();
$email = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

$db = v7_db($C);
$stmt = $db->prepare('SELECT u.id, u.email, u.password_hash, u.totp_enabled, u.totp_secret, u.email_otp_enabled, r.name AS role
    FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch() ?: null;

// Constant-time-ish: always run password_verify, even against a dummy hash
// when the email doesn't exist, so a bad email doesn't respond faster than
// a bad password.
$dummyHash = '$2y$10$7EqJtq98hPqEX7fNZaFWoOeeTdKnhSPqEX7fNZaFWoOeeTdKnhSPq';
$validPassword = password_verify($password, $user['password_hash'] ?? $dummyHash);
if (!$user || !$validPassword) {
    v7_log_activity($db, $C, $user['id'] ?? null, $email, 'failed_password');
    v7_error('invalid email or password', 401);
}

if ($user['totp_enabled'] || $user['email_otp_enabled']) {
    $temp = v7_new_token();
    $ttl = (int) ($C['pending_2fa_ttl_minutes'] ?? 5);
    $otpHash = null;

    if ($user['email_otp_enabled']) {
        $code = v7_otp_generate();
        $otpHash = v7_otp_hash($code);
        $ttl = (int) ($C['email_otp_ttl_minutes'] ?? 15);
        v7_send_otp_email($C, $user['email'], $code, $ttl);
    }

    $db->prepare('INSERT INTO pending_2fa (temp_token, user_id, otp_code_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))')
        ->execute([$temp, $user['id'], $otpHash, $ttl]);

    v7_log_activity($db, $C, $user['id'], $email, '2fa_required');
    v7_json(['ok' => true, 'need_2fa' => true, 'temp_token' => $temp, 'email_otp_sent' => (bool) $user['email_otp_enabled']]);
}

$token = v7_issue_token($db, $C, $user['id']);
v7_log_activity($db, $C, $user['id'], $email, 'success');

v7_json(['ok' => true, 'token' => $token, 'role' => $user['role'], 'email' => $user['email']]);
