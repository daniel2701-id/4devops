<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

$user = current_user();
$pdo  = db();

// Mark all as read
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['id']]);
    header('Location: notifikasi.php');
    exit;
}

// Fetch notifications
$stmt = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50"
);
$stmt->execute([$user['id']]);
$notifs = $stmt->fetchAll();

$unreadCount = count(array_filter($notifs, fn($n) => !$n['is_read']));

// Type icons & colors
$typeConfig = [
    'info'          => ['notifications',   'bg-blue-50',  'text-blue-600'],
    'reminder'      => ['alarm',           'bg-amber-50', 'text-amber-600'],
    'status_change' => ['update',          'bg-purple-50','text-purple-600'],
    'system'        => ['settings',        'bg-slate-50', 'text-slate-500'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Notifikasi</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#2563eb') ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

<main class="p-6 lg:p-8 max-w-3xl mx-auto">

  <div class="mb-6 flex items-center gap-3">
    <a href="dashboard.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-blue-700 transition-colors bg-white px-4 py-2.5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md">
      <span class="material-symbols-outlined text-[18px]">arrow_back</span>
      Kembali
    </a>
  </div>

  <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center">
        <span class="material-symbols-outlined text-[24px]">notifications</span>
      </div>
      <div>
        <h1 class="text-2xl font-black text-slate-900">Notifikasi</h1>
        <p class="text-slate-500 text-sm"><?= $unreadCount ?> belum dibaca</p>
      </div>
    </div>
    <?php if ($unreadCount > 0): ?>
    <a href="?mark_read=1" class="text-sm font-bold text-blue-600 hover:underline flex items-center gap-1">
      <span class="material-symbols-outlined text-[16px]">done_all</span>
      Tandai Semua Dibaca
    </a>
    <?php endif; ?>
  </div>

  <?php if (empty($notifs)): ?>
  <div class="text-center py-24 text-slate-400">
    <span class="material-symbols-outlined text-[60px] text-slate-300">notifications_off</span>
    <p class="mt-4 font-medium">Tidak ada notifikasi.</p>
  </div>
  <?php else: ?>
  <div class="space-y-3">
    <?php foreach ($notifs as $n):
      [$icon, $bgIcon, $textIcon] = $typeConfig[$n['type']] ?? $typeConfig['info'];
      $isUnread = !$n['is_read'];
    ?>
    <div class="bg-white rounded-2xl border <?= $isUnread ? 'border-blue-200 shadow-sm' : 'border-slate-100' ?> p-4 flex items-start gap-4 transition-all">
      <div class="w-10 h-10 <?= $bgIcon ?> <?= $textIcon ?> rounded-xl flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-[20px]"><?= $icon ?></span>
      </div>
      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
          <p class="text-sm font-bold text-slate-900 <?= $isUnread ? '' : 'text-slate-600' ?>"><?= e($n['title']) ?></p>
          <?php if ($isUnread): ?>
          <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-1.5"></span>
          <?php endif; ?>
        </div>
        <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?= e($n['message']) ?></p>
        <p class="text-xs text-slate-400 mt-2"><?= format_date($n['created_at'], 'd M Y, H:i') ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</main>

</body>
</html>
