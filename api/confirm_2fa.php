<?php
// Step 2 of turning on 2FA: caller proves they can generate a valid code
// from the secret before it actually gets enabled on the account.
require __DIR__ . '/../lib/totp.php';

$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') v7_error('POST only', 405);

$db = v7_db($C);
$user = v7_require_user($db);
$body = v7_body();
$code = (string) ($body['code'] ?? '');

$stmt = $db->prepare('SELECT totp_secret FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$secret = $stmt->fetchColumn();

if (!$secret) v7_error('call enable_2fa first');
if (!v7_totp_verify($secret, $code)) v7_error('invalid code', 401);

$db->prepare('UPDATE users SET totp_enabled = 1 WHERE id = ?')->execute([$user['id']]);
v7_json(['ok' => true, 'totp_enabled' => true]);
