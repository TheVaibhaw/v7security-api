<?php
// GET -> login activity. Regular users see only their own rows; admins can
// see everyone's (optionally filtered by email) — same filter shape as logs.php.
$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') v7_error('GET only', 405);

$db = v7_db($C);
$user = v7_require_user($db);

$where = [];
$params = [];

if ($user['role'] !== 'admin') {
    $where[] = 'user_id = ?';
    $params[] = $user['id'];
} elseif (($email = $_GET['email'] ?? '') !== '') {
    $where[] = 'email = ?';
    $params[] = $email;
}
if (($status = $_GET['status'] ?? '') !== '') { $where[] = 'status = ?'; $params[] = $status; }
if (($from = $_GET['from'] ?? '') !== '') { $where[] = 'created_at >= ?'; $params[] = $from; }
if (($to = $_GET['to'] ?? '') !== '') { $where[] = 'created_at <= ?'; $params[] = $to; }

$sql = 'SELECT id, user_id, email, ip, user_agent, status, created_at FROM login_activity'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);
v7_json(['ok' => true, 'activity' => $stmt->fetchAll()]);
