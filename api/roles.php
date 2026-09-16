<?php
// GET  -> list available roles (any logged-in user)
// POST -> assign a role to a user_id (admin only)
$C = require __DIR__ . '/../lib/bootstrap.php';
$db = v7_db($C);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    v7_require_user($db);
    $roles = $db->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();
    v7_json(['ok' => true, 'roles' => $roles]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    v7_require_role($db, 'admin');
    $body = v7_body();
    $userId = (int) ($body['user_id'] ?? 0);
    $roleName = (string) ($body['role'] ?? '');

    $role = $db->prepare('SELECT id FROM roles WHERE name = ?');
    $role->execute([$roleName]);
    $roleId = $role->fetchColumn();
    if (!$roleId) v7_error('unknown role');
    if (!$userId) v7_error('user_id required');

    // Check existence separately — UPDATE's affected-row count is 0 both when
    // the user is missing AND when the role was already set to this value,
    // so it can't be used alone to tell "not found" from "no-op".
    $exists = $db->prepare('SELECT id FROM users WHERE id = ?');
    $exists->execute([$userId]);
    if (!$exists->fetchColumn()) v7_error('user not found', 404);

    $db->prepare('UPDATE users SET role_id = ? WHERE id = ?')->execute([$roleId, $userId]);

    v7_json(['ok' => true]);
}

v7_error('method not allowed', 405);
