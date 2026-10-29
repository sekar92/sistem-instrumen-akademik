<?php
declare(strict_types=1);

/**
 * SIMIA - Master Mata Kuliah & CPMK + Bulk Import Excel Paste
 * Acuan: PRD SIMIA FR-3.4, FR-3.5 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Master Mata Kuliah & CPMK';

// Handle POST (Tambah, Edit, Hapus MK, Import Bulk, Tambah/Hapus CPMK)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_mk') {
        $kode = strtoupper(trim((string)($_POST['kode_mk'] ?? '')));
        $nama = trim((string)($_POST['nama_mk'] ?? ''));
        $sks = (int)($_POST['sks'] ?? 2);
        $idTa = (int)($_POST['id_ta'] ?? 0);
        $semester = trim((string)($_POST['semester'] ?? 'Ganjil'));
        $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));

        if (empty($kode) || empty($nama)) {
            set_flash('error', 'Kode dan Nama Mata Kuliah wajib diisi.');
            redirect('master_mk.php');
        }

        $ins = $pdo->prepare("INSERT INTO mata_kuliah (kode_mk, nama_mk, deskripsi, sks, id_ta, semester) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$kode, $nama, $deskripsi, $sks, $idTa ?: null, $semester]);

        log_activity('TAMBAH_MK', "Tambah mata kuliah baru {$kode} - {$nama}.");
        set_flash('success', "Mata Kuliah {$kode} - {$nama} berhasil ditambahkan!");
        redirect('master_mk.php');

    } elseif ($action === 'delete_mk') {
        $idMk = (int)($_POST['id_mk'] ?? 0);
        $del = $pdo->prepare("DELETE FROM mata_kuliah WHERE id_mk = ?");
        $del->execute([$idMk]);

        log_activity('HAPUS_MK', "Hapus mata kuliah ID: {$idMk}.");
        set_flash('success', 'Mata Kuliah berhasil dihapus.');
        redirect('master_mk.php');

    } elseif ($action === 'bulk_import') {
        // Import Massal via Paste Excel / TSV (PRD FR-3.4)
        $idTa = (int)($_POST['id_ta'] ?? 0);
        $pastedText = trim((string)($_POST['excel_paste'] ?? ''));

        if (empty($pastedText)) {
            set_flash('error', 'Silakan tempel (paste) data dari Excel.');
            redirect('master_mk.php');
        }

        $lines = preg_split('/\r\n|\r|\n/', $pastedText);
        $imported = 0;

        $ins = $pdo->prepare("INSERT INTO mata_kuliah (kode_mk, nama_mk, deskripsi, sks, id_ta, semester) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Pisahkan kolom berdasarkan tab (standar clipboard copy dari Excel)
            $cols = explode("\t", $line);
            if (count($cols) < 2) {
                // Fallback pemisah koma / titik koma
                if (str_contains($line, ';')) {
                    $cols = explode(';', $line);
                } elseif (str_contains($line, ',')) {
                    $cols = explode(',', $line);
                }
            }

            $kode = strtoupper(trim($cols[0] ?? ''));
            $nama = trim($cols[1] ?? '');
            $sks = (int)($cols[2] ?? 2);
            $semester = trim($cols[3] ?? 'Ganjil');
            $deskripsi = trim($cols[4] ?? '');

            if (!empty($kode) && !empty($nama)) {
                $ins->execute([$kode, $nama, $deskripsi, $sks ?: 2, $idTa ?: null, $semester ?: 'Ganjil']);
                $imported++;
            }
        }

        log_activity('IMPORT_MK', "Import massal {$imported} mata kuliah dari salinan Excel.");
        set_flash('success', "Berhasil mengimpor {$imported} Mata Kuliah secara massal!");
        redirect('master_mk.php');

    } elseif ($action === 'add_cpmk') {
        $idMk = (int)($_POST['id_mk'] ?? 0);
        $kodeCpmk = strtoupper(trim((string)($_POST['kode_cpmk'] ?? '')));
        $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));

        if (!empty($kodeCpmk) && !empty($deskripsi)) {
            $insCpmk = $pdo->prepare("INSERT INTO cpmk (id_mk, kode_cpmk, deskripsi) VALUES (?, ?, ?)");
            $insCpmk->execute([$idMk, $kodeCpmk, $deskripsi]);
            log_activity('TAMBAH_CPMK', "Tambah {$kodeCpmk} untuk MK ID: {$idMk}.");
            set_flash('success', "CPMK {$kodeCpmk} berhasil ditambahkan!");
        }
        redirect("master_mk.php?detail_id=$idMk");

    } elseif ($action === 'delete_cpmk') {
        $idCpmk = (int)($_POST['id_cpmk'] ?? 0);
        $idMk = (int)($_POST['id_mk'] ?? 0);
        $delCpmk = $pdo->prepare("DELETE FROM cpmk WHERE id_cpmk = ?");
        $delCpmk->execute([$idCpmk]);
        log_activity('HAPUS_CPMK', "Hapus CPMK ID: {$idCpmk}.");
        set_flash('success', 'CPMK berhasil dihapus.');
        redirect("master_mk.php?detail_id=$idMk");
    }
}

// Data Pendukung
$taList = $pdo->query("SELECT id_ta, tahun, status FROM tahun_ajaran ORDER BY id_ta DESC")->fetchAll();

// Filter & Pencarian
$filterTa = $_GET['filter_ta'] ?? 'ALL';
$filterSem = $_GET['filter_sem'] ?? 'ALL';
$search = trim((string)($_GET['q'] ?? ''));

$sql = "
    SELECT m.*, t.tahun
    FROM mata_kuliah m
    LEFT JOIN tahun_ajaran t ON m.id_ta = t.id_ta
    WHERE 1=1
";
$prms = [];
if ($filterTa !== 'ALL') { $sql .= " AND m.id_ta = ?"; $prms[] = $filterTa; }
if ($filterSem !== 'ALL') { $sql .= " AND m.semester = ?"; $prms[] = $filterSem; }
if (!empty($search)) {
    $sql .= " AND (m.kode_mk LIKE ? OR m.nama_mk LIKE ?)";
    $prms[] = "%$search%";
    $prms[] = "%$search%";
}
$sql .= " ORDER BY m.nama_mk ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($prms);
$mkRows = $stmt->fetchAll();

// Ambil Detail MK jika diminta via GET
$detailId = (int)($_GET['detail_id'] ?? 0);
$detailData = null;
if ($detailId > 0) {
    $dStmt = $pdo->prepare("
        SELECT m.*, t.tahun
        FROM mata_kuliah m
        LEFT JOIN tahun_ajaran t ON m.id_ta = t.id_ta
        WHERE m.id_mk = ?
    ");
    $dStmt->execute([$detailId]);
    $mkDetail = $dStmt->fetch();

    if ($mkDetail) {
        $cpmkList = $pdo->prepare("SELECT * FROM cpmk WHERE id_mk = ? ORDER BY id_cpmk ASC");
        $cpmkList->execute([$detailId]);

        $pengampuList = $pdo->prepare("
            SELECT p.id_pengampu, u.nama, u.nip
            FROM pengampu p
            JOIN users u ON p.id_user = u.id_user
            WHERE p.id_mk = ?
        ");
        $pengampuList->execute([$detailId]);

        $detailData = [
            'mk'       => $mkDetail,
            'cpmk'     => $cpmkList->fetchAll(),
            'dosen'    => $pengampuList->fetchAll()
        ];
    }
}

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
                        Master Mata Kuliah & CPMK
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Kelola kurikulum mata kuliah, Capaian Pembelajaran (CPMK), serta impor data massal dari Excel.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openModal('modalImportMK')"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                        Import Salinan Excel
                    </button>
                    <button type="button" onclick="openModal('modalAddMK')"
                            class="px-4 py-2 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Tambah MK Manual
                    </button>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="<?= url('master_mk.php') ?>" class="flex flex-wrap items-center gap-3">
                    <!-- Search Input -->
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400"></i>
                        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari Kode / Nama MK..."
                               class="pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl text-slate-800 focus:outline-none focus:ring-1 focus:ring-maritime-700 w-48 sm:w-64">
                    </div>

                    <!-- Filter TA -->
                    <select name="filter_ta" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="ALL" <?= $filterTa === 'ALL' ? 'selected' : '' ?>>Semua T.A.</option>
                        <?php foreach ($taList as $t): ?>
                            <option value="<?= $t['id_ta'] ?>" <?= (string)$filterTa === (string)$t['id_ta'] ? 'selected' : '' ?>>
                                T.A. <?= e($t['tahun']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Filter Semester -->
                    <select name="filter_sem" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="ALL" <?= $filterSem === 'ALL' ? 'selected' : '' ?>>Semua Semester</option>
                        <option value="Ganjil" <?= $filterSem === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                        <option value="Genap" <?= $filterSem === 'Genap' ? 'selected' : '' ?>>Genap</option>
                        <option value="Sisipan" <?= $filterSem === 'Sisipan' ? 'selected' : '' ?>>Sisipan</option>
                    </select>
                </form>

                <div class="text-xs text-slate-500 font-medium">
                    Total: <span class="font-bold text-slate-800"><?= count($mkRows) ?></span> Mata Kuliah
                </div>
            </div>

            <!-- Tabel Data Mata Kuliah -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4 text-center w-12">No</th>
                                <th class="py-3 px-4">Kode MK</th>
                                <th class="py-3 px-4">Nama Mata Kuliah</th>
                                <th class="py-3 px-4 text-center">Bobot</th>
                                <th class="py-3 px-4">Tahun Ajaran</th>
                                <th class="py-3 px-4 text-center">Semester</th>
                                <th class="py-3 px-4 text-center">Aksi & Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($mkRows)): ?>
                                <?php foreach ($mkRows as $index => $row): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 text-center text-slate-400 font-mono"><?= $index + 1 ?></td>
                                    <td class="py-3 px-4">
                                        <span class="font-mono font-bold px-2 py-0.5 rounded bg-blue-50 text-maritime-700 border border-blue-100">
                                            <?= e($row['kode_mk']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-bold text-slate-900">
                                        <?= e($row['nama_mk']) ?>
                                    </td>
                                    <td class="py-3 px-4 text-center font-semibold text-slate-700">
                                        <?= e($row['sks']) ?> SKS
                                    </td>
                                    <td class="py-3 px-4 text-slate-600">
                                        <?= e($row['tahun'] ?? '-') ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                            <?= e($row['semester']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- Buka Detail & CPMK -->
                                            <a href="<?= url('master_mk.php?detail_id=' . $row['id_mk']) ?>"
                                               title="Kelola CPMK & Detail"
                                               class="px-2.5 py-1 rounded-lg bg-cyan-50 text-cyan-700 hover:bg-cyan-100 font-bold text-[11px] transition flex items-center gap-1">
                                                <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                                                CPMK
                                            </a>

                                            <!-- Hapus MK -->
                                            <form action="<?= url('master_mk.php') ?>" method="POST"
                                                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus mata kuliah <?= e($row['nama_mk']) ?>?')" class="inline m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_mk">
                                                <input type="hidden" name="id_mk" value="<?= $row['id_mk'] ?>">
                                                <button type="submit" title="Hapus MK" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        Tidak ada data mata kuliah yang sesuai filter.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        </div>

<!-- MODAL TAMBAH MK MANUAL -->
<div id="modalAddMK" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <button type="button" onclick="closeModal('modalAddMK')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h3 class="font-heading font-extrabold text-base text-slate-900 mb-1">Tambah Mata Kuliah Manual</h3>
        <p class="text-xs text-slate-500 mb-4">Masukkan informasi detail mata kuliah kurikulum.</p>

        <form action="<?= url('master_mk.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_mk">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kode MK</label>
                    <input type="text" name="kode_mk" required placeholder="Contoh: TKL-2101"
                           class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 uppercase">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Bobot SKS</label>
                    <input type="number" name="sks" required min="1" max="10" value="2"
                           class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Mata Kuliah</label>
                <input type="text" name="nama_mk" required placeholder="Nama lengkap mata kuliah"
                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Tahun Ajaran</label>
                    <select name="id_ta" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="">-- Pilih TA --</option>
                        <?php foreach ($taList as $t): ?>
                            <option value="<?= $t['id_ta'] ?>"><?= e($t['tahun']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Semester</label>
                    <select name="semester" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                        <option value="Sisipan">Sisipan</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Deskripsi Ringkas / Silabus</label>
                <textarea name="deskripsi" rows="3" placeholder="Uraian singkat pokok bahasan materi..."
                          class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700"></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalAddMK')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Simpan Mata Kuliah
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL IMPORT BULK VIA PASTE DARI EXCEL (PRD FR-3.4) -->
<div id="modalImportMK" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <button type="button" onclick="closeModal('modalImportMK')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="font-heading font-extrabold text-base text-slate-900">Import Massal Mata Kuliah dari Excel</h3>
                <p class="text-xs text-slate-500">Salin (Copy) tabel dari Excel lalu tempel (Paste) langsung ke kolom di bawah.</p>
            </div>
        </div>

        <!-- Petunjuk Format Salinan -->
        <div class="my-4 p-3.5 bg-blue-50/70 border border-blue-200 rounded-2xl text-xs text-maritime-900 space-y-1">
            <p class="font-bold flex items-center gap-1.5">
                <i data-lucide="info" class="w-4 h-4 text-maritime-700"></i>
                Urutan Kolom Salinan (Copy) dari Microsoft Excel:
            </p>
            <p class="font-mono text-[11px] bg-white/80 p-2 rounded-xl border border-blue-100 text-slate-800">
                [Kolom 1: Kode MK] &nbsp; | &nbsp; [Kolom 2: Nama MK] &nbsp; | &nbsp; [Kolom 3: SKS] &nbsp; | &nbsp; [Kolom 4: Semester] &nbsp; | &nbsp; [Kolom 5: Deskripsi]
            </p>
        </div>

        <form action="<?= url('master_mk.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_import">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Tahun Ajaran Tujuan</label>
                <select name="id_ta" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    <?php foreach ($taList as $t): ?>
                        <option value="<?= $t['id_ta'] ?>" <?= $t['status'] === 'Aktif' ? 'selected' : '' ?>>
                            T.A. <?= e($t['tahun']) ?> <?= $t['status'] === 'Aktif' ? '(Aktif)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Tempel Data Disini (Ctrl + V)
                </label>
                <textarea name="excel_paste" required rows="8"
                          placeholder="TKL-2101	Instrumentasi Kelautan	3	Ganjil	Prinsip kerja sensor...
TKL-2102	Akustik Kelautan	3	Ganjil	Propagasi gelombang suara..."
                          class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-emerald-600"></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalImportMK')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="check-check" class="w-4 h-4"></i>
                    Proses Import Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DETAIL MK & KELOLA CPMK (PRD FR-3.5) -->
<?php if ($detailData): ?>
<div id="modalDetailMK" class="fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
        <a href="<?= url('master_mk.php') ?>" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </a>

        <!-- Header Detail MK -->
        <div class="mb-6 pb-4 border-b border-slate-200">
            <div class="flex items-center gap-2 mb-1">
                <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded bg-blue-50 text-maritime-700">
                    <?= e($detailData['mk']['kode_mk']) ?>
                </span>
                <span class="text-xs text-slate-500 font-semibold">
                    <?= (int)$detailData['mk']['sks'] ?> SKS • <?= e($detailData['mk']['tahun'] ?? '-') ?> (<?= e($detailData['mk']['semester']) ?>)
                </span>
            </div>
            <h2 class="font-heading font-extrabold text-xl text-slate-900">
                <?= e($detailData['mk']['nama_mk']) ?>
            </h2>
            <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                <?= e($detailData['mk']['deskripsi'] ?: 'Belum ada silabus / deskripsi.') ?>
            </p>
        </div>

        <!-- Dosen Pengampu Terkait -->
        <div class="mb-6">
            <h3 class="font-heading font-bold text-xs text-slate-700 uppercase tracking-wider mb-2">
                Dosen Pengampu Terdaftar:
            </h3>
            <div class="flex flex-wrap gap-2">
                <?php if (!empty($detailData['dosen'])): ?>
                    <?php foreach ($detailData['dosen'] as $d): ?>
                        <span class="px-3 py-1 bg-slate-100 rounded-xl text-xs font-semibold text-slate-800 flex items-center gap-1.5">
                            <i data-lucide="user" class="w-3.5 h-3.5 text-cyan-600"></i>
                            <?= e($d['nama']) ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="text-xs text-slate-400 italic">Belum ada dosen yang ditugaskan ke kelas ini.</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Capaian Pembelajaran Mata Kuliah (CPMK) -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-heading font-bold text-sm text-slate-900 flex items-center gap-2">
                    <i data-lucide="award" class="w-4 h-4 text-maritime-700"></i>
                    Capaian Pembelajaran (CPMK)
                </h3>
            </div>

            <!-- List CPMK -->
            <div class="space-y-2 mb-6">
                <?php if (!empty($detailData['cpmk'])): ?>
                    <?php foreach ($detailData['cpmk'] as $c): ?>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-start justify-between gap-3">
                        <div>
                            <span class="font-mono text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 text-maritime-700">
                                <?= e($c['kode_cpmk']) ?>
                            </span>
                            <p class="text-xs text-slate-700 mt-1.5 leading-relaxed"><?= e($c['deskripsi']) ?></p>
                        </div>
                        <form action="<?= url('master_mk.php') ?>" method="POST"
                              onsubmit="return confirm('Hapus <?= e($c['kode_cpmk']) ?>?')" class="inline m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_cpmk">
                            <input type="hidden" name="id_cpmk" value="<?= $c['id_cpmk'] ?>">
                            <input type="hidden" name="id_mk" value="<?= $detailData['mk']['id_mk'] ?>">
                            <button type="submit" class="text-slate-400 hover:text-rose-600 p-1">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-xs text-slate-400 py-3 italic">Belum ada CPMK yang ditambahkan untuk mata kuliah ini.</p>
                <?php endif; ?>
            </div>

            <!-- Form Tambah CPMK Baru -->
            <form action="<?= url('master_mk.php') ?>" method="POST" class="p-4 bg-slate-100/70 rounded-2xl border border-slate-200 space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_cpmk">
                <input type="hidden" name="id_mk" value="<?= $detailData['mk']['id_mk'] ?>">

                <p class="text-xs font-bold text-slate-700">Tambah CPMK Baru:</p>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                    <div>
                        <input type="text" name="kode_cpmk" required placeholder="Kode (CPMK-1)"
                               class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 uppercase">
                    </div>
                    <div class="sm:col-span-3">
                        <input type="text" name="deskripsi" required placeholder="Uraian capaian pembelajaran..."
                               class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    </div>
                </div>
                <div class="text-right">
                    <button type="submit" class="px-4 py-2 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-sm transition">
                        Tambah CPMK
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-200 flex justify-end">
            <a href="<?= url('master_mk.php') ?>" class="px-5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold text-xs rounded-xl transition">
                Tutup
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
