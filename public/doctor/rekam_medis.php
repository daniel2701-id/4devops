<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('doctor');

$user = current_user();
$pdo  = db();
$error   = '';
$success = '';

$apptId = (int) ($_GET['appt_id'] ?? 0);

if (!$apptId) {
    header('Location: jadwal.php');
    exit;
}

// Check if appointment exists and belongs to this doctor
$stmt = $pdo->prepare(
    "SELECT a.*, p.name AS patient_name, pp.gender, pp.birth_date, pp.blood_type 
     FROM appointments a 
     JOIN users p ON p.id = a.patient_id 
     LEFT JOIN patient_profiles pp ON pp.user_id = p.id
     WHERE a.id = ? AND a.doctor_id = ?"
);
$stmt->execute([$apptId, $user['id']]);
$appt = $stmt->fetch();

if (!$appt) {
    die('Data tidak ditemukan atau Anda tidak berhak mengakses.');
}

// Fetch existing medical record
$stmt2 = $pdo->prepare("SELECT * FROM medical_records WHERE appointment_id = ? LIMIT 1");
$stmt2->execute([$apptId]);
$record = $stmt2->fetch() ?: [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $diagnosis    = sanitize_string($_POST['diagnosis'] ?? '', 1000);
    $treatment    = sanitize_string($_POST['treatment'] ?? '', 1000);
    $prescription = sanitize_string($_POST['prescription'] ?? '', 1000);

    if (empty($diagnosis)) {
        $error = 'Diagnosis tidak boleh kosong.';
    } else {
        if (!empty($record)) {
            // Update
            $upd = $pdo->prepare("UPDATE medical_records SET diagnosis = ?, treatment = ?, prescription = ? WHERE id = ?");
            $upd->execute([$diagnosis, $treatment, $prescription, $record['id']]);
        } else {
            // Insert
            $ins = $pdo->prepare(
                "INSERT INTO medical_records (appointment_id, patient_id, doctor_id, diagnosis, treatment, prescription) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([$apptId, $appt['patient_id'], $user['id'], $diagnosis, $treatment, $prescription]);
        }
        
        // Auto-finish appointment if not finished
        if ($appt['status'] !== 'finished') {
            $pdo->prepare("UPDATE appointments SET status = 'finished' WHERE id = ?")->execute([$apptId]);
            $appt['status'] = 'finished'; // Update local state for display
        }

        flash('success', 'Catatan Rekam Medis berhasil disimpan.');
        header("Location: rekam_medis.php?appt_id=$apptId");
        exit;
    }
}

$success = flash('success');

// Helper for age
$age = $appt['birth_date'] ? date_diff(date_create($appt['birth_date']), date_create('now'))->y : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Isi Rekam Medis</title>
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
        ['icon'=>'home',         'label'=>'Beranda',       'href'=>'dashboard.php', 'active'=>false],
        ['icon'=>'calendar_month','label'=>'Jadwal Saya',  'href'=>'jadwal.php',    'active'=>false],
        ['icon'=>'group',        'label'=>'Pasien',        'href'=>'#',             'active'=>false],
        ['icon'=>'chat_bubble',  'label'=>'Konsultasi',    'href'=>'#',             'active'=>true],
        ['icon'=>'person',       'label'=>'Profil',        'href'=>'#',             'active'=>false],
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

    <div class="mb-8 flex items-center gap-3">
        <a href="jadwal.php" class="w-10 h-10 bg-white border border-slate-200 rounded-xl flex items-center justify-center text-slate-500 hover:text-primary hover:border-primary-light transition-colors shadow-sm">
        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div>
        <h1 class="text-2xl font-black text-slate-900">Catatan Rekam Medis</h1>
        <p class="text-slate-500 font-medium mt-1">Dokumentasi klinis & resep elektronik.</p>
        </div>
    </div>

    <?= alert_html($error, 'error') ?>
    <?= alert_html($success, 'success') ?>

    <div class="grid lg:grid-cols-3 gap-6">
        <!-- Pasien Info -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center font-bold text-xl mb-4">
                    <?= e(initials($appt['patient_name'])) ?>
                </div>
                <h2 class="text-lg font-bold text-slate-900"><?= e($appt['patient_name']) ?></h2>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <span class="text-slate-500">Gender</span>
                        <span class="font-medium text-slate-800 capitalize"><?= e($appt['gender'] ?? '-') ?></span>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <span class="text-slate-500">Usia</span>
                        <span class="font-medium text-slate-800"><?= $age ?> tahun</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <span class="text-slate-500">Gol. Darah</span>
                        <span class="font-medium text-slate-800 uppercase"><?= e($appt['blood_type'] ?? '-') ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-primary-fixed border border-primary/20 rounded-2xl p-6 shadow-sm">
                <h3 class="text-xs font-bold text-primary uppercase tracking-wider mb-2">Alasan Kunjungan</h3>
                <p class="text-sm text-slate-700 font-medium leading-relaxed">
                    "<?= e($appt['reason'] ?: 'Tidak ada keluhan spesifik dicantumkan.') ?>"
                </p>
                <div class="mt-4 pt-4 border-t border-primary/10 flex justify-between items-center text-xs font-medium text-primary">
                    <span><?= format_date($appt['scheduled_at'], 'd M Y') ?></span>
                    <span><?= date('H:i', strtotime($appt['scheduled_at'])) ?> WIB</span>
                </div>
            </div>
        </div>

        <!-- Form Rekam Medis -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8">
                <form method="POST" class="space-y-6">
                    <?= csrf_field() ?>
                    
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-slate-700 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[18px]">stethoscope</span>
                            Diagnosis (Wajib)
                        </label>
                        <textarea name="diagnosis" rows="3" required placeholder="Tuliskan diagnosis medis..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 leading-relaxed"><?= e($_POST['diagnosis'] ?? $record['diagnosis'] ?? '') ?></textarea>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-slate-700 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[18px]">healing</span>
                            Tindakan / Perawatan
                        </label>
                        <textarea name="treatment" rows="3" placeholder="Jelaskan tindakan yang telah diberikan..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 leading-relaxed"><?= e($_POST['treatment'] ?? $record['treatment'] ?? '') ?></textarea>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-bold text-slate-700 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[18px]">medication</span>
                            E-Prescription (Resep Elektronik)
                        </label>
                        <textarea name="prescription" rows="3" placeholder="Tulis rincian resep obat untuk pasien..." class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-mono text-sm leading-relaxed"><?= e($_POST['prescription'] ?? $record['prescription'] ?? '') ?></textarea>
                    </div>

                    <div class="pt-4 flex items-center justify-between">
                        <p class="text-xs text-slate-400 font-medium">* Status sesi akan otomatis menjadi Selesai setelah disimpan.</p>
                        <button type="submit" class="inline-flex items-center gap-2 bg-primary text-white px-8 py-3 rounded-xl font-bold text-sm hover:bg-primary-light transition-colors shadow-md active:scale-95">
                            <span class="material-symbols-outlined text-[20px]">save</span>
                            <?= empty($record) ? 'Simpan Rekam Medis' : 'Perbarui Rekam Medis' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

  </main>
</body>
</html>
