<?php
require_once __DIR__ . '/../../includes/functions.php';

if (is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

$error   = '';
$success = '';
$tab     = $_GET['tab'] ?? 'login';

$isGated = empty($_SESSION['admin_gate_passed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    
    if ($isGated) {
        $otp = $_POST['gate_code'] ?? '';
        if ($otp === '110605') {
            $_SESSION['admin_gate_passed'] = true;
            header('Location: login.php');
            exit;
        } else {
            $error = 'Kode verifikasi sistem tidak valid.';
        }
    } else {
        $tab = $_POST['tab'] ?? 'login';

        if ($tab === 'login') {
            $email    = sanitize_email($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Isi semua kolom.';
            } else {
                $result = attempt_login($email, $password, 'admin');
                if ($result['success']) {
                    create_auth_session($result['user']);
                    audit_log('login', (int) $result['user']['id']);
                    header('Location: ' . APP_URL . '/admin/dashboard.php');
                    exit;
                }
                $error = $result['message'];
            }
        } elseif ($tab === 'register') {
            $name     = sanitize_string($_POST['name'] ?? '', 150);
            $email    = sanitize_email($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm_password'] ?? '';

            $passErrors = validate_password_strength($password);
            if (empty($name) || empty($email) || empty($password)) {
                $error = 'Isi semua kolom.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Format email tidak valid.';
            } elseif ($password !== $confirm) {
                $error = 'Konfirmasi kata sandi tidak cocok.';
            } elseif (!empty($passErrors)) {
                $error = 'Kata sandi: ' . implode(', ', $passErrors) . '.';
            } else {
                $result = register_admin($name, $email, $password);
                if ($result['success']) {
                    $success = 'Pendaftaran admin berhasil! Silakan masuk.';
                    $tab = 'login';
                } else {
                    $error = $result['message'];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Admin Portal</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#0f766e') ?>  <!-- teal primary for admin -->
<?= google_fonts() ?>
<style>
  body { font-family: 'Inter', sans-serif; }
  .tab-active { background: #0f766e; color: white !important; }
</style>
</head>
<body class="bg-slate-950 text-white antialiased min-h-screen">

<div class="min-h-screen flex w-full">

  <!-- Left Panel -->
  <div class="hidden md:flex md:w-1/2 relative overflow-hidden flex-col justify-between p-12 bg-slate-900">
    <div class="absolute inset-0" style="background:linear-gradient(160deg,#134e4a 0%,#0c1a19 100%);"></div>
    <div class="absolute inset-0 opacity-5" style="background:repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%);background-size:12px 12px;"></div>

    <div class="relative z-10 flex items-center gap-3">
      <div class="w-12 h-12 bg-teal-500/20 flex items-center justify-center rounded-xl transform rotate-45 shadow-lg border border-teal-500/30">
        <span class="material-symbols-outlined text-teal-400 transform -rotate-45 text-2xl" style="font-variation-settings:'FILL' 1;">medical_services</span>
      </div>
      <span class="text-2xl font-black text-white tracking-tight">MediCare<span class="text-teal-400"> Pro</span></span>
    </div>

    <div class="relative z-10 max-w-md mt-auto mb-10">
      <h1 class="text-3xl font-black text-white mb-4 leading-tight">Secure Access for Healthcare Administrators.</h1>
      <p class="text-slate-400 text-sm font-medium mb-8">Streamline your clinic's operations, manage staff securely, and maintain compliance.</p>
    </div>

    <div class="relative z-10 text-slate-500 text-xs font-medium">© 2026 CareConnect Systems. All rights reserved.</div>
  </div>

  <!-- Right Panel -->
  <div class="w-full md:w-1/2 flex flex-col items-center pt-16 justify-start p-6 lg:p-12 bg-slate-950">
    <div class="w-full max-w-[420px]">

      <a href="<?= APP_URL ?>/landing.php" class="flex items-center gap-1 text-slate-500 hover:text-teal-400 transition-colors mb-8 text-sm font-medium group">
        <span class="material-symbols-outlined group-hover:-translate-x-1 transition-transform text-[20px]">arrow_back</span>
        Kembali
      </a>

      <div class="mb-6">
        <h2 class="text-2xl font-black text-white mb-1"><?= $isGated ? 'System Security Gate' : 'Admin Portal' ?></h2>
        <p class="text-sm font-medium text-slate-400">
          <?php
          if ($isGated) {
              echo 'Enter the administrative verification code to continue.';
          } else {
              echo $tab === 'login' ? 'Sign in to your account.' : 'Create a new admin account.';
          }
          ?>
        </p>
      </div>

      <?= alert_html($error, 'error') ?>
      <?= alert_html($success, 'success') ?>

      <?php if ($isGated): ?>
      <!-- Gate Form -->
      <form method="POST" class="flex flex-col gap-5">
        <?= csrf_field() ?>
        
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Verification Code</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">pin</span>
            <input type="password" name="gate_code" placeholder="••••••" required autocomplete="off" class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none text-center tracking-[0.5em] font-bold">
          </div>
        </div>

        <button type="submit" class="w-full h-[52px] bg-teal-600 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 hover:bg-teal-500 transition-colors active:scale-95 shadow-lg shadow-teal-500/20">
          Verify Access <span class="material-symbols-outlined text-[20px]">security</span>
        </button>
      </form>
      <?php else: ?>

      <!-- Tab Switcher -->
      <div class="flex bg-slate-800 rounded-xl p-1 mb-6 border border-slate-700">
        <button type="button" onclick="switchTab('login')" id="btn-login" class="flex-1 py-2 rounded-lg text-sm font-bold transition-all <?= $tab === 'login' ? 'tab-active' : 'text-slate-400 hover:text-white' ?>">Masuk</button>
        <button type="button" onclick="switchTab('register')" id="btn-register" class="flex-1 py-2 rounded-lg text-sm font-bold transition-all <?= $tab === 'register' ? 'tab-active' : 'text-slate-400 hover:text-white' ?>">Daftar</button>
      </div>

      <!-- Login Form -->
      <form id="form-login" method="POST" class="flex flex-col gap-5 <?= $tab !== 'login' ? 'hidden' : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="login">

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Email Address</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">alternate_email</span>
            <input type="email" name="email" placeholder="admin@careconnect.id" required autocomplete="off" class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none" value="<?= e($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Password</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">lock</span>
            <input type="password" id="login-password" name="password" placeholder="••••••••" required autocomplete="new-password" class="w-full h-[52px] pl-12 pr-12 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none">
            <button type="button" onclick="togglePassword('login-password', this)" class="absolute right-4 text-slate-500 hover:text-teal-400 transition-colors"><span class="material-symbols-outlined text-[20px]">visibility_off</span></button>
          </div>
        </div>

        <button type="submit" class="w-full h-[52px] bg-teal-600 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 hover:bg-teal-500 transition-colors active:scale-95">
          Login <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
        </button>
      </form>

      <!-- Register Form -->
      <form id="form-register" method="POST" class="flex flex-col gap-5 <?= $tab !== 'register' ? 'hidden' : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="register">

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Nama Lengkap</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">badge</span>
            <input type="text" name="name" placeholder="Admin Name" required autocomplete="off" class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none" value="<?= e($_POST['name'] ?? '') ?>">
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Email Address</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">alternate_email</span>
            <input type="email" name="email" placeholder="admin@careconnect.id" required autocomplete="off" class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none" value="<?= e($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Password</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">lock</span>
            <input type="password" id="reg-password" name="password" placeholder="Min 8 karakter" required autocomplete="new-password" class="w-full h-[52px] pl-12 pr-12 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none">
            <button type="button" onclick="togglePassword('reg-password', this)" class="absolute right-4 text-slate-500 hover:text-teal-400 transition-colors"><span class="material-symbols-outlined text-[20px]">visibility_off</span></button>
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1">Konfirmasi Password</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">lock_reset</span>
            <input type="password" name="confirm_password" placeholder="Ulangi password" required autocomplete="new-password" class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none">
          </div>
        </div>

        <button type="submit" class="w-full h-[52px] bg-teal-600 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 hover:bg-teal-500 transition-colors active:scale-95">
          Register Admin <span class="material-symbols-outlined text-[20px]">person_add</span>
        </button>
      </form>
      
      <?php endif; ?>

    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  document.getElementById('form-login').classList.toggle('hidden', tab !== 'login');
  document.getElementById('form-register').classList.toggle('hidden', tab !== 'register');
  
  document.getElementById('btn-login').className = tab === 'login' ? 'flex-1 py-2 rounded-lg text-sm font-bold transition-all tab-active' : 'flex-1 py-2 rounded-lg text-sm font-bold transition-all text-slate-400 hover:text-white';
  document.getElementById('btn-register').className = tab === 'register' ? 'flex-1 py-2 rounded-lg text-sm font-bold transition-all tab-active' : 'flex-1 py-2 rounded-lg text-sm font-bold transition-all text-slate-400 hover:text-white';
}

function togglePassword(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('.material-symbols-outlined');
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.textContent = input.type === 'password' ? 'visibility_off' : 'visibility';
}
</script>
</body>
</html>
