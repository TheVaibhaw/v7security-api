<?php
// POST -> save a log entry (api key only — machine-to-machine, e.g. Guardian)
// GET  -> list/filter logs (api key + logged-in user — for viewing in a dashboard)
$C = require __DIR__ . '/../lib/bootstrap.php';
$db = v7_db($C);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = v7_body();
    $site = substr((string) ($body['site'] ?? ''), 0, 64);
    $type = substr((string) ($body['type'] ?? ''), 0, 64);
    $message = (string) ($body['message'] ?? '');
    $meta = isset($body['meta']) ? json_encode($body['meta'], JSON_UNESCAPED_SLASHES) : null;
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

    $stmt = $db->prepare('INSERT INTO logs (site, type, message, meta, ip) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$site, $type, $message, $meta, $ip]);

    v7_file_log($C, 'logs', ['site' => $site, 'type' => $type, 'message' => $message, 'meta' => $body['meta'] ?? null, 'ip' => $ip]);

    v7_json(['ok' => true, 'id' => (int) $db->lastInsertId()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    v7_require_user($db);

    $where = [];
    $params = [];
    if (($site = $_GET['site'] ?? '') !== '') { $where[] = 'site = ?'; $params[] = $site; }
    if (($type = $_GET['type'] ?? '') !== '') { $where[] = 'type = ?'; $params[] = $type; }
    if (($from = $_GET['from'] ?? '') !== '') { $where[] = 'created_at >= ?'; $params[] = $from; }
    if (($to = $_GET['to'] ?? '') !== '') { $where[] = 'created_at <= ?'; $params[] = $to; }
    $sql = 'SELECT * FROM logs' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 200';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    v7_json(['ok' => true, 'logs' => $stmt->fetchAll()]);
}

v7_error('method not allowed', 405);
