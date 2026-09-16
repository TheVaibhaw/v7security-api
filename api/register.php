<?php
require __DIR__ . '/../lib/rate_limit.php';

$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') v7_error('POST only', 405);
if (v7_rate_exceeded($C, 'register', 5, 60)) v7_error('too many attempts, try again shortly', 429);

$body = v7_body();
$email = strtolower(trim((string) ($body['email'] ?? '')));
$password = (string) ($body['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) v7_error('invalid email');
if (strlen($password) < 8) v7_error('password must be at least 8 characters');

$db = v7_db($C);
$exists = $db->prepare('SELECT id FROM users WHERE email = ?');
$exists->execute([$email]);
if ($exists->fetch()) v7_error('an account with this email already exists', 409);

$stmt = $db->prepare('INSERT INTO users (email, password_hash, role_id) VALUES (?, ?, 2)');
$stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);

v7_json(['ok' => true, 'user_id' => (int) $db->lastInsertId()]);
