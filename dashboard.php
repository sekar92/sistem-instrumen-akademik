<?php
declare(strict_types=1);

/**
 * SIMIA - Dashboard Utama
 * Acuan: skills.md Bagian 4, 5.2, 5.3 & PRD SIMIA Bagian 4.2
 */

require_once __DIR__ . '/includes/functions.php';
require_login();

$user = auth_user();
$branding = get_branding_info();
$pageTitle = 'Dashboard Utama';
$pdo = Database::getConnection();

// Ambil list semua Tahun Ajaran untuk Filter
$taList = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY id_ta DESC")->fetchAll();

// Filter TA & Semester
$activeTaSem = get_active_ta_semester();
$filterTa = $_GET['filter_ta'] ?? ($activeTaSem['ta']['id_ta'] ?? 'ALL');
$filterSem = $_GET['filter_sem'] ?? ($activeTaSem['semester']['jenis_semester'] ?? 'ALL');

// Query Statistik Berdasarkan Filter
$mkSql = "SELECT COUNT(*) FROM mata_kuliah WHERE 1=1";
$pengampuSql = "SELECT COUNT(*) FROM pengampu WHERE 1=1";
$dosenSql = "SELECT COUNT(DISTINCT id_user) FROM pengampu WHERE 1=1";
$insSql = "
    SELECT COUNT(*) FROM instrumen i
    JOIN pengampu p ON i.id_pengampu = p.id_pengampu
    WHERE 1=1
";
$params = [];

if ($filterTa !== 'ALL') {
    $mkSql .= " AND id_ta = ?";
    $pengampuSql .= " AND id_ta = ?";
    $dosenSql .= " AND id_ta = ?";
    $insSql .= " AND p.id_ta = ?";
    $params[] = $filterTa;
}

if ($filterSem !== 'ALL') {
    $mkSql .= " AND semester = ?";
    $pengampuSql .= " AND semester = ?";
    $insSql .= " AND p.semester = ?";
    $params[] = $filterSem;
}

// Eksekusi count
$stmtMk = $pdo->prepare($mkSql); $stmtMk->execute($params); $totalMk = (int)$stmtMk->fetchColumn();
$stmtPengampu = $pdo->prepare($pengampuSql); $stmtPengampu->execute($params); $totalPengampu = (int)$stmtPengampu->fetchColumn();
$stmtDosen = $pdo->prepare($dosenSql); $stmtDosen->execute(array_slice($params, 0, ($filterTa !== 'ALL' ? 1 : 0))); $totalDosen = (int)$stmtDosen->fetchColumn();
$stmtIns = $pdo->prepare($insSql); $stmtIns->execute($params); $totalInstrumen = (int)$stmtIns->fetchColumn();

// Distribusi Kategori Instrumen (Chart.js)
$chartSql = "
    SELECT i.kategori, COUNT(*) as cnt
    FROM instrumen i
    JOIN pengampu p ON i.id_pengampu = p.id_pengampu
    WHERE 1=1
";
if ($filterTa !== 'ALL') $chartSql .= " AND p.id_ta = " . (int)$filterTa;
if ($filterSem !== 'ALL') $chartSql .= " AND p.semester = " . $pdo->quote($filterSem);
$chartSql .= " GROUP BY i.kategori ORDER BY cnt DESC";

$chartData = $pdo->query($chartSql)->fetchAll();
$chartLabels = [];
$chartValues = [];
foreach ($chartData as $c) {
    $chartLabels[] = $c['kategori'];
    $chartValues[] = (int)$c['cnt'];
}

// Papan Informasi Terbaru (Microblog)
$infoList = $pdo->query("SELECT * FROM informasi ORDER BY id_info DESC LIMIT 4")->fetchAll();

