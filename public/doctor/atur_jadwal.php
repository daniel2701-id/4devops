<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('doctor');

$user = current_user();
$pdo  = db();
$error = ''; $success = '';

// Ensure doctor_schedule_dates table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS doctor_schedule_dates (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        doctor_id       BIGINT UNSIGNED NOT NULL,
        schedule_date   DATE NOT NULL,
        start_time      TIME NOT NULL,
        end_time        TIME NOT NULL,
        slot_duration   SMALLINT NOT NULL DEFAULT 30,
        is_closed       TINYINT(1) NOT NULL DEFAULT 0,
        note            VARCHAR(255),
        created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_doc_date (doctor_id, schedule_date),
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $action = $_POST['action'] ?? '';

    // --- Save Weekly Schedule ---
    if ($action === 'save_weekly') {
        try {
            $pdo->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ?")->execute([$user['id']]);
            $days = $_POST['days'] ?? [];
            foreach ($days as $dow => $d) {
                if (!isset($d['enabled'])) continue;
                $dow = (int)$dow;
                $start = $d['start'] ?? '08:00';
                $end   = $d['end']   ?? '17:00';
                $dur   = (int)($d['duration'] ?? 30);
                if ($dur < 10 || $dur > 240) $dur = 30;
                $pdo->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration) VALUES (?,?,?,?,?)")
                    ->execute([$user['id'], $dow, $start, $end, $dur]);
            }
            flash('success', 'Jadwal mingguan berhasil disimpan!');
        } catch (Exception $e) {
            flash('error', 'Gagal menyimpan jadwal: ' . $e->getMessage());
        }
        header('Location: atur_jadwal.php'); exit;
    }

    // --- Add Specific Date ---
    if ($action === 'add_date') {
        $date    = $_POST['spec_date'] ?? '';
        $isClosed = isset($_POST['is_closed']) ? 1 : 0;
        $start   = $_POST['spec_start'] ?? '08:00';
        $end     = $_POST['spec_end']   ?? '17:00';
        $dur     = (int)($_POST['spec_duration'] ?? 30);
        $note    = trim($_POST['note'] ?? '');
        if (empty($date)) {
            flash('error', 'Tanggal tidak boleh kosong.'); header('Location: atur_jadwal.php'); exit;
        }
        try {
            $pdo->prepare("INSERT INTO doctor_schedule_dates (doctor_id, schedule_date, start_time, end_time, slot_duration, is_closed, note)
                           VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time),
                           slot_duration=VALUES(slot_duration), is_closed=VALUES(is_closed), note=VALUES(note)")
                ->execute([$user['id'], $date, $start, $end, $dur, $isClosed, $note]);
            flash('success', 'Jadwal tanggal ' . date('d M Y', strtotime($date)) . ' berhasil disimpan!');
        } catch (Exception $e) {
            flash('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
        header('Location: atur_jadwal.php'); exit;
    }

    // --- Delete Specific Date ---
    if ($action === 'delete_date') {
        $id = (int)($_POST['del_id'] ?? 0);
        $pdo->prepare("DELETE FROM doctor_schedule_dates WHERE id = ? AND doctor_id = ?")->execute([$id, $user['id']]);
        flash('success', 'Jadwal khusus berhasil dihapus.'); header('Location: atur_jadwal.php'); exit;
    }
}

// ── Load Data ────────────────────────────────────────────────────────────────
$weeklySchedules = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY day_of_week ASC");
$weeklySchedules->execute([$user['id']]);
$weekly = $weeklySchedules->fetchAll();
$weeklyMap = [];
foreach ($weekly as $w) { $weeklyMap[(int)$w['day_of_week']] = $w; }

$specStmt = $pdo->prepare("SELECT * FROM doctor_schedule_dates WHERE doctor_id = ? AND schedule_date >= CURDATE() ORDER BY schedule_date ASC LIMIT 60");
$specStmt->execute([$user['id']]);
$specificDates = $specStmt->fetchAll();

$success = flash('success');
$error   = flash('error');

$dayNames = [0=>'Minggu',1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Atur Jadwal Praktik</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>body{font-family:'Inter',sans-serif;}</style>
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
        <div class="w-10 h-10 bg-primary-fixed text-primary rounded-full flex items-center justify-center font-bold text-sm"><?= e(initials($user['name'])) ?></div>
        <div>
          <p class="text-sm font-bold text-slate-800"><?= e($user['name']) ?></p>
          <p class="text-xs text-slate-400">Dokter</p>
        </div>
      </div>
    </div>
    <nav class="flex-1 p-4 space-y-1">
      <?php
      $navItems = [
        ['icon'=>'home',           'label'=>'Beranda',         'href'=>'dashboard.php',   'active'=>false],
        ['icon'=>'calendar_month', 'label'=>'Jadwal Pasien',   'href'=>'jadwal.php',      'active'=>false],
        ['icon'=>'edit_calendar',  'label'=>'Atur Jadwal',     'href'=>'atur_jadwal.php', 'active'=>true],
        ['icon'=>'chat',           'label'=>'Chat',            'href'=>'chat.php',        'active'=>false],
      ];
      foreach ($navItems as $item):
        $cls = $item['active'] ? 'bg-primary-fixed text-primary font-bold' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800 font-medium';
      ?>
      <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
        <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
        <?= e($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-slate-100">
      <a href="<?= APP_URL ?>/doctor/logout.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors">
        <span class="material-symbols-outlined text-[20px]">logout</span> Keluar
      </a>
    </div>
  </aside>

  <!-- Main -->
  <main class="flex-1 overflow-auto p-6 lg:p-8">
    <div class="max-w-4xl mx-auto">
      <div class="mb-8">
        <h1 class="text-2xl font-black text-slate-900">Atur Jadwal Praktik</h1>
        <p class="text-slate-500 font-medium mt-1">Kelola jadwal mingguan rutin dan tanggal-tanggal khusus Anda.</p>
      </div>

      <?= alert_html($error, 'error') ?>
      <?= alert_html($success, 'success') ?>

      <!-- SECTION 1: Jadwal Mingguan -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-blue-600">date_range</span>
          </div>
          <div>
            <h2 class="text-lg font-black text-slate-900">Jadwal Mingguan Rutin</h2>
            <p class="text-xs text-slate-500">Centang hari kerja dan tentukan jam praktik untuk setiap hari.</p>
          </div>
        </div>

        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_weekly">
          <div class="space-y-3">
            <?php foreach ($dayNames as $dow => $dayName):
              $active = isset($weeklyMap[$dow]);
              $s = $weeklyMap[$dow] ?? ['start_time'=>'08:00:00','end_time'=>'17:00:00','slot_duration'=>30];
            ?>
            <div class="flex flex-wrap items-center gap-4 p-4 rounded-xl border-2 <?= $active ? 'border-blue-200 bg-blue-50/50' : 'border-slate-100 bg-slate-50' ?> transition-all" id="row-<?= $dow ?>">
              <label class="flex items-center gap-3 cursor-pointer min-w-[120px]">
                <input type="checkbox" name="days[<?= $dow ?>][enabled]" value="1"
                       class="w-5 h-5 rounded text-blue-600 border-slate-300 cursor-pointer"
                       <?= $active ? 'checked' : '' ?>
                       onchange="toggleRow(<?= $dow ?>, this.checked)">
                <span class="font-bold text-slate-800 text-sm"><?= $dayName ?></span>
              </label>
              <div class="flex items-center gap-2 flex-wrap" id="inputs-<?= $dow ?>" <?= !$active ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-bold text-slate-500">Mulai</span>
                  <input type="time" name="days[<?= $dow ?>][start]" value="<?= substr($s['start_time'],0,5) ?>"
                         class="text-sm font-bold border border-slate-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-bold text-slate-500">Selesai</span>
                  <input type="time" name="days[<?= $dow ?>][end]" value="<?= substr($s['end_time'],0,5) ?>"
                         class="text-sm font-bold border border-slate-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-bold text-slate-500">Durasi/Slot</span>
                  <select name="days[<?= $dow ?>][duration]" class="text-sm font-bold border border-slate-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-blue-500">
                    <?php foreach ([15,20,30,45,60] as $dur): ?>
                    <option value="<?= $dur ?>" <?= (int)$s['slot_duration']===$dur?'selected':'' ?>><?= $dur ?> menit</option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-5">
            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 shadow-sm transition-all flex items-center gap-2">
              <span class="material-symbols-outlined text-[20px]">save</span> Simpan Jadwal Mingguan
            </button>
          </div>
        </form>
      </div>

      <!-- SECTION 2: Jadwal Tanggal Khusus -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-purple-600">event</span>
          </div>
          <div>
            <h2 class="text-lg font-black text-slate-900">Jadwal Tanggal Khusus</h2>
            <p class="text-xs text-slate-500">Tambah jam khusus atau tutup praktik di tanggal tertentu (hari libur, dll).</p>
          </div>
        </div>

        <form method="POST" class="p-4 bg-slate-50 rounded-xl border border-slate-200 mb-6">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_date">
          <h3 class="font-bold text-slate-800 mb-4 text-sm">Tambah / Edit Jadwal Tanggal</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="text-xs font-bold text-slate-600 block mb-1">Tanggal *</label>
              <input type="date" name="spec_date" min="<?= date('Y-m-d') ?>" required
                     class="w-full p-2.5 border border-slate-200 rounded-xl text-sm font-bold bg-white focus:outline-none focus:border-blue-500">
            </div>
            <div>
              <label class="text-xs font-bold text-slate-600 block mb-1">Catatan (opsional)</label>
              <input type="text" name="note" placeholder="Misal: Libur Nasional, Tugas Luar..."
                     class="w-full p-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:border-blue-500">
            </div>
          </div>
          <div class="mb-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="is_closed" id="is_closed_cb" class="w-5 h-5 rounded text-red-600"
                     onchange="document.getElementById('time_fields').style.display=this.checked?'none':'grid'">
              <span class="font-bold text-red-700 text-sm">Tutup / Tidak Praktik pada tanggal ini</span>
            </label>
          </div>
          <div id="time_fields" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
              <label class="text-xs font-bold text-slate-600 block mb-1">Jam Mulai</label>
              <input type="time" name="spec_start" value="08:00"
                     class="w-full p-2.5 border border-slate-200 rounded-xl text-sm font-bold bg-white focus:outline-none focus:border-blue-500">
            </div>
            <div>
              <label class="text-xs font-bold text-slate-600 block mb-1">Jam Selesai</label>
              <input type="time" name="spec_end" value="17:00"
                     class="w-full p-2.5 border border-slate-200 rounded-xl text-sm font-bold bg-white focus:outline-none focus:border-blue-500">
            </div>
            <div>
              <label class="text-xs font-bold text-slate-600 block mb-1">Durasi/Slot</label>
              <select name="spec_duration" class="w-full p-2.5 border border-slate-200 rounded-xl text-sm font-bold bg-white focus:outline-none focus:border-blue-500">
                <?php foreach ([15,20,30,45,60] as $dur): ?>
                <option value="<?= $dur ?>" <?= $dur===30?'selected':'' ?>><?= $dur ?> menit</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button type="submit" class="px-5 py-2.5 bg-purple-600 text-white rounded-xl font-bold hover:bg-purple-700 text-sm transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">add_circle</span> Tambah Jadwal Khusus
          </button>
        </form>

        <!-- List of upcoming specific dates -->
        <?php if (empty($specificDates)): ?>
        <div class="text-center py-8 text-slate-400">
          <span class="material-symbols-outlined text-4xl">event_busy</span>
          <p class="font-medium mt-2 text-sm">Belum ada jadwal tanggal khusus.</p>
        </div>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($specificDates as $sd): ?>
          <div class="flex items-center justify-between p-4 rounded-xl border <?= $sd['is_closed'] ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' ?>">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined <?= $sd['is_closed'] ? 'text-red-500' : 'text-green-600' ?> text-2xl">
                <?= $sd['is_closed'] ? 'event_busy' : 'event_available' ?>
              </span>
              <div>
                <p class="font-black text-slate-900 text-sm"><?= date('D, d M Y', strtotime($sd['schedule_date'])) ?></p>
                <?php if ($sd['is_closed']): ?>
                  <p class="text-xs text-red-700 font-bold">🔴 Tutup / Tidak Praktik</p>
                <?php else: ?>
                  <p class="text-xs text-green-700 font-bold">
                    <?= substr($sd['start_time'],0,5) ?> – <?= substr($sd['end_time'],0,5) ?> WIB
                    (<?= $sd['slot_duration'] ?> mnt/slot)
                  </p>
                <?php endif; ?>
                <?php if (!empty($sd['note'])): ?>
                  <p class="text-xs text-slate-500 mt-0.5"><?= e($sd['note']) ?></p>
                <?php endif; ?>
              </div>
            </div>
            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_date">
              <input type="hidden" name="del_id" value="<?= $sd['id'] ?>">
              <button type="submit" class="p-2 rounded-lg text-red-500 hover:bg-red-100 transition-colors"
                      onclick="return confirm('Hapus jadwal ini?')" title="Hapus">
                <span class="material-symbols-outlined text-[18px]">delete</span>
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</body>
<script>
function toggleRow(dow, enabled) {
    const inputs = document.getElementById('inputs-' + dow);
    const row    = document.getElementById('row-' + dow);
    if (enabled) {
        inputs.style.opacity = '1';
        inputs.style.pointerEvents = 'auto';
        row.classList.add('border-blue-200', 'bg-blue-50/50');
        row.classList.remove('border-slate-100', 'bg-slate-50');
    } else {
        inputs.style.opacity = '0.4';
        inputs.style.pointerEvents = 'none';
        row.classList.remove('border-blue-200', 'bg-blue-50/50');
        row.classList.add('border-slate-100', 'bg-slate-50');
    }
}
</script>
</html>
