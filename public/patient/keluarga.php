<?php
require_once __DIR__ . '/../../includes/functions.php';
require_role('patient');

$user = current_user();
$pdo  = db();
$error   = '';
$success = '';

// ---- Handle Delete ----
if (isset($_GET['delete'])) {
    csrf_abort_get();
    $delId = (int) $_GET['delete'];
    $pdo->prepare("UPDATE family_members SET is_active = 0 WHERE id = ? AND user_id = ?")->execute([$delId, $user['id']]);
    flash('success', 'Anggota keluarga berhasil dihapus.');
    header('Location: keluarga.php');
    exit;
}

// ---- Handle Add/Edit POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_abort();
    $editId       = (int) ($_POST['edit_id'] ?? 0);
    $name         = sanitize_string($_POST['name'] ?? '', 150);
    $relationship = sanitize_string($_POST['relationship'] ?? '', 60);
    $birthDate    = $_POST['birth_date'] ?? '';
    $gender       = in_array($_POST['gender'] ?? '', ['male', 'female', 'other']) ? $_POST['gender'] : null;
    $nik          = sanitize_string($_POST['nik'] ?? '', 20);
    $phone        = sanitize_string($_POST['phone'] ?? '', 20);
    $bloodType    = sanitize_string($_POST['blood_type'] ?? '', 5);
    $notes        = sanitize_string($_POST['notes'] ?? '', 500);

    if (empty($name) || empty($relationship)) {
        $error = 'Nama dan hubungan wajib diisi.';
    } else {
        try {
            if ($editId) {
                // Verify ownership
                $own = $pdo->prepare("SELECT id FROM family_members WHERE id = ? AND user_id = ? AND is_active = 1");
                $own->execute([$editId, $user['id']]);
                if (!$own->fetch()) {
                    $error = 'Data tidak ditemukan.';
                } else {
                    $pdo->prepare(
                        "UPDATE family_members SET name=?, relationship=?, birth_date=?, gender=?, nik=?, phone=?, blood_type=?, notes=? WHERE id=?"
                    )->execute([$name, $relationship, $birthDate ?: null, $gender, $nik, $phone, $bloodType, $notes, $editId]);
                    flash('success', 'Data anggota keluarga berhasil diperbarui.');
                    header('Location: keluarga.php');
                    exit;
                }
            } else {
                $pdo->prepare(
                    "INSERT INTO family_members (user_id, name, relationship, birth_date, gender, nik, phone, blood_type, notes) VALUES (?,?,?,?,?,?,?,?,?)"
                )->execute([$user['id'], $name, $relationship, $birthDate ?: null, $gender, $nik, $phone, $bloodType, $notes]);
                flash('success', 'Anggota keluarga berhasil ditambahkan.');
                header('Location: keluarga.php');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan. Silakan coba lagi.';
        }
    }
}

$success = flash('success') ?? $success;

