<?php
$C = require __DIR__ . '/../lib/bootstrap.php';

$db = v7_db($C);
$user = v7_require_user($db);

v7_json(['ok' => true, 'user' => ['id' => (int) $user['id'], 'email' => $user['email'], 'role' => $user['role']]]);
