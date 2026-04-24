<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('admin');

$user = current_user();
$pdo  = db();

// ---- Stats ----
$totalPatients = 0; $activeDoctors = 0; $activeAppts = 0; $pendingConf = 0;
$newPatientsWeek = 0; $recentLogs = []; $recentAppts = [];
$weeklyData = []; $busyDoctors = []; $statusBreakdown = [];

try {
    $totalPatients   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
    $activeDoctors   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor' AND is_active=1")->fetchColumn();
    $activeAppts     = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('waiting','in_session')")->fetchColumn();
    $pendingConf     = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='waiting'")->fetchColumn();
    $newPatientsWeek = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient' AND created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();

    // Weekly appointment trend (last 7 days)
    $weeklyRaw = $pdo->query(
        "SELECT DATE(scheduled_at) AS day, COUNT(*) AS cnt
         FROM appointments
         WHERE scheduled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(scheduled_at)"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $weeklyData[] = [
            'label' => date('d/m', strtotime($d)),
            'count' => (int) ($weeklyRaw[$d] ?? 0),
        ];
    }

    // Busiest doctors
    $busyDoctors = $pdo->query(
        "SELECT u.name, dp.specialization,
                COUNT(a.id) AS total,
                SUM(a.status='finished') AS done,
                SUM(a.status='waiting') AS waiting
         FROM users u
         JOIN doctor_profiles dp ON dp.user_id = u.id
         LEFT JOIN appointments a ON a.doctor_id = u.id
         WHERE u.role='doctor'
         GROUP BY u.id
         ORDER BY total DESC LIMIT 5"
    )->fetchAll();

    // Status breakdown
    $statusBreakdown = $pdo->query(
        "SELECT status, COUNT(*) AS cnt FROM appointments GROUP BY status"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    $recentLogs = $pdo->query(
        "SELECT al.*, u.name AS user_name FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.created_at DESC LIMIT 8"
    )->fetchAll();

    $recentAppts = $pdo->query(
        "SELECT a.*, p.name AS patient_name, d.name AS doctor_name
         FROM appointments a
         JOIN users p ON p.id = a.patient_id
         JOIN users d ON d.id = a.doctor_id
         ORDER BY a.created_at DESC LIMIT 5"
    )->fetchAll();

} catch (Exception $e) { /* Graceful */ }

