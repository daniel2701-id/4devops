<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('doctor');

$user = current_user();
$pdo  = db();

// ── Buat tabel jika belum ada (menghindari error 500 jika schema_additions belum dijalankan) ──
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS doctor_schedules (
            id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            doctor_id       BIGINT UNSIGNED NOT NULL,
            day_of_week     TINYINT NOT NULL,
            start_time      TIME NOT NULL,
            end_time        TIME NOT NULL,
            slot_duration   SMALLINT NOT NULL DEFAULT 30,
            is_active       TINYINT(1) NOT NULL DEFAULT 1,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_doc_day (doctor_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS doctor_schedule_dates (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            doctor_id     BIGINT UNSIGNED NOT NULL,
            schedule_date DATE NOT NULL,
            start_time    TIME NOT NULL DEFAULT '08:00:00',
            end_time      TIME NOT NULL DEFAULT '17:00:00',
            slot_duration SMALLINT NOT NULL DEFAULT 30,
            is_closed     TINYINT(1) NOT NULL DEFAULT 0,
            note          VARCHAR(255),
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_doc_date (doctor_id, schedule_date),
            INDEX idx_doc_sdate (doctor_id, schedule_date),
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Tabel mungkin sudah ada atau FK sudah ada — abaikan
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $action = $_POST['action'] ?? '';

    // Simpan jadwal mingguan
    if ($action === 'save_weekly') {
        try {
            $pdo->prepare("DELETE FROM doctor_schedules WHERE doctor_id = ?")->execute([$user['id']]);
            $days = $_POST['days'] ?? [];
            foreach ($days as $dow => $d) {
                if (empty($d['enabled'])) continue;
                $dow  = (int)$dow;
                $st   = $d['start'] ?? '08:00';
                $en   = $d['end']   ?? '17:00';
                $dur  = max(10, min(240, (int)($d['duration'] ?? 30)));
                $pdo->prepare(
                    "INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration)
                     VALUES (?,?,?,?,?)"
                )->execute([$user['id'], $dow, $st, $en, $dur]);
            }
            flash('success', 'Jadwal mingguan berhasil disimpan!');
        } catch (Exception $e) {
            error_log('save_weekly: ' . $e->getMessage());
            flash('error', 'Gagal menyimpan jadwal mingguan.');
        }
        header('Location: atur_jadwal.php'); exit;
    }

    // Tambah / edit tanggal khusus
    if ($action === 'add_date') {
        $date     = trim($_POST['spec_date'] ?? '');
        $isClosed = isset($_POST['is_closed']) ? 1 : 0;
        $st       = $_POST['spec_start']    ?? '08:00';
        $en       = $_POST['spec_end']      ?? '17:00';
        $dur      = max(10, min(240, (int)($_POST['spec_duration'] ?? 30)));
        $note     = substr(trim($_POST['note'] ?? ''), 0, 255);

        if (empty($date)) {
            flash('error', 'Tanggal tidak boleh kosong.');
            header('Location: atur_jadwal.php'); exit;
        }
        try {
            $pdo->prepare(
                "INSERT INTO doctor_schedule_dates
                    (doctor_id, schedule_date, start_time, end_time, slot_duration, is_closed, note)
                 VALUES (?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    start_time=VALUES(start_time), end_time=VALUES(end_time),
                    slot_duration=VALUES(slot_duration), is_closed=VALUES(is_closed), note=VALUES(note)"
            )->execute([$user['id'], $date, $st, $en, $dur, $isClosed, $note]);
            flash('success', 'Jadwal tanggal ' . date('d M Y', strtotime($date)) . ' berhasil disimpan!');
        } catch (Exception $e) {
            error_log('add_date: ' . $e->getMessage());
            flash('error', 'Gagal menyimpan jadwal tanggal khusus.');
        }
        header('Location: atur_jadwal.php'); exit;
    }

    // Hapus tanggal khusus
    if ($action === 'delete_date') {
        $id = (int)($_POST['del_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM doctor_schedule_dates WHERE id=? AND doctor_id=?")
                ->execute([$id, $user['id']]);
        }
        flash('success', 'Jadwal khusus berhasil dihapus.');
        header('Location: atur_jadwal.php'); exit;
    }
}

// ── Load Data ─────────────────────────────────────────────────────────────────
$weeklyStmt = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id=? ORDER BY day_of_week ASC");
$weeklyStmt->execute([$user['id']]);
$weeklyMap = [];
foreach ($weeklyStmt->fetchAll() as $w) { $weeklyMap[(int)$w['day_of_week']] = $w; }

$specStmt = $pdo->prepare(
    "SELECT * FROM doctor_schedule_dates WHERE doctor_id=? AND schedule_date >= CURDATE()
     ORDER BY schedule_date ASC LIMIT 60"
);
$specStmt->execute([$user['id']]);
$specificDates = $specStmt->fetchAll();

$msgSuccess = flash('success');
$msgError   = flash('error');

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
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex">

  <!-- Sidebar -->
  <aside class="w-64 bg-purple-700 text-white flex-shrink-0 flex flex-col hidden md:flex shadow-xl">
    <div class="p-6 border-b border-purple-600/50">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-white/20 flex items-center justify-center rounded-lg transform rotate-45">
          <span class="material-symbols-outlined text-white transform -rotate-45 text-[16px]" style="font-variation-settings:'FILL' 1;">medical_services</span>
        </div>
        <span class="font-extrabold tracking-tight text-white text-lg">CareConnect</span>
      </div>
      <div class="mt-1 text-xs text-purple-200 font-medium ml-10">Medical Portal</div>
    </div>

    <!-- Doctor info -->
    <div class="p-4 border-b border-purple-600/50">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-white/20 text-white rounded-full flex items-center justify-center font-bold text-sm">
          <?= e(initials($user['name'])) ?>
        </div>
        <div>
          <p class="text-sm font-bold text-white"><?= e($user['name']) ?></p>
          <p class="text-xs text-purple-200">Dokter</p>
        </div>
      </div>
    </div>

    <nav class="flex-1 p-4 space-y-1">
      <?php
      $navItems = [
        ['icon'=>'home',          'label'=>'Beranda',       'href'=>'dashboard.php',   'active'=>false],
        ['icon'=>'calendar_month','label'=>'Jadwal Pasien', 'href'=>'jadwal.php',      'active'=>false],
        ['icon'=>'history',       'label'=>'Riwayat',       'href'=>'riwayat.php',     'active'=>false],
        ['icon'=>'edit_calendar', 'label'=>'Atur Jadwal',   'href'=>'atur_jadwal.php', 'active'=>true],
        ['icon'=>'chat',          'label'=>'Chat',          'href'=>'chat.php',        'active'=>false],
      ];
      foreach ($navItems as $item):
        $cls = $item['active']
          ? 'bg-white/20 text-white font-bold shadow-sm'
          : 'text-purple-100 hover:bg-white/10 hover:text-white font-medium';
      ?>
      <a href="<?= e($item['href']) ?>" class="flex items-center gap-3 px-4 py-2.5 rounded-xl transition-colors text-sm <?= $cls ?>">
        <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
        <?= e($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-purple-600/50">
      <a href="<?= APP_URL ?>/doctor/logout.php"
         class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-purple-200 hover:bg-red-500/20 hover:text-red-200 transition-colors">
        <span class="material-symbols-outlined text-[20px]">logout</span> Keluar
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 overflow-auto p-6 lg:p-8">
    <div class="max-w-4xl mx-auto">

      <div class="mb-8">
        <h1 class="text-2xl font-black text-slate-900">Atur Jadwal Praktik</h1>
        <p class="text-slate-500 font-medium mt-1">Kelola jadwal mingguan rutin dan tanggal khusus Anda.</p>
      </div>

      <?= alert_html($msgError, 'error') ?>
      <?= alert_html($msgSuccess, 'success') ?>

      <!-- JADWAL MINGGUAN -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-blue-600">date_range</span>
          </div>
          <div>
            <h2 class="text-lg font-black text-slate-900">Jadwal Mingguan Rutin</h2>
            <p class="text-xs text-slate-500">Centang hari kerja dan tentukan jam praktik. Pasien hanya bisa booking di slot ini.</p>
          </div>
        </div>

        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_weekly">
          <div class="space-y-3">
            <?php foreach ($dayNames as $dow => $dayName):
              $active = isset($weeklyMap[$dow]);
              $w = $weeklyMap[$dow] ?? ['start_time'=>'08:00:00','end_time'=>'17:00:00','slot_duration'=>30];
            ?>
            <div class="flex flex-wrap items-center gap-4 p-4 rounded-xl border-2 transition-all
                <?= $active ? 'border-blue-200 bg-blue-50/40' : 'border-slate-100 bg-slate-50' ?>"
                 id="row-<?= $dow ?>">

              <label class="flex items-center gap-3 cursor-pointer w-32 flex-shrink-0">
                <input type="checkbox" name="days[<?= $dow ?>][enabled]" value="1"
                       class="w-5 h-5 rounded text-blue-600 border-slate-300"
                       <?= $active ? 'checked' : '' ?>
                       onchange="toggleRow(<?= $dow ?>, this.checked)">
                <span class="font-bold text-slate-800 text-sm"><?= $dayName ?></span>
              </label>

              <div class="flex items-center gap-3 flex-wrap" id="inputs-<?= $dow ?>"
                   style="<?= !$active ? 'opacity:.35;pointer-events:none' : '' ?>">
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-semibold text-slate-500">Mulai</span>
                  <input type="time" name="days[<?= $dow ?>][start]" value="<?= substr($w['start_time'],0,5) ?>"
                         class="text-sm font-bold border border-slate-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-semibold text-slate-500">Selesai</span>
                  <input type="time" name="days[<?= $dow ?>][end]" value="<?= substr($w['end_time'],0,5) ?>"
                         class="text-sm font-bold border border-slate-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-blue-500">
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="text-xs font-semibold text-slate-500">Durasi</span>
                  <select name="days[<?= $dow ?>][duration]"
                          class="text-sm font-bold border border-slate-200 rounded-lg px-2 py-1.5 bg-white focus:outline-none focus:border-blue-500">
                    <?php foreach ([15,20,30,45,60] as $dur): ?>
                    <option value="<?= $dur ?>" <?= (int)$w['slot_duration']===$dur ? 'selected' : '' ?>><?= $dur ?> mnt</option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-5">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 shadow-sm transition-all text-sm">
              <span class="material-symbols-outlined text-[20px]">save</span>
              Simpan Jadwal Mingguan
            </button>
          </div>
        </form>
      </div>

      <!-- JADWAL TANGGAL KHUSUS -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <span class="material-symbols-outlined text-purple-600">event</span>
          </div>
          <div>
            <h2 class="text-lg font-black text-slate-900">Jadwal Tanggal Khusus</h2>
            <p class="text-xs text-slate-500">Tambah jadwal berbeda atau tutup praktik di tanggal tertentu. Ini menggantikan jadwal mingguan.</p>
          </div>
        </div>

        <!-- Form tambah -->
        <form method="POST" class="p-5 bg-slate-50 rounded-xl border border-slate-200 mb-6">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_date">
          <p class="font-bold text-slate-800 mb-4 text-sm">Tambah / Edit Tanggal Khusus</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="text-xs font-bold text-slate-600 block mb-1">Tanggal *</label>
              <input type="date" name="spec_date" min="<?= date('Y-m-d') ?>" required
                     class="w-full p-2.5 border border-slate-200 rounded-xl text-sm font-bold bg-white focus:outline-none focus:border-blue-500">
            </div>
            <div>
              <label class="text-xs font-bold text-slate-600 block mb-1">Catatan</label>
              <input type="text" name="note" maxlength="255" placeholder="Cth: Libur Nasional, Dinas Luar..."
                     class="w-full p-2.5 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:border-blue-500">
            </div>
          </div>

          <div class="mb-4">
            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" name="is_closed" id="is_closed_cb" class="w-5 h-5 rounded text-red-600"
                     onchange="document.getElementById('time_fields').style.display = this.checked ? 'none' : 'grid'">
              <span class="font-bold text-red-700 text-sm">Tutup / Tidak Praktik pada tanggal ini</span>
            </label>
          </div>

          <div id="time_fields" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
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
              <select name="spec_duration"
                      class="w-full p-2.5 border border-slate-200 rounded-xl text-sm font-bold bg-white focus:outline-none focus:border-blue-500">
                <?php foreach ([15,20,30,45,60] as $dur): ?>
                <option value="<?= $dur ?>" <?= $dur===30 ? 'selected' : '' ?>><?= $dur ?> menit</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <button type="submit"
                  class="inline-flex items-center gap-2 px-5 py-2.5 bg-purple-600 text-white rounded-xl font-bold hover:bg-purple-700 text-sm transition-all">
            <span class="material-symbols-outlined text-[18px]">add_circle</span>
            Tambah Jadwal Khusus
          </button>
        </form>

        <!-- Daftar tanggal khusus -->
        <?php if (empty($specificDates)): ?>
        <div class="text-center py-10 text-slate-400">
          <span class="material-symbols-outlined text-5xl text-slate-300">event_note</span>
          <p class="font-medium mt-2 text-sm">Belum ada jadwal tanggal khusus yang ditetapkan.</p>
        </div>
        <?php else: ?>
        <div class="space-y-2">
          <?php foreach ($specificDates as $sd): ?>
          <?php $closed = (bool)$sd['is_closed']; ?>
          <div class="flex items-center justify-between gap-3 p-4 rounded-xl border
               <?= $closed ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50' ?>">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-2xl <?= $closed ? 'text-red-500' : 'text-emerald-600' ?>">
                <?= $closed ? 'event_busy' : 'event_available' ?>
              </span>
              <div>
                <p class="font-black text-slate-900 text-sm">
                  <?= date('D, d M Y', strtotime($sd['schedule_date'])) ?>
                </p>
                <?php if ($closed): ?>
                  <p class="text-xs text-red-700 font-bold">🔴 Tutup / Tidak Praktik</p>
                <?php else: ?>
                  <p class="text-xs text-emerald-700 font-bold">
                    🟢 <?= substr($sd['start_time'],0,5) ?> – <?= substr($sd['end_time'],0,5) ?> WIB &nbsp;·&nbsp; <?= (int)$sd['slot_duration'] ?> mnt/slot
                  </p>
                <?php endif; ?>
                <?php if (!empty($sd['note'])): ?>
                  <p class="text-xs text-slate-500 mt-0.5 italic"><?= e($sd['note']) ?></p>
                <?php endif; ?>
              </div>
            </div>
            <form method="POST" class="flex-shrink-0">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_date">
              <input type="hidden" name="del_id" value="<?= (int)$sd['id'] ?>">
              <button type="submit"
                      class="p-2 rounded-lg text-red-500 hover:bg-red-100 transition-colors"
                      onclick="return confirm('Hapus jadwal tanggal ini?')" title="Hapus">
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

<script>
function toggleRow(dow, enabled) {
    var inputs = document.getElementById('inputs-' + dow);
    var row    = document.getElementById('row-' + dow);
    if (enabled) {
        inputs.style.opacity = '1';
        inputs.style.pointerEvents = 'auto';
        row.classList.replace('border-slate-100', 'border-blue-200');
        row.classList.remove('bg-slate-50');
        row.classList.add('bg-blue-50/40');
    } else {
        inputs.style.opacity = '0.35';
        inputs.style.pointerEvents = 'none';
        row.classList.replace('border-blue-200', 'border-slate-100');
        row.classList.remove('bg-blue-50/40');
        row.classList.add('bg-slate-50');
    }
}
</script>
</body>
</html>
