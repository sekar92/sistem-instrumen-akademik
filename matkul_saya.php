<?php
declare(strict_types=1);

/**
 * SIMIA - Ruang Mata Kuliah Saya (Dosen Pengampu)
 * Fitur: Pengelompokan Otomatis Periode Aktif vs Lampau, Pelengkapan Instrumen Lampau, Filter & Sortir Tabel per Semester & Tahun Akademik
 * Acuan: PRD SIMIA FR-4.2 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_login();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Mata Kuliah Saya';

// Ambil Periode Aktif Sistem
$activeTaSem = get_active_ta_semester();
$activeTa = $activeTaSem['ta'];
$activeIdTa = (int)($activeTa['id_ta'] ?? 0);
$activeSemester = $activeTaSem['semester'];

// Opsi Tahun Ajaran untuk Filter Dropdown
$taOptions = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY id_ta DESC")->fetchAll();
$semOptions = ['Ganjil', 'Genap', 'Sisipan'];

// Ambil semua penugasan untuk user ini
$stmt = $pdo->prepare("
    SELECT p.id_pengampu, p.id_ta, p.semester, m.id_mk, m.kode_mk, m.nama_mk, m.sks, m.deskripsi, t.tahun, t.status as status_ta
    FROM pengampu p
    JOIN mata_kuliah m ON p.id_mk = m.id_mk
    JOIN tahun_ajaran t ON p.id_ta = t.id_ta
    WHERE p.id_user = ?
    ORDER BY t.id_ta DESC, m.nama_mk ASC
");
$stmt->execute([$user['id_user']]);
$rawKelasList = $stmt->fetchAll();

// Hitung progres kelengkapan per kelas (28 standar berkas) dan klasifikasikan Aktif vs Lampau
$allKelas = [];
$countTotal = count($rawKelasList);
$countAktif = 0;
$countLampau = 0;
$countBelumLengkap = 0;
$countLengkap = 0;
$countBelumLengkapLampau = 0;

foreach ($rawKelasList as $k) {
    $insStmt = $pdo->prepare("SELECT DISTINCT sub_kategori FROM instrumen WHERE id_pengampu = ?");
    $insStmt->execute([$k['id_pengampu']]);
    $uploadedCount = count($insStmt->fetchAll());
    $percentage = (int)round(($uploadedCount / TOTAL_STANDAR_FILE) * 100);
    if ($percentage > 100) $percentage = 100;
    $missingCount = max(0, TOTAL_STANDAR_FILE - $uploadedCount);
    $isComplete = ($uploadedCount >= TOTAL_STANDAR_FILE);

    // Kriteria Periode Aktif: ID TA sesuai TA aktif dan status TA 'Aktif'
    $isAktif = ((int)$k['id_ta'] === $activeIdTa && $k['status_ta'] === 'Aktif');
    $isLampau = !$isAktif;

    if ($isAktif) {
        $countAktif++;
    } else {
        $countLampau++;
        if (!$isComplete) {
            $countBelumLengkapLampau++;
        }
    }

    if ($isComplete) {
        $countLengkap++;
    } else {
        $countBelumLengkap++;
    }

    $k['uploaded_count'] = $uploadedCount;
    $k['percentage'] = $percentage;
    $k['missing_count'] = $missingCount;
    $k['is_complete'] = $isComplete;
    $k['is_aktif'] = $isAktif;
    $k['is_lampau'] = $isLampau;
    $k['kategori_periode'] = $isAktif ? 'Aktif' : 'Lampau';

    $allKelas[] = $k;
}

// Parameter Filter & Sortir
$tab = trim((string)($_GET['tab'] ?? 'semua')); // 'semua', 'aktif', 'lampau'
$filterTa = trim((string)($_GET['ta'] ?? 'semua'));
$filterSem = trim((string)($_GET['semester'] ?? 'semua'));
$filterStatus = trim((string)($_GET['status'] ?? 'semua')); // 'semua', 'belum_lengkap', 'lengkap'
$sort = trim((string)($_GET['sort'] ?? 'ta_desc')); // 'ta_desc', 'ta_asc', 'nama_asc', 'nama_desc', 'kode_asc', 'progress_asc', 'progress_desc'
$view = trim((string)($_GET['view'] ?? 'cards')); // 'cards', 'table'

// Validasi Tab
if (!in_array($tab, ['semua', 'aktif', 'lampau'], true)) {
    $tab = 'semua';
}
if (!in_array($view, ['cards', 'table'], true)) {
    $view = 'cards';
}

// Proses Filtering
$filteredKelas = array_filter($allKelas, function($item) use ($tab, $filterTa, $filterSem, $filterStatus) {
    // Filter Tab (Aktif vs Lampau)
    if ($tab === 'aktif' && !$item['is_aktif']) return false;
    if ($tab === 'lampau' && !$item['is_lampau']) return false;

    // Filter Tahun Ajaran
    if ($filterTa !== 'semua') {
        if ((string)$item['id_ta'] !== $filterTa && (string)$item['tahun'] !== $filterTa) {
            return false;
        }
    }

    // Filter Semester
    if ($filterSem !== 'semua') {
        if (strcasecmp($item['semester'], $filterSem) !== 0) {
            return false;
        }
    }

    // Filter Status Kelengkapan
    if ($filterStatus === 'belum_lengkap' && $item['is_complete']) return false;
    if ($filterStatus === 'lengkap' && !$item['is_complete']) return false;

    return true;
});

// Proses Sortir
usort($filteredKelas, function($a, $b) use ($sort) {
    switch ($sort) {
        case 'ta_asc':
            return ($a['id_ta'] <=> $b['id_ta']) ?: strcasecmp($a['nama_mk'], $b['nama_mk']);
        case 'ta_desc':
            return ($b['id_ta'] <=> $a['id_ta']) ?: strcasecmp($a['nama_mk'], $b['nama_mk']);
        case 'nama_asc':
            return strcasecmp($a['nama_mk'], $b['nama_mk']);
        case 'nama_desc':
            return strcasecmp($b['nama_mk'], $a['nama_mk']);
        case 'kode_asc':
            return strcasecmp($a['kode_mk'], $b['kode_mk']);
        case 'progress_asc':
            return ($a['percentage'] <=> $b['percentage']) ?: strcasecmp($a['nama_mk'], $b['nama_mk']);
        case 'progress_desc':
            return ($b['percentage'] <=> $a['percentage']) ?: strcasecmp($a['nama_mk'], $b['nama_mk']);
        default:
            return ($b['id_ta'] <=> $a['id_ta']) ?: strcasecmp($a['nama_mk'], $b['nama_mk']);
    }
});

// Helper URL Generator untuk mempertahankan parameter
if (!function_exists('build_url')) {
    function build_url(array $params = []): string {
        $query = array_merge($_GET, $params);
        // Hapus parameter kosong/default
        foreach ($query as $k => $v) {
            if ($v === '' || $v === null) unset($query[$k]);
        }
        return url('matkul_saya.php') . (!empty($query) ? '?' . http_build_query($query) : '');
    }
}

$hasActiveFilters = ($filterTa !== 'semua' || $filterSem !== 'semua' || $filterStatus !== 'semua' || $tab !== 'semua');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header Halaman & Badge Periode Aktif -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">Mata Kuliah Saya</h1>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            T.A. Aktif: <?= e($activeTa['tahun'] ?? '-') ?> <?= $activeSemester ? '• ' . e($activeSemester['jenis_semester']) : '' ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">
                        Daftar kelas perkuliahan Anda. Mata kuliah dari periode sebelumnya otomatis dikelompokkan ke <strong>Mata Kuliah Lampau</strong> dan ruang unggah tetap terbuka untuk melengkapi berkas.
                    </p>
                </div>

                <!-- Tampilan Switcher (Cards vs Table) -->
                <div class="flex items-center gap-2 bg-white p-1 rounded-2xl border border-slate-200 shadow-sm self-start md:self-auto">
                    <a href="<?= build_url(['view' => 'cards']) ?>"
                       class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-2 <?= $view === 'cards' ? 'bg-maritime-700 text-white shadow' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <i data-lucide="layout-grid" class="w-4 h-4"></i>
                        <span>Kartu Grid</span>
                    </a>
                    <a href="<?= build_url(['view' => 'table']) ?>"
                       class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-2 <?= $view === 'table' ? 'bg-maritime-700 text-white shadow' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                        <i data-lucide="table" class="w-4 h-4"></i>
                        <span>Tabel Rinci</span>
                    </a>
                </div>
            </div>

            <!-- Kartu Ringkasan Statistik -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Kelas -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Penugasan</p>
                        <p class="text-2xl font-heading font-extrabold text-slate-900 mt-1"><?= $countTotal ?></p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Semua Tahun Akademik</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-maritime-700 flex items-center justify-center shrink-0">
                        <i data-lucide="book-open" class="w-6 h-6"></i>
                    </div>
                </div>

                <!-- Kelas Periode Aktif -->
                <a href="<?= build_url(['tab' => 'aktif']) ?>" class="bg-white p-4 sm:p-5 rounded-2xl border <?= $tab === 'aktif' ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200' ?> shadow-sm hover:shadow-md transition flex items-center justify-between group">
                    <div>
                        <p class="text-[11px] font-semibold text-emerald-700 uppercase tracking-wider">Periode Aktif</p>
                        <p class="text-2xl font-heading font-extrabold text-emerald-800 mt-1"><?= $countAktif ?></p>
                        <p class="text-[10px] text-emerald-600/80 mt-0.5">T.A. <?= e($activeTa['tahun'] ?? '-') ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition">
                        <i data-lucide="calendar-check" class="w-6 h-6"></i>
                    </div>
                </a>

                <!-- Kelas Lampau (Arsip) -->
                <a href="<?= build_url(['tab' => 'lampau']) ?>" class="bg-white p-4 sm:p-5 rounded-2xl border <?= $tab === 'lampau' ? 'border-amber-500 ring-2 ring-amber-100' : 'border-slate-200' ?> shadow-sm hover:shadow-md transition flex items-center justify-between group">
                    <div>
                        <p class="text-[11px] font-semibold text-amber-700 uppercase tracking-wider">Mata Kuliah Lampau</p>
                        <p class="text-2xl font-heading font-extrabold text-amber-900 mt-1"><?= $countLampau ?></p>
                        <p class="text-[10px] text-amber-600/80 mt-0.5">Periode Terdahulu (Arsip)</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition">
                        <i data-lucide="archive" class="w-6 h-6"></i>
                    </div>
                </a>

                <!-- Perlu Dilengkapi (< 100%) -->
                <a href="<?= build_url(['status' => 'belum_lengkap']) ?>" class="bg-white p-4 sm:p-5 rounded-2xl border <?= $filterStatus === 'belum_lengkap' ? 'border-rose-500 ring-2 ring-rose-100' : 'border-slate-200' ?> shadow-sm hover:shadow-md transition flex items-center justify-between group">
                    <div>
                        <p class="text-[11px] font-semibold text-rose-700 uppercase tracking-wider">Perlu Dilengkapi</p>
                        <p class="text-2xl font-heading font-extrabold text-rose-700 mt-1"><?= $countBelumLengkap ?></p>
                        <p class="text-[10px] text-rose-500 mt-0.5">
                            <?= $countBelumLengkapLampau > 0 ? $countBelumLengkapLampau . ' berkas di matkul lampau' : 'Belum 100% lengkap' ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition">
                        <i data-lucide="alert-circle" class="w-6 h-6"></i>
                    </div>
                </a>
            </div>

            <!-- Tab Kategori Periode -->
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
                <a href="<?= build_url(['tab' => 'semua']) ?>"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap <?= $tab === 'semua' ? 'bg-maritime-700 text-white shadow-sm' : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200 hover:bg-slate-100' ?>">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    <span>Semua Mata Kuliah</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] <?= $tab === 'semua' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' ?>">
                        <?= $countTotal ?>
                    </span>
                </a>

                <a href="<?= build_url(['tab' => 'aktif']) ?>"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap <?= $tab === 'aktif' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200 hover:bg-slate-100' ?>">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    <span>Periode Aktif (<?= e($activeTa['tahun'] ?? '-') ?>)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] <?= $tab === 'aktif' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' ?>">
                        <?= $countAktif ?>
                    </span>
                </a>

                <a href="<?= build_url(['tab' => 'lampau']) ?>"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 whitespace-nowrap <?= $tab === 'lampau' ? 'bg-amber-700 text-white shadow-sm' : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200 hover:bg-slate-100' ?>">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                    <span>Mata Kuliah Lampau</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] <?= $tab === 'lampau' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' ?>">
                        <?= $countLampau ?>
                    </span>
                    <?php if ($countBelumLengkapLampau > 0): ?>
                    <span class="w-2 h-2 rounded-full bg-rose-500" title="Terdapat <?= $countBelumLengkapLampau ?> kelas lampau belum lengkap"></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Filter & Sort Control Bar -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4">
                <form method="GET" action="<?= url('matkul_saya.php') ?>" class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Preserved State -->
                    <input type="hidden" name="tab" value="<?= e($tab) ?>">
                    <input type="hidden" name="view" value="<?= e($view) ?>">

                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:flex lg:flex-wrap items-center gap-3 flex-1">
                        <!-- Filter Tahun Akademik -->
                        <div class="flex-1 min-w-[170px]">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                <i data-lucide="calendar" class="w-3 h-3 inline mr-1 text-slate-400"></i> Tahun Akademik
                            </label>
                            <select name="ta" onchange="this.form.submit()" class="w-full text-xs font-medium rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:border-maritime-700 focus:ring-maritime-700 py-2 px-3">
                                <option value="semua" <?= $filterTa === 'semua' ? 'selected' : '' ?>>Semua Tahun Akademik</option>
                                <?php foreach ($taOptions as $ta): ?>
                                <option value="<?= (int)$ta['id_ta'] ?>" <?= $filterTa === (string)$ta['id_ta'] ? 'selected' : '' ?>>
                                    T.A. <?= e($ta['tahun']) ?> <?= $ta['status'] === 'Aktif' ? '(Aktif)' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Filter Semester -->
                        <div class="flex-1 min-w-[150px]">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                <i data-lucide="bookmark" class="w-3 h-3 inline mr-1 text-slate-400"></i> Semester
                            </label>
                            <select name="semester" onchange="this.form.submit()" class="w-full text-xs font-medium rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:border-maritime-700 focus:ring-maritime-700 py-2 px-3">
                                <option value="semua" <?= $filterSem === 'semua' ? 'selected' : '' ?>>Semua Semester</option>
                                <?php foreach ($semOptions as $sem): ?>
                                <option value="<?= $sem ?>" <?= $filterSem === $sem ? 'selected' : '' ?>>Semester <?= $sem ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Filter Status Kelengkapan -->
                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                <i data-lucide="check-square" class="w-3 h-3 inline mr-1 text-slate-400"></i> Kelengkapan Berkas
                            </label>
                            <select name="status" onchange="this.form.submit()" class="w-full text-xs font-medium rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:border-maritime-700 focus:ring-maritime-700 py-2 px-3">
                                <option value="semua" <?= $filterStatus === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                                <option value="belum_lengkap" <?= $filterStatus === 'belum_lengkap' ? 'selected' : '' ?>>Perlu Dilengkapi (&lt; 100%)</option>
                                <option value="lengkap" <?= $filterStatus === 'lengkap' ? 'selected' : '' ?>>Lengkap 100% (28 Dokumen)</option>
                            </select>
                        </div>

                        <!-- Menu Sortir -->
                        <div class="flex-1 min-w-[190px]">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                <i data-lucide="arrow-down-up" class="w-3 h-3 inline mr-1 text-slate-400"></i> Urutkan Berdasarkan
                            </label>
                            <select name="sort" onchange="this.form.submit()" class="w-full text-xs font-medium rounded-xl border-slate-200 bg-slate-50 focus:bg-white focus:border-maritime-700 focus:ring-maritime-700 py-2 px-3">
                                <option value="ta_desc" <?= $sort === 'ta_desc' ? 'selected' : '' ?>>T.A. Terbaru &darr;</option>
                                <option value="ta_asc" <?= $sort === 'ta_asc' ? 'selected' : '' ?>>T.A. Terlama &uarr;</option>
                                <option value="nama_asc" <?= $sort === 'nama_asc' ? 'selected' : '' ?>>Nama MK (A &rarr; Z)</option>
                                <option value="nama_desc" <?= $sort === 'nama_desc' ? 'selected' : '' ?>>Nama MK (Z &rarr; A)</option>
                                <option value="kode_asc" <?= $sort === 'kode_asc' ? 'selected' : '' ?>>Kode MK (A &rarr; Z)</option>
                                <option value="progress_asc" <?= $sort === 'progress_asc' ? 'selected' : '' ?>>Kelengkapan Terendah (Prioritas)</option>
                                <option value="progress_desc" <?= $sort === 'progress_desc' ? 'selected' : '' ?>>Kelengkapan Tertinggi</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tombol Reset Filter -->
                    <?php if ($hasActiveFilters): ?>
                    <div class="flex items-center gap-2 self-end lg:self-center pt-2 lg:pt-4">
                        <a href="<?= url('matkul_saya.php?view=' . $view) ?>"
                           class="px-3.5 py-2 text-xs font-bold text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-xl border border-rose-200 transition flex items-center gap-1.5 whitespace-nowrap">
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                            Reset Filter
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Banner Penjelasan untuk Kelompok Mata Kuliah Lampau -->
            <?php if ($tab === 'lampau' || ($countBelumLengkapLampau > 0 && $tab === 'semua')): ?>
            <div class="p-4 rounded-2xl bg-amber-50/90 border border-amber-200 text-amber-900 flex items-start gap-3 shadow-sm">
                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5">
                    <i data-lucide="info" class="w-4 h-4"></i>
                </div>
                <div class="flex-1 text-xs">
                    <h4 class="font-bold text-amber-900 text-xs">Akses Ruang Unggah Mata Kuliah Lampau Tetap Aktif</h4>
                    <p class="text-amber-800 mt-0.5 leading-relaxed">
                        Saat Administrator mengaktifkan Tahun Ajaran Baru, mata kuliah periode sebelumnya secara otomatis terkelompokkan ke dalam <strong>Mata Kuliah Lampau</strong>. Anda tetap dapat membuka ruang unggah untuk melengkapi sisa dokumen instrumen perkuliahan yang belum lengkap demi pemenuhan dokumen akreditasi.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- KONTEN: JIKA DATA DITEMUKAN -->
            <?php if (!empty($filteredKelas)): ?>

                <!-- TAMPILAN 1: TABEL RINCI (TABLE VIEW) -->
                <?php if ($view === 'table'): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
                        <div class="flex items-center gap-2">
                            <i data-lucide="list-ordered" class="w-4 h-4 text-maritime-700"></i>
                            <h3 class="font-heading font-bold text-sm text-slate-800">Tabel Mata Kuliah & Sortir Semester</h3>
                            <span class="text-[11px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-slate-200">
                                Menampilkan <?= count($filteredKelas) ?> kelas
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400">Klik tombol "Buka Ruang Unggah" untuk mengunggah atau melengkapi berkas.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs text-left">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="py-3.5 px-4 text-center w-12">No</th>
                                    <th class="py-3.5 px-4">
                                        <a href="<?= build_url(['sort' => $sort === 'kode_asc' ? 'ta_desc' : 'kode_asc']) ?>" class="hover:text-maritime-700 flex items-center gap-1">
                                            Kode MK
                                            <i data-lucide="chevrons-up-down" class="w-3 h-3 text-slate-400"></i>
                                        </a>
                                    </th>
                                    <th class="py-3.5 px-4 min-w-[220px]">
                                        <a href="<?= build_url(['sort' => $sort === 'nama_asc' ? 'nama_desc' : 'nama_asc']) ?>" class="hover:text-maritime-700 flex items-center gap-1">
                                            Nama Mata Kuliah
                                            <i data-lucide="chevrons-up-down" class="w-3 h-3 text-slate-400"></i>
                                        </a>
                                    </th>
                                    <th class="py-3.5 px-4 text-center">
                                        <a href="<?= build_url(['sort' => $sort === 'ta_desc' ? 'ta_asc' : 'ta_desc']) ?>" class="hover:text-maritime-700 inline-flex items-center gap-1">
                                            Tahun Akademik
                                            <i data-lucide="chevrons-up-down" class="w-3 h-3 text-slate-400"></i>
                                        </a>
                                    </th>
                                    <th class="py-3.5 px-4 text-center">Semester</th>
                                    <th class="py-3.5 px-4 text-center">Kategori Periode</th>
                                    <th class="py-3.5 px-4 min-w-[180px]">
                                        <a href="<?= build_url(['sort' => $sort === 'progress_asc' ? 'progress_desc' : 'progress_asc']) ?>" class="hover:text-maritime-700 flex items-center gap-1">
                                            Progress Instrumen
                                            <i data-lucide="chevrons-up-down" class="w-3 h-3 text-slate-400"></i>
                                        </a>
                                    </th>
                                    <th class="py-3.5 px-4 text-center">Status Kelengkapan</th>
                                    <th class="py-3.5 px-4 text-center w-40">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($filteredKelas as $idx => $k): ?>
                                <tr class="hover:bg-slate-50/80 transition <?= $k['is_lampau'] ? 'bg-amber-50/20' : '' ?>">
                                    <td class="py-3 px-4 text-center font-mono text-slate-400 font-semibold"><?= $idx + 1 ?></td>
                                    <td class="py-3 px-4">
                                        <span class="font-mono font-bold px-2 py-0.5 rounded bg-blue-50 text-maritime-700 border border-blue-100 text-[11px]">
                                            <?= e($k['kode_mk']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-900 text-xs"><?= e($k['nama_mk']) ?></div>
                                        <div class="text-[11px] text-slate-400 mt-0.5"><?= (int)$k['sks'] ?> SKS</div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="font-medium text-slate-800"><?= e($k['tahun']) ?></span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2 py-0.5 rounded-md font-semibold text-[11px] <?= $k['semester'] === 'Ganjil' ? 'bg-indigo-50 text-indigo-700' : ($k['semester'] === 'Genap' ? 'bg-cyan-50 text-cyan-700' : 'bg-purple-50 text-purple-700') ?>">
                                            <?= e($k['semester']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($k['is_aktif']): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Periode Aktif
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            <i data-lucide="archive" class="w-3 h-3 text-amber-600"></i>
                                            Matkul Lampau
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center justify-between text-[11px] mb-1">
                                            <span class="text-slate-500 font-medium"><?= $k['uploaded_count'] ?>/<?= TOTAL_STANDAR_FILE ?> Berkas</span>
                                            <span class="font-bold <?= $k['percentage'] === 100 ? 'text-emerald-600' : ($k['percentage'] >= 50 ? 'text-amber-600' : 'text-rose-600') ?>">
                                                <?= $k['percentage'] ?>%
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                            <div class="h-full rounded-full <?= $k['percentage'] === 100 ? 'bg-emerald-500' : ($k['percentage'] >= 50 ? 'bg-amber-500' : 'bg-rose-500') ?>"
                                                 style="width: <?= $k['percentage'] ?>%;"></div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($k['is_complete']): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i data-lucide="check-circle" class="w-3 h-3 text-emerald-600"></i>
                                            Lengkap (100%)
                                        </span>
                                        <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200" title="Kurang <?= $k['missing_count'] ?> berkas untuk memenuhi 28 standar">
                                            <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i>
                                            Kurang <?= $k['missing_count'] ?> Berkas
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <a href="<?= url('upload_instrumen.php?id_pengampu=' . $k['id_pengampu']) ?>"
                                           class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-xl font-bold text-xs transition shadow-sm <?= $k['is_complete'] ? 'bg-slate-100 hover:bg-slate-200 text-slate-700' : 'bg-maritime-700 hover:bg-maritime-800 text-white' ?>">
                                            <i data-lucide="folder-open" class="w-3.5 h-3.5"></i>
                                            <?= $k['is_complete'] ? 'Lihat Berkas' : 'Unggah Berkas' ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAMPILAN 2: KARTU GRID (GRID VIEW) -->
                <?php else: ?>

                    <!-- Jika Tab Semua dan tanpa filter spesifik, kelompokkan secara visual dengan Section Divider -->
                    <?php
                    $isGroupedView = ($tab === 'semua' && $filterTa === 'semua' && $filterSem === 'semua' && $filterStatus === 'semua');
                    $kelasAktifList = array_values(array_filter($filteredKelas, fn($k) => $k['is_aktif']));
                    $kelasLampauList = array_values(array_filter($filteredKelas, fn($k) => $k['is_lampau']));
                    ?>

                    <?php if ($isGroupedView): ?>

                        <!-- SECTION 1: KELOMPOK PERIODE AKTIF -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <h2 class="font-heading font-extrabold text-lg text-slate-900">
                                        Mata Kuliah Periode Aktif
                                    </h2>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        T.A. <?= e($activeTa['tahun'] ?? '-') ?> <?= $activeSemester ? '• ' . e($activeSemester['jenis_semester']) : '' ?> (<?= count($kelasAktifList) ?> Kelas)
                                    </span>
                                </div>
                                <span class="text-xs text-slate-400">Kelas yang sedang berlangsung semester ini</span>
                            </div>

                            <?php if (!empty($kelasAktifList)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                <?php foreach ($kelasAktifList as $k): ?>
                                    <?php include __DIR__ . '/includes/matkul_card_partial.php'; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="p-6 bg-white rounded-2xl border border-dashed border-slate-200 text-center">
                                <p class="text-xs text-slate-500">Belum ada mata kuliah yang ditugaskan kepada Anda pada Tahun Ajaran Aktif (<?= e($activeTa['tahun'] ?? '-') ?>).</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- SECTION 2: KELOMPOK MATA KULIAH LAMPAU (ARSIP) -->
                        <div class="space-y-4 pt-4">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 pb-2 gap-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                                    <h2 class="font-heading font-extrabold text-lg text-slate-900">
                                        Kelompok Mata Kuliah Lampau (Arsip Perkuliahan)
                                    </h2>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        <?= count($kelasLampauList) ?> Kelas
                                    </span>
                                </div>
                                <span class="text-xs text-amber-700 font-medium flex items-center gap-1">
                                    <i data-lucide="folder-check" class="w-3.5 h-3.5"></i>
                                    Dosen masih dapat melengkapi instrumen yang belum lengkap
                                </span>
                            </div>

                            <?php if (!empty($kelasLampauList)): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                <?php foreach ($kelasLampauList as $k): ?>
                                    <?php include __DIR__ . '/includes/matkul_card_partial.php'; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="p-6 bg-white rounded-2xl border border-dashed border-slate-200 text-center">
                                <p class="text-xs text-slate-500">Tidak ada riwayat mata kuliah dari periode lampau.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                    <?php else: ?>
                        <!-- Tampilan Grid Standar (Jika Filter Terpasang atau Tab Khusus Dipilih) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                            <?php foreach ($filteredKelas as $k): ?>
                                <?php include __DIR__ . '/includes/matkul_card_partial.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

            <?php else: ?>
                <!-- Empty State -->
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-lg mx-auto shadow-sm">
                    <div class="w-14 h-14 bg-slate-100 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="book-x" class="w-7 h-7"></i>
                    </div>
                    <h3 class="font-heading font-bold text-base text-slate-800">
                        <?= $hasActiveFilters ? 'Tidak Ada Mata Kuliah yang Cocok' : 'Belum Ada Kelas yang Ditugaskan' ?>
                    </h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        <?= $hasActiveFilters
                            ? 'Tidak ditemukan kelas perkuliahan dengan filter yang Anda tentukan. Silakan ubah filter atau lakukan reset.'
                            : 'Akun Anda belum memiliki penugasan mata kuliah pada periode ini. Silakan hubungi Koordinator Program Studi atau Administrator GKMP.' ?>
                    </p>

                    <div class="mt-4 flex items-center justify-center gap-2">
                        <?php if ($hasActiveFilters): ?>
                        <a href="<?= url('matkul_saya.php?view=' . $view) ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-maritime-700 text-white font-bold text-xs rounded-xl hover:bg-maritime-800 transition shadow-sm">
                            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                            Reset Filter Pencarian
                        </a>
                        <?php elseif (is_admin()): ?>
                        <a href="<?= url('assign_pengampu.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-maritime-700 text-white font-bold text-xs rounded-xl hover:bg-maritime-800 transition shadow-sm">
                            <i data-lucide="user-plus" class="w-4 h-4"></i>
                            Buka Menu Penugasan Pengampu
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
