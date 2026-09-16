<?php
if (defined('V7_MAILER_LOADED')) return;
define('V7_MAILER_LOADED', 1);

function v7_smtp_send(array $s, string $to, string $subject, string $html): bool
{
    if (empty($s['host'])) return false;
    try {
        $host = ($s['secure'] === 'ssl' ? 'ssl://' : '') . $s['host'];
        $fp = @stream_socket_client("$host:{$s['port']}", $errno, $errstr, $s['timeout'] ?? 8, STREAM_CLIENT_CONNECT);
        if (!$fp) return false;
        stream_set_timeout($fp, $s['timeout'] ?? 8);

        $read = function () use ($fp) {
            $data = '';
            while ($line = fgets($fp, 515)) {
                $data .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
            return $data;
        };
        $cmd = function ($c) use ($fp, $read) { fwrite($fp, "$c\r\n"); return $read(); };

        $read();
        $ehlo = 'EHLO ' . $s['host'];
        $cmd($ehlo);
        if ($s['secure'] === 'tls') {
            $cmd('STARTTLS');
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
            $cmd($ehlo);
        }
        $cmd('AUTH LOGIN');
        $cmd(base64_encode($s['user']));
        $r = $cmd(base64_encode($s['pass']));
        if (strpos($r, '235') === false) { fclose($fp); return false; }

        $from = $s['from'] ?: $s['user'];
        $fromName = $s['from_name'] ?? 'v7Security';
        $cmd("MAIL FROM:<$from>");
        foreach (array_map('trim', explode(',', $to)) as $rcpt) $cmd("RCPT TO:<$rcpt>");
        $cmd('DATA');
        $msg = "From: $fromName <$from>\r\nTo: $to\r\nSubject: $subject\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n"
            . str_replace("\n.", "\n..", $html) . "\r\n.";
        $r = $cmd($msg);
        $cmd('QUIT');
        fclose($fp);
        return strpos($r, '250') !== false;
    } catch (\Throwable $e) {
        return false;
    }
}

function v7_send_otp_email(array $C, string $to, string $code, int $ttlMinutes): bool
{
    $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $html = "<!doctype html>
<html><body style='margin:0;padding:0;background:#eef1f6;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;'>
<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background:#eef1f6;padding:24px 0;'>
<tr><td align='center'>
<table role='presentation' width='420' cellpadding='0' cellspacing='0' style='max-width:420px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.08);'>
  <tr><td style='background:#4f7cff;padding:22px 28px;'>
    <div style='color:#fff;font-size:18px;font-weight:800;'>&#128274; Your login code</div>
  </td></tr>
  <tr><td style='padding:28px;text-align:center;'>
    <div style='font-size:36px;font-weight:800;letter-spacing:8px;color:#1f2733;margin-bottom:6px;'>" . $h($code) . "</div>
    <div style='font-size:13px;color:#8a94a6;'>Valid for {$ttlMinutes} minutes. Use it to finish signing in.</div>
  </td></tr>
  <tr><td style='padding:0 28px 24px;'>
    <div style='background:#f4f6fa;border-radius:8px;padding:12px 14px;font-size:12px;color:#8a94a6;line-height:1.6;'>
      If you didn't try to sign in, you can ignore this email — nothing happens until the correct code is entered.
    </div>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>";

    return v7_smtp_send($C['smtp'], $to, 'Your login code: ' . $code, $html);
}
