<?php
declare(strict_types=1);

/**
 * SIMIA - Manajemen Penugasan Dosen Pengampu Kelas
 * Acuan: PRD SIMIA FR-4.1 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Penugasan Dosen (Pengampu)';

// PENANGANAN POST: TAMBAH / HAPUS PENUGASAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $idUser = (int)($_POST['id_user'] ?? 0);
        $idMk = (int)($_POST['id_mk'] ?? 0);
        $idTa = (int)($_POST['id_ta'] ?? 0);
        $semester = trim((string)($_POST['semester'] ?? 'Ganjil'));

        if ($idUser <= 0 || $idMk <= 0 || $idTa <= 0) {
            set_flash('error', 'Semua bidang wajib dipilih.');
            redirect('assign_pengampu.php');
        }

        // Cek duplikasi
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM pengampu WHERE id_user = ? AND id_mk = ? AND id_ta = ? AND semester = ?");
        $checkStmt->execute([$idUser, $idMk, $idTa, $semester]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            set_flash('error', 'Dosen tersebut sudah di-assign pada mata kuliah dan periode yang sama.');
            redirect('assign_pengampu.php');
        }

        $ins = $pdo->prepare("INSERT INTO pengampu (id_user, id_mk, id_ta, semester) VALUES (?, ?, ?, ?)");
        $ins->execute([$idUser, $idMk, $idTa, $semester]);

        // Detail untuk log
        $dosenNama = $pdo->query("SELECT nama FROM users WHERE id_user = $idUser")->fetchColumn();
        $mkNama = $pdo->query("SELECT nama_mk FROM mata_kuliah WHERE id_mk = $idMk")->fetchColumn();

        log_activity('ASSIGN_DOSEN', "Assign dosen {$dosenNama} ke mata kuliah {$mkNama} ({$semester}).");
        set_flash('success', "Berhasil menugaskan {$dosenNama} pada mata kuliah {$mkNama}!");
        redirect('assign_pengampu.php');

    } elseif ($action === 'delete') {
        $idPengampu = (int)($_POST['id_pengampu'] ?? 0);
        $del = $pdo->prepare("DELETE FROM pengampu WHERE id_pengampu = ?");
        $del->execute([$idPengampu]);

        log_activity('ASSIGN_DOSEN', "Hapus penugasan pengampu ID: {$idPengampu}.");
        set_flash('success', 'Penugasan dosen berhasil dihapus.');
        redirect('assign_pengampu.php');
    }
}

// Data Dropdown
$dosenList = $pdo->query("SELECT id_user, nama, nip FROM users WHERE role != 'Super Admin' ORDER BY nama ASC")->fetchAll();
$mkList = $pdo->query("SELECT id_mk, kode_mk, nama_mk, sks FROM mata_kuliah ORDER BY nama_mk ASC")->fetchAll();
$taList = $pdo->query("SELECT id_ta, tahun, status FROM tahun_ajaran ORDER BY id_ta DESC")->fetchAll();

// Filter
$activeTaSem = get_active_ta_semester();
$filterTa = $_GET['filter_ta'] ?? ($activeTaSem['ta']['id_ta'] ?? 'ALL');
$filterSem = $_GET['filter_sem'] ?? ($activeTaSem['semester']['jenis_semester'] ?? 'ALL');

$q = "
    SELECT p.id_pengampu, p.semester, u.id_user, u.nama as nama_dosen, u.nip,
           m.id_mk, m.kode_mk, m.nama_mk, m.sks, t.id_ta, t.tahun
    FROM pengampu p
    JOIN users u ON p.id_user = u.id_user
    JOIN mata_kuliah m ON p.id_mk = m.id_mk
    JOIN tahun_ajaran t ON p.id_ta = t.id_ta
    WHERE 1=1
";
$prms = [];
if ($filterTa !== 'ALL') { $q .= " AND p.id_ta = ?"; $prms[] = $filterTa; }
if ($filterSem !== 'ALL') { $q .= " AND p.semester = ?"; $prms[] = $filterSem; }
$q .= " ORDER BY t.id_ta DESC, m.nama_mk ASC, u.nama ASC";

$stmt = $pdo->prepare($q);
$stmt->execute($prms);
$assignments = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header Halaman -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">
                        Penugasan Dosen (Pengampu)
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Petakan dosen pengajar ke mata kuliah pada Tahun Ajaran dan Semester tertentu.
                    </p>
                </div>
                <button type="button" onclick="openModal('modalAddAssign')"
                        class="px-4 py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Tambah Penugasan Baru
                </button>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="<?= url('assign_pengampu.php') ?>" class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-bold text-slate-600 flex items-center gap-1.5">
                        <i data-lucide="filter" class="w-3.5 h-3.5 text-maritime-700"></i>
                        Filter:
                    </span>

                    <select name="filter_ta" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="ALL" <?= $filterTa === 'ALL' ? 'selected' : '' ?>>Semua T.A.</option>
                        <?php foreach ($taList as $t): ?>
                            <option value="<?= $t['id_ta'] ?>" <?= (string)$filterTa === (string)$t['id_ta'] ? 'selected' : '' ?>>
                                T.A. <?= e($t['tahun']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filter_sem" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="ALL" <?= $filterSem === 'ALL' ? 'selected' : '' ?>>Semua Semester</option>
                        <option value="Ganjil" <?= $filterSem === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="Genap" <?= $filterSem === 'Genap' ? 'selected' : '' ?>>Genap</option>
                        <option value="Sisipan" <?= $filterSem === 'Sisipan' ? 'selected' : '' ?>>Sisipan</option>
                    </select>
                </form>

                <div class="text-xs text-slate-500 font-medium">
                    Total: <span class="font-bold text-slate-800"><?= count($assignments) ?></span> Penugasan
                </div>
            </div>

            <!-- Tabel Penugasan -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4 text-center w-12">No</th>
                                <th class="py-3 px-4">Dosen Pengampu</th>
                                <th class="py-3 px-4">Mata Kuliah & Bobot</th>
                                <th class="py-3 px-4">Tahun Ajaran</th>
                                <th class="py-3 px-4 text-center">Semester</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($assignments)): ?>
                                <?php foreach ($assignments as $index => $row): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 text-center text-slate-400 font-mono"><?= $index + 1 ?></td>
                                    <td class="py-3 px-4">
                                        <p class="font-bold text-slate-900"><?= e($row['nama_dosen']) ?></p>
                                        <p class="text-[10px] text-slate-400 font-mono">NIP: <?= e($row['nip']) ?></p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <p class="font-bold text-slate-800"><?= e($row['nama_mk']) ?></p>
                                        <span class="font-mono text-[10px] text-slate-500"><?= e($row['kode_mk']) ?> • <?= e($row['sks']) ?> SKS</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="font-semibold text-slate-700"><?= e($row['tahun']) ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-maritime-700">
                                            <?= e($row['semester']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="<?= url('upload_instrumen.php?id_pengampu=' . $row['id_pengampu']) ?>"
                                               title="Buka Ruang Dokumen"
                                               class="p-1.5 rounded-lg bg-blue-50 text-maritime-700 hover:bg-maritime-100 transition">
                                                <i data-lucide="folder-open" class="w-4 h-4"></i>
                                            </a>
                                            <form action="<?= url('assign_pengampu.php') ?>" method="POST"
                                                  onsubmit="return confirm('Hapus penugasan dosen ini?')" class="inline m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_pengampu" value="<?= $row['id_pengampu'] ?>">
                                                <button type="submit" title="Hapus Penugasan" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-slate-400">
                                        Belum ada data penugasan pada filter periode ini.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        </div>

<!-- MODAL TAMBAH PENUGASAN -->
<div id="modalAddAssign" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <button type="button" onclick="closeModal('modalAddAssign')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h3 class="font-heading font-extrabold text-base text-slate-900 mb-1">Tambah Penugasan Dosen</h3>
        <p class="text-xs text-slate-500 mb-4">Petakan dosen pengajar ke mata kuliah spesifik.</p>

        <form action="<?= url('assign_pengampu.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <!-- Dosen -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Dosen</label>
                <select name="id_user" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <option value="">-- Pilih Dosen Pengampu --</option>
                    <?php foreach ($dosenList as $d): ?>
                        <option value="<?= $d['id_user'] ?>">
                            <?= e($d['nama']) ?> (NIP: <?= e($d['nip']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- MK -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Mata Kuliah</label>
                <select name="id_mk" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <option value="">-- Pilih Mata Kuliah --</option>
                    <?php foreach ($mkList as $m): ?>
                        <option value="<?= $m['id_mk'] ?>">
                            <?= e($m['kode_mk']) ?> - <?= e($m['nama_mk']) ?> (<?= e($m['sks']) ?> SKS)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- TA -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Ajaran</label>
                <select name="id_ta" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <?php foreach ($taList as $t): ?>
                        <option value="<?= $t['id_ta'] ?>" <?= $t['status'] === 'Aktif' ? 'selected' : '' ?>>
                            <?= e($t['tahun']) ?> <?= $t['status'] === 'Aktif' ? '(Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Semester -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Semester</label>
                <select name="semester" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                    <option value="Sisipan">Sisipan</option>
                </select>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalAddAssign')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Simpan Penugasan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
