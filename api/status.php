<?php
// GET -> system health: DB reachability + basic counts. Api-key only, no user
// login required, so it can be used as a plain uptime/health check.
$C = require __DIR__ . '/../lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') v7_error('GET only', 405);

$out = ['ok' => true, 'checked_at' => date('c'), 'db' => false];

try {
    $db = v7_db($C);
    $out['db'] = true;
    $out['users'] = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $out['active_sessions'] = (int) $db->query('SELECT COUNT(*) FROM tokens WHERE expires_at > NOW()')->fetchColumn();
    $out['logins_24h'] = (int) $db->query("SELECT COUNT(*) FROM login_activity WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
    $out['failed_logins_24h'] = (int) $db->query("SELECT COUNT(*) FROM login_activity WHERE status IN ('failed_password','2fa_failed') AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
} catch (\Throwable $e) {
    $out['ok'] = false;
    $out['db_error'] = 'unreachable';
}

$out['log_dir_writable'] = is_dir($C['log_dir'] ?? '') || @mkdir($C['log_dir'] ?? '', 0770, true);

v7_json($out);
