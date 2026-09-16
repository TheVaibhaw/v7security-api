<?php
if (defined('V7_RESPONSE_LOADED')) return;
define('V7_RESPONSE_LOADED', 1);

function v7_json($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function v7_error(string $message, int $status = 400): never
{
    v7_json(['ok' => false, 'error' => $message], $status);
}

function v7_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : $_POST;
}

/** Every endpoint calls this first — rejects anything without the shared API key. */
function v7_require_api_key(array $C): void
{
    $sent = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!hash_equals($C['api_key'], $sent)) {
        v7_error('invalid or missing api key', 401);
    }
}
