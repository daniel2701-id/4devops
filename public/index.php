<?php
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
  $role = $_SESSION['user_role'] ?? 'patient';
  header('Location: ' . APP_URL . '/' . $role . '/dashboard.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">

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

    .brand-shadow {
      box-shadow: 0 4px 24px 0 rgba(79, 55, 138, 0.35);
    }

    @keyframes fade-up {
      from {
        opacity: 0;
        transform: translateY(30px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-fade-up {
      animation: fade-up 0.7s ease both;
    }

    .delay-1 {
      animation-delay: .15s;
    }

    .delay-2 {
      animation-delay: .30s;
    }

    .delay-3 {
      animation-delay: .45s;
    }
  </style>
</head>

<body class="h-full bg-surface text-on-surface relative overflow-hidden flex items-center justify-center">

  <!-- Decorative Blobs -->
  <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none" aria-hidden="true">
    <div class="absolute top-[-20%] left-[-10%] w-[70vw] h-[70vw] rounded-full bg-primary-fixed opacity-40 blur-3xl">
    </div>
    <div class="absolute bottom-[-20%] right-[-10%] w-[60vw] h-[60vw] rounded-full bg-primary opacity-10 blur-3xl">
    </div>
  </div>

  <!-- Main Content -->
  <main
    class="relative z-10 flex flex-col items-center justify-center px-6 py-12 max-w-2xl text-center animate-fade-up">

    <!-- Logo -->
    <div class="w-28 h-28 mb-8 relative delay-1 animate-fade-up">
      <div class="absolute inset-0 bg-primary opacity-10 rounded-3xl rotate-45 blur-xl"></div>
      <div
        class="absolute inset-0 bg-primary-light rounded-3xl rotate-45 flex items-center justify-center shadow-xl border border-white/20">
        <span class="material-symbols-outlined text-white -rotate-45"
          style="font-size:52px;font-variation-settings:'FILL' 1;">
          medical_services
        </span>
      </div>
    </div>

    <!-- Brand -->
    <h1 class="text-5xl md:text-6xl font-black tracking-tight text-on-surface mb-4 delay-1 animate-fade-up">
      CareConnect
    </h1>
    <p class="text-lg md:text-xl text-on-surface-variant font-medium max-w-lg mx-auto mb-10 delay-2 animate-fade-up">
      Portal Kesehatan Terpadu – Presisi, Kemudahan, dan Performa untuk Lingkungan Medis Modern.
    </p>

    <!-- CTA Button -->
    <div class="delay-3 animate-fade-up">
      <a href="<?= APP_URL ?>/landing.php"
        class="inline-flex items-center gap-2 px-10 py-4 bg-primary text-white text-lg font-bold rounded-full brand-shadow hover:bg-primary-light transition-all active:scale-95">
        ney sigma
        <span class="material-symbols-outlined text-[22px]">arrow_forward</span>
      </a>
    </div>

    <!-- Role badges -->
    <div class="flex flex-wrap items-center justify-center gap-3 mt-10 delay-3 animate-fade-up">
      <span
        class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-50 text-blue-600 text-xs font-bold border border-blue-100">
        <span class="material-symbols-outlined text-[14px]">person</span>Pasien
      </span>
      <span
        class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-primary-fixed text-primary text-xs font-bold border border-primary/20">
        <span class="material-symbols-outlined text-[14px]">stethoscope</span>Dokter
      </span>
      <span
        class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-teal-50 text-teal-600 text-xs font-bold border border-teal-100">
        <span class="material-symbols-outlined text-[14px]">admin_panel_settings</span>Admin
      </span>
    </div>
  </main>

</body>

</html>