// Fetch family members
$stmt = $pdo->prepare("SELECT * FROM family_members WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$members = $stmt->fetchAll();

// If editing
$editMember = null;
if (isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    $es  = $pdo->prepare("SELECT * FROM family_members WHERE id = ? AND user_id = ? AND is_active = 1");
    $es->execute([$eid, $user['id']]);
    $editMember = $es->fetch();
}

$relOptions = [
    'anak'      => 'Anak',
    'pasangan'  => 'Pasangan/Suami/Istri',
    'orang_tua' => 'Orang Tua',
    'saudara'   => 'Saudara Kandung',
    'lainnya'   => 'Lainnya',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareConnect – Anggota Keluarga</title>
<?= tailwind_cdn() ?>
<?= tailwind_config('#2563eb') ?>
<?= google_fonts() ?>
<style>body { font-family: 'Inter', sans-serif; }</style>
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
      <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-2xl flex items-center justify-center">
        <span class="material-symbols-outlined text-[24px]">family_restroom</span>
      </div>
      <div>
        <h1 class="text-2xl font-black text-slate-900">Anggota Keluarga</h1>
        <p class="text-slate-500 font-medium mt-1">Kelola data keluarga untuk reservasi atas nama mereka.</p>
      </div>
    </div>

    <?= alert_html($error, 'error') ?>
    <?= alert_html($success, 'success') ?>

    <!-- Add/Edit Form -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 md:p-8 mb-8">
      <h2 class="text-lg font-bold text-slate-900 mb-5 flex items-center gap-2">
        <span class="material-symbols-outlined text-purple-500 text-[20px]"><?= $editMember ? 'edit' : 'person_add' ?></span>
        <?= $editMember ? 'Edit Anggota Keluarga' : 'Tambah Anggota Keluarga Baru' ?>
      </h2>

      <form method="POST" class="space-y-5">
        <?= csrf_field() ?>
        <?php if ($editMember): ?>
        <input type="hidden" name="edit_id" value="<?= $editMember['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
            <input type="text" name="name" required value="<?= e($editMember['name'] ?? $_POST['name'] ?? '') ?>"
                   placeholder="Nama lengkap anggota keluarga"
                   class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
          </div>
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">Hubungan <span class="text-red-500">*</span></label>
            <select name="relationship" required
                    class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
              <option value="">-- Pilih Hubungan --</option>
              <?php foreach ($relOptions as $val => $label): ?>
              <option value="<?= $val ?>" <?= ($editMember['relationship'] ?? $_POST['relationship'] ?? '') === $val ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">Tanggal Lahir</label>
            <input type="date" name="birth_date" value="<?= e($editMember['birth_date'] ?? $_POST['birth_date'] ?? '') ?>"
                   class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
          </div>
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">Jenis Kelamin</label>
            <select name="gender"
                    class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
              <option value="">-- Pilih --</option>
              <option value="male" <?= ($editMember['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="female" <?= ($editMember['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Perempuan</option>
              <option value="other" <?= ($editMember['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Lainnya</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">Golongan Darah</label>
            <select name="blood_type"
                    class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
              <option value="">-- Pilih --</option>
              <?php foreach (['A', 'B', 'AB', 'O'] as $bt): ?>
              <option value="<?= $bt ?>" <?= ($editMember['blood_type'] ?? '') === $bt ? 'selected' : '' ?>><?= $bt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">NIK</label>
            <input type="text" name="nik" value="<?= e($editMember['nik'] ?? $_POST['nik'] ?? '') ?>"
                   placeholder="Nomor Induk Kependudukan" maxlength="20"
                   class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
          </div>
          <div>
            <label class="text-sm font-bold text-slate-700 block mb-1.5">No. Telepon</label>
            <input type="text" name="phone" value="<?= e($editMember['phone'] ?? $_POST['phone'] ?? '') ?>"
                   placeholder="0812-xxxx-xxxx" maxlength="20"
                   class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
          </div>
        </div>

        <div>
          <label class="text-sm font-bold text-slate-700 block mb-1.5">Catatan Tambahan</label>
          <textarea name="notes" rows="2" placeholder="Alergi, kondisi khusus, dll (opsional)"
                    class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"><?= e($editMember['notes'] ?? $_POST['notes'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button type="submit" class="inline-flex items-center gap-2 bg-purple-600 text-white px-8 py-3 rounded-xl font-bold text-sm hover:bg-purple-700 transition-colors shadow-md">
            <span class="material-symbols-outlined text-[18px]"><?= $editMember ? 'save' : 'person_add' ?></span>
            <?= $editMember ? 'Simpan Perubahan' : 'Tambah Anggota' ?>
          </button>
          <?php if ($editMember): ?>
          <a href="keluarga.php" class="text-sm font-bold text-slate-500 hover:text-slate-700">Batal</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Members List -->
    <?php if (!empty($members)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="p-5 border-b border-slate-100">
        <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
          <span class="material-symbols-outlined text-purple-500 text-[18px]">group</span>
          Daftar Anggota Keluarga (<?= count($members) ?>)
        </h3>
      </div>
      <div class="divide-y divide-slate-100">
        <?php foreach ($members as $m):
          $relLabel = $relOptions[$m['relationship']] ?? ucfirst($m['relationship']);
          $age = $m['birth_date'] ? date_diff(date_create($m['birth_date']), date_create('now'))->y . ' tahun' : '-';
          $genderLabel = ['male' => 'Laki-laki', 'female' => 'Perempuan', 'other' => 'Lainnya'][$m['gender']] ?? '-';
        ?>
        <div class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50/60 transition-colors">
          <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center font-bold text-sm flex-shrink-0">
            <?= e(initials($m['name'])) ?>
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-bold text-slate-900 text-sm"><?= e($m['name']) ?></p>
            <div class="flex items-center gap-3 mt-1 flex-wrap">
              <span class="text-xs font-medium text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full"><?= e($relLabel) ?></span>
              <span class="text-xs text-slate-500"><?= $genderLabel ?></span>
              <span class="text-xs text-slate-500">Usia: <?= $age ?></span>
              <?php if ($m['blood_type']): ?>
              <span class="text-xs text-slate-500">Gol. Darah: <?= e($m['blood_type']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($m['notes']): ?>
            <p class="text-xs text-slate-400 mt-1 truncate"><?= e($m['notes']) ?></p>
            <?php endif; ?>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <a href="?edit=<?= $m['id'] ?>" class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center hover:bg-blue-100 transition-colors" title="Edit">
              <span class="material-symbols-outlined text-[16px]">edit</span>
            </a>
            <a href="?delete=<?= $m['id'] ?>&_csrf_token=<?= urlencode(csrf_token()) ?>"
               onclick="return confirm('Hapus anggota keluarga ini?')"
               class="w-8 h-8 bg-red-50 text-red-500 rounded-lg flex items-center justify-center hover:bg-red-100 transition-colors" title="Hapus">
              <span class="material-symbols-outlined text-[16px]">delete</span>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="text-center py-16 text-slate-400">
      <span class="material-symbols-outlined text-[56px] text-slate-300">family_restroom</span>
      <p class="mt-4 font-medium">Belum ada anggota keluarga yang terdaftar.</p>
      <p class="text-xs mt-1 text-slate-400">Tambahkan anggota keluarga untuk membuat reservasi atas nama mereka.</p>
    </div>
    <?php endif; ?>

  </main>

</body>
</html>
