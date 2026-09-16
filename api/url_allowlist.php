<?php
$C = require __DIR__ . '/../lib/bootstrap.php';
$db = v7_db($C);
$user = v7_require_role($db, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $db->query('SELECT id, site, pattern, created_by, created_at FROM url_allowlist ORDER BY id DESC')->fetchAll();
    v7_json(['ok' => true, 'entries' => $rows]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = v7_body();

    if (isset($body['id']) && !isset($body['pattern'])) {
        $id = (int) $body['id'];
        $row = $db->prepare('SELECT site, pattern FROM url_allowlist WHERE id = ?');
        $row->execute([$id]);
        $entry = $row->fetch();
        if (!$entry) v7_error('entry not found', 404);

        $db->prepare('DELETE FROM url_allowlist WHERE id = ?')->execute([$id]);
        v7_file_log($C, 'url_allowlist', ['action' => 'remove', 'id' => $id, 'site' => $entry['site'], 'pattern' => $entry['pattern'], 'by' => $user['email']]);
        v7_json(['ok' => true, 'removed' => $id]);
    }

    $site = trim((string) ($body['site'] ?? '*')) ?: '*';
    $pattern = trim((string) ($body['pattern'] ?? ''));
    if ($pattern === '') v7_error('pattern required');
    $pattern = ltrim($pattern, '/');

    $stmt = $db->prepare('INSERT INTO url_allowlist (site, pattern, created_by) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE created_by = VALUES(created_by), created_at = NOW()');
    $stmt->execute([$site, $pattern, $user['email']]);

    v7_file_log($C, 'url_allowlist', ['action' => 'add', 'site' => $site, 'pattern' => $pattern, 'by' => $user['email']]);
    v7_json(['ok' => true, 'id' => (int) $db->lastInsertId()]);
}

v7_error('method not allowed', 405);
