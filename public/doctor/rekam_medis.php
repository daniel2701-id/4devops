<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';
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

// ---- PDF Download ----
if (isset($_GET['pdf']) && $_GET['pdf'] === '1') {
    $stmt = $pdo->prepare(
        "SELECT a.*, p.name AS patient_name, pp.gender, pp.birth_date, pp.blood_type, pp.nik, pp.phone,
                u.name AS doctor_name, dp.specialization, dp.license_number
         FROM appointments a 
         JOIN users p ON p.id = a.patient_id 
         JOIN users u ON u.id = a.doctor_id
         LEFT JOIN patient_profiles pp ON pp.user_id = p.id
         LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
         WHERE a.id = ? AND a.doctor_id = ?"
    );
    $stmt->execute([$apptId, $user['id']]);
    $apptData = $stmt->fetch();

    if (!$apptData) { die('Data tidak ditemukan.'); }

    $rec = $pdo->prepare("SELECT * FROM medical_records WHERE appointment_id = ? LIMIT 1");
    $rec->execute([$apptId]);
    $recData = $rec->fetch();

    if (!$recData) { die('Rekam medis belum diisi.'); }

    // Generate clean HTML for print/PDF
    $ageYears = $apptData['birth_date']
        ? date_diff(date_create($apptData['birth_date']), date_create('now'))->y . ' tahun'
        : '-';

    $prescLines     = nl2br(htmlspecialchars($recData['prescription'] ?? '-'));
    $diagnosisHtml  = nl2br(htmlspecialchars($recData['diagnosis'] ?? '-'));
    $treatmentHtml  = nl2br(htmlspecialchars($recData['treatment'] ?? '-'));
    $scheduledDate  = date('d F Y', strtotime($apptData['scheduled_at']));
    $todayDate      = date('d F Y');

    header('Content-Type: text/html; charset=UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Resep – {$apptData['patient_name']}</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Times New Roman', serif; font-size: 13px; color: #111; background: #fff; padding: 30px; }
  .letterhead { border-bottom: 3px double #1e40af; padding-bottom: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; }
  .clinic-name { font-size: 22px; font-weight: 900; color: #1e40af; letter-spacing: -0.5px; }
  .clinic-sub { font-size: 11px; color: #64748b; }
  .doctor-info { font-size: 12px; color: #374151; line-height: 1.6; }
  .rx-title { font-size: 48px; color: #1e40af; font-style: italic; font-weight: 900; float: left; line-height: 1; margin-right: 8px; }
  .patient-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 12px 16px; margin-bottom: 18px; }
  .patient-box table { width: 100%; }
  .patient-box td { padding: 2px 8px 2px 0; font-size: 12px; vertical-align: top; }
  .patient-box td:first-child { font-weight: 700; color: #374151; width: 120px; }
  .section { margin-bottom: 16px; }
  .section-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; font-weight: 700; margin-bottom: 4px; }
  .section-body { background: #f8fafc; border-left: 3px solid #2563eb; padding: 10px 14px; border-radius: 0 6px 6px 0; font-size: 13px; line-height: 1.8; }
  .prescription-body { background: #fefce8; border-left: 3px solid #d97706; }
  .footer { margin-top: 40px; display: flex; justify-content: flex-end; }
  .sig-box { text-align: center; }
  .sig-line { margin-top: 60px; border-top: 1px solid #374151; padding-top: 6px; font-size: 12px; }
  .date-stamp { font-size: 11px; color: #6b7280; }
  @media print { body { padding: 15px; } }
</style>
</head>
<body>
<div class="letterhead">
  <div>
    <div class="clinic-name">&#9829; CareConnect</div>
    <div class="clinic-sub">Platform Kesehatan Digital &bull; careconnect.id</div>
  </div>
  <div style="margin-left:auto;text-align:right;" class="doctor-info">
    <strong>{$apptData['doctor_name']}</strong><br>
    {$apptData['specialization']}<br>
    STR: {$apptData['license_number']}
  </div>
</div>

<div class="patient-box">
  <table>
    <tr><td>Nama Pasien</td><td>: <strong>{$apptData['patient_name']}</strong></td>
        <td>NIK</td><td>: {$apptData['nik']}</td></tr>
    <tr><td>Tanggal Lahir</td><td>: {$apptData['birth_date']} ({$ageYears})</td>
        <td>Gol. Darah</td><td>: {$apptData['blood_type']}</td></tr>
    <tr><td>Jenis Kelamin</td><td>: {$apptData['gender']}</td>
        <td>Tanggal Periksa</td><td>: {$scheduledDate}</td></tr>
  </table>
</div>

<div class="section">
  <div class="section-label">Diagnosis</div>
  <div class="section-body">{$diagnosisHtml}</div>
</div>

<div class="section">
  <div class="section-label">Tindakan / Perawatan</div>
  <div class="section-body">{$treatmentHtml}</div>
</div>

<div class="section">
  <div class="section-label">&#82;&#120; &nbsp; Resep Elektronik</div>
  <div class="section-body prescription-body">{$prescLines}</div>
</div>

<div class="footer">
  <div class="sig-box">
    <div class="date-stamp">Jakarta, {$todayDate}</div>
    <div class="sig-line">{$apptData['doctor_name']}<br><span style='font-size:10px;color:#64748b'>{$apptData['specialization']}</span></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
window.addEventListener('load', () => {
    const element = document.body;
    const opt = {
      margin:       0.5,
      filename:     'Resep_{$apptData['patient_name']}.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
});
</script>
</body>
</html>
HTML;
    exit;
}

// Check appointment
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

$stmt2 = $pdo->prepare("SELECT * FROM medical_records WHERE appointment_id = ? LIMIT 1");
$stmt2->execute([$apptId]);
$record = $stmt2->fetch() ?: [];


// Handle main form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['attachment'])) {
    csrf_abort();
    $diagnosis    = sanitize_string($_POST['diagnosis'] ?? '', 1000);
    $treatment    = sanitize_string($_POST['treatment'] ?? '', 1000);
    $prescription = sanitize_string($_POST['prescription'] ?? '', 1000);

    if (empty($diagnosis)) {
        $error = 'Diagnosis tidak boleh kosong.';
    } else {
        if (!empty($record)) {
            $pdo->prepare("UPDATE medical_records SET diagnosis=?, treatment=?, prescription=? WHERE id=?")
                ->execute([$diagnosis, $treatment, $prescription, $record['id']]);
        } else {
            $pdo->prepare(
                "INSERT INTO medical_records (appointment_id, patient_id, doctor_id, diagnosis, treatment, prescription) VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$apptId, $appt['patient_id'], $user['id'], $diagnosis, $treatment, $prescription]);
        }

        if ($appt['status'] !== 'finished') {
            $pdo->prepare("UPDATE appointments SET status='finished' WHERE id=?")->execute([$apptId]);
            $appt['status'] = 'finished';

            // Status notification to patient
            $patient = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
            $patient->execute([$appt['patient_id']]);
            $patientRow = $patient->fetch();
            if ($patientRow) {
                $appt['doctor_name'] = $user['name'];
                create_notification(
                    $patientRow['id'],
                    'status_change',
                    'Konsultasi Selesai',
                    'Konsultasi Anda dengan ' . $user['name'] . ' telah selesai. Rekam medis & resep tersedia.',
                    $apptId
                );
                send_status_notification($appt, $patientRow, 'finished');
            }
        }

        flash('success', 'Catatan Rekam Medis berhasil disimpan.');
        header("Location: rekam_medis.php?appt_id=$apptId");
        exit;
    }
}

$success = flash('success') ?? $success;

// Refresh record after save
$stmt2->execute([$apptId]);
$record = $stmt2->fetch() ?: [];


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
      <?php $navItems = [
        ['icon'=>'home',             'label'=>'Beranda',    'href'=>'dashboard.php',   'active'=>false],
        ['icon'=>'calendar_month',   'label'=>'Jadwal',     'href'=>'jadwal.php',      'active'=>false],
        ['icon'=>'chat',             'label'=>'Chat',       'href'=>'chat.php',        'active'=>false],
      ];
      foreach ($navItems as $item):
        $cls = $item['active'] ? 'bg-primary-fixed text-primary font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800 font-medium'; ?>
      <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
        <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
        <?= e($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-slate-100">
      <a href="<?= APP_URL ?>/doctor/logout.php"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors">
        <span class="material-symbols-outlined text-[20px]">logout</span>Keluar
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
        <p class="text-slate-500 font-medium mt-1">Dokumentasi klinis &amp; resep elektronik.</p>
      </div>
      <iframe name="pdfFrame" id="pdfFrame" style="position:absolute; left:-9999px; top:-9999px; width:800px; height:1200px; border:none;"></iframe>
      <?php if (!empty($record)): ?>
      <a href="javascript:void(0)"
         onclick="const btn=this; btn.innerHTML='<span class=\'material-symbols-outlined text-[18px] animate-spin\'>sync</span> Memproses...'; btn.classList.add('opacity-75','pointer-events-none'); document.getElementById('pdfFrame').src='rekam_medis.php?appt_id=<?= $apptId ?>&pdf=1'; setTimeout(()=>{btn.innerHTML='<span class=\'material-symbols-outlined text-[18px]\'>picture_as_pdf</span> Unduh Resep PDF'; btn.classList.remove('opacity-75','pointer-events-none');}, 3000);"
         class="ml-auto inline-flex items-center gap-2 bg-emerald-600 text-white font-bold px-5 py-2.5 rounded-xl hover:bg-emerald-700 transition-colors text-sm shadow-md">
        <span class="material-symbols-outlined text-[18px]">picture_as_pdf</span>
        Unduh Resep PDF
      </a>
      <?php endif; ?>
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
            "<?= e($appt['reason'] ?: 'Tidak ada keluhan spesifik.') ?>"
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
              <textarea name="diagnosis" rows="3" required placeholder="Tuliskan diagnosis medis..."
                        class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 leading-relaxed"><?= e($_POST['diagnosis'] ?? $record['diagnosis'] ?? '') ?></textarea>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-bold text-slate-700 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[18px]">healing</span>
                Tindakan / Perawatan
              </label>
              <textarea name="treatment" rows="3" placeholder="Jelaskan tindakan yang telah diberikan..."
                        class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 leading-relaxed"><?= e($_POST['treatment'] ?? $record['treatment'] ?? '') ?></textarea>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm font-bold text-slate-700 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[18px]">medication</span>
                E-Prescription (Resep Elektronik)
              </label>
              <textarea name="prescription" rows="4" placeholder="Contoh:&#10;Amoxicillin 500mg 3x1 (7 hari)&#10;Paracetamol 500mg 3x1 (jika demam)&#10;Vitamin C 250mg 1x1"
                        class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-mono text-sm leading-relaxed"><?= e($_POST['prescription'] ?? $record['prescription'] ?? '') ?></textarea>
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
