<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('admin');

$user = current_user();
$pdo  = db();
$error = '';

$docId = (int) ($_GET['id'] ?? 0);
if (!$docId) {
    header('Location: doctors.php');
    exit;
}

// Fetch doctor
$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.is_active,
            dp.specialization, dp.license_number, dp.phone, dp.is_available
     FROM users u
     LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
     WHERE u.id = ? AND u.role = 'doctor' LIMIT 1"
);
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    flash('error', 'Dokter tidak ditemukan.');
    header('Location: doctors.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $name           = sanitize_string($_POST['name'] ?? '', 150);
    $email          = sanitize_email($_POST['email'] ?? '');
    $specialization = sanitize_string($_POST['specialization'] ?? '', 100);
    $license        = sanitize_string($_POST['license_number'] ?? '', 50);
    $phone          = sanitize_string($_POST['phone'] ?? '', 20);
    $isActive       = isset($_POST['is_active']) ? 1 : 0;
    $isAvailable    = isset($_POST['is_available']) ? 1 : 0;
    $newPassword    = $_POST['new_password'] ?? '';

    if (empty($name) || empty($email)) {
        $error = 'Nama dan Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        // Check duplicate email (exclude self)
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $dup->execute([$email, $docId]);
        if ($dup->fetch()) {
            $error = 'Email sudah digunakan oleh akun lain.';
        } else {
            $pdo->beginTransaction();
            try {
                // Update user
                if (!empty($newPassword)) {
                    $passErrors = validate_password_strength($newPassword);
                    if (!empty($passErrors)) {
                        throw new Exception('Kata sandi: ' . implode(', ', $passErrors));
                    }
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                    $pdo->prepare('UPDATE users SET name=?, email=?, password_hash=?, is_active=? WHERE id=?')
                        ->execute([$name, $email, $hash, $isActive, $docId]);
                } else {
                    $pdo->prepare('UPDATE users SET name=?, email=?, is_active=? WHERE id=?')
                        ->execute([$name, $email, $isActive, $docId]);
                }

                // Upsert doctor_profile
                $exists = $pdo->prepare('SELECT id FROM doctor_profiles WHERE user_id = ?');
                $exists->execute([$docId]);
                if ($exists->fetch()) {
                    $pdo->prepare(
                        'UPDATE doctor_profiles SET specialization=?, license_number=?, phone=?, is_available=? WHERE user_id=?'
                    )->execute([$specialization, $license, $phone, $isAvailable, $docId]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO doctor_profiles (user_id, specialization, license_number, phone, is_available) VALUES (?,?,?,?,?)'
                    )->execute([$docId, $specialization, $license, $phone, $isAvailable]);
                }

                $pdo->commit();
                audit_log('edit_doctor', $user['id'], "Doctor ID: $docId");
                flash('success', 'Data dokter berhasil diperbarui.');
                header('Location: doctors.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal menyimpan: ' . $e->getMessage();
            }
        }
    }
    // Reload doc with posted values for repopulation
    $doc['name']          = $_POST['name'] ?? $doc['name'];
    $doc['email']         = $_POST['email'] ?? $doc['email'];
    $doc['specialization']= $_POST['specialization'] ?? $doc['specialization'];
    $doc['license_number']= $_POST['license_number'] ?? $doc['license_number'];
    $doc['phone']         = $_POST['phone'] ?? $doc['phone'];
    $doc['is_active']     = $isActive ?? $doc['is_active'];
    $doc['is_available']  = $isAvailable ?? $doc['is_available'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Edit Dokter</title>
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
      <h1 class="text-2xl font-black text-slate-900">Edit Dokter</h1>
      <p class="text-slate-500 font-medium mt-1">Perbarui informasi dan profil dokter.</p>
    </div>
  </div>

  <?= alert_html($error, 'error') ?>

  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-4xl">
    <form method="POST" class="p-6 md:p-8 space-y-8">
      <?= csrf_field() ?>

      <!-- 1. Akun -->
      <div>
        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-teal-600 text-[18px]">account_circle</span>
          Informasi Akun
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Nama Lengkap & Gelar *</label>
            <input type="text" name="name" required value="<?= e($doc['name']) ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Email *</label>
            <input type="email" name="email" required value="<?= e($doc['email']) ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
          </div>
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Kata Sandi Baru <span class="normal-case font-normal text-slate-400">(kosongkan jika tidak ingin ubah)</span></label>
            <input type="password" name="new_password" placeholder="Min. 8 karakter, huruf & angka"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
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
            <input type="text" name="specialization" list="spec-list" value="<?= e($doc['specialization'] ?? '') ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
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
            <input type="text" name="license_number" value="<?= e($doc['license_number'] ?? '') ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all font-mono">
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Nomor Handphone</label>
            <input type="text" name="phone" value="<?= e($doc['phone'] ?? '') ?>"
              class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition-all">
          </div>
        </div>
      </div>

      <hr class="border-slate-100">

      <!-- 3. Status -->
      <div>
        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4 flex items-center gap-2">
          <span class="material-symbols-outlined text-teal-600 text-[18px]">toggle_on</span>
          Status Akun
        </h3>
        <div class="flex flex-col gap-4">
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" <?= $doc['is_active'] ? 'checked' : '' ?>
              class="w-5 h-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            <span class="text-sm font-medium text-slate-700">Akun Aktif (dokter dapat login)</span>
          </label>
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_available" value="1" <?= $doc['is_available'] ? 'checked' : '' ?>
              class="w-5 h-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            <span class="text-sm font-medium text-slate-700">Tersedia untuk Reservasi</span>
          </label>
        </div>
      </div>

      <div class="pt-4 flex items-center justify-between">
        <a href="doctors.php" class="text-sm font-bold text-slate-500 hover:text-slate-700">Batal</a>
        <button type="submit" class="inline-flex items-center gap-2 bg-teal-600 text-white px-8 py-3 rounded-xl font-bold text-sm hover:bg-teal-700 transition-colors shadow-sm active:scale-95">
          <span class="material-symbols-outlined text-[20px]">save</span>
          Simpan Perubahan
        </button>
      </div>
    </form>
  </div>
</main>

</body>
</html>
