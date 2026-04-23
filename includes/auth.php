<?php
// ============================================================
//  CareConnect – Auth Library
// ============================================================

// -------------------------------------------------------
// 1.  Login
// -------------------------------------------------------
function attempt_login(string $email, string $password, string $role): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Rate limit check
    if (is_rate_limited($email, $ip)) {
        return ['success' => false, 'message' => 'Terlalu banyak percobaan login. Coba lagi dalam ' . LOGIN_LOCKOUT_MIN . ' menit.'];
    }

    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role, is_active
         FROM users WHERE email = ? AND role = ? LIMIT 1'
    );
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($email, $ip, false);
        audit_log('login_failed', null, $email);
        return ['success' => false, 'message' => 'Email atau kata sandi salah.'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Akun Anda dinonaktifkan. Hubungi administrator.'];
    }

    record_login_attempt($email, $ip, true);

    // Rehash if needed
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$newHash, $user['id']]);
    }

    return ['success' => true, 'user' => $user];
}

// -------------------------------------------------------
// 2.  Session Creation (call after successful auth)
// -------------------------------------------------------
function create_auth_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['logged_in']  = true;
    $_SESSION['_last_active'] = time();
    audit_log('login_success', (int) $user['id']);
}

// -------------------------------------------------------
// 3.  Logout
// -------------------------------------------------------
function logout(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    audit_log('logout', $uid);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// -------------------------------------------------------
// 4.  Access guards
// -------------------------------------------------------
function require_login(): void
{
    if (empty($_SESSION['logged_in'])) {
        // Detect role from URL path to redirect properly
        $path = $_SERVER['REQUEST_URI'] ?? '';
        if (str_contains($path, '/admin/')) {
            header('Location: ' . APP_URL . '/admin/login.php');
        } elseif (str_contains($path, '/doctor/')) {
            header('Location: ' . APP_URL . '/doctor/login.php');
        } else {
            header('Location: ' . APP_URL . '/patient/login.php');
        }
        exit;
    }
    // Timeout check
    if (isset($_SESSION['_last_active']) && (time() - $_SESSION['_last_active'] > SESSION_LIFETIME)) {
        logout();
        header('Location: ' . APP_URL . '/landing.php?timeout=1');
        exit;
    }
    $_SESSION['_last_active'] = time();
}

function require_role(string ...$roles): void
{
    require_login();
    if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        http_response_code(403);
        die('<h1>403 – Akses Ditolak</h1><p>Anda tidak memiliki izin untuk halaman ini.</p>');
    }
}

// -------------------------------------------------------
// 5.  Current user helpers
// -------------------------------------------------------
function current_user(): array
{
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role']  ?? '',
    ];
}

function is_logged_in(): bool
{
    return !empty($_SESSION['logged_in']);
}

// -------------------------------------------------------
// 6.  Patient registration
// -------------------------------------------------------
function register_patient(string $name, string $email, string $password): array
{
    $pdo = db();

    // Duplicate check
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email sudah terdaftar.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash, 'patient']);
        $userId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'INSERT INTO patient_profiles (user_id) VALUES (?)'
        )->execute([$userId]);

        $pdo->commit();
        audit_log('register', $userId);
        return ['success' => true, 'user_id' => $userId];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Register error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal mendaftar. Coba lagi.'];
    }
}

function register_admin(string $name, string $email, string $password): array
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email sudah terdaftar.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$name, $email, $hash, 'admin']);
        $userId = (int) $pdo->lastInsertId();

        audit_log('register_admin', $userId);
        return ['success' => true, 'user_id' => $userId];
    } catch (Exception $e) {
        error_log('Admin register error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal mendaftar. Coba lagi.'];
    }
}
