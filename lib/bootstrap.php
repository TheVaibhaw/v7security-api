<?php
// Every endpoint starts with: $C = require __DIR__ . '/../lib/bootstrap.php';
// Handles CORS, config load, and the mandatory X-Api-Key check in one place —
// no endpoint can accidentally skip one of these.
require __DIR__ . '/response.php';
require __DIR__ . '/cors.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/file_log.php';

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'not configured']);
    exit;
}
$C = require $configFile;
v7_cors($C);
v7_require_api_key($C);
return $C;
