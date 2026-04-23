<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('admin');

$user = current_user();
$pdo  = db();

// Stats defaults
$totalPatients = 0;
$activeDoctors = 0;
$activeAppts   = 0;
$pendingConf   = 0;
$recentLogs    = [];
$recentAppts   = [];

try {
    $totalPatients = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
    $activeDoctors = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor' AND is_active=1")->fetchColumn();
    $activeAppts   = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('waiting','in_session')")->fetchColumn();
    $pendingConf   = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='waiting'")->fetchColumn();

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
} catch (Exception $e) {
    // Graceful degradation
}
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
<style>body { font-family: 'Inter', sans-serif; }</style>
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

    <!-- Admin info -->
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
        ['icon'=>'dashboard',   'label'=>'Beranda',       'href'=>'dashboard.php', 'active'=>true],
        ['icon'=>'stethoscope', 'label'=>'Daftar Dokter', 'href'=>'doctors.php',   'active'=>false],
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

  <!-- Main -->
  <main class="flex-1 overflow-auto p-6 lg:p-8">

    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-black text-slate-900">Dashboard Overview</h1>
      <p class="text-slate-500 font-medium mt-1">Welcome back, <?= e($user['name']) ?>. Here's what's happening today.</p>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <?php
      $stats = [
        ['label'=>'Total Pasien',       'value'=>number_format($totalPatients), 'icon'=>'group',            'color'=>'blue',  'change'=>'+12%'],
        ['label'=>'Dokter Aktif',       'value'=>$activeDoctors,                'icon'=>'stethoscope',      'color'=>'purple','change'=>'+5%'],
        ['label'=>'Reservasi Aktif',    'value'=>$activeAppts,                  'icon'=>'event',            'color'=>'teal',  'change'=>''],
        ['label'=>'Menunggu Konfirmasi','value'=>$pendingConf,                   'icon'=>'pending_actions',  'color'=>'amber', 'change'=>''],
      ];
      foreach ($stats as $s):
      ?>
      <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <div class="flex items-start justify-between mb-3">
          <div class="w-10 h-10 bg-<?= $s['color'] ?>-50 text-<?= $s['color'] ?>-600 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]"><?= $s['icon'] ?></span>
          </div>
          <?php if ($s['change']): ?>
          <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-0.5 rounded-full"><?= e($s['change']) ?></span>
          <?php endif; ?>
        </div>
        <p class="text-3xl font-black text-slate-900"><?= $s['value'] ?></p>
        <p class="text-xs font-medium text-slate-500 mt-1"><?= e($s['label']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-3 gap-6 mb-6">

      <!-- Weekly Chart Placeholder -->
      <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h3 class="font-bold text-slate-900">Tren Reservasi Mingguan</h3>
            <p class="text-xs text-slate-400 font-medium mt-0.5">Volume reservasi 7 hari terakhir</p>
          </div>
        </div>
        <!-- Simple bar chart (pure CSS) -->
        <div class="flex items-end justify-between gap-2 h-32">
          <?php
          $days = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];
          $vals = [45,72,38,89,65,28,55];
          $max  = max($vals);
          foreach ($days as $i => $day):
            $h = round(($vals[$i] / $max) * 100);
          ?>
          <div class="flex-1 flex flex-col items-center gap-1">
            <div class="w-full bg-primary rounded-t-lg transition-all" style="height:<?= $h ?>%"></div>
            <span class="text-xs font-medium text-slate-400"><?= $day ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Distribution Pie Placeholder -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h3 class="font-bold text-slate-900 mb-4">Distribusi Spesialisasi</h3>
        <div class="space-y-3">
          <?php
          $specialties = [
            ['name'=>'Penyakit Dalam', 'pct'=>45, 'color'=>'teal'],
            ['name'=>'Anak',           'pct'=>30, 'color'=>'blue'],
            ['name'=>'Gigi & Mulut',   'pct'=>25, 'color'=>'purple'],
          ];
          foreach ($specialties as $sp):
          ?>
          <div>
            <div class="flex justify-between text-xs font-medium text-slate-600 mb-1">
              <span><?= e($sp['name']) ?></span>
              <span class="font-bold"><?= $sp['pct'] ?>%</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2">
              <div class="bg-<?= $sp['color'] ?>-500 h-2 rounded-full" style="width:<?= $sp['pct'] ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Recent Activity + Appointments -->
    <div class="grid lg:grid-cols-2 gap-6">

      <!-- Audit Log -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
          <h3 class="font-bold text-slate-900 text-sm">Aktivitas Sistem Terkini</h3>
          <a href="#" class="text-xs font-bold text-teal-600 hover:underline">Lihat Semua</a>
        </div>
        <div class="divide-y divide-slate-50">
          <?php if (empty($recentLogs)): ?>
          <p class="p-6 text-center text-slate-400 text-sm">Belum ada aktivitas.</p>
          <?php else: ?>
          <?php foreach ($recentLogs as $log): ?>
          <div class="flex items-start gap-3 px-5 py-3">
            <div class="w-7 h-7 bg-teal-50 text-teal-600 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
              <span class="material-symbols-outlined text-[14px]">
                <?= str_contains($log['action'], 'login') ? 'login' : 'info' ?>
              </span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-slate-700"><?= e($log['user_name'] ?? 'System') ?></p>
              <p class="text-xs text-slate-500 truncate"><?= e($log['action']) ?> <?= $log['target'] ? '→ ' . e($log['target']) : '' ?></p>
            </div>
            <span class="text-xs text-slate-400 flex-shrink-0 font-medium">
              <?= format_date($log['created_at'], 'H:i') ?>
            </span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent Reservations -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
          <h3 class="font-bold text-slate-900 text-sm">Reservasi Terbaru</h3>
          <a href="#" class="text-xs font-bold text-teal-600 hover:underline">Lihat Semua</a>
        </div>
        <div class="divide-y divide-slate-50">
          <?php if (empty($recentAppts)): ?>
          <p class="p-6 text-center text-slate-400 text-sm">Belum ada reservasi.</p>
          <?php else: ?>
          <?php foreach ($recentAppts as $appt):
            $stMap = [
              'waiting'   =>['bg-amber-100','text-amber-700','Menunggu'],
              'in_session'=>['bg-green-100','text-green-700','Berlangsung'],
              'finished'  =>['bg-slate-100','text-slate-600','Selesai'],
              'cancelled' =>['bg-red-100','text-red-600','Dibatalkan'],
            ];
            [$bg,$tc,$lbl] = $stMap[$appt['status']] ?? $stMap['waiting'];
          ?>
          <div class="flex items-center gap-3 px-5 py-3">
            <div class="w-8 h-8 bg-teal-50 text-teal-600 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0">
              <?= e(initials($appt['patient_name'])) ?>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-slate-700 truncate"><?= e($appt['patient_name']) ?></p>
              <p class="text-xs text-slate-400 truncate">→ <?= e($appt['doctor_name']) ?> · <?= format_date($appt['scheduled_at'], 'd M, H:i') ?></p>
            </div>
            <span class="text-xs font-bold px-2 py-0.5 rounded-full <?= $bg ?> <?= $tc ?> flex-shrink-0">
              <?= $lbl ?>
            </span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </main>
</div>

</body>
</html>
