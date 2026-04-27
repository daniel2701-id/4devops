<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

$user = current_user();
$pdo  = db();
$error   = '';
$success = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $apptId  = (int) ($_POST['appointment_id'] ?? 0);
    $rating  = (int) ($_POST['rating'] ?? 0);
    $comment = sanitize_string($_POST['comment'] ?? '', 1000);

    if ($rating < 1 || $rating > 5) {
        $error = 'Rating harus antara 1 sampai 5 bintang.';
    } else {
        try {
            // Verify appointment belongs to this patient and is finished
            $appt = $pdo->prepare(
                "SELECT id, doctor_id FROM appointments WHERE id = ? AND patient_id = ? AND status = 'finished' LIMIT 1"
            );
            $appt->execute([$apptId, $user['id']]);
            $apptRow = $appt->fetch();

            if (!$apptRow) {
                $error = 'Reservasi tidak ditemukan atau belum selesai.';
            } else {
                // Check duplicate review
                $dup = $pdo->prepare("SELECT id FROM reviews WHERE appointment_id = ? LIMIT 1");
                $dup->execute([$apptId]);
                if ($dup->fetch()) {
                    $error = 'Anda sudah memberikan ulasan untuk konsultasi ini.';
                } else {
                    $pdo->prepare(
                        "INSERT INTO reviews (appointment_id, patient_id, doctor_id, rating, comment) VALUES (?, ?, ?, ?, ?)"
                    )->execute([$apptId, $user['id'], $apptRow['doctor_id'], $rating, $comment]);

                    if (function_exists('audit_log')) {
                        audit_log('review_submitted', $user['id'], "Appt: $apptId, Rating: $rating");
                    }
                    $success = 'Ulasan berhasil dikirimkan. Terima kasih!';
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    }
}

// Fetch finished appointments without review
$pending = [];
try {
    $pendingReview = $pdo->prepare(
        "SELECT a.id, a.scheduled_at, u.name AS doctor_name, dp.specialization
         FROM appointments a
         JOIN users u ON u.id = a.doctor_id
         LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
         LEFT JOIN reviews r ON r.appointment_id = a.id
         WHERE a.patient_id = ? AND a.status = 'finished' AND r.id IS NULL
         ORDER BY a.scheduled_at DESC"
    );
    $pendingReview->execute([$user['id']]);
    $pending = $pendingReview->fetchAll();
} catch (Exception $e) {
    // reviews table may not exist yet
}

// Fetch submitted reviews
$reviewed = [];
try {
    $myReviews = $pdo->prepare(
        "SELECT r.*, a.scheduled_at, u.name AS doctor_name, dp.specialization
         FROM reviews r
         JOIN appointments a ON a.id = r.appointment_id
         JOIN users u ON u.id = r.doctor_id
         LEFT JOIN doctor_profiles dp ON dp.user_id = u.id
         WHERE r.patient_id = ?
         ORDER BY r.created_at DESC"
    );
    $myReviews->execute([$user['id']]);
    $reviewed = $myReviews->fetchAll();
} catch (Exception $e) {
    // reviews table may not exist yet
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Rating & Ulasan Dokter</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#2563eb') ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }
.star-btn { transition: color .1s; cursor: pointer; font-size: 28px; }
.star-btn:hover, .star-btn.active { color: #f59e0b; }
</style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

  <main class="p-6 lg:p-8 max-w-4xl mx-auto">

    <div class="mb-6">
      <a href="dashboard.php" class="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-blue-700 transition-colors bg-white px-4 py-2.5 rounded-xl border border-slate-200 shadow-sm hover:shadow-md">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
        Kembali ke Dashboard
      </a>
    </div>

    <div class="mb-8 flex items-center gap-4">
      <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center">
        <span class="material-symbols-outlined text-[24px]" style="font-variation-settings:'FILL' 1;">star</span>
      </div>
      <div>
        <h1 class="text-2xl font-black text-slate-900">Rating & Ulasan Dokter</h1>
        <p class="text-slate-500 font-medium mt-1">Bagikan pengalaman konsultasi Anda.</p>
      </div>
    </div>

    <?= alert_html($error, 'error') ?>
    <?= alert_html($success, 'success') ?>

    <!-- Pending Reviews -->
    <?php if (!empty($pending)): ?>
    <div class="mb-8">
      <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-amber-500 text-[20px]">rate_review</span>
        Konsultasi Menunggu Ulasan (<?= count($pending) ?>)
      </h2>
      <div class="space-y-4">
        <?php foreach ($pending as $appt): ?>
        <div class="bg-white rounded-2xl border border-amber-100 shadow-sm p-6">
          <div class="flex items-start justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-sm">
                <?= e(initials($appt['doctor_name'])) ?>
              </div>
              <div>
                <p class="font-bold text-slate-900"><?= e($appt['doctor_name']) ?></p>
                <p class="text-xs text-slate-500"><?= e($appt['specialization'] ?? 'Umum') ?> &bull; <?= format_date($appt['scheduled_at'], 'd M Y') ?></p>
              </div>
            </div>
            <button onclick="toggleReviewForm(<?= $appt['id'] ?>)"
                    class="text-sm font-bold text-amber-600 bg-amber-50 border border-amber-200 px-4 py-2 rounded-xl hover:bg-amber-100 transition-colors flex items-center gap-1">
              <span class="material-symbols-outlined text-[16px]">star</span>
              Beri Ulasan
            </button>
          </div>

          <!-- Review Form (hidden by default) -->
          <div id="review-form-<?= $appt['id'] ?>" class="hidden mt-5 pt-5 border-t border-slate-100">
            <form method="POST">
              <?= csrf_field() ?>
              <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
              <div class="mb-4">
                <label class="text-sm font-bold text-slate-700 block mb-2">Rating</label>
                <div class="flex gap-1" id="stars-<?= $appt['id'] ?>">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star-btn text-slate-300"
                          data-value="<?= $i ?>"
                          data-group="<?= $appt['id'] ?>"
                          onclick="setRating(<?= $appt['id'] ?>, <?= $i ?>)">★</span>
                  <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="rating-input-<?= $appt['id'] ?>" required>
              </div>
              <div class="mb-4">
                <label class="text-sm font-bold text-slate-700 block mb-1.5">Komentar (opsional)</label>
                <textarea name="comment" rows="3" placeholder="Ceritakan pengalaman konsultasi Anda..."
                          class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20 text-sm"></textarea>
              </div>
              <button type="submit" class="inline-flex items-center gap-2 bg-amber-500 text-white font-bold px-6 py-2.5 rounded-xl hover:bg-amber-600 transition-colors text-sm">
                <span class="material-symbols-outlined text-[18px]">send</span>
                Kirim Ulasan
              </button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Submitted Reviews -->
    <?php if (!empty($reviewed)): ?>
    <div>
      <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-green-500 text-[20px]">task_alt</span>
        Ulasan Yang Sudah Dikirim (<?= count($reviewed) ?>)
      </h2>
      <div class="space-y-3">
        <?php foreach ($reviewed as $rev): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
          <div class="flex items-start gap-3">
            <div class="w-9 h-9 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-xs">
              <?= e(initials($rev['doctor_name'])) ?>
            </div>
            <div class="flex-1">
              <div class="flex items-center justify-between flex-wrap gap-2">
                <p class="font-bold text-slate-900 text-sm"><?= e($rev['doctor_name']) ?></p>
                <span class="text-xs text-slate-400"><?= format_date($rev['created_at'], 'd M Y') ?></span>
              </div>
              <div class="flex gap-0.5 mt-1 mb-2">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span class="text-[16px] <?= $i <= $rev['rating'] ? 'text-amber-400' : 'text-slate-200' ?>">★</span>
                <?php endfor; ?>
              </div>
              <?php if ($rev['comment']): ?>
              <p class="text-sm text-slate-600 leading-relaxed">"<?= e($rev['comment']) ?>"</p>
              <?php else: ?>
              <p class="text-xs text-slate-400 italic">Tidak ada komentar tertulis.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php elseif (empty($pending)): ?>
    <div class="text-center py-20 text-slate-400">
      <span class="material-symbols-outlined text-[56px] text-slate-300">star_border</span>
      <p class="mt-4 font-medium">Belum ada konsultasi selesai untuk diulas.</p>
    </div>
    <?php endif; ?>

  </main>

<script>
function toggleReviewForm(id) {
  const el = document.getElementById('review-form-' + id);
  el.classList.toggle('hidden');
}

function setRating(groupId, value) {
  document.getElementById('rating-input-' + groupId).value = value;
  const stars = document.querySelectorAll('[data-group="' + groupId + '"]');
  stars.forEach(s => {
    const v = parseInt(s.dataset.value);
    s.classList.toggle('active', v <= value);
    s.classList.toggle('text-slate-300', v > value);
  });
}

// Hover effects
document.querySelectorAll('.star-btn').forEach(btn => {
  btn.addEventListener('mouseenter', function() {
    const group = this.dataset.group;
    const val   = parseInt(this.dataset.value);
    document.querySelectorAll(`[data-group="${group}"]`).forEach(s => {
      s.style.color = parseInt(s.dataset.value) <= val ? '#f59e0b' : '';
    });
  });
  btn.addEventListener('mouseleave', function() {
    const group    = this.dataset.group;
    const selected = parseInt(document.getElementById('rating-input-' + group).value || 0);
    document.querySelectorAll(`[data-group="${group}"]`).forEach(s => {
      const v = parseInt(s.dataset.value);
      s.style.color = v <= selected ? '#f59e0b' : '#cbd5e1';
    });
  });
});
</script>
</body>
</html>
