<?php
if (defined('V7_AUTH_LOADED')) return;
define('V7_AUTH_LOADED', 1);

function v7_new_token(): string
{
    return bin2hex(random_bytes(32));
}

/** Creates a real session token + row, returns the plain token string. */
function v7_issue_token(PDO $db, array $C, int $userId): string
{
    $token = v7_new_token();
    $days = (int) ($C['token_ttl_days'] ?? 30);
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $db->prepare('INSERT INTO tokens (token, user_id, ip, user_agent, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))')
        ->execute([$token, $userId, $ip, $ua, $days]);
    return $token;
}

/** Returns the logged-in user row for a bearer token, or null. Also drops expired tokens. */
function v7_user_from_token(PDO $db, string $token): ?array
{
    if ($token === '') return null;
    $stmt = $db->prepare('SELECT u.id, u.email, u.role_id, r.name AS role, t.id AS token_id, t.expires_at
        FROM tokens t JOIN users u ON u.id = t.user_id JOIN roles r ON r.id = u.role_id
        WHERE t.token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) return null;
    if (strtotime($row['expires_at']) < time()) {
        $db->prepare('DELETE FROM tokens WHERE token = ?')->execute([$token]);
        return null;
    }
    return $row;
}

function v7_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) return $m[1];
    return $_GET['token'] ?? $_POST['token'] ?? (v7_body()['token'] ?? '');
}

/** Require a valid, non-expired token. Exits with 401 if missing/invalid. */
function v7_require_user(PDO $db): array
{
    $user = v7_user_from_token($db, v7_bearer_token());
    if (!$user) v7_error('invalid or expired token', 401);
    return $user;
}

function v7_require_role(PDO $db, string $role): array
{
    $user = v7_require_user($db);
    if ($user['role'] !== $role) v7_error('forbidden: requires ' . $role . ' role', 403);
    return $user;
}
