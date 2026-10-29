<?php
declare(strict_types=1);

/**
 * SIMIA - Master Semester & Sisipan
 * Acuan: PRD SIMIA FR-3.3 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Master Semester & Periode';

// Handle POST (Tambah, Toggle Status, Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $idTa = (int)($_POST['id_ta'] ?? 0);
        $jenis = trim((string)($_POST['jenis_semester'] ?? 'Ganjil'));
        $status = trim((string)($_POST['status'] ?? 'Berjalan'));

        if ($idTa <= 0) {
            set_flash('error', 'Tahun Ajaran wajib dipilih.');
            redirect('master_semester.php');
        }

        // Cek duplikasi
        $check = $pdo->prepare("SELECT COUNT(*) FROM semesters WHERE id_ta = ? AND jenis_semester = ?");
        $check->execute([$idTa, $jenis]);
        if ((int)$check->fetchColumn() > 0) {
            set_flash('error', "Semester {$jenis} sudah terdaftar pada Tahun Ajaran tersebut.");
            redirect('master_semester.php');
        }

        $ins = $pdo->prepare("INSERT INTO semesters (id_ta, jenis_semester, status) VALUES (?, ?, ?)");
        $ins->execute([$idTa, $jenis, $status]);

        log_activity('TAMBAH_SEMESTER', "Tambah semester {$jenis} ({$status}) pada TA ID: {$idTa}.");
        set_flash('success', "Semester {$jenis} berhasil ditambahkan!");
        redirect('master_semester.php');

    } elseif ($action === 'toggle_status') {
        $idSem = (int)($_POST['id_semester'] ?? 0);
        $statusBaru = $_POST['status_baru'] ?? 'Berjalan';

        $up = $pdo->prepare("UPDATE semesters SET status = ? WHERE id_semester = ?");
        $up->execute([$statusBaru, $idSem]);

        log_activity('UPDATE_SEMESTER', "Ubah status semester ID: {$idSem} menjadi {$statusBaru}.");
        set_flash('success', "Status semester berhasil diubah menjadi '$statusBaru'.");
        redirect('master_semester.php');

    } elseif ($action === 'delete') {
        $idSem = (int)($_POST['id_semester'] ?? 0);
        $del = $pdo->prepare("DELETE FROM semesters WHERE id_semester = ?");
        $del->execute([$idSem]);

        log_activity('HAPUS_SEMESTER', "Hapus semester ID: {$idSem}.");
        set_flash('success', 'Semester berhasil dihapus.');
        redirect('master_semester.php');
    }
}

// Data List Semester Gabungan TA
$semList = $pdo->query("
    SELECT s.*, t.tahun
    FROM semesters s
    JOIN tahun_ajaran t ON s.id_ta = t.id_ta
    ORDER BY t.id_ta DESC, s.id_semester ASC
")->fetchAll();

$taList = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY id_ta DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Header Halaman -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">
                        Master Semester & Sisipan
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Buka atau tutup periode perkuliahan (Ganjil, Genap, Sisipan) yang tertaut pada Tahun Ajaran.
                    </p>
                </div>
                <button type="button" onclick="openModal('modalAddSem')"
                        class="px-4 py-2 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Buka Semester Baru
                </button>
            </div>

            <!-- Tabel Data Semester -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="min-w-full text-xs text-left">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="py-3 px-4 text-center w-12">No</th>
                            <th class="py-3 px-4">Tahun Ajaran</th>
                            <th class="py-3 px-4">Jenis Semester</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Aksi Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($semList)): ?>
                            <?php foreach ($semList as $idx => $s): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4 text-center text-slate-400 font-mono"><?= $idx + 1 ?></td>
                                <td class="py-3 px-4 font-bold text-slate-900">
                                    T.A. <?= e($s['tahun']) ?>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-800">
                                    <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-maritime-700 font-bold">
                                        Semester <?= e($s['jenis_semester']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if ($s['status'] === 'Berjalan'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center justify-center gap-1.5 w-fit mx-auto">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Sedang Berjalan
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600">
                                            Selesai / Ditutup
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <form action="<?= url('master_semester.php') ?>" method="POST" class="inline m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id_semester" value="<?= $s['id_semester'] ?>">
                                            <?php if ($s['status'] === 'Berjalan'): ?>
                                                <input type="hidden" name="status_baru" value="Selesai">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 text-[11px] font-semibold transition">
                                                    Tutup Semester
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="status_baru" value="Berjalan">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-[11px] font-bold transition flex items-center gap-1">
                                                    <i data-lucide="play" class="w-3 h-3"></i>
                                                    Buka Berjalan
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <form action="<?= url('master_semester.php') ?>" method="POST"
                                              onsubmit="return confirm('Hapus semester <?= e($s['jenis_semester']) ?> TA <?= e($s['tahun']) ?>?')" class="inline m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_semester" value="<?= $s['id_semester'] ?>">
                                            <button type="submit" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 transition" title="Hapus Semester">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    Belum ada data semester.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        </div>

<!-- MODAL TAMBAH SEMESTER -->
<div id="modalAddSem" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-sm w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <button type="button" onclick="closeModal('modalAddSem')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h3 class="font-heading font-extrabold text-base text-slate-900 mb-1">Buka Semester Baru</h3>
        <p class="text-xs text-slate-500 mb-4">Pilih Tahun Ajaran dan periode semester.</p>

        <form action="<?= url('master_semester.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Ajaran</label>
                <select name="id_ta" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <?php foreach ($taList as $t): ?>
                        <option value="<?= $t['id_ta'] ?>" <?= $t['status'] === 'Aktif' ? 'selected' : '' ?>>
                            T.A. <?= e($t['tahun']) ?> <?= $t['status'] === 'Aktif' ? '(Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Jenis Semester</label>
                <select name="jenis_semester" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                    <option value="Sisipan">Sisipan</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Status Awal</label>
                <select name="status" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <option value="Berjalan">Berjalan</option>
                    <option value="Selesai">Selesai</option>
                </select>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalAddSem')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
