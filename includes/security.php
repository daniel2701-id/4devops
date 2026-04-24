<?php
// ============================================================
//  CareConnect – Security Library
// ============================================================

// -------------------------------------------------------
// 1.  HTTP Security Headers
// -------------------------------------------------------
function send_security_headers(): void
{
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    // Prevent MIME sniffing
    header('X-Content-Type-Options: nosniff');
    // XSS protection (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');
    // Referrer
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // HSTS – force HTTPS for 1 year (production)
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    // Content-Security-Policy – allow Tailwind CDN + Google Fonts + Material Icons
    $csp  = "default-src 'self'; ";
    $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com; ";
    $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; ";
    $csp .= "font-src 'self' https://fonts.gstatic.com data:; ";
    $csp .= "img-src 'self' data: https:; ";
    $csp .= "connect-src 'self' https://cdn.tailwindcss.com; ";
    $csp .= "worker-src 'self' blob:; ";
    $csp .= "frame-ancestors 'none';";
    header('Content-Security-Policy: ' . $csp);
}

// -------------------------------------------------------
// 2.  Session Bootstrap
// -------------------------------------------------------
function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,                  // browser-session cookie
            'path'     => '/',
            'domain'   => '',
            'secure'   => true,               // TRUE karena pakai HTTPS
            'httponly' => true,               // no JS access
            'samesite' => 'Lax',             // Lax agar redirect antar halaman tidak putus session
        ]);
        session_start();

        // Rotate session ID every 20 minutes (CSRF / fixation protection)
        if (!isset($_SESSION['_last_regen'])) {
            $_SESSION['_last_regen'] = time();
        } elseif (time() - $_SESSION['_last_regen'] > 1200) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }

        // Check inactivity timeout
        if (isset($_SESSION['_last_active'])) {
            if (time() - $_SESSION['_last_active'] > SESSION_LIFETIME) {
                session_unset();
                session_destroy();
                secure_session_start();       // fresh session
                return;
            }
        }
        $_SESSION['_last_active'] = time();
    }
}

// -------------------------------------------------------
// 3.  CSRF Token
// -------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($token) && hash_equals(csrf_token(), $token);
}

function csrf_abort(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}

function csrf_abort_get(): void
{
    $token = $_GET['_csrf_token'] ?? '';
    if (empty($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}

// -------------------------------------------------------
// 4.  Rate Limiting / Brute-Force Protection
// -------------------------------------------------------
function record_login_attempt(string $identifier, string $ip, bool $success): void
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, ?)'
    );
    $stmt->execute([$identifier, $ip, $success ? 1 : 0]);

    // Clean up old records (> 24h) occasionally
    if (rand(1, 50) === 1) {
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 24 HOUR");
    }
}

function is_rate_limited(string $identifier, string $ip): bool
{
    $pdo  = db();
    $since = date('Y-m-d H:i:s', strtotime('-' . LOGIN_LOCKOUT_MIN . ' minutes'));

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE (identifier = ? OR ip_address = ?)
           AND success = 0
           AND attempted_at >= ?'
    );
    $stmt->execute([$identifier, $ip, $since]);
    return (int) $stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
}

// -------------------------------------------------------
// 5.  Input Sanitization helpers
// -------------------------------------------------------
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize_email(string $email): string
{
    return strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));
}

function sanitize_string(string $s, int $maxLen = 255): string
{
    return mb_substr(trim(strip_tags($s)), 0, $maxLen);
}

// -------------------------------------------------------
// 6.  Audit Log
// -------------------------------------------------------
function audit_log(string $action, ?int $userId = null, ?string $target = null): void
{
    try {
        $pdo  = db();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, target, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, $target, $ip, $ua]);
    } catch (Exception $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

// -------------------------------------------------------
// 7.  OTP Generation & Verification
// -------------------------------------------------------
function generate_otp(int $userId): string
{
    $otp    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash   = password_hash($otp, PASSWORD_BCRYPT, ['cost' => 10]);
    $expire = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRE_MIN . ' minutes'));

    $pdo = db();
    // Invalidate old OTPs
    $pdo->prepare('UPDATE admin_otps SET used = 1 WHERE user_id = ?')->execute([$userId]);
    // Insert new
    $pdo->prepare(
        'INSERT INTO admin_otps (user_id, otp_hash, expires_at) VALUES (?, ?, ?)'
    )->execute([$userId, $hash, $expire]);

    return $otp;
}

function verify_otp(int $userId, string $otp): bool
{
    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT id, otp_hash FROM admin_otps
         WHERE user_id = ? AND used = 0 AND expires_at > NOW()
         ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($otp, $row['otp_hash'])) {
        return false;
    }
    // Mark as used
    $pdo->prepare('UPDATE admin_otps SET used = 1 WHERE id = ?')->execute([$row['id']]);
    return true;
}

// -------------------------------------------------------
// 8.  Password Validation
// -------------------------------------------------------
function validate_password_strength(string $password): array
{
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Minimal ' . PASSWORD_MIN_LENGTH . ' karakter';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Minimal 1 huruf kapital';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Minimal 1 huruf kecil';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Minimal 1 angka';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Minimal 1 karakter spesial (!@#$%^&*)';
    }
    return $errors;
}
