<?php
// Simple file-bucket rate limiter — same pattern as Guardian's own
// lib/rate_limit.php. No DB round-trip, no extension, works on shared
// hosting: one small counter file per IP per time window.
if (defined('V7_RATE_LIMIT_LOADED')) return;
define('V7_RATE_LIMIT_LOADED', 1);

function v7_rate_exceeded(array $C, string $bucket, int $max = 10, int $window = 60): bool
{
    $dir = rtrim($C['log_dir'] ?? sys_get_temp_dir(), '/') . '/rate';
    if (!@is_dir($dir)) { @mkdir($dir, 0770, true); }

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $slot = (int) floor(time() / $window);
    $file = $dir . '/' . md5($bucket . '_' . $ip) . '_' . $slot;

    $n = 0;
    $fh = @fopen($file, 'c+');
    if ($fh) {
        @flock($fh, LOCK_EX);
        $n = (int) fgets($fh) + 1;
        @ftruncate($fh, 0); @rewind($fh); @fwrite($fh, (string) $n);
        @flock($fh, LOCK_UN); @fclose($fh);
        @unlink($dir . '/' . md5($bucket . '_' . $ip) . '_' . ($slot - 2));
    }
    return $n > $max;
}
