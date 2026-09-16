<?php
$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') v7_error('GET only', 405);

$db = v7_db($C);
v7_require_role($db, 'admin');

$rows = $db->query('SELECT u.id, u.email, r.name AS role, u.totp_enabled, u.email_otp_enabled, u.created_at
    FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.id ASC')->fetchAll();

v7_json(['ok' => true, 'users' => $rows]);
