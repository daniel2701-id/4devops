<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');
$user = current_user();
$pdo = db();

$profile = ['gender' => '-', 'age' => null, 'blood_type' => '-'];
try {
    $stmt = $pdo->prepare("SELECT gender, age, blood_type FROM patient_profiles WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    if ($row = $stmt->fetch()) {
        $profile = $row;
    }
} catch (Exception $e) {
    // If table/column doesn't exist, ignore
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Profil</title>
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
        ['icon'=>'history', 'label'=>'Riwayat', 'href'=>'riwayat.php',   'active'=>false],
        ['icon'=>'chat',    'label'=>'Chat',    'href'=>'chat.php',      'active'=>false],
        ['icon'=>'person',  'label'=>'Profil',  'href'=>'profil.php',    'active'=>true],
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
      <h1 class="text-2xl font-black text-on-surface">Profil Pengguna</h1>
      <p class="text-on-surface-variant font-medium mt-1">Kelola data pribadi dan informasi akun Anda.</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm max-w-2xl">
      <div class="p-8">
        <div class="flex items-center gap-6 mb-8">
          <div class="w-20 h-20 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center font-bold text-3xl">
            <?= e(initials($user['name'])) ?>
          </div>
          <div>
            <h2 class="text-xl font-bold text-slate-900"><?= e($user['name']) ?></h2>
            <p class="text-slate-500"><?= e($user['email']) ?></p>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Nama Lengkap</label>
            <div class="px-4 py-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-medium text-slate-700">
              <?= e($user['name']) ?>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Email</label>
            <div class="px-4 py-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-medium text-slate-700">
              <?= e($user['email']) ?>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Role Akun</label>
            <div class="px-4 py-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-medium text-slate-700">
              Pasien
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Jenis Kelamin</label>
            <div class="px-4 py-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-medium text-slate-700 capitalize">
              <?= e($profile['gender'] ?? '-') ?>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Usia</label>
            <div class="px-4 py-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-medium text-slate-700">
              <?= $profile['age'] ? e($profile['age']) . ' Tahun' : '-' ?>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Golongan Darah</label>
            <div class="px-4 py-3 bg-slate-50 rounded-xl border border-slate-200 text-sm font-medium text-slate-700 uppercase">
              <?= e($profile['blood_type'] ?? '-') ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>
</body>
</html>
