<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');
$user = current_user();
$pdo  = db();

$history = [];
try {
    $stmt = $pdo->prepare(
        'SELECT a.*, u.name AS doctor_name, dp.specialization,
                mr.diagnosis, mr.treatment, mr.prescription
         FROM appointments a
         JOIN users u ON u.id = a.doctor_id
         LEFT JOIN doctor_profiles dp ON dp.user_id = a.doctor_id
         LEFT JOIN medical_records mr ON mr.appointment_id = a.id
         WHERE a.patient_id = ? AND a.status IN (?, ?)
         ORDER BY a.scheduled_at DESC'
    );
    $stmt->execute([$user['id'], 'finished', 'cancelled']);
    $history = $stmt->fetchAll();
} catch (Exception $e) {
    // Graceful degradation
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Riwayat Medis</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-surface text-on-surface antialiased min-h-screen">
<div class="flex min-h-screen">
  
  <!-- Sidebar -->
  <aside class="w-64 bg-blue-700 text-white flex-shrink-0 flex flex-col hidden md:flex shadow-xl">
    <div class="p-6 border-b border-blue-600/50">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-white/20 flex items-center justify-center rounded-lg transform rotate-45">
          <span class="material-symbols-outlined text-white transform -rotate-45 text-[16px]" style="font-variation-settings:'FILL' 1;">medical_services</span>
        </div>
        <span class="font-extrabold tracking-tight text-white text-lg">CareConnect</span>
      </div>
      <div class="mt-1 text-xs text-blue-200 font-medium ml-10">Portal Kesehatan</div>
    </div>

    <!-- User -->
    <div class="p-4 border-b border-blue-600/50">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-white/20 text-white rounded-full flex items-center justify-center font-bold text-sm">
          <?= e(initials($user['name'])) ?>
        </div>
        <div>
          <p class="text-sm font-bold text-white"><?= e($user['name']) ?></p>
          <p class="text-xs text-blue-200">Pasien</p>
        </div>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 p-4 space-y-1">
      <?php
      $navItems = [
        ['icon'=>'home',    'label'=>'Beranda', 'href'=>'dashboard.php', 'active'=>false],
        ['icon'=>'history', 'label'=>'Riwayat', 'href'=>'riwayat.php',   'active'=>true],
        ['icon'=>'star',    'label'=>'Ulasan',  'href'=>'ulasan.php',    'active'=>false],
        ['icon'=>'chat',    'label'=>'Chat',    'href'=>'chat.php',      'active'=>false],
        ['icon'=>'person',  'label'=>'Profil',  'href'=>'profil.php',    'active'=>false],
      ];
      foreach ($navItems as $item):
        $cls = $item['active']
          ? 'bg-white/20 text-white font-bold shadow-sm'
          : 'text-blue-100 hover:bg-white/10 hover:text-white font-medium';
      ?>
      <a href="<?= e($item['href']) ?>"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
        <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
        <?= e($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="p-4 border-t border-blue-600/50">
      <a href="<?= APP_URL ?>/patient/logout.php"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-blue-200 hover:bg-red-500/20 hover:text-red-200 transition-colors">
        <span class="material-symbols-outlined text-[20px]">logout</span>
        Keluar
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-6 lg:p-8 overflow-auto bg-surface-container-lowest">
    <div class="mb-8">
      <h1 class="text-2xl font-black text-on-surface">Riwayat Medis</h1>
      <p class="text-on-surface-variant font-medium mt-1">Daftar riwayat konsultasi dan penanganan medis Anda.</p>
    </div>

    <?php if (empty($history)): ?>
    <div class="bg-white p-12 text-center rounded-2xl border border-slate-200 shadow-sm">
      <span class="material-symbols-outlined text-[64px] text-slate-300">history_edu</span>
      <h3 class="mt-4 text-lg font-bold text-slate-700">Belum Ada Riwayat</h3>
      <p class="mt-2 text-sm text-slate-500">Anda belum memiliki riwayat medis yang tersimpan di sistem.</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($history as $h): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col md:flex-row gap-6">
          <div class="flex-shrink-0">
             <div class="w-16 h-16 bg-blue-100 text-blue-700 rounded-xl flex flex-col items-center justify-center leading-none">
               <span class="text-sm font-bold uppercase"><?= strtoupper(date('M', strtotime($h['scheduled_at']))) ?></span>
               <span class="text-2xl font-black"><?= date('d', strtotime($h['scheduled_at'])) ?></span>
             </div>
          </div>
          <div class="flex-1">
             <div class="flex items-center justify-between mb-2">
               <h3 class="font-bold text-lg text-slate-900"><?= e($h['doctor_name']) ?></h3>
               <?php if ($h['status'] === 'finished'): ?>
                 <span class="text-xs font-bold px-3 py-1 rounded-full border bg-slate-100 text-slate-600 border-slate-200">Selesai</span>
               <?php else: ?>
                 <span class="text-xs font-bold px-3 py-1 rounded-full border bg-red-50 text-red-700 border-red-200">Dibatalkan</span>
               <?php endif; ?>
             </div>
             <p class="text-sm text-slate-500 font-medium mb-4"><?= e($h['specialization'] ?? 'Umum') ?> · <?= date('Y', strtotime($h['scheduled_at'])) ?> · <?= date('H:i', strtotime($h['scheduled_at'])) ?> WIB</p>
             
             <?php if ($h['status'] === 'finished' && !empty($h['diagnosis'])): ?>
               <div class="grid md:grid-cols-3 gap-4 bg-slate-50 rounded-xl p-4 border border-slate-100">
                 <div>
                   <span class="block text-xs font-bold text-slate-400 mb-1">Diagnosis</span>
                   <p class="text-sm text-slate-700"><?= nl2br(e($h['diagnosis'])) ?></p>
                 </div>
                 <div>
                   <span class="block text-xs font-bold text-slate-400 mb-1">Tindakan</span>
                   <p class="text-sm text-slate-700"><?= nl2br(e($h['treatment'] ?: '-')) ?></p>
                 </div>
                 <div>
                   <span class="block text-xs font-bold text-slate-400 mb-1">Resep</span>
                   <p class="text-sm text-slate-700"><?= nl2br(e($h['prescription'] ?: '-')) ?></p>
                 </div>
               </div>
             <?php elseif ($h['status'] === 'finished'): ?>
               <p class="text-sm text-slate-500 italic">Catatan medis belum tersedia.</p>
             <?php endif; ?>
             
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
