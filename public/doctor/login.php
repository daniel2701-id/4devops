<?php
require_once __DIR__ . '/../../includes/functions.php';

if (is_logged_in() && ($_SESSION['user_role'] ?? '') === 'doctor') {
    header('Location: ' . APP_URL . '/doctor/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $email    = sanitize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Isi semua kolom.';
    } else {
        $result = attempt_login($email, $password, 'doctor');
        if ($result['success']) {
            create_auth_session($result['user']);
            header('Location: ' . APP_URL . '/doctor/dashboard.php');
            exit;
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Portal Dokter</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-surface text-on-surface antialiased">

<div class="min-h-screen flex w-full">

  <!-- Left Panel – Violet theme -->
  <div class="hidden md:flex md:w-1/2 relative overflow-hidden flex-col justify-between p-12" style="background:linear-gradient(135deg,#3b0764 0%,#6750a4 100%);">
    <div class="absolute inset-0 opacity-10" style="background:radial-gradient(ellipse at 70% 80%,#fff,transparent 55%)"></div>

    <!-- Logo -->
    <div class="relative z-10 flex items-center gap-3">
      <div class="w-12 h-12 bg-white/20 flex items-center justify-center rounded-xl transform rotate-45 shadow-lg">
        <span class="material-symbols-outlined text-white transform -rotate-45 text-2xl" style="font-variation-settings:'FILL' 1;">medical_services</span>
      </div>
      <span class="text-2xl font-black text-white tracking-tight">CareConnect</span>
    </div>

    <div class="relative z-10 max-w-md mt-auto mb-10">
      <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/20 text-white text-xs font-bold mb-6">
        <span class="material-symbols-outlined text-[14px]">stethoscope</span>
        Portal Dokter
      </span>
      <h1 class="text-4xl font-black text-white mb-4 leading-tight">
        Manajemen Klinis<br>Presisi
      </h1>
      <p class="text-purple-200 text-base font-medium mb-8">
        Akses rekam medis, jadwal, dan komunikasi pasien dalam satu platform terpadu.
      </p>

      <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 text-white text-sm font-medium">
        <span class="material-symbols-outlined text-[18px] text-yellow-300 align-middle mr-1">info</span>
        Akun dokter dibuat oleh administrator klinik. Jika belum memiliki kredensial, hubungi tim manajemen.
      </div>
    </div>

    <div class="relative z-10 text-purple-300 text-xs font-medium">© 2026 CareConnect Solusi Terpadu</div>
  </div>

  <!-- Right Panel: Form -->
  <div class="w-full md:w-1/2 flex flex-col items-center justify-center p-6 lg:p-12 bg-slate-50">
    <div class="w-full max-w-[400px]">

      <a href="<?= APP_URL ?>/landing.php" class="flex items-center gap-1 text-slate-500 hover:text-primary transition-colors mb-8 text-sm font-medium group">
        <span class="material-symbols-outlined group-hover:-translate-x-1 transition-transform text-[20px]">arrow_back</span>
        Kembali ke Pilihan Peran
      </a>

      <div class="mb-6">
        <h2 class="text-3xl font-black text-on-surface mb-1">Portal Dokter</h2>
        <p class="text-sm font-medium text-on-surface-variant">Silakan masuk untuk mengakses dasbor medis Anda.</p>
      </div>

      <?= alert_html($error, 'error') ?>

      <form method="POST" class="flex flex-col gap-4">
        <?= csrf_field() ?>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider ml-1" for="email">Email Profesional</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[20px]">mail</span>
            <input type="email" id="email" name="email" placeholder="dr.nama@klinik.com" required autocomplete="off"
              class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-medium focus:border-primary focus:ring-2 focus:ring-purple-200 outline-none placeholder-slate-400 shadow-sm transition-all">
          </div>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-bold text-on-surface-variant uppercase tracking-wider ml-1" for="password">Kata Sandi</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-400 text-[20px]">lock</span>
            <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="off"
              class="w-full h-[52px] pl-12 pr-12 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-medium focus:border-primary focus:ring-2 focus:ring-purple-200 outline-none placeholder-slate-400 shadow-sm transition-all">
            <button type="button" onclick="togglePassword('password', this)" class="absolute right-4 text-slate-400 hover:text-primary transition-colors">
              <span class="material-symbols-outlined text-[20px]">visibility_off</span>
            </button>
          </div>
        </div>

        <a href="#" class="text-sm font-medium text-primary hover:underline self-end">Lupa sandi?</a>

        <button type="submit"
          class="w-full h-[52px] mt-1 bg-primary text-white rounded-xl font-bold text-base flex items-center justify-center gap-2 hover:bg-primary-light transition-colors shadow-[0_4px_14px_0_rgba(79,55,138,0.39)] active:scale-95">
          Masuk sebagai Dokter
          <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
        </button>
      </form>

      <p class="text-center text-xs text-slate-400 mt-6">
        Butuh bantuan teknis?
        <a href="mailto:it@careconnect.id" class="text-primary hover:underline font-semibold">Hubungi IT Support</a>
      </p>
    </div>
  </div>
</div>

<script>
function togglePassword(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('.material-symbols-outlined');
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.textContent = input.type === 'password' ? 'visibility_off' : 'visibility';
}
</script>
</body>
</html>
