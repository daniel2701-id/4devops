<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('admin');

$user = current_user();
$pdo  = db();

$patients = [];
$search   = trim($_GET['q'] ?? '');

try {
    if ($search !== '') {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.created_at, u.is_active,
                    pp.gender, pp.phone, pp.birth_date,
                    COUNT(a.id) AS total_appts
             FROM users u
             LEFT JOIN patient_profiles pp ON pp.user_id = u.id
             LEFT JOIN appointments a ON a.patient_id = u.id
             WHERE u.role = 'patient' AND (u.name LIKE ? OR u.email LIKE ?)
             GROUP BY u.id ORDER BY u.created_at DESC"
        );
        $like = '%' . $search . '%';
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.created_at, u.is_active,
                    pp.gender, pp.phone, pp.birth_date,
                    COUNT(a.id) AS total_appts
             FROM users u
             LEFT JOIN patient_profiles pp ON pp.user_id = u.id
             LEFT JOIN appointments a ON a.patient_id = u.id
             WHERE u.role = 'patient'
             GROUP BY u.id ORDER BY u.created_at DESC"
        );
        $stmt->execute();
    }
    $patients = $stmt->fetchAll();
} catch (Exception $e) {
    // Graceful degradation
}

$totalPatients = count($patients);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Daftar Pasien</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
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
      ['icon'=>'stethoscope', 'label'=>'Daftar Dokter', 'href'=>'doctors.php',   'active'=>false],
      ['icon'=>'group',       'label'=>'Daftar Pasien', 'href'=>'patients.php',  'active'=>true],
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

<!-- Main -->
<main class="flex-1 overflow-auto p-6 lg:p-8">

  <!-- Header -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
      <h1 class="text-2xl font-black text-slate-900">Daftar Pasien</h1>
      <p class="text-slate-500 font-medium mt-1">Total <span class="font-bold text-primary"><?= $totalPatients ?></span> pasien terdaftar.</p>
    </div>
  </div>

  <!-- Search -->
  <form method="GET" class="mb-6 flex gap-3">
    <div class="relative flex-1 max-w-md">
      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama atau email pasien…"
             class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white text-sm focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
    </div>
    <button type="submit" class="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary-light transition-colors">Cari</button>
    <?php if ($search): ?>
    <a href="patients.php" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-200 transition-colors">Reset</a>
    <?php endif; ?>
  </form>

  <!-- Table -->
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($patients)): ?>
    <div class="p-12 text-center text-slate-400">
      <span class="material-symbols-outlined text-[48px] text-slate-300">person_off</span>
      <p class="font-medium mt-3"><?= $search ? 'Tidak ada pasien yang cocok dengan pencarian.' : 'Belum ada pasien terdaftar.' ?></p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50 border-b border-slate-100">
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Nama Pasien</th>
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Email</th>
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">No. Telepon</th>
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Total Kunjungan</th>
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Terdaftar</th>
            <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($patients as $p): ?>
          <tr class="hover:bg-slate-50/50 transition-colors">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-primary-fixed text-primary rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0">
                  <?= e(initials($p['name'])) ?>
                </div>
                <div>
                  <p class="font-bold text-slate-900"><?= e($p['name']) ?></p>
                  <p class="text-xs text-slate-400"><?= $p['gender'] ? e(ucfirst($p['gender'])) : '-' ?></p>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 text-slate-600"><?= e($p['email']) ?></td>
            <td class="px-6 py-4 text-slate-600"><?= e($p['phone'] ?? '-') ?></td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center gap-1 font-bold text-slate-900">
                <span class="material-symbols-outlined text-[16px] text-primary">event</span>
                <?= (int) $p['total_appts'] ?> kunjungan
              </span>
            </td>
            <td class="px-6 py-4 text-slate-500 text-xs"><?= format_date($p['created_at'], 'd M Y') ?></td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= $p['is_active'] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>">
                <?= $p['is_active'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</main>
</body>
</html>
