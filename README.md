# v7security-api

Standalone auth/logs backend for `v7security.vaibhawkumar.in`. Register,
login, 2FA (TOTP app codes **or** emailed OTP codes), roles, active-session
management, login-activity tracking, and a generic logs API — everything
over plain HTTPS + JSON, no framework, no extension beyond `pdo_mysql`
(already bundled with PHP). CORS is enabled on every endpoint so it works
correctly whether this API and Guardian end up on the same server or
completely separate ones — the actual access control is always the
`X-Api-Key` header, never the browser's CORS check.

Tested end-to-end locally against a real MySQL database — register, login
(plain / TOTP 2FA / emailed-OTP 2FA / both together), verify_2fa (both code
types, including a wrong-code rejection), enable/confirm TOTP,
enable/disable email OTP, roles (including the admin-only guard and the
idempotent-update edge case), logs (save/filter + file dual-write),
sessions (list/revoke, including self-revoke), activity (self-scoped and
admin-scoped), status, and every api-key/token negative case — all
confirmed working, then all test artifacts (test DB, local config, log
files) removed.

## 1. Deploy

Upload the whole `v7security-api/` folder to the subdomain's docroot. Copy
`config.example.php` → `config.php` and fill in:

```php
'api_key' => '...',          // php -r "echo bin2hex(random_bytes(32));"
'db' => ['host' => '...', 'user' => '...', 'pass' => '...', 'database' => '...'],
'smtp' => ['host' => '...', 'user' => '...', 'pass' => '...', ...], // for emailed OTP codes
```

Then import the schema once:
```bash
mysql -u <user> -p <database> < db/schema.sql
```

`config.php` and everything in `lib/`/`db/` are blocked from direct web
access via `.htaccess` — confirm `AllowOverride` is on for Apache, or add
equivalent `location` blocks if this ends up on nginx.

Logs are written to `log_dir` (default: `logs/` inside this folder) as
JSON-lines, *in addition to* the database row, for every log/activity
event — so nothing is lost even if the DB is briefly unreachable. Make sure
that directory is writable and **not** publicly served (it isn't, by
default, since it's outside any URL any endpoint returns).

## 2. Endpoints

Every request needs header `X-Api-Key: <api_key>`. Authenticated endpoints
also need `Authorization: Bearer <token>` (or a `token` field/query param).

| Endpoint | Method | Needs | Does |
|---|---|---|---|
| `/api/register.php` | POST | api key | `{email, password}` → creates a `user`-role account |
| `/api/login.php` | POST | api key | `{email, password}` → `{token}`, or `{need_2fa, temp_token, email_otp_sent}` if TOTP or email-OTP is on |
| `/api/verify_2fa.php` | POST | api key | `{temp_token, code}` → `{token}` — accepts a TOTP app code **or** the emailed OTP code, whichever the account has enabled |
| `/api/enable_2fa.php` | POST | api key + token | → `{secret, otpauth_url}` — show as a QR code (app-based TOTP, step 1 of 2) |
| `/api/confirm_2fa.php` | POST | api key + token | `{code}` → turns TOTP on for real (step 2 of 2) |
| `/api/enable_email_otp.php` | POST | api key + token | `{enabled: true\|false}` → turns emailed-OTP 2FA on/off, no confirmation step needed |
| `/api/me.php` | GET | api key + token | → current user + role |
| `/api/roles.php` | GET | api key + token | → list of roles |
| `/api/roles.php` | POST | api key + admin token | `{user_id, role}` → reassigns a user's role |
| `/api/logs.php` | POST | api key | `{site, type, message, meta?}` → saves a log row (DB + file) |
| `/api/logs.php` | GET | api key + token | `?site=&type=&from=&to=` → filtered log list |
| `/api/sessions.php` | GET | api key + token | → this user's active (non-expired) sessions, with `current` flagged |
| `/api/sessions.php` | POST | api key + token | `{id}` → revokes one of this user's own sessions |
| `/api/activity.php` | GET | api key + token | `?status=&from=&to=` (`&email=` too, admins only) → login-attempt history; regular users see only their own rows, admins see everyone's |
| `/api/status.php` | GET | api key | → DB reachability, user/session counts, 24h login/failure counts, log-dir writability |

First account you register is a normal `user`. Promote it to `admin` once,
directly in the database:
```sql
UPDATE users SET role_id = 1 WHERE email = 'you@example.com';
```

### 2FA flow (either kind)

1. `login.php` — password is correct, but the account has TOTP and/or
   email-OTP enabled → returns `{need_2fa: true, temp_token, email_otp_sent}`
   instead of a real token. If email-OTP is on, the code is emailed
   immediately (15-minute validity by default, `email_otp_ttl_minutes` in
   config).
2. `verify_2fa.php` — call with `{temp_token, code}`. `code` can be either
   the 6-digit app code (TOTP) or the 6-digit emailed code — whichever
   matches is accepted. On success, returns a real session `{token}` and
   logs `2fa_success` to activity; a wrong code logs `2fa_failed` and the
   `temp_token` still expires on its own after the TTL either way.

Every attempt — success, wrong password, 2FA required, 2FA failed, 2FA
success — is written to `login_activity` (DB) and `logs/login_activity.jsonl`
(file), with who, from what IP/user-agent, and when.

## 3. Calling it from Guardian

Guardian's own `dashboard/login.php` still uses its own local
`admin_email`/`admin_password_hash` check — this API is separate and
additive, nothing in Guardian depends on it yet, deliberately. It's built to
be dropped in later without any rework: CORS is already open, all state
lives behind the `X-Api-Key` header (not IP or session affinity), so it
works identically whether Guardian and this API share a server or not. When
that connection is wanted, add a small `lib/v7_client.php` to Guardian that
POSTs to `login.php`/`logs.php` here with `curl`, the same fire-and-forget
pattern already used for `lib/mailer.php` and `lib/mysql_log.php`.
