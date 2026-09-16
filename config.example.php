<?php
return [
    // Shared secret every caller (including Guardian) must send as the
    // X-Api-Key header. Generate with: php -r "echo bin2hex(random_bytes(32));"
    'api_key' => 'REPLACE_WITH_RANDOM_SECRET',

    // Allow-listed origins for browser (CORS) calls. '*' means any server can
    // call this API — fine here because every request still needs the api_key
    // above; CORS alone was never the real access control.
    'cors_origin' => '*',

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'user'     => '',
        'pass'     => '',
        'database' => '',
    ],

    // Every log/activity row is written to the DB *and* appended here as
    // JSONL, so nothing is lost if the DB is briefly unreachable.
    'log_dir' => __DIR__ . '/logs',

    'smtp' => [
        'host'    => 'smtp.gmail.com',
        'port'    => 465,
        'secure'  => 'ssl',
        'user'    => '',
        'pass'    => '',
        'from'    => '',
        'from_name' => 'v7Security',
        'timeout' => 8,
    ],

    'token_ttl_days'          => 30,
    'pending_2fa_ttl_minutes' => 5,   // TOTP window to enter a 2FA app code
    'email_otp_ttl_minutes'   => 15,  // emailed OTP validity window
];
