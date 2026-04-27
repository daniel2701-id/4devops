<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

$user = current_user();
$pdo  = db();

$appointments  = [];
$selectedAppt  = 0;
$selAppt       = null;
$dbError       = '';

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
    // Verify appointment belongs to patient
    $check = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND patient_id = ? LIMIT 1");
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
    // Verify
    $check = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND patient_id = ? LIMIT 1");
    $check->execute([$apptId, $user['id']]);
    if (!$check->fetch()) {
      echo json_encode(['messages' => [], 'error' => 'Tidak diizinkan.']);
      exit;
    }

    // Mark doctor's messages as read
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

// Fetch appointments with chat capability (non-cancelled)
try {
  $appts = $pdo->prepare(
    "SELECT a.id, a.scheduled_at, a.status, a.reason,
              u.name AS doctor_name, dp.specialization,
              (SELECT COUNT(*) FROM messages m2 WHERE m2.appointment_id = a.id AND m2.sender_id != ? AND m2.read_at IS NULL) AS unread
       FROM appointments a
       JOIN users u ON u.id = a.doctor_id
       LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
       WHERE a.patient_id = ? AND a.status != 'cancelled'
       ORDER BY a.scheduled_at DESC
       LIMIT 30"
  );
  $appts->execute([$user['id'], $user['id']]);
  $appointments = $appts->fetchAll();
} catch (Exception $e) {
  // If messages table doesn't exist, try without unread count
  try {
    $appts = $pdo->prepare(
      "SELECT a.id, a.scheduled_at, a.status, a.reason,
                u.name AS doctor_name, dp.specialization, 0 AS unread
         FROM appointments a
         JOIN users u ON u.id = a.doctor_id
         LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
         WHERE a.patient_id = ? AND a.status != 'cancelled'
         ORDER BY a.scheduled_at DESC
         LIMIT 30"
    );
    $appts->execute([$user['id']]);
    $appointments = $appts->fetchAll();
  } catch (Exception $e2) {
    $dbError = 'Tidak dapat memuat data. Silakan coba lagi.';
  }
}

$selectedAppt = (int) ($_GET['appt'] ?? ($appointments[0]['id'] ?? 0));

// Status map defined here so it's available everywhere in template
$statusMap = [
  'waiting'    => ['Menunggu',    'bg-amber-50 text-amber-600'],
  'in_session' => ['Berlangsung', 'bg-blue-50 text-blue-600'],
  'finished'   => ['Selesai',     'bg-green-50 text-green-600'],
  'cancelled'  => ['Dibatalkan',  'bg-red-50 text-red-600'],
];

// Find selected appointment details
$selAppt = null;
foreach ($appointments as $_a) {
  if ($_a['id'] == $selectedAppt) {
    $selAppt = $_a;
    break;
  }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CareConnect – Chat Dokter</title>
  <?= tailwind_cdn() ?>
  <?= tailwind_config('#2563eb') ?>
  <?= google_fonts() ?>
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }

    .msg-bubble {
      max-width: 80%;
      word-wrap: break-word;
      animation: fadeIn .2s ease;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(4px)
      }

      to {
        opacity: 1;
        transform: none
      }
    }

    #chat-messages {
      scroll-behavior: smooth;
    }
  </style>
</head>