$totalAppts = array_sum($statusBreakdown) ?: 1;
$weeklyMax  = max(array_column($weeklyData, 'count') ?: [1]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Admin Dashboard</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>
body { font-family: 'Inter', sans-serif; }
.bar-fill { transition: height .5s cubic-bezier(.4,0,.2,1); }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
.animate-in { animation: fadeIn .4s ease forwards; }
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

<div class="flex min-h-screen">

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
      <?php $navItems = [
        ['icon'=>'dashboard',   'label'=>'Beranda',       'href'=>'dashboard.php', 'active'=>true],
        ['icon'=>'stethoscope', 'label'=>'Daftar Dokter', 'href'=>'doctors.php',   'active'=>false],
        ['icon'=>'group',       'label'=>'Daftar Pasien', 'href'=>'patients.php',  'active'=>false],
      ];
      foreach ($navItems as $item):
        $cls = $item['active'] ? 'bg-white/20 text-white font-bold shadow-sm' : 'text-emerald-100 hover:bg-white/10 hover:text-white font-medium'; ?>
      <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
        <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
        <?= e($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-emerald-600/50">
      <a href="<?= APP_URL ?>/admin/logout.php"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-emerald-200 hover:bg-red-500/20 hover:text-red-200 transition-colors">
        <span class="material-symbols-outlined text-[20px]">logout</span>Keluar
      </a>
    </div>
  </aside>

  <!-- Main -->
  <main class="flex-1 overflow-auto p-6 lg:p-8">

    <!-- Header -->
    <div class="mb-8 flex items-start justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-black text-slate-900">Dashboard Overview</h1>
        <p class="text-slate-500 font-medium mt-1">Selamat datang, <?= e($user['name']) ?>. Ringkasan sistem hari ini.</p>
      </div>
      <div class="text-right">
        <p class="text-sm font-bold text-slate-700"><?= date('l, d F Y') ?></p>
        <p class="text-xs text-slate-400"><?= date('H:i') ?> WIB</p>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <?php $stats = [
        ['label'=>'Total Pasien',       'value'=>number_format($totalPatients), 'icon'=>'group',           'color'=>'blue',   'sub'=>"+{$newPatientsWeek} minggu ini", 'badge'=>'emerald'],
        ['label'=>'Dokter Aktif',       'value'=>$activeDoctors,                'icon'=>'stethoscope',     'color'=>'purple', 'sub'=>'Siap melayani',                  'badge'=>''],
        ['label'=>'Reservasi Aktif',    'value'=>$activeAppts,                  'icon'=>'event_available', 'color'=>'teal',   'sub'=>"{$pendingConf} menunggu",         'badge'=>'amber'],
        ['label'=>'Selesai Bulan Ini',  'value'=>($statusBreakdown['finished'] ?? 0), 'icon'=>'task_alt', 'color'=>'green',  'sub'=>'Konsultasi sukses',               'badge'=>''],
      ];
      foreach ($stats as $s):
        $iconBg = [
          'blue'=>'bg-blue-50 text-blue-600','purple'=>'bg-purple-50 text-purple-600',
          'teal'=>'bg-teal-50 text-teal-600','green'=>'bg-green-50 text-green-600'
        ][$s['color']];
      ?>
      <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm animate-in">
        <div class="flex items-start justify-between mb-3">
          <div class="w-11 h-11 <?= $iconBg ?> rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[22px]"><?= $s['icon'] ?></span>
          </div>
          <?php if ($s['badge'] === 'emerald'): ?>
          <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full"><?= $s['sub'] ?></span>
          <?php elseif ($s['badge'] === 'amber'): ?>
          <span class="text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full"><?= $s['sub'] ?></span>
          <?php endif; ?>
        </div>
        <p class="text-3xl font-black text-slate-900"><?= $s['value'] ?></p>
        <p class="text-xs font-medium text-slate-500 mt-1"><?= e($s['label']) ?></p>
        <?php if (!$s['badge']): ?>
        <p class="text-xs text-slate-400 mt-0.5"><?= $s['sub'] ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts Row -->
    <div class="grid lg:grid-cols-3 gap-6 mb-6">

      <!-- Bar Chart: Weekly Appointments -->
      <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h3 class="font-bold text-slate-900">Tren Reservasi 7 Hari Terakhir</h3>
            <p class="text-xs text-slate-400 font-medium mt-0.5">Jumlah reservasi per hari</p>
          </div>
          <div class="flex items-center gap-2 text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full">
            <span class="material-symbols-outlined text-[14px]">trending_up</span>
            Data Real
          </div>
        </div>
        <div class="flex items-end justify-between gap-2 h-36">
          <?php foreach ($weeklyData as $d):
            $h = $weeklyMax > 0 ? round(($d['count'] / $weeklyMax) * 100) : 0;
            $h = max($h, 4);
          ?>
          <div class="flex-1 flex flex-col items-center gap-1.5">
            <span class="text-xs font-bold text-slate-600"><?= $d['count'] ?: '' ?></span>
            <div class="w-full rounded-t-lg bg-emerald-500 hover:bg-emerald-600 transition-colors bar-fill" style="height:<?= $h ?>%"></div>
            <span class="text-xs font-medium text-slate-400"><?= $d['label'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Status Breakdown Donut-style -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h3 class="font-bold text-slate-900 mb-4">Distribusi Status</h3>
        <?php
        $statuses = [
          'waiting'    => ['Menunggu',    'bg-amber-400',  $statusBreakdown['waiting']    ?? 0],
          'in_session' => ['Dalam Sesi',  'bg-blue-400',   $statusBreakdown['in_session'] ?? 0],
          'finished'   => ['Selesai',     'bg-emerald-400',$statusBreakdown['finished']   ?? 0],
          'cancelled'  => ['Dibatalkan',  'bg-red-400',    $statusBreakdown['cancelled']  ?? 0],
        ];
        foreach ($statuses as [$label, $color, $cnt]):
          $pct = $totalAppts > 0 ? round(($cnt / $totalAppts) * 100) : 0;
        ?>
        <div class="mb-4">
          <div class="flex justify-between text-xs font-medium text-slate-700 mb-1.5">
            <span><?= $label ?></span>
            <span class="font-bold"><?= $cnt ?> <span class="text-slate-400 font-normal">(<?= $pct ?>%)</span></span>
          </div>
          <div class="w-full bg-slate-100 rounded-full h-2">
            <div class="<?= $color ?> h-2 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Busiest Doctors + Recent Activity -->
    <div class="grid lg:grid-cols-2 gap-6 mb-6">

      <!-- Busiest Doctors -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
          <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-emerald-600 text-[18px]">workspace_premium</span>
            Dokter Tersibuk
          </h3>
        </div>
        <div class="divide-y divide-slate-50">
          <?php if (empty($busyDoctors)): ?>
          <p class="p-6 text-center text-slate-400 text-sm">Belum ada data.</p>
          <?php else: ?>
          <?php $maxBusy = max(array_column($busyDoctors, 'total') ?: [1]); ?>
          <?php foreach ($busyDoctors as $i => $doc): ?>
          <div class="flex items-center gap-3 px-5 py-3">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center font-black text-sm <?= $i === 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600' ?>">
              <?= $i + 1 ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-slate-900 truncate"><?= e($doc['name']) ?></p>
              <div class="flex items-center gap-2 mt-0.5">
                <div class="flex-1 bg-slate-100 rounded-full h-1.5">
                  <div class="bg-emerald-500 h-1.5 rounded-full" style="width:<?= round(($doc['total'] / $maxBusy) * 100) ?>%"></div>
                </div>
                <span class="text-xs font-bold text-slate-500"><?= $doc['total'] ?> sesi</span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Audit Log -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
          <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
            <span class="material-symbols-outlined text-slate-500 text-[18px]">history</span>
            Aktivitas Sistem Terkini
          </h3>
        </div>
        <div class="divide-y divide-slate-50">
          <?php if (empty($recentLogs)): ?>
          <p class="p-6 text-center text-slate-400 text-sm">Belum ada aktivitas.</p>
          <?php else: ?>
          <?php foreach ($recentLogs as $log): ?>
          <div class="flex items-start gap-3 px-5 py-3">
            <div class="w-7 h-7 bg-slate-50 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
              <span class="material-symbols-outlined text-[14px] text-slate-500">circle</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-slate-700"><?= e($log['user_name'] ?? 'System') ?></p>
              <p class="text-xs text-slate-500 truncate"><?= e($log['action']) ?> <?= $log['target'] ? '→ ' . e($log['target']) : '' ?></p>
            </div>
            <span class="text-xs text-slate-400 flex-shrink-0 font-medium"><?= format_date($log['created_at'], 'H:i') ?></span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recent Reservations -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between p-5 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
          <span class="material-symbols-outlined text-emerald-600 text-[18px]">event_note</span>
          Reservasi Terbaru
        </h3>
        <a href="<?= APP_URL ?>/admin/patients.php" class="text-xs font-bold text-emerald-600 hover:underline">Lihat Pasien</a>
      </div>
      <div class="overflow-x-auto">
        <?php if (empty($recentAppts)): ?>
        <p class="p-6 text-center text-slate-400 text-sm">Belum ada reservasi.</p>
        <?php else: ?>
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="text-left px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Pasien</th>
              <th class="text-left px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Dokter</th>
              <th class="text-left px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Jadwal</th>
              <th class="text-left px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            <?php
            $stMap = [
              'waiting'   =>['bg-amber-100','text-amber-700','Menunggu'],
              'in_session'=>['bg-blue-100','text-blue-700','Berlangsung'],
              'finished'  =>['bg-emerald-100','text-emerald-700','Selesai'],
              'cancelled' =>['bg-red-100','text-red-600','Dibatalkan'],
            ];
            foreach ($recentAppts as $appt):
              [$bg,$tc,$lbl] = $stMap[$appt['status']] ?? $stMap['waiting'];
            ?>
            <tr class="hover:bg-slate-50/60 transition-colors">
              <td class="px-5 py-3">
                <div class="flex items-center gap-2">
                  <div class="w-7 h-7 bg-emerald-50 text-emerald-700 rounded-lg flex items-center justify-center text-xs font-bold">
                    <?= e(initials($appt['patient_name'])) ?>
                  </div>
                  <span class="font-semibold text-slate-800 text-xs"><?= e($appt['patient_name']) ?></span>
                </div>
              </td>
              <td class="px-5 py-3 text-xs text-slate-600 font-medium"><?= e($appt['doctor_name']) ?></td>
              <td class="px-5 py-3 text-xs text-slate-500"><?= format_date($appt['scheduled_at'], 'd M, H:i') ?></td>
              <td class="px-5 py-3">
                <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $bg ?> <?= $tc ?>"><?= $lbl ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>

</body>
</html>
