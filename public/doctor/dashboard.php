<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('doctor');

$user = current_user();
$pdo  = db();
$today = date('Y-m-d');

$todayAppts = [];
$totalToday   = 0;
$completed    = 0;
$pending      = 0;
$inSession    = [];
$nextAppt     = null;

try {
    // Today's appointments
    $stmt = $pdo->prepare(
        'SELECT a.*, u.name AS patient_name
         FROM appointments a
         JOIN users u ON u.id = a.patient_id
         WHERE a.doctor_id = ? AND DATE(a.scheduled_at) = ?
         ORDER BY a.scheduled_at ASC'
    );
    $stmt->execute([$user['id'], $today]);
    $todayAppts = $stmt->fetchAll();

    $totalToday   = count($todayAppts);
    $completed    = count(array_filter($todayAppts, fn($a) => $a['status'] === 'finished'));
    $pending      = count(array_filter($todayAppts, fn($a) => $a['status'] === 'waiting'));
    $inSession    = array_values(array_filter($todayAppts, fn($a) => $a['status'] === 'in_session'));
    $nextAppt     = $inSession[0] ?? (array_values(array_filter($todayAppts, fn($a) => $a['status'] === 'waiting'))[0] ?? null);
} catch (Exception $e) {
    // Graceful degradation
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Dashboard Dokter</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

<div class="flex min-h-screen">

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
        ['icon'=>'home',          'label'=>'Beranda',    'href'=>'dashboard.php', 'active'=>true],
        ['icon'=>'calendar_month','label'=>'Jadwal Saya','href'=>'jadwal.php',    'active'=>false],
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
  <main class="flex-1 flex flex-col overflow-hidden">

    <!-- Top bar -->
    <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between flex-shrink-0">
      <div>
        <h1 class="text-base font-bold text-slate-900">Halo, <?= e($user['name']) ?> 👋</h1>
        <p class="text-xs text-slate-500"><?= date('l, d F Y') ?></p>
      </div>
      <div class="flex items-center gap-3">
        <button class="relative p-2 text-slate-500 hover:text-primary transition-colors">
          <span class="material-symbols-outlined text-[22px]">notifications</span>
          <?php if ($pending > 0): ?>
          <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
          <?php endif; ?>
        </button>
      </div>
    </header>

    <div class="flex flex-1 overflow-hidden">

      <!-- Appointment List (left) -->
      <div class="flex-1 overflow-auto p-6">

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-6">
          <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-3xl font-black text-slate-900"><?= $totalToday ?></p>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mt-1">Today's Patients</p>
            <div class="flex items-center gap-1 mt-2 text-xs font-medium text-green-600">
              <span class="material-symbols-outlined text-[14px]">trending_up</span>
              Today's Appointments
            </div>
          </div>
          <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-3xl font-black text-slate-900"><?= $completed ?></p>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mt-1">Completed Sessions</p>
          </div>
          <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
            <p class="text-3xl font-black text-slate-900"><?= $pending ?></p>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mt-1">Pending Consultations</p>
          </div>
        </div>

        <!-- Appointment Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-sm uppercase tracking-wider">Jadwal Hari Ini</h3>
            <button class="text-xs font-bold text-primary hover:underline">View All</button>
          </div>

          <?php if (empty($todayAppts)): ?>
          <div class="p-12 text-center text-slate-400">
            <span class="material-symbols-outlined text-[48px] text-slate-300">event_busy</span>
            <p class="font-medium mt-3">Tidak ada jadwal hari ini.</p>
          </div>
          <?php else: ?>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-slate-100">
                  <th class="text-left px-6 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Patient</th>
                  <th class="text-left px-6 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Time</th>
                  <th class="text-left px-6 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Type</th>
                  <th class="text-left px-6 py-3 text-xs font-bold text-slate-400 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <?php
                $statusMap = [
                  'finished'   => ['label'=>'Finished',   'cls'=>'bg-slate-100 text-slate-600'],
                  'in_session' => ['label'=>'In Session',  'cls'=>'bg-green-100 text-green-700'],
                  'waiting'    => ['label'=>'Waiting',     'cls'=>'bg-amber-100 text-amber-700'],
                  'cancelled'  => ['label'=>'Cancelled',   'cls'=>'bg-red-100 text-red-700'],
                ];
                foreach ($todayAppts as $appt):
                  $st = $statusMap[$appt['status']] ?? $statusMap['waiting'];
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 bg-primary-fixed text-primary rounded-full flex items-center justify-center text-xs font-bold">
                        <?= e(initials($appt['patient_name'])) ?>
                      </div>
                      <span class="font-medium text-slate-800"><?= e($appt['patient_name']) ?></span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-slate-600 font-medium"><?= date('H:i', strtotime($appt['scheduled_at'])) ?></td>
                  <td class="px-6 py-4 text-slate-600 font-medium"><?= e($appt['type']) ?></td>
                  <td class="px-6 py-4">
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full <?= $st['cls'] ?>"><?= $st['label'] ?></span>
                  </td>
                  <td class="px-6 py-4">
                    <?php if ($appt['status'] === 'waiting'): ?>
                    <a href="jadwal.php" class="text-xs font-bold text-primary hover:underline">Kelola Sesi</a>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right Panel -->
      <div class="w-72 border-l border-slate-200 bg-white flex-shrink-0 p-5 overflow-auto hidden lg:block">

        <?php if ($nextAppt): ?>
        <div class="bg-primary-fixed rounded-2xl p-5 mb-5">
          <p class="text-xs font-bold text-primary uppercase tracking-wider mb-3">Next Appointment</p>
          <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center font-bold text-sm">
              <?= e(initials($nextAppt['patient_name'])) ?>
            </div>
            <div>
              <p class="font-bold text-sm text-slate-900"><?= e($nextAppt['patient_name']) ?></p>
              <p class="text-xs text-slate-500"><?= date('H:i', strtotime($nextAppt['scheduled_at'])) ?> WIB</p>
            </div>
          </div>
          <?php if ($nextAppt['reason']): ?>
          <p class="text-xs font-medium text-slate-600 bg-white/60 rounded-xl p-3"><?= e($nextAppt['reason']) ?></p>
          <?php endif; ?>
          <a href="jadwal.php" class="w-full mt-4 py-2 bg-primary text-white rounded-xl text-sm font-bold hover:bg-primary-light transition-colors text-center block">
            Kelola di Jadwal
          </a>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Aksi Cepat</h4>
        <div class="space-y-2">
          <a href="jadwal.php" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 hover:bg-primary-fixed text-slate-700 hover:text-primary transition-colors text-sm font-medium">
            <span class="material-symbols-outlined text-[20px]">calendar_month</span>
            Kelola Sesi Konsultasi
          </a>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>
