<?php
// GET  -> list the caller's own active (non-expired) sessions
// POST -> revoke a session by id ({"id": 123}), own sessions only
$C = require __DIR__ . '/../lib/bootstrap.php';
$db = v7_db($C);
$user = v7_require_user($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT id, ip, user_agent, created_at, expires_at
        FROM tokens WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);
    $sessions = $stmt->fetchAll();
    foreach ($sessions as &$s) $s['current'] = ((int) $s['id'] === (int) $user['token_id']);
    v7_json(['ok' => true, 'sessions' => $sessions]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = v7_body();
    $id = (int) ($body['id'] ?? 0);
    if (!$id) v7_error('id required');
    $db->prepare('DELETE FROM tokens WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
    v7_json(['ok' => true, 'revoked' => $id]);
}

v7_error('method not allowed', 405);
