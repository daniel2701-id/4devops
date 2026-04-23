<?php
require_once __DIR__ . '/../../includes/functions.php';

if (is_logged_in() && ($_SESSION['user_role'] ?? '') === 'patient') {
    header('Location: ' . APP_URL . '/patient/dashboard.php');
    exit;
}

$error   = '';
$success = '';
$tab     = $_GET['tab'] ?? 'login';   // 'login' | 'register'

// ---- Handle POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $tab = $_POST['tab'] ?? 'login';

    if ($tab === 'login') {
        $email    = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Isi semua kolom.';
        } else {
            $result = attempt_login($email, $password, 'patient');
            if ($result['success']) {
                create_auth_session($result['user']);
                header('Location: ' . APP_URL . '/patient/dashboard.php');
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
            $result = register_patient($name, $email, $password);
            if ($result['success']) {
                $success = 'Pendaftaran berhasil! Silakan masuk.';
                $tab = 'login';
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Pasien Masuk / Daftar</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#2563eb') ?>
<?= google_fonts() ?>
<style>
  body { font-family: 'Inter', sans-serif; }
  .tab-active   { background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  .input-field  { @apply w-full h-[52px] pl-12 pr-4 rounded-xl bg-white border border-outline-variant text-on-surface text-sm font-medium focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none placeholder-slate-400 shadow-sm transition-all; }
</style>
</head>
<body class="bg-slate-50 text-on-surface antialiased">

<div class="min-h-screen flex w-full">

  <!-- Left Panel -->
  <div class="hidden md:flex md:w-1/2 relative bg-primary overflow-hidden flex-col justify-between p-12">
    <div class="absolute inset-0 z-0 opacity-10" style="background:radial-gradient(ellipse at 30% 20%,#fff,transparent 60%)"></div>
    <div class="absolute inset-0 z-0" style="background:linear-gradient(135deg,#1d4ed8 0%,#4f46e5 100%);"></div>

    <!-- Logo -->
    <div class="relative z-10 flex items-center gap-3">
      <div class="w-12 h-12 bg-white/20 flex items-center justify-center rounded-xl transform rotate-45 shadow-lg">
        <span class="material-symbols-outlined text-white transform -rotate-45 text-2xl" style="font-variation-settings:'FILL' 1;">medical_services</span>
      </div>
      <span class="text-2xl font-black text-white tracking-tight">CareConnect</span>
    </div>

    <!-- Content -->
    <div class="relative z-10 max-w-md mt-auto mb-10">
      <h1 class="text-4xl font-black text-white mb-4 leading-tight">
        Kesehatan Anda,<br>Terhubung Lebih Mudah.
      </h1>
      <p class="text-blue-200 text-base font-medium mb-8">
        Akses rekam medis, jadwalkan konsultasi, dan pantau kesehatan Anda dalam satu platform terpadu.
      </p>

      <!-- Feature cards -->
      <div class="flex flex-col gap-4">
        <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 flex items-start gap-4 shadow-lg">
          <div class="p-2 bg-white/20 rounded-xl text-white flex-shrink-0">
            <span class="material-symbols-outlined text-xl">calendar_clock</span>
          </div>
          <div>
            <h4 class="text-white font-bold text-sm mb-0.5">Reservasi Instan</h4>
            <p class="text-blue-200 text-xs font-medium">Pilih jadwal dokter tanpa antre berjam-jam di klinik.</p>
          </div>
        </div>
        <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 flex items-start gap-4 shadow-lg">
          <div class="p-2 bg-white/20 rounded-xl text-white flex-shrink-0">
            <span class="material-symbols-outlined text-xl">history_edu</span>
          </div>
          <div>
            <h4 class="text-white font-bold text-sm mb-0.5">Riwayat Medis Digital</h4>
            <p class="text-blue-200 text-xs font-medium">Semua rekam medis tersimpan aman dan mudah diakses.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="relative z-10 text-blue-300 text-xs font-medium">© 2024 CareConnect Solusi Terpadu</div>
  </div>

  <!-- Right Panel: Form -->
  <div class="w-full md:w-1/2 flex flex-col items-center justify-center p-6 lg:p-12 bg-slate-50">
    <div class="w-full max-w-[400px]">

      <!-- Back -->
      <a href="<?= APP_URL ?>/landing.php" class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors mb-8 text-sm font-medium group">
        <span class="material-symbols-outlined group-hover:-translate-x-1 transition-transform text-[20px]">arrow_back</span>
        Kembali ke Pilihan Peran
      </a>

      <div class="mb-6">
        <h2 class="text-3xl font-black text-on-surface mb-1">Selamat Datang</h2>
        <p class="text-sm font-medium text-on-surface-variant">
          <?= $tab === 'login' ? 'Silakan masuk ke akun Pasien Anda.' : 'Buat akun Pasien baru.' ?>
        </p>
      </div>

      <!-- Alerts -->
      <?= alert_html($error, 'error') ?>
      <?= alert_html($success, 'success') ?>

      <!-- Tab Switcher -->
      <div class="flex bg-slate-200/80 rounded-full p-1 mb-8">
        <button type="button" onclick="switchTab('login')" id="btn-login"
          class="flex-1 py-2 px-4 rounded-full text-xs font-bold text-center transition-all <?= $tab === 'login' ? 'tab-active text-primary' : 'text-slate-500 hover:text-primary' ?>">
          MASUK
        </button>
        <button type="button" onclick="switchTab('register')" id="btn-register"
          class="flex-1 py-2 px-4 rounded-full text-xs font-bold text-center transition-all <?= $tab === 'register' ? 'tab-active text-primary' : 'text-slate-500 hover:text-primary' ?>">
          DAFTAR
        </button>
      </div>

      <!-- ============  LOGIN FORM  ============ -->
      <form id="form-login" method="POST" class="flex flex-col gap-4 <?= $tab !== 'login' ? 'hidden' : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="login">

        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider ml-1" for="login-email">Email Anda</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[20px]">mail</span>
            <input type="email" id="login-email" name="email" placeholder="contoh@email.com" required autocomplete="off"
              class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-medium focus:border-primary focus:ring-2 focus:ring-blue-200 outline-none placeholder-slate-400 shadow-sm transition-all"
              value="<?= e($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider ml-1" for="login-password">Kata Sandi</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[20px]">lock</span>
            <input type="password" id="login-password" name="password" placeholder="••••••••" required autocomplete="new-password"
              class="w-full h-[52px] pl-12 pr-12 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-medium focus:border-primary focus:ring-2 focus:ring-blue-200 outline-none placeholder-slate-400 shadow-sm transition-all">
            <button type="button" onclick="togglePassword('login-password', this)" class="absolute right-4 text-slate-400 hover:text-primary transition-colors">
              <span class="material-symbols-outlined text-[20px]">visibility_off</span>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-primary">
            <span class="text-sm font-medium text-on-surface-variant">Ingat saya</span>
          </label>
          <a href="#" class="text-sm font-medium text-primary hover:underline">Lupa Kata Sandi?</a>
        </div>

        <button type="submit"
          class="w-full h-[52px] mt-1 bg-primary text-white rounded-xl font-bold text-base flex items-center justify-center gap-2 hover:bg-blue-700 transition-colors shadow-[0_4px_14px_0_rgba(37,99,235,0.39)] active:scale-95">
          Masuk
          <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
        </button>
      </form>

      <!-- ============  REGISTER FORM  ============ -->
      <form id="form-register" method="POST" class="flex flex-col gap-4 <?= $tab !== 'register' ? 'hidden' : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="register">

        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider ml-1" for="reg-name">Nama Lengkap</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[20px]">badge</span>
            <input type="text" id="reg-name" name="name" placeholder="Nama Anda" required maxlength="150"
              class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-medium focus:border-primary focus:ring-2 focus:ring-blue-200 outline-none placeholder-slate-400 shadow-sm transition-all"
              value="<?= e($_POST['name'] ?? '') ?>">
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider ml-1" for="reg-email">Email</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[20px]">mail</span>
            <input type="email" id="reg-email" name="email" placeholder="contoh@email.com" required autocomplete="off"
              class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-medium focus:border-primary focus:ring-2 focus:ring-blue-200 outline-none placeholder-slate-400 shadow-sm transition-all"
              value="<?= e($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider ml-1" for="reg-password">Kata Sandi</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[20px]">lock</span>
            <input type="password" id="reg-password" name="password" placeholder="Min 8 karakter" required autocomplete="new-password"
              class="w-full h-[52px] pl-12 pr-12 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-medium focus:border-primary focus:ring-2 focus:ring-blue-200 outline-none placeholder-slate-400 shadow-sm transition-all">
            <button type="button" onclick="togglePassword('reg-password', this)" class="absolute right-4 text-slate-400 hover:text-primary transition-colors">
              <span class="material-symbols-outlined text-[20px]">visibility_off</span>
            </button>
          </div>
          <p class="text-xs text-slate-400 ml-1 mt-0.5">Min 8 karakter, huruf kapital, angka, dan simbol</p>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider ml-1" for="reg-confirm">Konfirmasi Kata Sandi</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[20px]">lock_reset</span>
            <input type="password" id="reg-confirm" name="confirm_password" placeholder="Ulangi kata sandi" required
              class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-medium focus:border-primary focus:ring-2 focus:ring-blue-200 outline-none placeholder-slate-400 shadow-sm transition-all">
          </div>
        </div>

        <button type="submit"
          class="w-full h-[52px] mt-1 bg-primary text-white rounded-xl font-bold text-base flex items-center justify-center gap-2 hover:bg-blue-700 transition-colors shadow-[0_4px_14px_0_rgba(37,99,235,0.39)] active:scale-95">
          Daftar Sekarang
          <span class="material-symbols-outlined text-[20px]">person_add</span>
        </button>
      </form>

    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  const loginForm = document.getElementById('form-login');
  const regForm   = document.getElementById('form-register');
  const btnLogin  = document.getElementById('btn-login');
  const btnReg    = document.getElementById('btn-register');

  if (tab === 'login') {
    loginForm.classList.remove('hidden');
    regForm.classList.add('hidden');
    btnLogin.classList.add('tab-active','text-primary');
    btnLogin.classList.remove('text-slate-500');
    btnReg.classList.remove('tab-active','text-primary');
    btnReg.classList.add('text-slate-500');
  } else {
    regForm.classList.remove('hidden');
    loginForm.classList.add('hidden');
    btnReg.classList.add('tab-active','text-primary');
    btnReg.classList.remove('text-slate-500');
    btnLogin.classList.remove('tab-active','text-primary');
    btnLogin.classList.add('text-slate-500');
  }
}

function togglePassword(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('.material-symbols-outlined');
  if (input.type === 'password') {
    input.type = 'text';
    icon.textContent = 'visibility';
  } else {
    input.type = 'password';
    icon.textContent = 'visibility_off';
  }
}
</script>
</body>
</html>
