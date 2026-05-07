<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('doctor');

$user = current_user();
$pdo  = db();



$stmt = $pdo->prepare(
    "SELECT a.*, p.name AS patient_name, pp.gender, pp.phone
     FROM appointments a
     JOIN users p ON p.id = a.patient_id
     LEFT JOIN patient_profiles pp ON pp.user_id = p.id
     WHERE a.doctor_id = ? AND a.status = 'finished'
     ORDER BY a.scheduled_at DESC"
);
$stmt->execute([$user['id']]);
$appointments = $stmt->fetchAll();

$msgSuccess = flash('success');
$msgError   = flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Riwayat Pasien</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex">

  <!-- Sidebar -->
  <aside class="w-64 bg-blue-700 text-white flex-shrink-0 flex flex-col hidden md:flex shadow-xl">
    <div class="p-6 border-b border-blue-600/50">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-white/20 flex items-center justify-center rounded-lg transform rotate-45">
          <span class="material-symbols-outlined text-white transform -rotate-45 text-[16px]" style="font-variation-settings:'FILL' 1;">medical_services</span>
        </div>
        <span class="font-extrabold tracking-tight text-white text-lg">CareConnect</span>
      </div>
      <div class="mt-1 text-xs text-blue-200 font-medium ml-10">Medical Portal</div>
    </div>

    <!-- Doctor info -->
    <div class="p-4 border-b border-blue-600/50">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-white/20 text-white rounded-full flex items-center justify-center font-bold text-sm">
          <?= e(initials($user['name'])) ?>
        </div>
        <div>
          <p class="text-sm font-bold text-white"><?= e($user['name']) ?></p>
          <p class="text-xs text-blue-200">Dokter</p>
        </div>
      </div>
    </div>

    <nav class="flex-1 p-4 space-y-1">
      <?php
      $navItems = [
        ['icon'=>'home',          'label'=>'Beranda',       'href'=>'dashboard.php',   'active'=>false],
        ['icon'=>'calendar_month','label'=>'Jadwal Pasien', 'href'=>'jadwal.php',      'active'=>false],
        ['icon'=>'history',       'label'=>'Riwayat',       'href'=>'riwayat.php',     'active'=>true],
        ['icon'=>'edit_calendar', 'label'=>'Atur Jadwal',   'href'=>'atur_jadwal.php', 'active'=>false],
        ['icon'=>'chat',          'label'=>'Chat',          'href'=>'chat.php',        'active'=>false],
      ];
      foreach ($navItems as $item):
        $cls = $item['active']
          ? 'bg-white/20 text-white font-bold shadow-sm'
          : 'text-blue-100 hover:bg-white/10 hover:text-white font-medium';
      ?>
      <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
        <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
        <?= e($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-blue-600/50">
      <a href="<?= APP_URL ?>/doctor/logout.php"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-blue-200 hover:bg-red-500/20 hover:text-red-200 transition-colors">
        <span class="material-symbols-outlined text-[20px]">logout</span>
        Keluar
      </a>
    </div>
  </aside>

  <!-- Main -->
  <main class="flex-1 overflow-auto p-6 lg:p-8">

    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-black text-slate-900">Riwayat Pasien</h1>
      <p class="text-slate-500 font-medium mt-1">Daftar sesi konsultasi yang telah selesai.</p>
    </div>

    <?= alert_html($msgError, 'error') ?>
    <?= alert_html($msgSuccess, 'success') ?>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <?php if (empty($appointments)): ?>
      <div class="p-12 text-center text-slate-400">
        <span class="material-symbols-outlined text-[48px] text-slate-300">event_busy</span>
        <p class="font-medium mt-3">Tidak ada riwayat pasien saat ini.</p>
      </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Waktu</th>
              <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Pasien</th>
              <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Keluhan / Alasan</th>
              <th class="text-left px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Status</th>
              <th class="text-right px-6 py-4 font-bold text-slate-500 uppercase tracking-wider text-xs">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php
            $statusMap = [
              'waiting'    => ['bg-amber-100 text-amber-700',  'Menunggu'],
              'in_session' => ['bg-blue-100 text-blue-700',    'Dalam Sesi'],
              'finished'   => ['bg-slate-100 text-slate-600',  'Selesai'],
              'cancelled'  => ['bg-red-100 text-red-700',      'Dibatalkan'],
            ];
            foreach ($appointments as $appt):
              [$badgeCls, $badgeLabel] = $statusMap[$appt['status']] ?? ['bg-slate-100 text-slate-600', $appt['status']];
            ?>
            <tr class="hover:bg-slate-50/50 transition-colors">
              <td class="px-6 py-4">
                <span class="text-xs font-bold text-slate-500 block"><?= date('d M Y', strtotime($appt['scheduled_at'])) ?></span>
                <span class="font-black text-slate-900 text-base"><?= date('H:i', strtotime($appt['scheduled_at'])) ?></span>
                <span class="text-xs font-bold text-slate-400 uppercase">WIB</span>
              </td>
              <td class="px-6 py-4">
                <p class="font-bold text-slate-900"><?= e($appt['patient_name']) ?></p>
                <p class="text-xs text-slate-500"><?= e($appt['phone'] ?? '-') ?></p>
              </td>
              <td class="px-6 py-4 max-w-xs">
                <p class="text-slate-600 truncate" title="<?= e($appt['reason'] ?? '') ?>"><?= e($appt['reason'] ?: '-') ?></p>
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= $badgeCls ?>">
                  <?= $badgeLabel ?>
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <?php if (in_array($appt['status'], ['finished','cancelled'])): ?>
                  <span class="text-xs font-bold text-slate-400 px-2 py-1.5">Terkunci</span>
                <?php else: ?>
                <form method="POST" class="inline-flex items-center gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="appt_id" value="<?= (int)$appt['id'] ?>">
                  <select name="status" onchange="this.form.submit()"
                          class="text-xs font-bold border border-slate-200 rounded-lg bg-slate-50 px-2 py-1.5 focus:outline-none focus:border-primary">
                    <option value="waiting"    <?= $appt['status']==='waiting'    ? 'selected' : '' ?>>Menunggu</option>
                    <option value="in_session" <?= $appt['status']==='in_session' ? 'selected' : '' ?>>Mulai Sesi</option>
                    <option value="finished"   <?= $appt['status']==='finished'   ? 'selected' : '' ?>>Selesai</option>
                    <option value="cancelled"  <?= $appt['status']==='cancelled'  ? 'selected' : '' ?>>Batalkan</option>
                  </select>
                </form>
                <?php endif; ?>
                <?php if (in_array($appt['status'], ['in_session','finished'])): ?>
                <a href="rekam_medis.php?appt_id=<?= (int)$appt['id'] ?>"
                   class="ml-2 inline-flex items-center justify-center w-8 h-8 rounded-lg bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors"
                   title="Isi Rekam Medis">
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
