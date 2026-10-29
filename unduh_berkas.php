<?php
declare(strict_types=1);

/**
 * SIMIA - Ekspor Arsip Dokumen Perkuliahan (ZIP Archiving untuk Akreditasi)
 * Acuan: PRD SIMIA FR-5.3 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Unduh Arsip Berkas (ZIP)';

$taList = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY id_ta DESC")->fetchAll();
$activeTaSem = get_active_ta_semester();
$filterTa = $_GET['filter_ta'] ?? ($activeTaSem['ta']['id_ta'] ?? 'ALL');
$filterSem = $_GET['filter_sem'] ?? ($activeTaSem['semester']['jenis_semester'] ?? 'ALL');

// PROSES PEMBUATAN & PENGUNDUHAN ZIP (ZipArchive)
if (isset($_GET['action']) && $_GET['action'] === 'download_zip') {
    $targetIdMk = isset($_GET['id_mk']) ? (int)$_GET['id_mk'] : 0;

    // Pastikan ekstensi ZipArchive terpasang
    if (!class_exists('ZipArchive')) {
        set_flash('error', 'Ekstensi PHP ZipArchive tidak terpasang di server hosting.');
        redirect('unduh_berkas.php');
    }

    // Ambil list berkas yang akan diarsipkan
    $query = "
        SELECT i.*, p.semester, m.kode_mk, m.nama_mk, u.nama as nama_dosen
        FROM instrumen i
        JOIN pengampu p ON i.id_pengampu = p.id_pengampu
        JOIN mata_kuliah m ON p.id_mk = m.id_mk
        JOIN users u ON p.id_user = u.id_user
        WHERE 1=1
    ";
    $prms = [];
    if ($filterTa !== 'ALL') { $query .= " AND p.id_ta = ?"; $prms[] = $filterTa; }
    if ($filterSem !== 'ALL') { $query .= " AND p.semester = ?"; $prms[] = $filterSem; }
    if ($targetIdMk > 0) { $query .= " AND m.id_mk = ?"; $prms[] = $targetIdMk; }

    $stmt = $pdo->prepare($query);
    $stmt->execute($prms);
    $fileList = $stmt->fetchAll();

    if (empty($fileList)) {
        set_flash('error', 'Tidak ada berkas instrumen fisik yang dapat diarsipkan untuk kriteria tersebut.');
        redirect('unduh_berkas.php');
    }

    $zipDir = __DIR__ . '/storage/zips/';
    if (!is_dir($zipDir)) {
        mkdir($zipDir, 0755, true);
    }

    $cleanLabel = ($targetIdMk > 0) ? preg_replace('/[^a-zA-Z0-9]/', '_', $fileList[0]['nama_mk']) : 'Semua_MK';
    $zipFilename = "SIMIA_Arsip_{$cleanLabel}_" . date('Ymd_His') . ".zip";
    $zipFilePath = $zipDir . $zipFilename;

    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        set_flash('error', 'Gagal membuat file arsip ZIP di server.');
        redirect('unduh_berkas.php');
    }

    $addedFiles = 0;
    foreach ($fileList as $item) {
        // Abaikan URL eksternal jika bukan berkas lokal
        if (str_starts_with($item['file_path'], 'http')) {
            // Tulis teks pintasan (shortcut file) untuk tautan Google Drive
            $shortcutContent = "[Tautan Cloud Google Drive]\r\n" . $item['file_url'];
            $internalPath = "{$item['nama_mk']}/{$item['kategori']}/{$item['sub_kategori']} - {$item['nama_dosen']} - Tautan_Drive.txt";
            $zip->addFromString($internalPath, $shortcutContent);
            $addedFiles++;
            continue;
        }

        $localPath = __DIR__ . '/' . ltrim($item['file_path'], '/');
        if (file_exists($localPath)) {
            // Struktur: [Nama MK] / [Kategori] / [SubKategori - NamaDosen - NamaFileAsli]
            $baseName = !empty($item['original_name']) ? $item['original_name'] : basename($localPath);
            $entryName = "{$item['nama_mk']}/{$item['kategori']}/{$item['sub_kategori']} - {$item['nama_dosen']} - {$baseName}";
            $zip->addFile($localPath, $entryName);
            $addedFiles++;
        }
    }

    $zip->close();

    if ($addedFiles === 0) {
        if (file_exists($zipFilePath)) @unlink($zipFilePath);
        set_flash('error', 'Semua berkas fisik pada MK tersebut tidak ditemukan di direktori server penyimpanan.');
        redirect('unduh_berkas.php');
    }

    log_activity('DOWNLOAD_ZIP', "Unduh arsip ZIP {$zipFilename} ({$addedFiles} berkas).");

    // Streaming File Download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Content-Length: ' . filesize($zipFilePath));
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($zipFilePath);
    exit;
}

// Ambil List Mata Kuliah yang memiliki dokumen
$mkQuery = "
    SELECT m.id_mk, m.kode_mk, m.nama_mk, m.sks,
           COUNT(DISTINCT i.id_instrumen) as total_file,
           COUNT(DISTINCT p.id_user) as total_dosen
    FROM mata_kuliah m
    JOIN pengampu p ON m.id_mk = p.id_mk
    LEFT JOIN instrumen i ON p.id_pengampu = i.id_pengampu
    WHERE 1=1
";
$prms = [];
if ($filterTa !== 'ALL') { $mkQuery .= " AND p.id_ta = ?"; $prms[] = $filterTa; }
if ($filterSem !== 'ALL') { $mkQuery .= " AND p.semester = ?"; $prms[] = $filterSem; }
$mkQuery .= " GROUP BY m.id_mk ORDER BY m.nama_mk ASC";

$stmt = $pdo->prepare($mkQuery);
$stmt->execute($prms);
$mkSummary = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Header Halaman -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">
                        Unduh Arsip Berkas (ZIP)
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Kompresi otomatis seluruh instrumen perkuliahan ke dalam file ZIP berstruktur hierarki untuk akreditasi LAM-INFOKOM / BAN-PT.
                    </p>
                </div>

                <!-- Tombol Unduh Seluruh MK Sekaligus -->
                <a href="<?= url("unduh_berkas.php?action=download_zip&filter_ta=$filterTa&filter_sem=$filterSem") ?>"
                   class="px-4 py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                    Unduh Semua MK (ZIP Kumulatif)
                </a>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="<?= url('unduh_berkas.php') ?>" class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-bold text-slate-600 flex items-center gap-1.5">
                        <i data-lucide="filter" class="w-3.5 h-3.5 text-maritime-700"></i>
                        Filter Periode:
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
                    Struktur folder otomatis: <span class="font-mono text-slate-700">[Mata Kuliah] / [Kategori] / [File]</span>
                </div>
            </div>

            <!-- List Kartu Mata Kuliah -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php if (!empty($mkSummary)): ?>
                    <?php foreach ($mkSummary as $mk): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="font-mono text-[11px] font-bold px-2.5 py-0.5 rounded bg-blue-50 text-maritime-700 border border-blue-100">
                                    <?= e($mk['kode_mk']) ?>
                                </span>
                                <span class="text-[11px] font-semibold text-slate-500">
                                    <?= (int)$mk['sks'] ?> SKS
                                </span>
                            </div>

                            <h3 class="font-heading font-bold text-base text-slate-900 leading-snug">
                                <?= e($mk['nama_mk']) ?>
                            </h3>

                            <div class="mt-4 flex items-center justify-between text-xs text-slate-500 pt-3 border-t border-slate-100">
                                <span>Pengampu: <strong class="text-slate-800"><?= $mk['total_dosen'] ?> Dosen</strong></span>
                                <span>File: <strong class="<?= $mk['total_file'] > 0 ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $mk['total_file'] ?> Dokumen</strong></span>
                            </div>
                        </div>

                        <!-- Button Download ZIP per MK -->
                        <div class="mt-5">
                            <?php if ($mk['total_file'] > 0): ?>
                            <a href="<?= url("unduh_berkas.php?action=download_zip&id_mk={$mk['id_mk']}&filter_ta=$filterTa&filter_sem=$filterSem") ?>"
                               class="w-full py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition flex items-center justify-center gap-1.5">
                                <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                Unduh Arsip ZIP MK
                            </a>
                            <?php else: ?>
                            <button disabled class="w-full py-2 px-3 bg-slate-100 text-slate-400 font-semibold text-xs rounded-xl cursor-not-allowed">
                                Belum Ada Berkas
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-16 text-center text-slate-400 bg-white rounded-2xl border border-slate-200">
                        <i data-lucide="archive-x" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                        Belum ada data mata kuliah pada filter periode ini.
                    </div>
                <?php endif; ?>
            </div>

        </div>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
