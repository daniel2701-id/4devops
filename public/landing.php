<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
  $role = $_SESSION['user_role'] ?? 'patient';
  header('Location: ' . APP_URL . '/' . $role . '/dashboard.php');
  exit;
}

$timeout = !empty($_GET['timeout']) ? 'Sesi Anda telah berakhir. Silakan masuk kembali.' : null;
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CareConnect – Portal Kesehatan Terpadu</title>
  <?= tailwind_cdn() ?>
  <?= tailwind_config() ?>
  <?= google_fonts() ?>
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
  </style>
</head>

<body class="bg-surface text-on-surface min-h-screen flex flex-col antialiased">

  <!-- Nav -->
  <nav class="sticky top-0 w-full z-50 border-b border-slate-200 shadow-sm bg-white/90 backdrop-blur-md">
    <div class="flex items-center justify-between px-6 py-4 max-w-7xl mx-auto">
      <a href="<?= APP_URL ?>/index.php" class="flex items-center gap-2">
        <div class="w-8 h-8 bg-primary-fixed flex items-center justify-center rounded-lg transform rotate-45">
          <span class="material-symbols-outlined text-primary transform -rotate-45 text-[18px]"
            style="font-variation-settings:'FILL' 1;">medical_services</span>
        </div>
        <span class="text-xl font-extrabold tracking-tight text-slate-900">Andi Daniel Tenri Dio</span>
      </a>
    </div>
  </nav>

  <!-- Timeout flash -->
  <?php if ($timeout): ?>
    <div class="max-w-7xl mx-auto px-6 mt-4">
      <div class="bg-amber-50 border border-amber-300 text-amber-700 rounded-xl px-4 py-3 text-sm font-medium">
        <?= e($timeout) ?>
      </div>
    </div>
  <?php endif; ?>

  <main class="flex-grow">
    <!-- Hero -->
    <section class="max-w-7xl mx-auto px-6 py-20 lg:py-32 flex flex-col items-center text-center">
      <div class="space-y-8 max-w-3xl flex flex-col items-center">
        <span
          class="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 font-bold text-xs border border-blue-100">
          <span class="material-symbols-outlined text-[16px] mr-1">new_releases</span>
          Portal Kesehatan Terpadu
        </span>
        <h1 class="text-4xl md:text-5xl font-black tracking-tight text-slate-900 leading-tight">
          Kelola Klinik Anda Lebih<br>Cerdas &amp; Efisien
        </h1>
        <p class="text-lg text-slate-600 font-medium max-w-xl">
          Tingkatkan kualitas pelayanan dengan sistem manajemen klinik berbasis cloud yang dirancang khusus untuk
          kemudahan operasional medis modern.
        </p>
      </div>
    </section>

    <!-- Role Cards -->
    <section class="pb-24 max-w-7xl mx-auto px-6">
      <div class="text-center mb-14">
        <h2 class="text-3xl font-black text-on-surface tracking-tight">Solusi Untuk Setiap Peran</h2>
        <p class="text-base font-medium text-on-surface-variant mt-4 max-w-xl mx-auto">
          Platform kami dirancang untuk mengoptimalkan alur kerja setiap individu dalam ekosistem klinik Anda.
        </p>
      </div>

      <div class="grid md:grid-cols-3 gap-8">

        <!-- Patient Card -->
        <div
          class="bg-blue-50 text-slate-900 rounded-2xl border-2 border-blue-500 p-8 shadow-md relative flex flex-col">
          <div class="w-12 h-12 bg-blue-600 text-white rounded-xl flex items-center justify-center mb-6 mt-2">
            <span class="material-symbols-outlined">person</span>
          </div>
          <h3 class="text-xl font-bold mb-2">Pasien</h3>
          <p class="text-sm font-medium opacity-80 mb-6 flex-grow">
            Akses layanan kesehatan dengan mudah dari genggaman.
          </p>
          <ul class="space-y-2 mb-8 text-sm font-medium">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-blue-600 text-[18px]">check_circle</span>
              Booking Online
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-blue-600 text-[18px]">check_circle</span>
              Riwayat Medis Digital
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-blue-600 text-[18px]">check_circle</span>
              Resep Digital
            </li>
          </ul>
          <a href="<?= APP_URL ?>/patient/login.php"
            class="w-full py-2.5 bg-blue-600 text-white rounded-xl text-sm font-bold text-center hover:bg-blue-700 transition-colors mt-auto block shadow-md">
            Masuk sebagai Pasien
          </a>
        </div>

        <!-- Doctor Card (highlighted) -->
        <div
          class="bg-primary-fixed text-on-surface rounded-2xl border-2 border-primary p-8 shadow-md relative flex flex-col">
          <div class="w-12 h-12 bg-primary text-white rounded-xl flex items-center justify-center mb-6 mt-2">
            <span class="material-symbols-outlined">stethoscope</span>
          </div>
          <h3 class="text-xl font-bold mb-2">Dokter</h3>
          <p class="text-sm font-medium opacity-80 mb-6 flex-grow">
            Fokus pada pasien, biarkan sistem menangani administrasi.
          </p>
          <ul class="space-y-2 mb-8 text-sm font-medium">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-[18px]">check_circle</span>
              E-Prescription Digital
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-[18px]">check_circle</span>
              Jadwal Pintar
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-primary text-[18px]">check_circle</span>
              Catatan Klinis
            </li>
          </ul>
          <a href="<?= APP_URL ?>/doctor/login.php"
            class="w-full py-2.5 bg-primary text-white rounded-xl text-sm font-bold text-center hover:bg-primary-light transition-colors mt-auto block shadow-md">
            Masuk sebagai Dokter
          </a>
        </div>

        <!-- Admin Card -->
        <div
          class="bg-teal-50 text-slate-900 rounded-2xl border-2 border-teal-500 p-8 shadow-md relative flex flex-col">
          <div class="w-12 h-12 bg-teal-600 text-white rounded-xl flex items-center justify-center mb-6 mt-2">
            <span class="material-symbols-outlined">admin_panel_settings</span>
          </div>
          <h3 class="text-xl font-bold mb-2">Admin</h3>
          <p class="text-sm font-medium opacity-80 mb-6 flex-grow">
            Kontrol penuh atas operasional dan laporan klinik.
          </p>
          <ul class="space-y-2 mb-8 text-sm font-medium">
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-teal-600 text-[18px]">check_circle</span>
              Manajemen Inventaris
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-teal-600 text-[18px]">check_circle</span>
              Laporan Keuangan
            </li>
            <li class="flex items-center gap-2">
              <span class="material-symbols-outlined text-teal-600 text-[18px]">check_circle</span>
              Manajemen Pengguna
            </li>
          </ul>
          <a href="<?= APP_URL ?>/admin/login.php"
            class="w-full py-2.5 bg-teal-600 text-white rounded-xl text-sm font-bold text-center hover:bg-teal-700 transition-colors mt-auto block shadow-md">
            Masuk sebagai Admin
          </a>
        </div>

      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="w-full py-12 bg-slate-900 border-t border-slate-800">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-8 px-8 max-w-7xl mx-auto">
      <div>
        <div class="text-white font-bold text-lg mb-3 flex items-center gap-2">
          <div class="w-6 h-6 bg-blue-50/10 flex items-center justify-center rounded transform rotate-45">
            <span class="material-symbols-outlined text-blue-400 text-[16px] transform -rotate-45"
              style="font-variation-settings:'FILL' 1;">medical_services</span>
          </div>
          CareConnect
        </div>
        <p class="text-xs text-slate-400">© 2026 CareConnect. Solusi Manajemen Klinik Terpadu.</p>
      </div>
      <div class="flex flex-col space-y-3 text-xs text-slate-400 uppercase tracking-widest">
        <a href="#" class="hover:text-blue-300 transition-colors">Fitur</a>
        <a href="#" class="hover:text-blue-300 transition-colors">Tentang Kami</a>
      </div>
      <div class="flex flex-col space-y-3 text-xs text-slate-400 uppercase tracking-widest">
        <a href="https://wa.me/628124790007" class="hover:text-blue-300 transition-colors">Kontak (08124790007)</a>
      </div>
    </div>
  </footer>
</body>

</html>