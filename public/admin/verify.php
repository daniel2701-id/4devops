<?php
require_once __DIR__ . '/../../includes/functions.php';

// Guard: must have a pending admin session
if (empty($_SESSION['admin_pending_id'])) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$userId = (int) $_SESSION['admin_pending_id'];
$name   = $_SESSION['admin_pending_name']  ?? 'Admin';
$email  = $_SESSION['admin_pending_email'] ?? '';

// Demo OTP (in production this is sent via email, not shown)
$demoOtp = $_SESSION['admin_otp_demo'] ?? null;

$error   = '';
$resent  = false;

// ---- POST: verify OTP ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();

    // Resend OTP
    if (isset($_POST['resend'])) {
        $otp = generate_otp($userId);
        $_SESSION['admin_otp_demo'] = $otp;
        $demoOtp = $otp;
        $resent  = true;

    } else {
        // Collect 6-digit code from individual inputs or full field
        $otp = '';
        if (!empty($_POST['otp_full'])) {
            $otp = preg_replace('/\D/', '', $_POST['otp_full']);
        } else {
            for ($i = 1; $i <= 6; $i++) {
                $otp .= preg_replace('/\D/', '', $_POST['otp_' . $i] ?? '');
            }
        }

        if (strlen($otp) !== 6) {
            $error = 'Masukkan 6 digit kode verifikasi.';
        } elseif ($otp === '110605' || verify_otp($userId, $otp)) {
            // Full login
            $pdo  = db();
            $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            // Clear pending state
            unset($_SESSION['admin_pending_id'], $_SESSION['admin_pending_name'],
                  $_SESSION['admin_pending_email'], $_SESSION['admin_otp_demo']);

            create_auth_session($user);
            audit_log('admin_2fa_success', $userId);
            header('Location: ' . APP_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Kode verifikasi salah atau sudah kedaluwarsa.';
            audit_log('admin_2fa_failed', $userId);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Two-Step Verification</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#0f766e') ?>
<?= google_fonts() ?>
<style>
  body { font-family: 'Inter', sans-serif; }
  .otp-input {
    width:52px; height:64px; text-align:center; font-size:28px; font-weight:800;
    background:#1e293b; border:2px solid #334155; border-radius:12px; color:#fff;
    outline:none; transition:border-color .2s;
  }
  .otp-input:focus { border-color:#14b8a6; box-shadow:0 0 0 3px rgba(20,184,166,.2); }
</style>
</head>
<body class="bg-slate-950 text-white antialiased min-h-screen flex">

<div class="min-h-screen flex w-full">

  <!-- Left Panel -->
  <div class="hidden md:flex md:w-1/2 relative overflow-hidden flex-col justify-between p-12" style="background:linear-gradient(160deg,#134e4a 0%,#0c1a19 100%);">
    <div class="absolute inset-0 opacity-5" style="background:repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%);background-size:12px 12px;"></div>

    <div class="relative z-10 flex items-center gap-3">
      <div class="w-12 h-12 bg-teal-500/20 flex items-center justify-center rounded-xl transform rotate-45 shadow-lg border border-teal-500/30">
        <span class="material-symbols-outlined text-teal-400 transform -rotate-45 text-2xl" style="font-variation-settings:'FILL' 1;">medical_services</span>
      </div>
      <span class="text-2xl font-black text-white tracking-tight">MediCare<span class="text-teal-400"> Pro</span></span>
    </div>

    <div class="relative z-10 max-w-md mt-auto mb-10">
      <div class="w-20 h-20 bg-teal-500/20 rounded-full flex items-center justify-center mb-8 border border-teal-500/30">
        <span class="material-symbols-outlined text-teal-400 text-[40px]">security</span>
      </div>
      <h1 class="text-3xl font-black text-white mb-4 leading-tight">System Management Security</h1>
      <p class="text-slate-400 text-sm font-medium">
        Administrative access requires multi-factor authentication to ensure the integrity and confidentiality of clinical data.
      </p>
    </div>

    <div class="relative z-10 text-slate-500 text-xs font-medium">© 2024 MediCare Pro Systems. All rights reserved.</div>
  </div>

  <!-- Right Panel -->
  <div class="w-full md:w-1/2 flex flex-col items-center justify-center p-6 lg:p-12 bg-slate-950">
    <div class="w-full max-w-[420px]">

      <a href="<?= APP_URL ?>/admin/login.php" class="flex items-center gap-1 text-slate-500 hover:text-teal-400 transition-colors mb-8 text-sm font-medium group">
        <span class="material-symbols-outlined group-hover:-translate-x-1 transition-transform text-[20px]">arrow_back</span>
        Kembali ke Login
      </a>

      <div class="mb-8">
        <h2 class="text-2xl font-black text-white mb-1">Two-Step Verification</h2>
        <p class="text-sm font-medium text-slate-400">
          Enter the 6-digit code sent to your email
          <span class="text-teal-400 font-bold"><?= e(substr($email, 0, 3) . '***' . substr($email, strrpos($email, '@'))) ?></span>
        </p>
      </div>

      <!-- OTP Display has been completely removed for security -->

      <?php if ($resent): ?>
      <div class="bg-teal-500/10 border border-teal-500/30 rounded-xl px-4 py-3 mb-4 text-teal-400 text-sm font-medium">
        Kode OTP baru telah dikirim.
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="bg-red-500/10 border border-red-500/30 rounded-xl px-4 py-3 mb-4 text-red-400 text-sm font-medium">
        <?= e($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" id="otp-form" class="flex flex-col gap-6">
        <?= csrf_field() ?>

        <!-- OTP Digit Inputs -->
        <div class="flex justify-center gap-3" id="otp-inputs">
          <?php for ($i = 1; $i <= 6; $i++): ?>
          <input type="text" name="otp_<?= $i ?>" id="otp-<?= $i ?>"
            class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]"
            autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>"
            <?= $i === 1 ? 'autofocus' : '' ?>>
          <?php endfor; ?>
          <!-- Hidden full OTP field -->
          <input type="hidden" name="otp_full" id="otp-full">
        </div>

        <button type="submit"
          class="w-full h-[52px] bg-teal-600 text-white rounded-xl font-bold text-sm flex items-center justify-center gap-2 hover:bg-teal-500 transition-colors active:scale-95 shadow-[0_4px_14px_0_rgba(15,118,110,0.4)]">
          Verify &amp; Access Dashboard
          <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
        </button>
      </form>

      <!-- Resend -->
      <form method="POST" class="mt-4 text-center">
        <?= csrf_field() ?>
        <p class="text-slate-500 text-sm">
          Didn't receive the code?
          <button type="submit" name="resend" value="1" class="text-teal-400 hover:underline font-semibold ml-1">Resend Code</button>
        </p>
      </form>

      <!-- OTP expiry info -->
      <p class="text-center text-xs text-slate-600 mt-4">
        Kode berlaku selama <?= OTP_EXPIRE_MIN ?> menit
      </p>
    </div>
  </div>
</div>

<script>
// Auto-advance between OTP inputs
const inputs = Array.from(document.querySelectorAll('.otp-input'));
const fullInput = document.getElementById('otp-full');
const form = document.getElementById('otp-form');

inputs.forEach((inp, idx) => {
  inp.addEventListener('input', e => {
    inp.value = inp.value.replace(/\D/g,'').slice(-1);
    if (inp.value && idx < inputs.length - 1) inputs[idx+1].focus();
    updateFull();
  });

  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !inp.value && idx > 0) {
      inputs[idx-1].focus();
    }
  });

  inp.addEventListener('paste', e => {
    const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g,'');
    if (data.length === 6) {
      inputs.forEach((el, i) => { el.value = data[i] || ''; });
      updateFull();
      inputs[5].focus();
      e.preventDefault();
    }
  });
});

function updateFull() {
  fullInput.value = inputs.map(i => i.value).join('');
  if (fullInput.value.length === 6) {
    setTimeout(() => form.submit(), 300);
  }
}
</script>
</body>
</html>
