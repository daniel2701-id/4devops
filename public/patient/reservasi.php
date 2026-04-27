<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_role('patient');

$user = current_user();
$pdo  = db();
$error   = '';
$success = '';

// ---- AJAX: get available time slots ----
if (isset($_GET['slots']) && isset($_GET['doctor_id']) && isset($_GET['date'])) {
    header('Content-Type: application/json');
    $doctorId = (int) $_GET['doctor_id'];
    $date     = $_GET['date'];

    // Validate date
    $ts = strtotime($date);
    if (!$ts || $date < date('Y-m-d')) {
        echo json_encode(['slots' => [], 'error' => 'Tanggal tidak valid.']);
        exit;
    }

    $dayOfWeek = (int) date('w', $ts); // 0=Sun, 6=Sat

    // Get doctor schedule for that day
    $schedule = null;
    try {
        $sched = $pdo->prepare(
            "SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1 LIMIT 1"
        );
        $sched->execute([$doctorId, $dayOfWeek]);
        $schedule = $sched->fetch();
    } catch (Exception $e) {}

    if (!$schedule) {
        // DEFAULT FALLBACK IF NO SCHEDULE SET
        $schedule = [
            'start_time' => '08:00:00',
            'end_time'   => '16:00:00',
            'slot_duration' => 30
        ];
    }

    // Generate slot times
    $start    = strtotime($date . ' ' . $schedule['start_time']);
    $end      = strtotime($date . ' ' . $schedule['end_time']);
    $duration = (int) $schedule['slot_duration'];
    $allSlots = [];
    for ($t = $start; $t < $end; $t += $duration * 60) {
        $allSlots[] = date('H:i', $t);
    }

    // Get booked slots
    $booked = $pdo->prepare(
        "SELECT TIME_FORMAT(scheduled_at,'%H:%i') as slot_time
         FROM appointments 
         WHERE doctor_id = ? AND DATE(scheduled_at) = ? AND status NOT IN ('cancelled')"
    );
    $booked->execute([$doctorId, $date]);
    $bookedTimes = array_column($booked->fetchAll(), 'slot_time');

    $result = [];
    foreach ($allSlots as $slot) {
        $result[] = [
            'time'      => $slot,
            'available' => !in_array($slot, $bookedTimes, true),
        ];
    }

    echo json_encode(['slots' => $result]);
    exit;
}

// ---- Handle Booking POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $doctorId    = (int) ($_POST['doctor_id'] ?? 0);
    $date        = $_POST['scheduled_date'] ?? '';
    $time        = $_POST['scheduled_time'] ?? '';
    $reason      = sanitize_string($_POST['reason'] ?? '', 500);
    $gender      = $_POST['gender'] ?? '';
    $age         = (int) ($_POST['age'] ?? 0);
    $blood_type  = $_POST['blood_type'] ?? '';

    if (!$doctorId || empty($date) || empty($time) || empty($gender) || !$age || empty($blood_type)) {
        $error = 'Silakan pilih dokter, jadwal, dan lengkapi data profil.';
    } else {
        $scheduledAt = date('Y-m-d H:i:s', strtotime("$date $time"));

        if (strtotime($scheduledAt) < time()) {
            $error = 'Waktu reservasi tidak boleh di masa lalu.';
        } else {
            // CONFLICT CHECK: prevent double-booking for same doctor + same slot
            $conflict = $pdo->prepare(
                "SELECT id FROM appointments 
                 WHERE doctor_id = ? AND scheduled_at = ? AND status NOT IN ('cancelled') LIMIT 1"
            );
            $conflict->execute([$doctorId, $scheduledAt]);

            if ($conflict->fetch()) {
                $error = 'Slot waktu tersebut sudah dipesan oleh pasien lain. Silakan pilih waktu lain.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "INSERT INTO appointments (patient_id, doctor_id, scheduled_at, type, status, reason) 
                         VALUES (?, ?, ?, 'Consultation', 'waiting', ?)"
                    );
                    $stmt->execute([$user['id'], $doctorId, $scheduledAt, $reason]);
                    $apptId = (int) $pdo->lastInsertId();

                    try {
                        $pdo->exec("ALTER TABLE patient_profiles ADD COLUMN age INT NULL AFTER birth_date");
                    } catch (Exception $e) {}

                    $pdo->prepare(
                        "INSERT INTO patient_profiles (user_id, gender, age, blood_type) 
                         VALUES (?, ?, ?, ?) 
                         ON DUPLICATE KEY UPDATE gender=VALUES(gender), age=VALUES(age), blood_type=VALUES(blood_type)"
                    )->execute([$user['id'], $gender, $age, $blood_type]);

                    $pdo->commit();
                    audit_log('create_appointment', $user['id'], "Doc ID: $doctorId, Date: $scheduledAt");
                    $success = 'Reservasi berhasil dibuat! Silakan cek menu Beranda untuk statusnya.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                }
            }
        }
    }
}

