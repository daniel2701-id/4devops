<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

$user = current_user();
$pdo  = db();

$upcoming = [];
$pastCount = 0;

try {
    // Upcoming appointments
    $stmt = $pdo->prepare(
        'SELECT a.*, u.name AS doctor_name, dp.specialization
         FROM appointments a
         JOIN users u ON u.id = a.doctor_id
         LEFT JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
         WHERE a.patient_id = ? AND a.scheduled_at >= NOW() AND a.status != ?
         ORDER BY a.scheduled_at ASC LIMIT 5'
    );
    $stmt->execute([$user['id'], 'cancelled']);
    $upcoming = $stmt->fetchAll();

    // Past appointments count
    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = ?');
    $stmt2->execute([$user['id'], 'finished']);
    $pastCount = (int) $stmt2->fetchColumn();
} catch (Exception $e) {
    // Graceful degradation
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Dashboard Pasien</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-surface text-on-surface antialiased min-h-screen">

<!-- Sidebar + Main layout -->
<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-surface border-r border-outline-variant flex-shrink-0 flex flex-col hidden md:flex">
    <div class="p-6 border-b border-outline-variant">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-primary-fixed flex items-center justify-center rounded-lg transform rotate-45">
          <span class="material-symbols-outlined text-primary transform -rotate-45 text-[16px]" style="font-variation-settings:'FILL' 1;">medical_services</span>
        </div>
        <span class="font-extrabold tracking-tight text-on-surface text-lg">CareConnect</span>
      </div>
      <div class="mt-1 text-xs text-on-surface-variant font-medium ml-10">Portal Kesehatan</div>
    </div>

    <!-- User -->
    <div class="p-4 border-b border-outline-variant">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-primary-fixed text-primary rounded-full flex items-center justify-center font-bold text-sm">
          <?= e(initials($user['name'])) ?>
        </div>
        <div>
          <p class="text-sm font-bold text-on-surface"><?= e($user['name']) ?></p>
          <p class="text-xs text-on-surface-variant">Pasien</p>
        </div>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 p-4 space-y-1">
      <?php
      $navItems = [
        ['icon'=>'home',  'label'=>'Beranda',  'href'=>'dashboard.php', 'active'=>true],
        ['icon'=>'event', 'label'=>'Reservasi','href'=>'reservasi.php', 'active'=>false],
      ];
      foreach ($navItems as $item):
        $cls = $item['active']
          ? 'bg-primary-fixed text-primary font-bold'
          : 'text-on-surface-variant hover:bg-surface-container hover:text-on-surface font-medium';
      ?>
      <a href="<?= e($item['href']) ?>"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
        <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
        <?= e($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="p-4 border-t border-outline-variant">
      <a href="<?= APP_URL ?>/patient/logout.php"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-on-surface-variant hover:bg-error/10 hover:text-error transition-colors">
        <span class="material-symbols-outlined text-[20px]">logout</span>
        Keluar
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-6 lg:p-8 overflow-auto bg-surface-container-lowest">

    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-black text-on-surface">Selamat datang, <?= e(explode(' ', $user['name'])[0]) ?>!</h1>
        <p class="text-on-surface-variant font-medium mt-1">Kelola jadwal dan reservasi kesehatan Anda di sini.</p>
      </div>
      <button class="w-10 h-10 rounded-full border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary transition-colors flex items-center justify-center relative">
        <span class="material-symbols-outlined text-[20px]">notifications</span>
        <span class="absolute top-2 right-2 w-2 h-2 bg-error rounded-full"></span>
      </button>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <?php
      $stats = [
        ['label'=>'Janji Mendatang', 'value'=>count($upcoming), 'icon'=>'event',           'color'=>'blue'],
        ['label'=>'Riwayat Kunjungan','value'=>$pastCount,       'icon'=>'history_edu',     'color'=>'purple'],
        ['label'=>'Resep Aktif',     'value'=>0,                 'icon'=>'medication',      'color'=>'teal'],
        ['label'=>'Notifikasi',      'value'=>0,                 'icon'=>'notifications',   'color'=>'amber'],
      ];
      foreach ($stats as $s):
        $bg   = "bg-{$s['color']}-50";
        $text = "text-{$s['color']}-600";
      ?>
      <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <div class="w-10 h-10 <?= $bg ?> <?= $text ?> rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]"><?= $s['icon'] ?></span>
          </div>
        </div>
        <p class="text-3xl font-black text-slate-900"><?= $s['value'] ?></p>
        <p class="text-xs font-medium text-slate-500 mt-1"><?= e($s['label']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Quick Actions -->
    <div class="grid md:grid-cols-2 gap-6 mb-8">

      <!-- Quick Konsultasi -->
      <div class="bg-gradient-to-br from-blue-600 to-blue-700 text-white rounded-2xl p-6 shadow-md">
        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center mb-4">
          <span class="material-symbols-outlined text-white text-2xl">chat_bubble</span>
        </div>
        <h3 class="text-lg font-bold mb-1">Konsultasi Cepat</h3>
        <p class="text-blue-200 text-sm font-medium mb-5">Butuh saran medis segera? Hubungi dokter umum kami dalam hitungan menit.</p>
        <a href="#" class="inline-flex items-center gap-1 bg-white text-blue-700 px-4 py-2 rounded-full text-sm font-bold hover:bg-blue-50 transition-colors">
          Mulai Konsultasi
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
      </div>

      <!-- Quick Access Grid -->
      <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
        <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">Akses Cepat</h3>
        <div class="grid grid-cols-2 gap-3">
          <?php
          $quick = [
            ['icon'=>'event_available','label'=>'Reservasi Baru', 'color'=>'blue',   'href'=>'reservasi.php'],
            ['icon'=>'history_edu',    'label'=>'Riwayat Medis',  'color'=>'purple', 'href'=>'reservasi.php'],
            ['icon'=>'medication',     'label'=>'Resep Obat',     'color'=>'teal',   'href'=>'reservasi.php'],
            ['icon'=>'stethoscope',    'label'=>'Info Dokter',    'color'=>'pink',   'href'=>'reservasi.php'],
          ];
          foreach ($quick as $q):
          ?>
          <a href="<?= $q['href'] ?>" class="flex flex-col items-center gap-2 p-4 rounded-xl bg-<?= $q['color'] ?>-50 text-<?= $q['color'] ?>-600 hover:bg-<?= $q['color'] ?>-100 transition-colors text-center">
            <span class="material-symbols-outlined text-[24px]"><?= $q['icon'] ?></span>
            <span class="text-xs font-bold"><?= e($q['label']) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Upcoming Appointments -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between p-6 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 flex items-center gap-2">
          <span class="material-symbols-outlined text-blue-600 text-[20px]">event</span>
          Janji Temu Mendatang
        </h3>
        <a href="reservasi.php" class="text-xs font-bold text-primary hover:underline">Lihat Semua</a>
      </div>

      <?php if (empty($upcoming)): ?>
      <div class="p-12 text-center text-slate-400">
        <span class="material-symbols-outlined text-[48px] text-slate-300">event_busy</span>
        <p class="font-medium mt-3">Belum ada janji temu mendatang.</p>
        <a href="reservasi.php" class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline">
          <span class="material-symbols-outlined text-[16px]">add_circle</span>
          Buat Reservasi
        </a>
      </div>
      <?php else: ?>
      <div class="divide-y divide-slate-100">
        <?php foreach ($upcoming as $appt):
          $statusMap = [
            'waiting'    => ['label'=>'Menunggu',  'cls'=>'bg-amber-50 text-amber-700 border-amber-200'],
            'in_session' => ['label'=>'Sedang Berjalan','cls'=>'bg-green-50 text-green-700 border-green-200'],
            'finished'   => ['label'=>'Selesai',   'cls'=>'bg-slate-100 text-slate-600 border-slate-200'],
          ];
          $st = $statusMap[$appt['status']] ?? $statusMap['waiting'];
        ?>
        <div class="flex items-center justify-between px-6 py-4 hover:bg-slate-50 transition-colors">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 text-blue-700 rounded-xl flex flex-col items-center justify-center leading-none">
              <span class="text-xs font-bold uppercase"><?= strtoupper(date('M', strtotime($appt['scheduled_at']))) ?></span>
              <span class="text-xl font-black"><?= date('d', strtotime($appt['scheduled_at'])) ?></span>
            </div>
            <div>
              <p class="font-bold text-sm text-slate-900"><?= e($appt['doctor_name']) ?></p>
              <p class="text-xs text-slate-500 font-medium"><?= e($appt['specialization'] ?? 'Umum') ?> · <?= date('H:i', strtotime($appt['scheduled_at'])) ?> WIB</p>
              <p class="text-xs text-slate-400"><?= e($appt['type']) ?></p>
            </div>
          </div>
          <span class="text-xs font-bold px-3 py-1 rounded-full border <?= $st['cls'] ?>">
            <?= $st['label'] ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

</body>
</html>
