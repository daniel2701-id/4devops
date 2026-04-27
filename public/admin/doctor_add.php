<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('admin');

$user = current_user();
$pdo  = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $name           = sanitize_string($_POST['name'] ?? '', 150);
    $email          = sanitize_email($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? '';
    $specialization = sanitize_string($_POST['specialization'] ?? '', 100);
    $license        = sanitize_string($_POST['license_number'] ?? '', 50);
    $phone          = sanitize_string($_POST['phone'] ?? '', 20);

    $passErrors = validate_password_strength($password);

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Nama, Email, dan Kata Sandi wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (!empty($passErrors)) {
        $error = 'Kata sandi: ' . implode(', ', $passErrors) . '.';
    } else {
        // Check duplicate email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar di sistem.';
        } else {
            // Insert
            $pdo->beginTransaction();
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, is_active, email_verified) VALUES (?, ?, ?, ?, 1, 1)');
                $stmt->execute([$name, $email, $hash, 'doctor']);
                $newDocId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare('INSERT INTO doctor_profiles (user_id, specialization, license_number, phone) VALUES (?, ?, ?, ?)');
                $stmt->execute([$newDocId, $specialization, $license, $phone]);

                $pdo->commit();
                audit_log('create_doctor', $user['id'], "Doctor ID: $newDocId");
                flash('success', 'Dokter baru berhasil ditambahkan.');
                header('Location: doctors.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal menyimpan data: ' . $e->getMessage();
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
<title>CareConnect – Tambah Dokter</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#0f766e') ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex">

<!-- Sidebar -->
<aside class="w-64 bg-emerald-700 text-white border-r border-emerald-800 flex-shrink-0 flex-col hidden md:flex shadow-xl">
  <div class="p-6 border-b border-emerald-600/50">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 bg-white/20 flex items-center justify-center rounded-lg transform rotate-45">
        <span class="material-symbols-outlined text-white transform -rotate-45 text-[16px]" style="font-variation-settings:'FILL' 1;">medical_services</span>
      </div>
      <span class="font-extrabold tracking-tight text-white text-sm">CareConnect <span class="text-emerald-200 text-xs font-bold">Admin</span></span>
    </div>
  </div>

  <div class="p-4 border-b border-emerald-600/50">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 bg-white/20 text-white rounded-full flex items-center justify-center font-bold text-sm">
        <?= e(initials($user['name'])) ?>
      </div>
      <div>
        <p class="text-sm font-bold text-white"><?= e($user['name']) ?></p>
        <p class="text-xs text-emerald-200">Administrator</p>
      </div>
    </div>
  </div>

  <nav class="flex-1 p-4 space-y-1">
    <?php
    $navItems = [
      ['icon'=>'dashboard',   'label'=>'Beranda',       'href'=>'dashboard.php', 'active'=>false],
      ['icon'=>'stethoscope', 'label'=>'Daftar Dokter', 'href'=>'doctors.php',   'active'=>true],
      ['icon'=>'group',       'label'=>'Daftar Pasien', 'href'=>'patients.php',  'active'=>false],
    ];
    foreach ($navItems as $item):
      $cls = $item['active']
        ? 'bg-white/20 text-white font-bold shadow-sm'
        : 'text-emerald-100 hover:bg-white/10 hover:text-white font-medium';
    ?>
    <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
      <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
      <?= e($item['label']) ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="p-4 border-t border-emerald-600/50">
    <a href="<?= APP_URL ?>/admin/logout.php"
       class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-emerald-200 hover:bg-red-500/20 hover:text-red-200 transition-colors">
      <span class="material-symbols-outlined text-[20px]">logout</span>
      Keluar
    </a>
  </div>
</aside>

<!-- Main Content -->
<main class="flex-1 overflow-auto p-6 lg:p-8">

  <!-- Header -->
  <div class="mb-8 flex items-center gap-3">
    <a href="doctors.php" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-slate-500 hover:text-teal-600 hover:border-teal-200 transition-colors shadow-sm">
      <span class="material-symbols-outlined text-[20px]">arrow_back</span>
    </a>
    <div>
      <h1 class="text-2xl font-black text-slate-900">Tambah Dokter Baru</h1>
      <p class="text-slate-500 font-medium mt-1">Buat akun akses dan lengkapi profil profesional medis.</p>
    </div>
  </div>

  <?= alert_html($error, 'error') ?>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-4xl">
    <form method="POST" class="p-6 md:p-8 space-y-8">
      <?= csrf_field() ?>

      <!-- 1. Akun Autentikasi -->
      <div>
        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-teal-600 text-[18px]">account_circle</span>
          Informasi Akun
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Nama Lengkap & Gelar *</label>
            <input type="text" name="name" placeholder="dr. Nama Lengkap, Sp.A" required
              value="<?= e($_POST['name'] ?? '') ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Email Profesional *</label>
            <input type="email" name="email" placeholder="contoh@klinik.com" required
              value="<?= e($_POST['email'] ?? '') ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Kata Sandi Akses *</label>
            <input type="password" name="password" placeholder="Min. 8 karakter, huruf & angka" required
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
            <p class="text-xs text-slate-400 ml-1">Harus mengandung huruf besar, kecil, angka, dan simbol.</p>
          </div>
        </div>
      </div>

      <hr class="border-slate-100">

      <!-- 2. Profil Medis -->
      <div>
        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-teal-600 text-[18px]">badge</span>
          Profil Profesional
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Spesialisasi</label>
            <input type="text" name="specialization" list="spec-list" placeholder="Pilih atau ketik spesialisasi..." required
              value="<?= e($_POST['specialization'] ?? '') ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all">
            <datalist id="spec-list">
              <option value="Umum">
              <option value="Penyakit Dalam">
              <option value="Anak">
              <option value="Kandungan & Kebidanan">
              <option value="Gigi & Mulut">
            </datalist>
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Nomor STR</label>
            <input type="text" name="license_number" placeholder="Nomor Surat Tanda Registrasi"
              value="<?= e($_POST['license_number'] ?? '') ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all font-mono">
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Nomor Handphone</label>
            <input type="text" name="phone" placeholder="0812xxxxxx"
              value="<?= e($_POST['phone'] ?? '') ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
          </div>
        </div>
      </div>

      <div class="pt-4 flex justify-end">
        <button type="submit" class="inline-flex items-center gap-2 bg-teal-600 text-white px-8 py-3 rounded-xl font-bold text-sm hover:bg-teal-700 transition-colors shadow-sm active:scale-95">
          <span class="material-symbols-outlined text-[20px]">save</span>
          Simpan Data Dokter
        </button>
      </div>
    </form>
  </div>
</main>

</body>
</html>
