<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

// -------------------------------------------------------
// Feature: Forgot Password (request form)
// -------------------------------------------------------
$sent  = false;
$error = '';
$step  = $_GET['step'] ?? 'request'; // request | reset

// ---- STEP 2: Reset with token ----
$token = $_GET['token'] ?? '';
if ($token && $step !== 'request') {
    // Show reset form or handle POST
}

// ---- STEP 1: Request reset ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$token) {
    csrf_abort();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $pdo  = db();
        $user = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $user->execute([$email]);
        $u = $user->fetch();

        if ($u) {
            // Invalidate old tokens
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ?")->execute([$email]);

            // Create new token (15-min expiry)
            $rawToken = bin2hex(random_bytes(32));
            $hash     = hash('sha256', $rawToken);
            $expiry   = date('Y-m-d H:i:s', time() + 900); // 15 minutes

            $pdo->prepare(
                "INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)"
            )->execute([$email, $hash, $expiry]);

            $resetUrl = APP_URL . '/password_reset.php?token=' . $rawToken;
            send_password_reset_email($u['email'], $u['name'], $resetUrl);

            audit_log('password_reset_request', $u['id'], $email);
        }

        // Always show success to prevent email enumeration
        $sent = true;
    }
}

// ---- Handle token-based reset POST ----
$resetSuccess = false;
$resetError   = '';
$tokenUser    = null;

if ($token) {
    $hash = hash('sha256', $token);
    $pdo  = db();
    $row  = $pdo->prepare(
        "SELECT pr.*, u.id AS user_id, u.name, u.email 
         FROM password_resets pr
         JOIN users u ON u.email = pr.email
         WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $row->execute([$hash]);
    $tokenUser = $row->fetch();

    if (!$tokenUser) {
        $resetError = 'Link reset tidak valid atau sudah kedaluwarsa. Silakan minta link baru.';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenUser) {
        csrf_abort();
        $pass1 = $_POST['password'] ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if (strlen($pass1) < PASSWORD_MIN_LENGTH) {
            $resetError = 'Password minimal ' . PASSWORD_MIN_LENGTH . ' karakter.';
        } elseif ($pass1 !== $pass2) {
            $resetError = 'Konfirmasi password tidak cocok.';
        } else {
            $newHash = password_hash($pass1, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $tokenUser['user_id']]);
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token_hash = ?")->execute([$hash]);
            audit_log('password_reset_complete', $tokenUser['user_id']);
            $resetSuccess = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Reset Password</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#2563eb') ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-slate-100 flex items-center justify-center p-4">

<div class="w-full max-w-md">

  <!-- Logo -->
  <div class="text-center mb-8">
    <div class="inline-flex items-center gap-3">
      <div class="w-10 h-10 bg-blue-600 rounded-2xl flex items-center justify-center rotate-12">
        <span class="material-symbols-outlined text-white text-[20px] -rotate-12" style="font-variation-settings:'FILL' 1;">medical_services</span>
      </div>
      <span class="text-2xl font-black text-slate-900">Care<span class="text-blue-600">Connect</span></span>
    </div>
  </div>

  <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">

    <?php if ($token): ?>
      <!-- STEP 2: Reset Form -->
      <div class="p-8">
        <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mb-5">
          <span class="material-symbols-outlined text-blue-600 text-[28px]">lock_reset</span>
        </div>

        <?php if ($resetSuccess): ?>
          <h1 class="text-2xl font-black text-slate-900 mb-2">Password Diperbarui!</h1>
          <p class="text-slate-500 mb-6">Password Anda berhasil diganti. Silakan masuk dengan password baru.</p>
          <a href="<?= APP_URL ?>/patient/login.php" class="flex items-center justify-center gap-2 w-full bg-blue-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-blue-700 transition-colors">
            <span class="material-symbols-outlined text-[18px]">login</span>
            Masuk Sekarang
          </a>
        <?php else: ?>
          <h1 class="text-2xl font-black text-slate-900 mb-2">Buat Password Baru</h1>
          <p class="text-slate-500 mb-6 text-sm">Masukkan password baru untuk akun <strong><?= $tokenUser ? e($tokenUser['email']) : '' ?></strong>.</p>

          <?= alert_html($resetError, 'error') ?>

          <?php if ($tokenUser): ?>
          <form method="POST" class="space-y-5">
            <?= csrf_field() ?>
            <div>
              <label class="block text-sm font-bold text-slate-700 mb-1.5">Password Baru</label>
              <input type="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>"
                     placeholder="Min. <?= PASSWORD_MIN_LENGTH ?> karakter"
                     class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
              <label class="block text-sm font-bold text-slate-700 mb-1.5">Konfirmasi Password</label>
              <input type="password" name="password2" required placeholder="Ulangi password baru"
                     class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
              <span class="material-symbols-outlined text-[18px]">save</span>
              Simpan Password Baru
            </button>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>

    <?php elseif ($sent): ?>
      <!-- Sent confirmation -->
      <div class="p-8 text-center">
        <div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-5">
          <span class="material-symbols-outlined text-green-500 text-[32px]">mark_email_read</span>
        </div>
        <h1 class="text-xl font-black text-slate-900 mb-2">Cek Email Anda</h1>
        <p class="text-slate-500 text-sm leading-relaxed mb-6">
          Jika email terdaftar, kami telah mengirimkan link reset password. Link berlaku <strong>15 menit</strong>.
        </p>
        <a href="password_reset.php" class="text-blue-600 font-bold text-sm hover:underline">Kirim Ulang</a>
      </div>

    <?php else: ?>
      <!-- STEP 1: Request form -->
      <div class="p-8">
        <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center mb-5">
          <span class="material-symbols-outlined text-blue-600 text-[28px]">key</span>
        </div>
        <h1 class="text-2xl font-black text-slate-900 mb-1">Lupa Password?</h1>
        <p class="text-slate-500 text-sm mb-6">Masukkan email Anda dan kami akan mengirimkan link untuk membuat password baru.</p>

        <?= alert_html($error, 'error') ?>

        <form method="POST" class="space-y-5">
          <?= csrf_field() ?>
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-1.5">Alamat Email</label>
            <input type="email" name="email" required autofocus
                   placeholder="email@contoh.com"
                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
          </div>
          <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-colors flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-[18px]">send</span>
            Kirim Link Reset
          </button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-6">
          Ingat password?
          <a href="<?= APP_URL ?>/patient/login.php" class="text-blue-600 font-bold hover:underline">Masuk</a>
        </p>
      </div>
    <?php endif; ?>

  </div>
</div>

</body>
</html>
