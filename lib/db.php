<?php
if (defined('V7_DB_LOADED')) return;
define('V7_DB_LOADED', 1);

function v7_db(array $C): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $d = $C['db'];
    $dsn = "mysql:host={$d['host']};port={$d['port']};dbname={$d['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 4,
    ]);
    return $pdo;
}
