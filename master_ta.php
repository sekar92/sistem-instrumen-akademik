<?php
declare(strict_types=1);

/**
 * SIMIA - Master Tahun Ajaran (TA)
 * Acuan: PRD SIMIA FR-3.2 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Master Tahun Ajaran';

// Handle POST (Tambah, Toggle Status, Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $tahun = trim((string)($_POST['tahun'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Nonaktif'));

        if (empty($tahun)) {
            set_flash('error', 'Format Tahun Ajaran tidak boleh kosong (contoh: 2025/2026).');
            redirect('master_ta.php');
        }

        // Cek duplikasi
        $check = $pdo->prepare("SELECT COUNT(*) FROM tahun_ajaran WHERE tahun = ?");
        $check->execute([$tahun]);
        if ((int)$check->fetchColumn() > 0) {
            set_flash('error', "Tahun Ajaran '$tahun' sudah terdaftar.");
            redirect('master_ta.php');
        }

        // Jika diset Aktif, nonaktifkan yang lain
        if ($status === 'Aktif') {
            $pdo->exec("UPDATE tahun_ajaran SET status = 'Nonaktif'");
        }

        $ins = $pdo->prepare("INSERT INTO tahun_ajaran (tahun, status) VALUES (?, ?)");
        $ins->execute([$tahun, $status]);

        log_activity('TAMBAH_TA', "Tambah Tahun Ajaran: {$tahun} ({$status}).");
        set_flash('success', "Tahun Ajaran '$tahun' berhasil ditambahkan!");
        redirect('master_ta.php');

    } elseif ($action === 'toggle_status') {
        $idTa = (int)($_POST['id_ta'] ?? 0);
        $statusBaru = $_POST['status_baru'] ?? 'Aktif';

        if ($statusBaru === 'Aktif') {
            $pdo->exec("UPDATE tahun_ajaran SET status = 'Nonaktif'");
        }

        $up = $pdo->prepare("UPDATE tahun_ajaran SET status = ? WHERE id_ta = ?");
        $up->execute([$statusBaru, $idTa]);

        log_activity('UPDATE_TA', "Ubah status Tahun Ajaran ID: {$idTa} menjadi {$statusBaru}.");
        set_flash('success', "Status Tahun Ajaran berhasil diubah menjadi '$statusBaru'.");
        redirect('master_ta.php');

    } elseif ($action === 'delete') {
        $idTa = (int)($_POST['id_ta'] ?? 0);
        $del = $pdo->prepare("DELETE FROM tahun_ajaran WHERE id_ta = ?");
        $del->execute([$idTa]);

        log_activity('HAPUS_TA', "Hapus Tahun Ajaran ID: {$idTa}.");
        set_flash('success', 'Tahun Ajaran berhasil dihapus.');
        redirect('master_ta.php');
    }
}

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
                        Master Tahun Ajaran
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Kelola kalender periode akademik dan tetapkan Tahun Ajaran yang aktif berjalan.
                    </p>
                </div>
                <button type="button" onclick="openModal('modalAddTA')"
                        class="px-4 py-2 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Tambah Tahun Ajaran
                </button>
            </div>

            <!-- Tabel Data TA -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="min-w-full text-xs text-left">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="py-3 px-4 text-center w-12">No</th>
                            <th class="py-3 px-4">Tahun Ajaran</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Aksi & Pengaturan Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($taList)): ?>
                            <?php foreach ($taList as $idx => $t): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4 text-center text-slate-400 font-mono"><?= $idx + 1 ?></td>
                                <td class="py-3 px-4 font-bold text-slate-900 text-sm">
                                    <?= e($t['tahun']) ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <?php if ($t['status'] === 'Aktif'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center justify-center gap-1.5 w-fit mx-auto">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif Berjalan
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600">
                                            Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Tombol Set Status -->
                                        <form action="<?= url('master_ta.php') ?>" method="POST" class="inline m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id_ta" value="<?= $t['id_ta'] ?>">
                                            <?php if ($t['status'] === 'Aktif'): ?>
                                                <input type="hidden" name="status_baru" value="Nonaktif">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 text-[11px] font-semibold transition">
                                                    Nonaktifkan
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="status_baru" value="Aktif">
                                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-[11px] font-bold transition flex items-center gap-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    Jadikan Aktif
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Hapus TA -->
                                        <form action="<?= url('master_ta.php') ?>" method="POST"
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus Tahun Ajaran <?= e($t['tahun']) ?>?')" class="inline m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_ta" value="<?= $t['id_ta'] ?>">
                                            <button type="submit" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 transition" title="Hapus TA">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="py-12 text-center text-slate-400">
                                    Belum ada data Tahun Ajaran.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
        </div>

<!-- MODAL TAMBAH TA -->
<div id="modalAddTA" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-sm w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <button type="button" onclick="closeModal('modalAddTA')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h3 class="font-heading font-extrabold text-base text-slate-900 mb-1">Tambah Tahun Ajaran</h3>
        <p class="text-xs text-slate-500 mb-4">Contoh format: 2026/2027.</p>

        <form action="<?= url('master_ta.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Ajaran</label>
                <input type="text" name="tahun" required placeholder="2026/2027"
                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Status Awal</label>
                <select name="status" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <option value="Nonaktif">Nonaktif</option>
                    <option value="Aktif">Aktif</option>
                </select>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalAddTA')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
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
