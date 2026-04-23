<?php
require_once __DIR__ . '/../../includes/functions.php';

if (is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
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
        $result = attempt_login($email, $password, 'admin');
        if ($result['success']) {
            // Don't create full session yet — need OTP
            $_SESSION['admin_pending_id']    = $result['user']['id'];
            $_SESSION['admin_pending_name']  = $result['user']['name'];
            $_SESSION['admin_pending_email'] = $result['user']['email'];

            // Generate OTP
            $otp = generate_otp((int) $result['user']['id']);

            // In production: send via email (PHPMailer/SMTP)
            // For demo: store in session so verify page can show it
            $_SESSION['admin_otp_demo'] = $otp;

            audit_log('admin_otp_sent', (int) $result['user']['id']);
            header('Location: ' . APP_URL . '/admin/verify.php');
            exit;
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Admin Portal Login</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#0f766e') ?>  <!-- teal primary for admin -->
<?= google_fonts() ?>
<style>
  body { font-family: 'Inter', sans-serif; }
  :root { --primary-admin: #0f766e; }
</style>
</head>
<body class="bg-slate-950 text-white antialiased min-h-screen">

<div class="min-h-screen flex w-full">

  <!-- Left Panel – Dark Teal Admin -->
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
      <h1 class="text-3xl font-black text-white mb-4 leading-tight">
        Secure Access for Healthcare Administrators.
      </h1>
      <p class="text-slate-400 text-sm font-medium mb-8">
        Streamline your clinic's operations, manage staff securely, and maintain compliance with our enterprise-grade administration platform.
      </p>

      <div class="space-y-4">
        <div class="flex items-start gap-3 bg-slate-800/60 rounded-xl p-4 border border-slate-700/50">
          <div class="w-8 h-8 bg-teal-500/20 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
            <span class="material-symbols-outlined text-teal-400 text-[18px]">lock</span>
          </div>
          <div>
            <h4 class="text-white font-bold text-sm mb-0.5">End-to-End Encryption</h4>
            <p class="text-slate-400 text-xs">All patient and clinic data is securely encrypted at rest and in transit.</p>
          </div>
        </div>
        <div class="flex items-start gap-3 bg-slate-800/60 rounded-xl p-4 border border-slate-700/50">
          <div class="w-8 h-8 bg-teal-500/20 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
            <span class="material-symbols-outlined text-teal-400 text-[18px]">manage_accounts</span>
          </div>
          <div>
            <h4 class="text-white font-bold text-sm mb-0.5">Role-Based Access Control</h4>
            <p class="text-slate-400 text-xs">Granular permissions ensure staff only see what they need to.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="relative z-10 text-slate-500 text-xs font-medium">© 2024 MediCare Pro Systems. All rights reserved.</div>
  </div>

  <!-- Right Panel: Form -->
  <div class="w-full md:w-1/2 flex flex-col items-center justify-center p-6 lg:p-12 bg-slate-950">
    <div class="w-full max-w-[420px]">

      <a href="<?= APP_URL ?>/landing.php" class="flex items-center gap-1 text-slate-500 hover:text-teal-400 transition-colors mb-8 text-sm font-medium group">
        <span class="material-symbols-outlined group-hover:-translate-x-1 transition-transform text-[20px]">arrow_back</span>
        Kembali
      </a>

      <div class="mb-6">
        <h2 class="text-2xl font-black text-white mb-1">Admin Portal Login</h2>
        <p class="text-sm font-medium text-slate-400">Welcome back. Please sign in to your account.</p>
      </div>

      <!-- Restricted area notice -->
      <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl px-4 py-3 mb-6 flex items-start gap-2">
        <span class="material-symbols-outlined text-yellow-400 text-[18px] mt-0.5 flex-shrink-0">warning</span>
        <p class="text-yellow-300 text-xs font-medium">
          Restricted Area. Authorized administrative personnel only. All access is logged and monitored.
        </p>
      </div>

      <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 rounded-xl px-4 py-3 mb-4 text-red-400 text-sm font-medium">
        <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="flex flex-col gap-5">
        <?= csrf_field() ?>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1" for="email">Email Address</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">alternate_email</span>
            <input type="email" id="email" name="email" placeholder="admin@mercygeneral.org" required
              class="w-full h-[52px] pl-12 pr-4 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm font-medium focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none placeholder-slate-600 transition-all"
              value="<?= e($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider ml-1" for="password">Password</label>
          <div class="relative flex items-center">
            <span class="material-symbols-outlined absolute left-4 text-slate-500 text-[20px]">lock</span>
            <input type="password" id="password" name="password" placeholder="••••••••" required
              class="w-full h-[52px] pl-12 pr-12 rounded-xl bg-slate-800 border border-slate-700 text-white text-sm font-medium focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none placeholder-slate-600 transition-all">
            <button type="button" onclick="togglePassword('password', this)" class="absolute right-4 text-slate-500 hover:text-teal-400 transition-colors">
              <span class="material-symbols-outlined text-[20px]">visibility_off</span>
            </button>
          </div>
        </div>

        <a href="#" class="text-xs font-medium text-teal-400 hover:underline self-end">Forgot Password?</a>

        <button type="submit"
          class="w-full h-[52px] bg-teal-600 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 hover:bg-teal-500 transition-colors active:scale-95 shadow-[0_4px_14px_0_rgba(15,118,110,0.4)]">
          Masuk Ke Portal
          <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
        </button>
      </form>

      <p class="text-center text-xs text-slate-500 mt-6">
        Need Help? <a href="mailto:it@careconnect.id" class="text-teal-400 hover:underline">Contact IT Support</a>
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
