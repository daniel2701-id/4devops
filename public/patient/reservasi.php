<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_role('patient');

$user = current_user();
$pdo  = db();
$error   = '';
$success = '';

// Check if user has profile
$hasProfile = false;
$myProfile = [];
try {
    $stmtProf = $pdo->prepare("SELECT gender, age, blood_type FROM patient_profiles WHERE user_id = ?");
    $stmtProf->execute([$user['id']]);
    if ($row = $stmtProf->fetch()) {
        $myProfile = $row;
        if (!empty($myProfile['gender']) && !empty($myProfile['age'])) {
            $hasProfile = true;
        }
    }
} catch (Exception $e) {}

// Fetch Doctors with specializations
$doctors = [];
try {
    $doctors = $pdo->query(
        "SELECT u.id, u.name, dp.specialization
         FROM users u 
         JOIN doctor_profiles dp ON dp.user_id = u.id 
         WHERE u.role = 'doctor' AND u.is_active = 1 AND dp.is_available = 1
         ORDER BY u.name ASC"
    )->fetchAll();
} catch (Exception $e) {}

// Pass doctors to JS
$doctorsJson = json_encode($doctors);

// Handle Booking POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $doctorId    = (int) ($_POST['doctor_id'] ?? 0);
    $date        = $_POST['scheduled_date'] ?? '';
    $time        = $_POST['scheduled_time'] ?? '';
    
    // The new reason format: "Symptoms: ... \nAI Diagnosis: ... (Specialization)"
    $symptomsRaw = $_POST['symptoms_raw'] ?? '';
    $aiDiagnosis = $_POST['ai_diagnosis'] ?? '';
    $aiSpec      = $_POST['ai_spec'] ?? '';
    
    $reason = "[INPUT MANUAL PASIEN]\n" . sanitize_string($symptomsRaw, 1000) . "\n\n[HASIL AI ANALYST]\nDiagnosis Awal: " . sanitize_string($aiDiagnosis, 1000) . "\nRekomendasi Spesialisasi: " . sanitize_string($aiSpec, 100);
    
    $gender      = $_POST['gender'] ?? '';
    $age         = (int) ($_POST['age'] ?? 0);
    $blood_type  = $_POST['blood_type'] ?? '';

    if ($hasProfile) {
        $gender = $myProfile['gender'];
        $age = $myProfile['age'];
        $blood_type = $myProfile['blood_type'];
    }

    if (!$doctorId || empty($date) || empty($time) || empty($gender) || !$age || empty($blood_type)) {
        $error = 'Silakan pilih dokter, jadwal, dan lengkapi data profil.';
    } else {
        $scheduledAt = date('Y-m-d H:i:s', strtotime("$date $time"));

        if (strtotime($scheduledAt) < time()) {
            $error = 'Waktu reservasi tidak boleh di masa lalu.';
        } else {
            // CONFLICT CHECK
            $conflict = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND scheduled_at = ? AND status NOT IN ('cancelled') LIMIT 1");
            $conflict->execute([$doctorId, $scheduledAt]);

            if ($conflict->fetch()) {
                $error = 'Slot waktu tersebut sudah dipesan. Silakan pilih waktu lain.';
            } else {
                try {
                    $pdo->exec("ALTER TABLE patient_profiles ADD COLUMN age INT NULL AFTER birth_date");
                } catch (Exception $e) {}

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "INSERT INTO appointments (patient_id, doctor_id, scheduled_at, type, status, reason) 
                         VALUES (?, ?, ?, 'Consultation', 'waiting', ?)"
                    );
                    $stmt->execute([$user['id'], $doctorId, $scheduledAt, $reason]);

                    $pdo->prepare(
                        "INSERT INTO patient_profiles (user_id, gender, age, blood_type) 
                         VALUES (?, ?, ?, ?) 
                         ON DUPLICATE KEY UPDATE gender=VALUES(gender), age=VALUES(age), blood_type=VALUES(blood_type)"
                    )->execute([$user['id'], $gender, $age, $blood_type]);

                    $pdo->commit();
                    audit_log('create_appointment', $user['id'], "Doc ID: $doctorId, Date: $scheduledAt");
                    $success = 'Reservasi berhasil dibuat! Silakan cek menu Beranda untuk statusnya.';
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                }
            }
        }
    }
}
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
.slot-btn.available { @apply cursor-pointer bg-white border-slate-200 text-slate-700 hover:border-blue-600 hover:bg-blue-600 hover:text-white; }
.slot-btn.booked { @apply hidden; }
.slot-btn.selected { @apply bg-blue-600 text-white border-blue-600 shadow-md hover:bg-blue-700 hover:border-blue-700; }
.doctor-card.selected { @apply border-blue-500 bg-blue-50/50 ring-2 ring-blue-500/20; }
.step-container { display: none; }
.step-container.active { display: block; animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
/* Hide scrollbar for horizontal scroll area */
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

  <main class="flex-1 p-6 lg:p-8 overflow-auto">
    <div class="max-w-4xl mx-auto w-full">

      <div class="mb-6 flex justify-between items-center">
        <a href="dashboard.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-blue-700 transition-colors bg-white px-4 py-2.5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span>
          Kembali ke Dashboard
        </a>
        <div class="text-sm font-bold text-slate-500 bg-white px-4 py-2 border border-slate-200 rounded-lg">
            Langkah <span id="current-step-indicator" class="text-blue-600">1</span> dari 4
        </div>
      </div>

      <?= alert_html($error, 'error') ?>
      <?= alert_html($success, 'success') ?>

      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 md:p-8">
        <form method="POST" id="reservasi-form" class="space-y-6">
          <?= csrf_field() ?>
          <input type="hidden" name="doctor_id" id="doctor_id_input">
          <input type="hidden" name="scheduled_date" id="scheduled_date_input">
          <input type="hidden" name="scheduled_time" id="scheduled_time_input">
          <input type="hidden" name="symptoms_raw" id="symptoms_raw_input">
          <input type="hidden" name="ai_diagnosis" id="ai_diagnosis_input">
          <input type="hidden" name="ai_spec" id="ai_spec_input">

          <!-- STEP 1: Gejala & Analisis -->
          <div id="step-1" class="step-container active">
            <div class="mb-6">
                <h2 class="text-2xl font-black text-slate-900 flex items-center gap-3 mb-2">
                    <span class="material-symbols-outlined text-blue-600 text-3xl">psychiatry</span>
                    Keluhan & Gejala
                </h2>
                <p class="text-slate-500 text-sm mb-4">
                  Jelaskan keluhan medis yang Anda alami secara mendetail.
                </p>
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
                  <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-blue-600 mt-0.5">smart_toy</span>
                    <div>
                      <strong class="font-bold block mb-1">Mengenal Fitur AI Analyst CareConnect</strong>
                      <p>Asisten AI cerdas kami akan menganalisis gejala yang Anda inputkan untuk memberikan <strong>prakiraan diagnosis awal, tingkat urgensi, serta rekomendasi dokter spesialis yang paling relevan</strong>. Hasil analisis dan input keluhan Anda akan dikirimkan kepada dokter untuk membantu mereka memberikan diagnosis medis yang lebih akurat saat sesi konsultasi.</p>
                    </div>
                  </div>
                </div>
            </div>
            
            <div class="space-y-4">
                <textarea id="symptoms-text" rows="5" placeholder="Contoh: Saya mengalami sakit kepala sebelah kanan sejak 3 hari yang lalu, disertai mual dan sensitif terhadap cahaya..."
                          class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 leading-relaxed resize-none"></textarea>
                
                <div id="ai-error" class="hidden bg-red-50 text-red-600 border border-red-200 p-4 rounded-xl text-sm"></div>

                <button type="button" id="btn-analyze" onclick="analyzeSymptoms()" class="w-full md:w-auto px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 shadow-md transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[20px]" id="analyze-icon">smart_toy</span>
                    <span>Analisis Gejala</span>
                </button>
            </div>
          </div>

          <!-- STEP 2: Hasil AI & Pilih Dokter -->
          <div id="step-2" class="step-container">
            <div class="mb-6 flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 flex items-center gap-3 mb-2">
                        <span class="material-symbols-outlined text-blue-600 text-3xl">stethoscope</span>
                        Rekomendasi Dokter
                    </h2>
                    <p class="text-slate-500 text-sm">Berdasarkan analisis AI, berikut adalah rekomendasi dokter yang sesuai untuk Anda.</p>
                </div>
                <button type="button" onclick="goToStep(1)" class="text-sm font-bold text-slate-500 hover:text-blue-600 underline">Ubah Gejala</button>
            </div>

            <!-- AI Result Card -->
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-2xl p-5 mb-8 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 bg-white rounded-xl shadow-sm flex items-center justify-center text-blue-600 flex-shrink-0">
                        <span class="material-symbols-outlined text-2xl">vital_signs</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Hasil Analisis</span>
                            <span id="ai-urgency-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold"></span>
                        </div>
                        <h3 id="ai-disease-name" class="text-lg font-black text-slate-900 mb-1"></h3>
                        <p id="ai-disease-desc" class="text-sm text-slate-600 mb-3"></p>
                        
                        <div class="bg-white/60 p-3 rounded-lg border border-white">
                            <p class="text-xs font-bold text-slate-500 mb-1">Saran Spesialisasi:</p>
                            <p id="ai-recommended-spec" class="text-sm font-bold text-blue-800"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <h3 class="font-bold text-slate-700">Pilih Dokter <span id="spec-label-display"></span></h3>
                <div id="doctor-list-container" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Doctors injected here by JS -->
                </div>
                <div id="no-doctor-msg" class="hidden p-6 text-center bg-amber-50 border border-amber-200 rounded-xl">
                    <span class="material-symbols-outlined text-amber-500 text-4xl mb-2">warning</span>
                    <p class="text-amber-800 font-bold">Mohon maaf, tidak ada dokter Spesialis <span id="no-doc-spec-name"></span> yang tersedia saat ini.</p>
                    <p class="text-sm text-amber-700 mt-1">Sesuai anjuran medis, silakan pilih spesialisasi Umum sebagai alternatif pertama.</p>
                    <button type="button" onclick="loadDoctorsForSpec('Umum')" class="mt-4 px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-bold hover:bg-amber-700">Tampilkan Dokter Umum</button>
                </div>
            </div>
          </div>

          <!-- STEP 3: Jadwal Real-Time -->
          <div id="step-3" class="step-container">
            <div class="mb-6 flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 flex items-center gap-3 mb-2">
                        <span class="material-symbols-outlined text-blue-600 text-3xl">calendar_month</span>
                        Pilih Jadwal
                    </h2>
                    <p class="text-slate-500 text-sm">Pilih waktu kunjungan untuk dokter <strong id="selected-doc-name" class="text-slate-800"></strong>.</p>
                </div>
                <button type="button" onclick="goToStep(2)" class="text-sm font-bold text-slate-500 hover:text-blue-600 underline">Ganti Dokter</button>
            </div>

            <div id="schedule-loading" class="py-12 text-center hidden">
                <span class="material-symbols-outlined animate-spin text-blue-500 text-4xl">sync</span>
                <p class="text-slate-500 font-medium mt-2">Memuat jadwal dokter...</p>
            </div>

            <div id="schedule-container" class="space-y-6 hidden">
                <!-- Schedule days injected here -->
            </div>
            
            <div id="schedule-error" class="hidden bg-red-50 text-red-600 p-4 rounded-xl text-center"></div>
          </div>

          <!-- STEP 4: Konfirmasi & Profil Lengkap -->
          <div id="step-4" class="step-container">
            <div class="mb-6 flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-black text-slate-900 flex items-center gap-3 mb-2">
                        <span class="material-symbols-outlined text-blue-600 text-3xl">check_circle</span>
                        Konfirmasi Reservasi
                    </h2>
                    <p class="text-slate-500 text-sm">Periksa kembali detail reservasi Anda sebelum menyimpan.</p>
                </div>
                <button type="button" onclick="goToStep(3)" class="text-sm font-bold text-slate-500 hover:text-blue-600 underline">Ganti Jadwal</button>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 mb-6">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-slate-500 mb-1">Dokter</p>
                        <p id="confirm-doc" class="font-bold text-slate-900"></p>
                    </div>
                    <div>
                        <p class="text-slate-500 mb-1">Waktu</p>
                        <p id="confirm-time" class="font-bold text-slate-900"></p>
                    </div>
                </div>
            </div>

            <?php if (!$hasProfile): ?>
            <div class="mb-6">
                <h3 class="font-bold text-slate-700 mb-3">Lengkapi Data Pasien</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                    <label class="text-sm font-bold text-slate-700 block mb-1.5">Jenis Kelamin</label>
                    <select name="gender" required id="gender-input" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500">
                        <option value="">Pilih...</option>
                        <option value="male">Laki-laki</option>
                        <option value="female">Perempuan</option>
                    </select>
                    </div>
                    <div>
                    <label class="text-sm font-bold text-slate-700 block mb-1.5">Usia (Tahun)</label>
                    <input type="number" name="age" id="age-input" required min="0" max="150" placeholder="Contoh: 25"
                            class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                    <label class="text-sm font-bold text-slate-700 block mb-1.5">Golongan Darah</label>
                    <select name="blood_type" required id="blood-input" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500">
                        <option value="">Pilih...</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="AB">AB</option>
                        <option value="O">O</option>
                    </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="pt-4">
              <button type="submit" id="btn-submit" class="w-full px-8 py-4 bg-blue-600 text-white rounded-xl font-bold text-lg hover:bg-blue-700 shadow-md transition-colors flex items-center justify-center gap-2">
                Simpan Reservasi Sekarang
                <span class="material-symbols-outlined text-[24px]">send</span>
              </button>
            </div>
          </div>

        </form>
      </div>

    </div>
  </main>

<script>
const allDoctors = <?= $doctorsJson ?>;
let currentStep = 1;
let currentSpec = '';
let hasProfile = <?= $hasProfile ? 'true' : 'false' ?>;

function goToStep(step) {
    document.querySelectorAll('.step-container').forEach(el => el.classList.remove('active'));
    document.getElementById('step-' + step).classList.add('active');
    document.getElementById('current-step-indicator').innerText = step;
    currentStep = step;
}

// STEP 1: Analyze
async function analyzeSymptoms() {
    const sympInput = document.getElementById('symptoms-text').value.trim();
    const errorBox = document.getElementById('ai-error');
    const btn = document.getElementById('btn-analyze');
    const icon = document.getElementById('analyze-icon');
    
    if (sympInput.length < 10) {
        errorBox.textContent = "Mohon jelaskan gejala Anda secara lebih detail (minimal 10 karakter).";
        errorBox.classList.remove('hidden');
        return;
    }
    
    errorBox.classList.add('hidden');
    btn.disabled = true;
    icon.classList.add('animate-spin');
    icon.innerText = 'sync';
    btn.querySelector('span:last-child').innerText = 'Menganalisis...';
    
    try {
        const response = await fetch('../api/analyze_symptoms.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ symptoms: sympInput })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            errorBox.textContent = data.error;
            errorBox.classList.remove('hidden');
        } else {
            // Save to hidden inputs
            document.getElementById('symptoms_raw_input').value = sympInput;
            document.getElementById('ai_diagnosis_input').value = data.disease;
            document.getElementById('ai_spec_input').value = data.specialization;
            
            // Populate Step 2 UI
            document.getElementById('ai-disease-name').textContent = data.disease;
            document.getElementById('ai-disease-desc').textContent = data.description;
            document.getElementById('ai-recommended-spec').textContent = data.specialization;
            
            const badge = document.getElementById('ai-urgency-badge');
            if (data.urgency === 'high') {
                badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200';
                badge.textContent = 'URGENSI TINGGI';
            } else if (data.urgency === 'medium') {
                badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200';
                badge.textContent = 'URGENSI SEDANG';
            } else {
                badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700 border border-green-200';
                badge.textContent = 'URGENSI RENDAH';
            }
            
            loadDoctorsForSpec(data.specialization);
            goToStep(2);
        }
    } catch (err) {
        errorBox.textContent = "Terjadi kesalahan jaringan saat menghubungi AI.";
        errorBox.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        icon.classList.remove('animate-spin');
        icon.innerText = 'smart_toy';
        btn.querySelector('span:last-child').innerText = 'Analisis Gejala';
    }
}

// STEP 2: Load Doctors
function loadDoctorsForSpec(spec) {
    currentSpec = spec;
    document.getElementById('spec-label-display').textContent = spec;
    const container = document.getElementById('doctor-list-container');
    const noMsg = document.getElementById('no-doctor-msg');
    
    container.innerHTML = '';
    
    let filtered = allDoctors.filter(d => d.specialization === spec);
    
    if (filtered.length === 0) {
        container.classList.add('hidden');
        document.getElementById('no-doc-spec-name').textContent = spec;
        noMsg.classList.remove('hidden');
    } else {
        container.classList.remove('hidden');
        noMsg.classList.add('hidden');
    }
    
    filtered.forEach(doc => {
            const div = document.createElement('div');
            div.className = 'doctor-card border-2 border-slate-200 rounded-xl p-4 cursor-pointer hover:border-blue-400 transition-all select-none flex items-start gap-3';
            div.onclick = () => selectDoctor(div, doc.id, doc.name);
            
            // Get initials
            const init = doc.name.split(' ').slice(0,2).map(n => n[0]).join('').toUpperCase();
            
            div.innerHTML = `
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-black flex-shrink-0">
                    ${init}
                </div>
                <div>
                    <h4 class="font-bold text-slate-900">${doc.name}</h4>
                    <p class="text-xs text-slate-500">Spesialis ${doc.specialization}</p>
                </div>
            `;
            container.appendChild(div);
        });
}

function selectDoctor(el, docId, docName) {
    document.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    
    document.getElementById('doctor_id_input').value = docId;
    document.getElementById('selected-doc-name').textContent = docName;
    document.getElementById('confirm-doc').textContent = docName + ' (Spesialis ' + currentSpec + ')';
    
    loadSchedule(docId);
    goToStep(3);
}

// STEP 3: Load Schedule Real-Time
async function loadSchedule(docId) {
    const container = document.getElementById('schedule-container');
    const loading = document.getElementById('schedule-loading');
    const errorBox = document.getElementById('schedule-error');
    
    container.innerHTML = '';
    container.classList.add('hidden');
    errorBox.classList.add('hidden');
    loading.classList.remove('hidden');
    
    try {
        const response = await fetch(`../api/doctor_schedules.php?doctor_id=${docId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Gagal memuat jadwal');
        }
        
        if (!data.days || data.days.length === 0) {
            throw new Error('Dokter ini belum memiliki jadwal aktif.');
        }
        
        renderSchedule(data.days);
        
    } catch (err) {
        errorBox.textContent = err.message || "Gagal menghubungi server jadwal.";
        errorBox.classList.remove('hidden');
    } finally {
        loading.classList.add('hidden');
    }
}

function renderSchedule(days) {
    const container = document.getElementById('schedule-container');
    container.classList.remove('hidden');
    
    days.forEach(day => {
        if (!day.slots || day.slots.length === 0) return;
        
        const dayDiv = document.createElement('div');
        dayDiv.className = 'border border-slate-200 rounded-xl overflow-hidden';
        
        const header = document.createElement('div');
        header.className = 'bg-slate-50 px-4 py-3 border-b border-slate-200 flex justify-between items-center';
        header.innerHTML = `
            <div>
                <span class="font-bold text-slate-800">${day.day_name}, ${day.day_num} ${day.month}</span>
                ${day.is_today ? '<span class="ml-2 text-[10px] bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-bold">HARI INI</span>' : ''}
            </div>
            <span class="text-xs font-bold text-slate-500">${day.slots.filter(s => s.available).length} slot tersedia</span>
        `;
        
        const slotsGrid = document.createElement('div');
        slotsGrid.className = 'p-4 grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2';
        
        day.slots.forEach(slot => {
            if (!slot.available) return; // Do not show booked slots
            
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = slot.time;
            
            btn.className = 'slot-btn available text-sm font-bold py-2 rounded-lg border-2 text-center transition-colors shadow-sm hover:shadow';
            btn.onclick = () => selectSlot(btn, day.date, slot.time, day.day_name + ', ' + day.day_num + ' ' + day.month);
            
            slotsGrid.appendChild(btn);
        });
        
        dayDiv.appendChild(header);
        dayDiv.appendChild(slotsGrid);
        container.appendChild(dayDiv);
    });
}

function selectSlot(btn, date, time, displayDate) {
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    
    document.getElementById('scheduled_date_input').value = date;
    document.getElementById('scheduled_time_input').value = time;
    
    document.getElementById('confirm-time').textContent = `${displayDate} | Jam ${time} WIB`;
    
    setTimeout(() => goToStep(4), 300);
}

// STEP 4: Submit validation
document.getElementById('reservasi-form').addEventListener('submit', function(e) {
    if (!hasProfile) {
        if (!document.getElementById('gender-input').value || 
            !document.getElementById('age-input').value || 
            !document.getElementById('blood-input').value) {
            e.preventDefault();
            alert('Harap lengkapi Jenis Kelamin, Usia, dan Golongan Darah.');
        }
    }
});
</script>
</body>
</html>
