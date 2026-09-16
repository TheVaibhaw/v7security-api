<?php
// Runs first on every endpoint so a caller on a *different* server (Guardian,
// or anything else) never gets blocked by the browser's CORS check. This is
// not the real access control — the X-Api-Key check that follows is — CORS
// only decides whether a browser is allowed to read the response.
if (defined('V7_CORS_LOADED')) return;
define('V7_CORS_LOADED', 1);

function v7_cors(array $C): void
{
    $origin = $C['cors_origin'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
