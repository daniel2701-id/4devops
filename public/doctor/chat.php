<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('doctor');

$user = current_user();
$pdo  = db();

$appointments = [];
$selectedAppt = 0;
$dbError      = '';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        appointment_id BIGINT UNSIGNED NOT NULL,
        sender_id BIGINT UNSIGNED NOT NULL,
        body TEXT NOT NULL,
        read_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_appt_msg (appointment_id),
        INDEX idx_sender_msg (sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// ---- AJAX: Send message ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_send'])) {
    header('Content-Type: application/json');
    $apptId = (int) ($_POST['appointment_id'] ?? 0);
    $body   = trim($_POST['body'] ?? '');

    if (!$apptId || empty($body)) {
        echo json_encode(['ok' => false, 'error' => 'Pesan tidak boleh kosong.']);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ? LIMIT 1");
        $check->execute([$apptId, $user['id']]);
        if (!$check->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Tidak diizinkan.']);
            exit;
        }

        $body = sanitize_string($body, 2000);
        $pdo->prepare("INSERT INTO messages (appointment_id, sender_id, body) VALUES (?, ?, ?)")
            ->execute([$apptId, $user['id'], $body]);

        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Gagal mengirim pesan.']);
    }
    exit;
}

// ---- AJAX: Fetch messages ----
if (isset($_GET['fetch']) && isset($_GET['appointment_id'])) {
    header('Content-Type: application/json');
    $apptId = (int) $_GET['appointment_id'];

    try {
        $check = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ? LIMIT 1");
        $check->execute([$apptId, $user['id']]);
        if (!$check->fetch()) {
            echo json_encode(['messages' => [], 'error' => 'Tidak diizinkan.']);
            exit;
        }

        // Mark patient's messages as read
        $pdo->prepare(
            "UPDATE messages SET read_at = NOW() WHERE appointment_id = ? AND sender_id != ? AND read_at IS NULL"
        )->execute([$apptId, $user['id']]);

        $msgs = $pdo->prepare(
            "SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE m.appointment_id = ? ORDER BY m.created_at ASC"
        );
        $msgs->execute([$apptId]);

        echo json_encode(['messages' => $msgs->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        echo json_encode(['messages' => [], 'error' => 'Gagal memuat pesan.']);
    }
    exit;
}

// Fetch doctor's appointments with chat
try {
    $appts = $pdo->prepare(
        "SELECT a.id, a.scheduled_at, a.status, a.reason,
                p.name AS patient_name,
                (SELECT COUNT(*) FROM messages m2 WHERE m2.appointment_id = a.id AND m2.sender_id != ? AND m2.read_at IS NULL) AS unread
         FROM appointments a
         JOIN users p ON p.id = a.patient_id
         WHERE a.doctor_id = ? AND a.status != 'cancelled'
         ORDER BY a.scheduled_at DESC
         LIMIT 30"
    );
    $appts->execute([$user['id'], $user['id']]);
    $appointments = $appts->fetchAll();
} catch (Exception $e) {
    // Fallback: fetch without unread count (if messages table missing)
    try {
        $appts = $pdo->prepare(
            "SELECT a.id, a.scheduled_at, a.status, a.reason,
                    p.name AS patient_name, 0 AS unread
             FROM appointments a
             JOIN users p ON p.id = a.patient_id
             WHERE a.doctor_id = ? AND a.status != 'cancelled'
             ORDER BY a.scheduled_at DESC
             LIMIT 30"
        );
        $appts->execute([$user['id']]);
        $appointments = $appts->fetchAll();
    } catch (Exception $e2) {
        $dbError = 'Tidak dapat memuat data konsultasi.';
    }
}

$selectedAppt = (int) ($_GET['appt'] ?? ($appointments[0]['id'] ?? 0));
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Chat Pasien</title>
<?= tailwind_cdn() ?>
<?= tailwind_config() ?>
<?= google_fonts() ?>
<style>
body { font-family: 'Inter', sans-serif; }
.msg-bubble { max-width: 80%; word-wrap: break-word; animation: fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:none} }
#chat-messages { scroll-behavior: smooth; }
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

<div class="flex min-h-screen">

  <!-- Sidebar: Conversations -->
  <aside class="w-80 bg-white border-r border-slate-200 flex-col hidden md:flex flex-shrink-0">
    <div class="p-5 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <a href="dashboard.php" class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center text-slate-500 hover:text-primary hover:bg-primary-fixed transition-colors">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        </a>
        <div>
          <h1 class="text-lg font-black text-slate-900">Chat Pasien</h1>
          <p class="text-xs text-slate-400">Komunikasi terkait konsultasi</p>
        </div>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto divide-y divide-slate-50">
      <?php if (empty($appointments)): ?>
      <div class="p-6 text-center text-slate-400 text-sm">
        <span class="material-symbols-outlined text-[36px] text-slate-300">forum</span>
        <p class="mt-2">Belum ada konsultasi.</p>
      </div>
      <?php else: ?>
      <?php foreach ($appointments as $a): 
        $isActive = $a['id'] == $selectedAppt;
        $statusMap = [
          'waiting'    => ['Menunggu',   'bg-amber-50 text-amber-600'],
          'in_session' => ['Berlangsung','bg-blue-50 text-blue-600'],
          'finished'   => ['Selesai',    'bg-green-50 text-green-600'],
        ];
        [$stLabel, $stCls] = $statusMap[$a['status']] ?? ['', 'bg-slate-50 text-slate-500'];
      ?>
      <a href="?appt=<?= $a['id'] ?>"
         class="flex items-start gap-3 px-4 py-3.5 transition-colors <?= $isActive ? 'bg-primary-fixed border-l-4 border-primary' : 'hover:bg-slate-50' ?>">
        <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center font-bold text-xs flex-shrink-0">
          <?= e(initials($a['patient_name'])) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center justify-between gap-2">
            <p class="font-bold text-sm text-slate-900 truncate"><?= e($a['patient_name']) ?></p>
            <?php if ($a['unread'] > 0): ?>
            <span class="bg-primary text-white text-xs font-black rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"><?= $a['unread'] ?></span>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-2 mt-1">
            <span class="text-xs <?= $stCls ?> px-1.5 py-0.5 rounded font-bold"><?= $stLabel ?></span>
            <span class="text-xs text-slate-400"><?= format_date($a['scheduled_at'], 'd M Y') ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <!-- Chat Area -->
  <main class="flex-1 flex flex-col bg-slate-50">
    <?php
      $selAppt = null;
      if ($selectedAppt) {
          foreach ($appointments as $a) { if ($a['id'] == $selectedAppt) { $selAppt = $a; break; } }
      }
    ?>
    <?php if ($selectedAppt && $selAppt): ?>
    <div class="bg-white border-b border-slate-200 px-6 py-4 flex items-center gap-4 shadow-sm">
      <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center font-bold text-sm">
        <?= e(initials($selAppt['patient_name'])) ?>
      </div>
      <div class="flex-1">
        <p class="font-bold text-slate-900"><?= e($selAppt['patient_name']) ?></p>
        <p class="text-xs text-slate-500"><?= format_date($selAppt['scheduled_at'], 'd M Y, H:i') ?> WIB</p>
      </div>
      <a href="rekam_medis.php?appt_id=<?= $selAppt['id'] ?>" class="text-xs font-bold text-primary bg-primary-fixed px-3 py-1.5 rounded-lg hover:bg-primary hover:text-white transition-colors flex items-center gap-1">
        <span class="material-symbols-outlined text-[14px]">medical_information</span>
        Rekam Medis
      </a>
    </div>

    <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-4 max-h-[calc(100vh-180px)]">
      <div class="flex items-center justify-center">
        <span class="text-xs text-slate-400 bg-slate-100 px-3 py-1 rounded-full">Memuat pesan...</span>
      </div>
    </div>

    <div class="bg-white border-t border-slate-200 px-6 py-4">
      <form id="chat-form" class="flex items-center gap-3">
        <input type="text" id="msg-input" placeholder="Tulis pesan..." autocomplete="off"
               class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
        <button type="submit" class="bg-primary text-white w-11 h-11 rounded-xl flex items-center justify-center hover:bg-primary-light transition-colors shadow-md active:scale-95">
          <span class="material-symbols-outlined text-[20px]">send</span>
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="flex-1 flex items-center justify-center text-slate-400">
      <div class="text-center">
        <span class="material-symbols-outlined text-[64px] text-slate-300">forum</span>
        <p class="mt-4 font-medium">Pilih percakapan dari daftar di samping.</p>
      </div>
    </div>
    <?php endif; ?>
  </main>
</div>

<?php if ($selectedAppt): ?>
<script>
const APPT_ID = <?= $selectedAppt ?>;
const MY_ID   = <?= $user['id'] ?>;
const chatBox = document.getElementById('chat-messages');
const form    = document.getElementById('chat-form');
const input   = document.getElementById('msg-input');

function renderMessages(messages) {
  if (!messages.length) {
    chatBox.innerHTML = '<div class="flex items-center justify-center h-full"><div class="text-center text-slate-400"><span class="material-symbols-outlined text-[48px] text-slate-300">chat_bubble_outline</span><p class="mt-2 text-sm font-medium">Belum ada pesan.</p></div></div>';
    return;
  }
  chatBox.innerHTML = messages.map(m => {
    const isMine = parseInt(m.sender_id) === MY_ID;
    const time   = new Date(m.created_at).toLocaleTimeString('id', {hour:'2-digit', minute:'2-digit'});
    const read   = m.read_at ? '✓✓' : '✓';
    return `<div class="flex ${isMine ? 'justify-end' : 'justify-start'}">
      <div class="msg-bubble ${isMine ? 'bg-primary text-white rounded-2xl rounded-br-md' : 'bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-bl-md'} px-4 py-3 shadow-sm">
        ${!isMine ? `<p class="text-xs font-bold text-primary mb-1">${m.sender_name}</p>` : ''}
        <p class="text-sm leading-relaxed">${escHtml(m.body)}</p>
        <p class="text-xs mt-1.5 ${isMine ? 'text-white/60' : 'text-slate-400'} text-right">${time} ${isMine ? read : ''}</p>
      </div>
    </div>`;
  }).join('');
  chatBox.scrollTop = chatBox.scrollHeight;
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

function loadMessages() {
  fetch(`chat.php?fetch=1&appointment_id=${APPT_ID}`)
    .then(r => r.json())
    .then(data => renderMessages(data.messages || []))
    .catch(() => {});
}

form.addEventListener('submit', function(e) {
  e.preventDefault();
  const body = input.value.trim();
  if (!body) return;
  input.value = '';

  const fd = new FormData();
  fd.append('ajax_send', '1');
  fd.append('appointment_id', APPT_ID);
  fd.append('body', body);

  fetch('chat.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (data.ok) loadMessages(); })
    .catch(() => {});
});

loadMessages();
setInterval(loadMessages, 5000);
input.focus();
</script>
<?php endif; ?>

</body>
</html>
