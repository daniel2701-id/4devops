<?php
// ============================================================
//  CareConnect – Application Configuration
// ============================================================

// ----- Environment -------------------------------------------
define('APP_ENV',     'development');   // 'production' in prod
define('APP_NAME',    'CareConnect');
define('APP_URL',     'http://localhost/careconnect/public');
define('APP_VERSION', '1.0.0');

// ----- Database ----------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'careconnect');
define('DB_USER', 'root');        // change in production
define('DB_PASS', '');            // change in production
define('DB_CHARSET', 'utf8mb4');

// ----- Security ----------------------------------------------
define('SESSION_NAME',         'CCSS');          // custom session name
define('SESSION_LIFETIME',     1800);            // 30 minutes
define('CSRF_TOKEN_LENGTH',    32);
define('MAX_LOGIN_ATTEMPTS',   5);
define('LOGIN_LOCKOUT_MIN',    15);              // lockout window in minutes
define('OTP_EXPIRE_MIN',       10);             // OTP validity

// Password policy
define('PASSWORD_MIN_LENGTH',  8);

// ----- Paths -------------------------------------------------
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');

// ----- Mail (use your SMTP in production) --------------------
define('MAIL_FROM',  'noreply@careconnect.id');
define('MAIL_NAME',  'CareConnect');

// ----- Error display -----------------------------------------
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ----- Timezone ----------------------------------------------
date_default_timezone_set('Asia/Jakarta');