<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

  <div class="flex min-h-screen">

    <!-- Sidebar: Conversations List -->
    <aside class="w-80 bg-white border-r border-slate-200 flex-col hidden md:flex flex-shrink-0">
      <div class="p-5 border-b border-slate-100">
        <div class="flex items-center gap-3">
          <a href="dashboard.php"
            class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
          </a>
          <div>
            <h1 class="text-lg font-black text-slate-900">Chat Dokter</h1>
            <p class="text-xs text-slate-400">Komunikasi terkait reservasi</p>
          </div>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto divide-y divide-slate-50">
        <?php if (empty($appointments)): ?>
          <div class="p-6 text-center text-slate-400 text-sm">
            <span class="material-symbols-outlined text-[36px] text-slate-300">forum</span>
            <p class="mt-2">Belum ada reservasi.</p>
          </div>
        <?php else: ?>
          <?php foreach ($appointments as $a):
            $isActive = $a['id'] == $selectedAppt;
            [$stLabel, $stCls] = $statusMap[$a['status']] ?? ['', 'bg-slate-50 text-slate-500'];
            ?>
            <a href="?appt=<?= $a['id'] ?>"
              class="flex items-start gap-3 px-4 py-3.5 transition-colors <?= $isActive ? 'bg-blue-50 border-l-4 border-blue-500' : 'hover:bg-slate-50' ?>">
              <div
                class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-xs flex-shrink-0">
                <?= e(initials($a['doctor_name'])) ?>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <p class="font-bold text-sm text-slate-900 truncate"><?= e($a['doctor_name']) ?></p>
                  <?php if ($a['unread'] > 0): ?>
                    <span
                      class="bg-blue-600 text-white text-xs font-black rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"><?= $a['unread'] ?></span>
                  <?php endif; ?>
                </div>
                <p class="text-xs text-slate-500 truncate"><?= e($a['specialization'] ?? 'Umum') ?></p>
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
      <?php if ($selectedAppt && $selAppt): ?>
        <!-- Chat Header -->
        <div class="bg-white border-b border-slate-200 px-6 py-4 flex items-center gap-4 shadow-sm">
          <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-sm">
            <?= e(initials($selAppt['doctor_name'])) ?>
          </div>
          <div class="flex-1">
            <p class="font-bold text-slate-900"><?= e($selAppt['doctor_name']) ?></p>
            <p class="text-xs text-slate-500"><?= e($selAppt['specialization'] ?? 'Umum') ?> ·
              <?= format_date($selAppt['scheduled_at'], 'd M Y, H:i') ?></p>
          </div>
          <span
            class="text-xs font-bold px-2.5 py-1 rounded-full <?= ($statusMap[$selAppt['status']] ?? ['', 'bg-slate-50 text-slate-500'])[1] ?>">
            <?= ($statusMap[$selAppt['status']] ?? ['Status', ''])[0] ?>
          </span>
        </div>

        <!-- Messages Area -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-6 space-y-4 max-h-[calc(100vh-180px)]">
          <div class="flex items-center justify-center">
            <span class="text-xs text-slate-400 bg-slate-100 px-3 py-1 rounded-full">Memuat pesan...</span>
          </div>
        </div>

        <!-- Input Bar -->
        <div class="bg-white border-t border-slate-200 px-6 py-4">
          <form id="chat-form" class="flex items-center gap-3">
            <input type="text" id="msg-input" placeholder="Tulis pesan..." autocomplete="off"
              class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
            <button type="submit"
              class="bg-blue-600 text-white w-11 h-11 rounded-xl flex items-center justify-center hover:bg-blue-700 transition-colors shadow-md active:scale-95">
              <span class="material-symbols-outlined text-[20px]">send</span>
            </button>
          </form>
        </div>
      <?php else: ?>
        <div class="flex-1 flex items-center justify-center text-slate-400">
          <div class="text-center">
            <span class="material-symbols-outlined text-[64px] text-slate-300">forum</span>
            <p class="mt-4 font-medium">Pilih reservasi untuk memulai percakapan.</p>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <?php if ($selectedAppt): ?>
    <script>
      const APPT_ID = <?= $selectedAppt ?>;
      const MY_ID = <?= $user['id'] ?>;
      const chatBox = document.getElementById('chat-messages');
      const form = document.getElementById('chat-form');
      const input = document.getElementById('msg-input');

      function renderMessages(messages) {
        if (!messages.length) {
          chatBox.innerHTML = '<div class="flex items-center justify-center h-full"><div class="text-center text-slate-400"><span class="material-symbols-outlined text-[48px] text-slate-300">chat_bubble_outline</span><p class="mt-2 text-sm font-medium">Belum ada pesan. Mulai percakapan!</p></div></div>';
          return;
        }
        chatBox.innerHTML = messages.map(m => {
          const isMine = parseInt(m.sender_id) === MY_ID;
          const time = new Date(m.created_at).toLocaleTimeString('id', { hour: '2-digit', minute: '2-digit' });
          const read = m.read_at ? '✓✓' : '✓';
          return `<div class="flex ${isMine ? 'justify-end' : 'justify-start'}">
      <div class="msg-bubble ${isMine ? 'bg-blue-600 text-white rounded-2xl rounded-br-md' : 'bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-bl-md'} px-4 py-3 shadow-sm">
        ${!isMine ? `<p class="text-xs font-bold ${isMine ? 'text-blue-200' : 'text-blue-600'} mb-1">${m.sender_name}</p>` : ''}
        <p class="text-sm leading-relaxed">${escHtml(m.body)}</p>
        <p class="text-xs mt-1.5 ${isMine ? 'text-blue-200' : 'text-slate-400'} text-right">${time} ${isMine ? read : ''}</p>
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
          .catch(() => { });
      }

      form.addEventListener('submit', function (e) {
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
          .catch(() => { });
      });

      loadMessages();
      setInterval(loadMessages, 5000); // Poll every 5 seconds
      input.focus();
    </script>
  <?php endif; ?>

</body>

</html>