// Fetch Doctors (tanpa rating - fitur ulasan dihapus)
$doctors = [];
try {
    $doctors = $pdo->query(
        "SELECT u.id, u.name, dp.specialization
         FROM users u 
         JOIN doctor_profiles dp ON dp.user_id = u.id 
         WHERE u.role = 'doctor' AND u.is_active = 1 AND dp.is_available = 1
         ORDER BY u.name ASC"
    )->fetchAll();
} catch (Exception $e) {
    // Graceful degradation
}

// Unique specializations for filter dropdown
$specializations = array_unique(array_filter(array_column($doctors, 'specialization')));
sort($specializations);


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
<style>
body { font-family: 'Inter', sans-serif; }
.slot-btn { transition: all .15s ease; }
.slot-btn.available { @apply cursor-pointer; }
.slot-btn.booked { opacity: .45; cursor: not-allowed; }
.slot-btn.selected { background: #2563eb !important; color: white !important; border-color: #2563eb !important; }
#slot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px; }
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

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
          <p class="text-slate-500 font-medium mt-1">Pilih dokter dan slot waktu yang tersedia.</p>
        </div>
      </div>

      <?= alert_html($error, 'error') ?>
      <?= alert_html($success, 'success') ?>

      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 md:p-8">
        <form method="POST" id="reservasi-form" class="space-y-6">
          <?= csrf_field() ?>

          <!-- Filter & Search Doctors -->
          <div class="bg-slate-50 rounded-xl p-4 space-y-3">
            <label class="text-sm font-bold text-slate-700 flex items-center gap-2">
              <span class="material-symbols-outlined text-blue-500 text-[18px]">filter_alt</span>
              Filter & Cari Dokter
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <select id="filter-spec" onchange="filterDoctors()"
                      class="p-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <option value="">Semua Spesialisasi</option>
                <?php foreach ($specializations as $spec): ?>
                <option value="<?= e($spec) ?>"><?= e($spec) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="relative">
                <span class="material-symbols-outlined text-[18px] text-slate-400 absolute left-3 top-1/2 -translate-y-1/2">search</span>
                <input type="text" id="search-name" placeholder="Cari nama dokter..." oninput="filterDoctors()"
                       class="w-full pl-10 pr-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
              </div>
            </div>
          </div>

          <!-- Doctor Cards -->
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-3">Pilih Dokter Spesialis</label>
            <input type="hidden" name="doctor_id" id="doctor_id_input" required>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="doctor-cards">
              <?php foreach ($doctors as $doc): ?>
              <div class="doctor-card border-2 border-slate-200 rounded-xl p-4 cursor-pointer hover:border-blue-400 hover:bg-blue-50/40 transition-all select-none"
                   data-doctor-id="<?= $doc['id'] ?>"
                   data-spec="<?= e($doc['specialization'] ?? '') ?>"
                   data-name="<?= e(strtolower($doc['name'])) ?>"
                   onclick="selectDoctor(this, <?= $doc['id'] ?>)">
                <div class="flex items-start gap-3">
                  <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-sm flex-shrink-0">
                    <?= e(initials($doc['name'])) ?>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-bold text-slate-900 text-sm truncate"><?= e($doc['name']) ?></p>
                    <p class="text-xs text-slate-500"><?= e($doc['specialization'] ?? 'Umum') ?></p>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <p id="no-doctors-msg" class="hidden text-center text-sm text-slate-400 mt-4 py-4">Tidak ada dokter yang sesuai filter.</p>
          </div>

          <!-- Date -->
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">Tanggal Kunjungan</label>
            <input type="date" name="scheduled_date" id="date-input" required min="<?= date('Y-m-d') ?>"
                   onchange="loadSlots()"
                   class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
          </div>

          <!-- Time Slots -->
          <div id="slot-section" class="hidden">
            <label class="text-sm font-bold text-slate-700 block mb-2">
              Pilih Slot Waktu
              <span id="slot-loading" class="text-xs font-normal text-blue-500 ml-2 hidden">Memuat...</span>
            </label>
            <input type="hidden" name="scheduled_time" id="scheduled_time_input" required>
            <div id="slot-grid" class="min-h-[48px]"></div>
            <p id="slot-error" class="text-sm text-red-600 mt-2 hidden"></p>
          </div>



          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="text-sm font-bold text-slate-700 block mb-1.5">Jenis Kelamin</label>
              <select name="gender" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <option value="">Pilih...</option>
                <option value="male">Laki-laki</option>
                <option value="female">Perempuan</option>
              </select>
            </div>
            <div>
              <label class="text-sm font-bold text-slate-700 block mb-1.5">Usia (Tahun)</label>
              <input type="number" name="age" required min="0" max="150" placeholder="Contoh: 25"
                     class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            </div>
            <div>
              <label class="text-sm font-bold text-slate-700 block mb-1.5">Golongan Darah</label>
              <select name="blood_type" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                <option value="">Pilih...</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="AB">AB</option>
                <option value="O">O</option>
              </select>
            </div>
          </div>

          <!-- Reason -->
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">Keluhan Utama</label>
            <textarea name="reason" rows="3" placeholder="Ceritakan keluhan Anda secara singkat..."
                      class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 leading-relaxed"></textarea>
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

<script>
let selectedDoctorId = null;

function filterDoctors() {
  const specFilter = document.getElementById('filter-spec').value.toLowerCase();
  const nameSearch = document.getElementById('search-name').value.toLowerCase().trim();
  const cards      = document.querySelectorAll('.doctor-card');
  let visible = 0;

  cards.forEach(card => {
    const spec = (card.dataset.spec || '').toLowerCase();
    const name = (card.dataset.name || '');
    const matchSpec = !specFilter || spec === specFilter;
    const matchName = !nameSearch || name.includes(nameSearch);

    if (matchSpec && matchName) {
      card.style.display = '';
      visible++;
    } else {
      card.style.display = 'none';
    }
  });

  document.getElementById('no-doctors-msg').classList.toggle('hidden', visible > 0);
}

function selectDoctor(el, doctorId) {
  document.querySelectorAll('.doctor-card').forEach(c => {
    c.classList.remove('border-blue-500', 'bg-blue-50');
  });
  el.classList.add('border-blue-500', 'bg-blue-50');
  selectedDoctorId = doctorId;
  document.getElementById('doctor_id_input').value = doctorId;
  loadSlots();
}

function loadSlots() {
  const dateInput = document.getElementById('date-input').value;
  if (!selectedDoctorId || !dateInput) return;

  const section  = document.getElementById('slot-section');
  const grid     = document.getElementById('slot-grid');
  const loading  = document.getElementById('slot-loading');
  const slotErr  = document.getElementById('slot-error');
  const timeInput = document.getElementById('scheduled_time_input');

  section.classList.remove('hidden');
  loading.classList.remove('hidden');
  grid.innerHTML = '';
  timeInput.value = '';
  slotErr.classList.add('hidden');

  fetch(`reservasi.php?slots=1&doctor_id=${selectedDoctorId}&date=${dateInput}`)
    .then(r => r.json())
    .then(data => {
      loading.classList.add('hidden');
      if (data.error) {
        slotErr.textContent = data.error;
        slotErr.classList.remove('hidden');
        return;
      }
      if (!data.slots.length) {
        slotErr.textContent = 'Tidak ada slot tersedia untuk tanggal ini.';
        slotErr.classList.remove('hidden');
        return;
      }
      data.slots.forEach(s => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = s.time;
        btn.className = 'slot-btn text-sm font-bold py-2 rounded-lg border-2 ' +
          (s.available
            ? 'border-slate-200 bg-white text-slate-700 hover:border-blue-500 hover:bg-blue-50 available'
            : 'border-slate-100 bg-slate-50 text-slate-400 booked');
        if (s.available) {
          btn.onclick = () => {
            document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            timeInput.value = s.time;
          };
        }
        grid.appendChild(btn);
      });
    })
    .catch(() => {
      loading.classList.add('hidden');
      slotErr.textContent = 'Gagal memuat slot. Coba lagi.';
      slotErr.classList.remove('hidden');
    });
}

document.getElementById('reservasi-form').addEventListener('submit', function(e) {
  if (!selectedDoctorId) {
    e.preventDefault();
    alert('Silakan pilih dokter terlebih dahulu.');
    return;
  }
  if (!document.getElementById('scheduled_time_input').value) {
    e.preventDefault();
    alert('Silakan pilih slot waktu terlebih dahulu.');
  }
});
</script>
</body>
</html>
