<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('admin');

$user = current_user();
$pdo  = db();

// Handle Delete Doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doctor_id'])) {
    csrf_abort();
    $deleteId = (int) $_POST['delete_doctor_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'");
        $stmt->execute([$deleteId]);
        audit_log('delete_doctor', $user['id'], "Doctor ID: $deleteId");
        flash('success', 'Dokter berhasil dihapus.');
    } catch (Exception $e) {
        flash('error', 'Gagal menghapus dokter: ' . $e->getMessage());
    }
    header('Location: doctors.php');
    exit;
}

// Fetch all doctors with their profiles
$stmt = $pdo->query(
    "SELECT u.id, u.name, u.email, u.is_active, u.created_at, 
            dp.specialization, dp.license_number, dp.phone, dp.is_available
     FROM users u
     LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
     WHERE u.role = 'doctor'
     ORDER BY u.created_at DESC"
);
$doctors = $stmt->fetchAll();

$success = flash('success');
$error   = flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Manajemen Dokter</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex">

<!-- Sidebar -->
<aside class="w-64 bg-white border-r border-slate-200 flex-shrink-0 flex-col hidden md:flex">
  <div class="p-6 border-b border-slate-100">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 bg-primary-fixed flex items-center justify-center rounded-lg transform rotate-45">
        <span class="material-symbols-outlined text-primary transform -rotate-45 text-[16px]" style="font-variation-settings:'FILL' 1;">medical_services</span>
      </div>
      <span class="font-extrabold tracking-tight text-slate-900 text-sm">CareConnect <span class="text-primary text-xs font-bold">Admin</span></span>
    </div>
  </div>

  <div class="p-4 border-b border-slate-100">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 bg-primary-fixed text-primary rounded-full flex items-center justify-center font-bold text-sm">
        <?= e(initials($user['name'])) ?>
      </div>
      <div>
        <p class="text-sm font-bold text-slate-800"><?= e($user['name']) ?></p>
        <p class="text-xs text-slate-400">Administrator</p>
      </div>
    </div>
  </div>

  <nav class="flex-1 p-4 space-y-1">
    <?php
    $navItems = [
      ['icon'=>'dashboard',   'label'=>'Beranda',        'href'=>'dashboard.php', 'active'=>false],
      ['icon'=>'stethoscope', 'label'=>'Daftar Dokter',  'href'=>'doctors.php',   'active'=>true],
      ['icon'=>'event',       'label'=>'Reservasi',      'href'=>'#',             'active'=>false],
      ['icon'=>'group',       'label'=>'Pasien',         'href'=>'#',             'active'=>false],
      ['icon'=>'history',     'label'=>'Riwayat',        'href'=>'#',             'active'=>false],
      ['icon'=>'person',      'label'=>'Profil',         'href'=>'#',             'active'=>false],
    ];
    foreach ($navItems as $item):
      $cls = $item['active']
        ? 'bg-primary-fixed text-primary font-bold'
        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800 font-medium';
    ?>
    <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
      <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
      <?= e($item['label']) ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="p-4 border-t border-slate-100">
    <a href="<?= APP_URL ?>/admin/logout.php"
       class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors">
      <span class="material-symbols-outlined text-[20px]">logout</span>
      Keluar
    </a>
  </div>
</aside>

<!-- Main -->
<main class="flex-1 overflow-auto p-6 lg:p-8">

  <!-- Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
      <h1 class="text-2xl font-black text-slate-900">Manajemen Dokter</h1>
      <p class="text-slate-500 font-medium mt-1">Kelola data profesional medis di klinik Anda.</p>
    </div>
    <a href="doctor_add.php" class="inline-flex items-center gap-2 bg-teal-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-teal-700 transition-colors shadow-sm active:scale-95">
      <span class="material-symbols-outlined text-[18px]">add</span>
      Tambah Dokter
    </a>
  </div>

  <?= alert_html($success, 'success') ?>
  <?= alert_html($error, 'error') ?>

  <!-- Data Table -->
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <div class="relative w-full max-w-xs">
        <span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400 text-[20px]">search</span>
        <input type="text" placeholder="Cari dokter..." class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500">
      </div>
      <div class="flex items-center gap-2">
        <button class="p-2 border border-slate-200 rounded-lg text-slate-500 hover:bg-slate-50">
          <span class="material-symbols-outlined text-[20px]">filter_list</span>
        </button>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100">
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Profil Dokter</th>
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Kontak & STR</th>
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Spesialisasi</th>
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Status</th>
            <th class="text-right px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (empty($doctors)): ?>
          <tr>
            <td colspan="5" class="px-6 py-12 text-center text-slate-400">
              <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">person_off</span>
              <p>Belum ada data dokter.</p>
            </td>
          </tr>
          <?php else: ?>
          <?php foreach ($doctors as $doc): ?>
          <tr class="hover:bg-slate-50/50 transition-colors group">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-50 text-teal-600 rounded-full flex items-center justify-center font-bold text-sm">
                  <?= e(initials($doc['name'])) ?>
                </div>
                <div>
                  <p class="font-bold text-slate-900"><?= e($doc['name']) ?></p>
                  <p class="text-xs text-slate-500">Bergabung <?= format_date($doc['created_at'], 'M Y') ?></p>
                </div>
              </div>
            </td>
            <td class="px-6 py-4">
              <p class="text-slate-700 font-medium"><?= e($doc['email']) ?></p>
              <div class="text-xs text-slate-500 mt-0.5 flex flex-col gap-0.5">
                <span><?= e($doc['phone'] ?? '-') ?></span>
                <span class="font-mono text-slate-400">STR: <?= e($doc['license_number'] ?? 'Belum ada') ?></span>
              </div>
            </td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold border border-slate-200">
                <?= e($doc['specialization'] ?? 'Umum') ?>
              </span>
            </td>
            <td class="px-6 py-4">
              <?php if ($doc['is_active'] && $doc['is_available']): ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-green-600">
                  <span class="w-2 h-2 rounded-full bg-green-500"></span> Aktif
                </span>
              <?php elseif ($doc['is_active'] && !$doc['is_available']): ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-600">
                  <span class="w-2 h-2 rounded-full bg-amber-500"></span> Cuti/Sibuk
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400">
                  <span class="w-2 h-2 rounded-full bg-slate-400"></span> Nonaktif
                </span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-teal-600 hover:border-teal-200 flex items-center justify-center shadow-sm" title="Edit Dokter">
                  <span class="material-symbols-outlined text-[18px]">edit</span>
                </button>
                <form method="POST" onsubmit="return confirm('Yakin ingin menghapus dokter ini? Semua data terkait (termasuk jadwal) akan terhapus.');" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete_doctor_id" value="<?= $doc['id'] ?>">
                  <button type="submit" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 hover:text-red-600 hover:border-red-200 hover:bg-red-50 flex items-center justify-center shadow-sm" title="Hapus Dokter">
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
      <span>Menampilkan <?= count($doctors) ?> dokter</span>
      <div class="flex gap-1">
        <button class="px-2 py-1 border border-slate-200 rounded disabled:opacity-50">Sebelumnnya</button>
        <button class="px-2 py-1 border border-slate-200 rounded disabled:opacity-50">Selanjutnya</button>
      </div>
    </div>
  </div>

</main>

</body>
</html>
