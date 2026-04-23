<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

$user = current_user();
$pdo  = db();
$error   = '';
$success = '';

// Handle Booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $date     = $_POST['scheduled_date'] ?? '';
    $time     = $_POST['scheduled_time'] ?? '';
    $reason   = sanitize_string($_POST['reason'] ?? '', 500);

    if (!$doctorId || empty($date) || empty($time)) {
        $error = 'Silakan pilih dokter, tanggal, dan waktu.';
    } else {
        $scheduledAt = date('Y-m-d H:i:s', strtotime("$date $time"));
        
        if (strtotime($scheduledAt) < time()) {
            $error = 'Waktu reservasi tidak boleh di masa lalu.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO appointments (patient_id, doctor_id, scheduled_at, type, status, reason) 
                     VALUES (?, ?, ?, 'Consultation', 'waiting', ?)"
                );
                $stmt->execute([$user['id'], $doctorId, $scheduledAt, $reason]);
                $success = 'Reservasi berhasil dibuat! Silakan cek menu Beranda untuk statusnya.';
                audit_log('create_appointment', $user['id'], "Doc ID: $doctorId, Date: $scheduledAt");
            } catch (Exception $e) {
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

// Fetch Doctors
$doctors = $pdo->query(
    "SELECT u.id, u.name, dp.specialization 
     FROM users u 
     JOIN doctor_profiles dp ON dp.user_id = u.id 
     WHERE u.role = 'doctor' AND u.is_active = 1 AND dp.is_available = 1"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Buat Reservasi</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#2563eb') ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

  <!-- Main Content -->
  <main class="flex-1 p-6 lg:p-8 overflow-auto">
    <div class="max-w-3xl mx-auto w-full">
      
      <div class="mb-6">
        <a href="dashboard.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-blue-700 transition-colors bg-white px-4 py-2.5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Kembali ke Dashboard
        </a>
      </div>
      
      <div class="mb-8 flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center">
            <span class="material-symbols-outlined text-[24px]">calendar_add_on</span>
        </div>
        <div>
            <h1 class="text-2xl font-black text-slate-900">Buat Reservasi Baru</h1>
            <p class="text-slate-500 font-medium mt-1">Pilih jadwal untuk berkonsultasi dengan dokter kami.</p>
        </div>
      </div>

      <?= alert_html($error, 'error') ?>
      <?= alert_html($success, 'success') ?>

      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 md:p-8">
        <form method="POST" class="space-y-6">
            <?= csrf_field() ?>
            
            <div class="flex flex-col gap-2">
                <label class="text-sm font-bold text-slate-700">Pilih Dokter Spesialis</label>
                <select name="doctor_id" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                    <option value="">-- Pilih Dokter --</option>
                    <?php foreach ($doctors as $doc): ?>
                    <option value="<?= $doc['id'] ?>"><?= e($doc['name']) ?> (<?= e($doc['specialization']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-bold text-slate-700">Tanggal Kunjungan</label>
                    <input type="date" name="scheduled_date" required min="<?= date('Y-m-d') ?>" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-bold text-slate-700">Waktu Estimasi</label>
                    <input type="time" name="scheduled_time" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-bold text-slate-700">Keluhan / Alasan Kunjungan</label>
                <textarea name="reason" rows="3" placeholder="Tuliskan keluhan yang Anda rasakan..." required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"></textarea>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full md:w-auto px-8 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 shadow-md transition-colors flex items-center justify-center gap-2">
                    Konfirmasi Reservasi
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                </button>
            </div>
        </form>
      </div>

    </div>
  </main>
</body>
</html>
