<?php
// Every DB write that matters (logs, login activity) also gets appended here
// as JSON-lines, so a record survives even if the DB write itself fails —
// same belt-and-braces pattern Guardian's own lib/store.php uses.
if (defined('V7_FILE_LOG_LOADED')) return;
define('V7_FILE_LOG_LOADED', 1);

function v7_file_log(array $C, string $stream, array $row): void
{
    $dir = $C['log_dir'] ?? null;
    if (!$dir) return;
    if (!is_dir($dir)) { @mkdir($dir, 0770, true); }
    $row['logged_at'] = $row['logged_at'] ?? date('c');
    @file_put_contents(
        rtrim($dir, '/') . '/' . preg_replace('/[^a-z0-9_]/i', '', $stream) . '.jsonl',
        json_encode($row, JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
}
