<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('doctor');

$user = current_user();
$pdo  = db();

// Handle Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appt_id'], $_POST['status'])) {
    csrf_abort();
    $apptId = (int) $_POST['appt_id'];
    $status = in_array($_POST['status'], ['waiting', 'in_session', 'finished', 'cancelled']) ? $_POST['status'] : 'waiting';
    
    // Only allow changing if the appointment belongs to this doctor
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
    $stmt->execute([$status, $apptId, $user['id']]);
    flash('success', 'Status jadwal berhasil diperbarui.');
    header('Location: jadwal.php');
    exit;
}

$dateFilter = $_GET['date'] ?? date('Y-m-d');

// Fetch appointments for selected date
$stmt = $pdo->prepare(
    "SELECT a.*, p.name AS patient_name, pp.gender, pp.phone 
     FROM appointments a 
     JOIN users p ON p.id = a.patient_id 
     LEFT JOIN patient_profiles pp ON pp.user_id = p.id
     WHERE a.doctor_id = ? AND DATE(a.scheduled_at) = ?
     ORDER BY a.scheduled_at ASC"
);
$stmt->execute([$user['id'], $dateFilter]);
$appointments = $stmt->fetchAll();

$success = flash('success');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Jadwal & Sesi Dokter</title>
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
        <span class="font-extrabold tracking-tight text-slate-900 text-sm">CareConnect <span class="text-primary text-xs font-bold">Medical Portal</span></span>
      </div>
    </div>

    <!-- Doctor info -->
    <div class="p-4 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-primary-fixed text-primary rounded-full flex items-center justify-center font-bold text-sm">
          <?= e(initials($user['name'])) ?>
        </div>
        <div>
          <p class="text-sm font-bold text-slate-800"><?= e($user['name']) ?></p>
          <p class="text-xs text-slate-400">Dokter</p>
        </div>
      </div>
    </div>

    <nav class="flex-1 p-4 space-y-1">
      <?php
      $navItems = [
        ['icon'=>'home',          'label'=>'Beranda',    'href'=>'dashboard.php', 'active'=>false],
        ['icon'=>'calendar_month','label'=>'Jadwal Saya','href'=>'jadwal.php',    'active'=>true],
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
      <a href="<?= APP_URL ?>/doctor/logout.php"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors">
        <span class="material-symbols-outlined text-[20px]">logout</span>
        Keluar
      </a>
    </div>
  </aside>

  <!-- Main -->
  <main class="flex-1 overflow-auto p-6 lg:p-8">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-black text-slate-900">Manajemen Jadwal</h1>
        <p class="text-slate-500 font-medium mt-1">Kelola sesi konsultasi Anda hari ini.</p>
      </div>
      
      <!-- Date Picker Form -->
      <form method="GET" class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl p-1 shadow-sm">
        <input type="date" name="date" value="<?= e($dateFilter) ?>" class="bg-transparent text-sm font-bold text-slate-700 outline-none px-3 py-1">
        <button type="submit" class="bg-primary text-white rounded-lg px-3 py-1.5 text-xs font-bold hover:bg-primary-light">Filter</button>
      </form>
    </div>

    <?= alert_html($success, 'success') ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <?php if (empty($appointments)): ?>
        <div class="p-12 text-center text-slate-400">
            <span class="material-symbols-outlined text-[48px] text-slate-300">event_busy</span>
            <p class="font-medium mt-3">Tidak ada jadwal pada tanggal <?= format_date($dateFilter, 'd M Y') ?>.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Waktu</th>
                        <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Pasien</th>
                        <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Keluhan / Alasan</th>
                        <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Status Sesi</th>
                        <th class="text-right px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Aksi Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php 
                    $statusMap = [
                        'waiting'    => ['bg-amber-100', 'text-amber-700', 'Menunggu'],
                        'in_session' => ['bg-blue-100', 'text-blue-700', 'Dalam Sesi'],
                        'finished'   => ['bg-slate-100', 'text-slate-600', 'Selesai'],
                        'cancelled'  => ['bg-red-100', 'text-red-700', 'Dibatalkan']
                    ];
                    foreach ($appointments as $appt): 
                        [$bg, $text, $label] = $statusMap[$appt['status']];
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-black text-slate-900 text-base"><?= date('H:i', strtotime($appt['scheduled_at'])) ?></span>
                            <span class="text-xs font-bold text-slate-400 block uppercase">WIB</span>
                        </td>
                        <td class="px-6 py-4">
                            <p class="font-bold text-slate-900"><?= e($appt['patient_name']) ?></p>
                            <p class="text-xs text-slate-500"><?= e($appt['phone'] ?? '-') ?></p>
                        </td>
                        <td class="px-6 py-4 max-w-xs">
                            <p class="text-slate-600 truncate" title="<?= e($appt['reason']) ?>"><?= e($appt['reason'] ?: '-') ?></p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= $bg ?> <?= $text ?>">
                                <?= $label ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form method="POST" class="inline-flex items-center gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="appt_id" value="<?= $appt['id'] ?>">
                                <select name="status" onchange="this.form.submit()" class="text-xs font-bold border border-slate-200 rounded-lg bg-slate-50 px-2 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                                    <option value="waiting" <?= $appt['status']==='waiting' ? 'selected':'' ?>>Menunggu</option>
                                    <option value="in_session" <?= $appt['status']==='in_session' ? 'selected':'' ?>>Mulai Sesi</option>
                                    <option value="finished" <?= $appt['status']==='finished' ? 'selected':'' ?>>Selesai</option>
                                    <option value="cancelled" <?= $appt['status']==='cancelled' ? 'selected':'' ?>>Batalkan</option>
                                </select>
                            </form>
                            <?php if ($appt['status'] === 'in_session' || $appt['status'] === 'finished'): ?>
                                <a href="rekam_medis.php?appt_id=<?= $appt['id'] ?>" class="ml-2 inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors" title="Isi Rekam Medis">
                                    <span class="material-symbols-outlined text-[18px]">medical_information</span>
                                </a>
                            <?php endif; ?>
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
