<?php
// Records one row per login attempt (any outcome) — DB + file, always both.
if (defined('V7_ACTIVITY_LOADED')) return;
define('V7_ACTIVITY_LOADED', 1);

function v7_log_activity(PDO $db, array $C, ?int $userId, string $email, string $status): void
{
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $db->prepare('INSERT INTO login_activity (user_id, email, ip, user_agent, status) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $email, $ip, $ua, $status]);

    v7_file_log($C, 'login_activity', [
        'user_id' => $userId,
        'email'   => $email,
        'ip'      => $ip,
        'user_agent' => $ua,
        'status'  => $status,
    ]);
}