// Pengingat Tenggat Waktu (Deadline Cards)
$deadlines = $pdo->query("
    SELECT * FROM informasi 
    WHERE deadline IS NOT NULL AND deadline != '' AND deadline != '-' 
    ORDER BY deadline ASC LIMIT 3
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <!-- Left Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Canvas -->
    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-7xl mx-auto space-y-8">

            <!-- 1. Banner Hero Selamat Datang -->
            <div class="relative overflow-hidden rounded-3xl bg-oceanic-gradient text-white p-6 sm:p-8 shadow-xl">
                <!-- SVG Wave Decoration -->
                <svg class="absolute -right-10 -bottom-10 w-96 h-96 opacity-10 pointer-events-none" viewBox="0 0 200 200" fill="none">
                    <path d="M0,100 C50,150 150,50 200,100 L200,200 L0,200 Z" fill="#FFFFFF"/>
                </svg>

                <div class="relative z-10 max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-950/70 border border-cyan-500/30 text-cyan-300 text-xs font-semibold mb-3">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5 text-cyan-400"></i>
                        <span><?= e($branding['app_name']) ?></span>
                    </div>

                    <h1 class="font-heading font-extrabold text-2xl sm:text-3xl lg:text-4xl tracking-tight leading-tight">
                        Selamat Datang, <?= e($user['nama']) ?>!
                    </h1>
                    
                    <p class="mt-2 text-xs sm:text-sm text-cyan-100/90 leading-relaxed font-normal">
                        Sistem manajemen kelengkapan instrumen perkuliahan, silabus, dan akreditasi pada 
                        <span class="font-semibold text-white"><?= e($branding['study_program']) ?></span>.
                        Pantau progres pengumpulan berkas dan kelola data perkuliahan secara terpusat.
                    </p>

                    <!-- Quick Action Chips -->
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="<?= url('matkul_saya.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs transition shadow-md">
                            <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                            Unggah Instrumen Saya
                        </a>
                        <?php if (is_admin()): ?>
                        <a href="<?= url('rekap_progress.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 transition">
                            <i data-lucide="activity" class="w-4 h-4"></i>
                            Pantau Rekapitulasi
                        </a>
                        <a href="<?= url('unduh_berkas.php') ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 transition">
                            <i data-lucide="download" class="w-4 h-4"></i>
                            Unduh Arsip ZIP
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 2. Kartu Pengingat Tenggat Waktu (Deadline Reminder Cards) -->
            <?php if (!empty($deadlines)): ?>
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-heading font-bold text-base text-slate-800 flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-maritime-700"></i>
                        Tenggat Waktu & Batas Akhir Unggah
                    </h2>
                    <a href="<?= url('informasi.php') ?>" class="text-xs text-maritime-700 hover:underline font-semibold">
                        Semua Pengumuman →
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php 
                    $now = time();
                    foreach ($deadlines as $d): 
                        $deadlineTime = strtotime($d['deadline']);
                        $diffHours = ($deadlineTime - $now) / 3600;
                        $diffDays = $diffHours / 24;

                        if ($diffHours <= 24 && $diffHours >= -24) {
                            // Urgensi Tinggi (<= 24 Jam)
                            $cardStyle = 'border-rose-300 bg-rose-50/70 text-rose-900';
                            $badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-600 text-white flex items-center gap-1 animate-pulse"><i data-lucide="alert-triangle" class="w-3 h-3"></i> Hari Ini / Mendesak</span>';
                        } elseif ($diffDays <= 3 && $diffDays > 0) {
                            // Urgensi Sedang (<= 3 Hari)
                            $cardStyle = 'border-amber-300 bg-amber-50/70 text-amber-900';
                            $badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-slate-950 flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> Segera Berakhir</span>';
                        } else {
                            // Urgensi Santai (> 3 Hari)
                            $cardStyle = 'border-blue-200 bg-blue-50/60 text-maritime-800';
                            $badge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-maritime-700">Tersedia Waktu</span>';
                        }
                    ?>
                    <div class="p-4 rounded-2xl border shadow-sm flex flex-col justify-between <?= $cardStyle ?>">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <?= $badge ?>
                                <span class="text-[11px] font-mono font-semibold"><?= tanggal_indo($d['deadline']) ?></span>
                            </div>
                            <h3 class="font-bold text-xs leading-snug line-clamp-2"><?= e($d['judul']) ?></h3>
                        </div>
                        <div class="mt-3 pt-2 border-t border-black/10 flex items-center justify-between text-[11px]">
                            <span class="text-slate-500">Oleh: <?= e($d['pembuat_nama']) ?></span>
                            <a href="<?= url('informasi.php') ?>" class="font-bold hover:underline">Detail</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Kondisi Tuntas (Empty State) -->
            <div class="p-4 rounded-2xl border border-emerald-200 bg-emerald-50/60 text-emerald-900 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                    <i data-lucide="check-check" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold text-xs">Luar Biasa! Tidak Ada Tenggat Waktu Tertunda</h3>
                    <p class="text-[11px] text-emerald-700/90 mt-0.5">Semua proses jadwal akademik saat ini berjalan normal dan tertib.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- 3. Filter Bar & Key Statistics Cards -->
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="font-heading font-bold text-lg text-slate-800">Ringkasan Statistik</h2>
                        <p class="text-xs text-slate-500">Kalkulasi real-time berkas dan aktivitas pengampu</p>
                    </div>

                    <!-- Filter Form -->
                    <form method="GET" action="<?= url('dashboard.php') ?>" class="flex flex-wrap items-center gap-2 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm">
                        <!-- Select TA -->
                        <select name="filter_ta" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                            <option value="ALL" <?= $filterTa === 'ALL' ? 'selected' : '' ?>>Semua T.A.</option>
                            <?php foreach ($taList as $t): ?>
                                <option value="<?= $t['id_ta'] ?>" <?= (string)$filterTa === (string)$t['id_ta'] ? 'selected' : '' ?>>
                                    T.A. <?= e($t['tahun']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Select Semester -->
                        <select name="filter_sem" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                            <option value="ALL" <?= $filterSem === 'ALL' ? 'selected' : '' ?>>Semua Semester</option>
                            <option value="Ganjil" <?= $filterSem === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                            <option value="Genap" <?= $filterSem === 'Genap' ? 'selected' : '' ?>>Genap</option>
                            <option value="Sisipan" <?= $filterSem === 'Sisipan' ? 'selected' : '' ?>>Sisipan</option>
                        </select>
                    </form>
                </div>

                <!-- 4 Kartu Statistik -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- MK -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-slate-500">Mata Kuliah</span>
                            <div class="w-9 h-9 rounded-xl bg-blue-50 text-maritime-700 flex items-center justify-center">
                                <i data-lucide="book" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <p class="font-heading font-extrabold text-2xl text-slate-900"><?= $totalMk ?></p>
                        <p class="text-[10px] text-slate-400 mt-1">Terdaftar di kurikulum</p>
                    </div>

                    <!-- Dosen Aktif -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-slate-500">Dosen Aktif</span>
                            <div class="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                                <i data-lucide="users" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <p class="font-heading font-extrabold text-2xl text-slate-900"><?= $totalDosen ?></p>
                        <p class="text-[10px] text-slate-400 mt-1">Di-assign mengajar</p>
                    </div>

                    <!-- Kelas Pengampu -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-slate-500">Total Penugasan</span>
                            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i data-lucide="layers" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <p class="font-heading font-extrabold text-2xl text-slate-900"><?= $totalPengampu ?></p>
                        <p class="text-[10px] text-slate-400 mt-1">Kelas perkuliahan</p>
                    </div>

                    <!-- Instrumen Terkumpul -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-slate-500">File Instrumen</span>
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <i data-lucide="file-check" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <p class="font-heading font-extrabold text-2xl text-emerald-600"><?= $totalInstrumen ?></p>
                        <p class="text-[10px] text-slate-400 mt-1">Telah diverifikasi</p>
                    </div>
                </div>
            </div>

            <!-- 4. Visual Chart Kategori & Papan Pengumuman Microblog -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Visual Bar/Doughnut Chart (2 Kolom) -->
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="font-heading font-bold text-sm text-slate-900">Distribusi Kategori Dokumen</h3>
                            <p class="text-xs text-slate-400">Total berkas yang diunggah per kategori standar instrumen</p>
                        </div>
                        <span class="text-xs font-bold text-maritime-700 bg-blue-50 px-2.5 py-1 rounded-lg">
                            8 Kategori Standar
                        </span>
                    </div>

                    <?php if (!empty($chartLabels)): ?>
                    <div class="h-64 relative">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <?php else: ?>
                    <div class="h-64 flex flex-col items-center justify-center text-slate-400 text-xs">
                        <i data-lucide="file-x" class="w-8 h-8 text-slate-300 mb-2"></i>
                        <p>Belum ada berkas instrumen yang diunggah pada periode filter ini.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Papan Informasi Terkini (1 Kolom) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                        <h3 class="font-heading font-bold text-sm text-slate-900 flex items-center gap-2">
                            <i data-lucide="megaphone" class="w-4 h-4 text-cyan-600"></i>
                            Papan Informasi
                        </h3>
                        <a href="<?= url('informasi.php') ?>" class="text-xs text-cyan-600 hover:underline font-semibold">
                            Lihat Semua
                        </a>
                    </div>

                    <div class="space-y-3 flex-1 overflow-y-auto">
                        <?php if (!empty($infoList)): ?>
                            <?php foreach ($infoList as $info): ?>
                            <div class="p-3 bg-slate-50 hover:bg-slate-100/80 rounded-xl border border-slate-200/70 transition">
                                <h4 class="text-xs font-bold text-slate-800 line-clamp-1"><?= e($info['judul']) ?></h4>
                                <p class="text-[11px] text-slate-600 mt-1 line-clamp-2"><?= e($info['isi']) ?></p>
                                <div class="mt-2 flex items-center justify-between text-[10px] text-slate-400">
                                    <span><?= tanggal_indo($info['tgl_posting']) ?></span>
                                    <?php if (!empty($info['deadline']) && $info['deadline'] !== '-'): ?>
                                    <span class="font-semibold text-rose-600">DL: <?= date('d M', strtotime($info['deadline'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-xs text-slate-400 py-8">Belum ada pengumuman.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        </div>

<?php if (!empty($chartLabels)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Jumlah Dokumen',
                    data: <?= json_encode($chartValues) ?>,
                    backgroundColor: [
                        '#06B6D4', '#1D4ED8', '#2563EB', '#0284C7',
                        '#38BDF8', '#0EA5E9', '#1E3A8A', '#10B981'
                    ],
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { family: 'Plus Jakarta Sans', size: 10 } },
                        grid: { color: '#F1F5F9' }
                    },
                    x: {
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10 } },
                        grid: { display: false }
                    }
                }
            }
        });
    });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
