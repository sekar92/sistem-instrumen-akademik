<?php
declare(strict_types=1);

/**
 * SIMIA - Rekapitulasi Progress Kelengkapan Instrumen & Email Reminder
 * Acuan: PRD SIMIA FR-5.1, FR-5.2 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$branding = get_branding_info();
$pageTitle = 'Rekapitulasi Progress & Monitoring';

// Ambil list semua Tahun Ajaran untuk Filter
$taList = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY id_ta DESC")->fetchAll();
$activeTaSem = get_active_ta_semester();
$filterTa = $_GET['filter_ta'] ?? ($activeTaSem['ta']['id_ta'] ?? 'ALL');
$filterSem = $_GET['filter_sem'] ?? ($activeTaSem['semester']['jenis_semester'] ?? 'ALL');

// EKSPOR CSV jika diminta
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Rekap_Progress_SIMIA_' . date('Ymd_His') . '.csv"');
    
    // Output UTF-8 BOM untuk kompatibilitas sempurna dengan Microsoft Excel (Acuan: skills.md 5.5)
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Nama Dosen', 'NIP', 'Email', 'Kode MK', 'Nama Mata Kuliah', 'SKS', 'Tahun Ajaran', 'Semester', 'Berkas Terkumpul', 'Standar Wajib', 'Persentase'], ',', '"', '\\');

    $q = "
        SELECT p.id_pengampu, p.semester, u.nama as nama_dosen, u.nip, u.email,
               m.kode_mk, m.nama_mk, m.sks, t.tahun
        FROM pengampu p
        JOIN users u ON p.id_user = u.id_user
        JOIN mata_kuliah m ON p.id_mk = m.id_mk
        JOIN tahun_ajaran t ON p.id_ta = t.id_ta
        WHERE 1=1
    ";
    $prms = [];
    if ($filterTa !== 'ALL') { $q .= " AND p.id_ta = ?"; $prms[] = $filterTa; }
    if ($filterSem !== 'ALL') { $q .= " AND p.semester = ?"; $prms[] = $filterSem; }
    $q .= " ORDER BY u.nama ASC, m.nama_mk ASC";

    $st = $pdo->prepare($q);
    $st->execute($prms);
    $rows = $st->fetchAll();

    $no = 1;
    foreach ($rows as $r) {
        $insSt = $pdo->prepare("SELECT DISTINCT sub_kategori FROM instrumen WHERE id_pengampu = ?");
        $insSt->execute([$r['id_pengampu']]);
        $cnt = count($insSt->fetchAll());
        $pct = (int)round(($cnt / TOTAL_STANDAR_FILE) * 100);
        if ($pct > 100) $pct = 100;

        fputcsv($output, [
            $no++,
            $r['nama_dosen'],
            $r['nip'],
            $r['email'] ?: '-',
            $r['kode_mk'],
            $r['nama_mk'],
            $r['sks'],
            $r['tahun'],
            $r['semester'],
            $cnt,
            TOTAL_STANDAR_FILE,
            $pct . '%'
        ], ',', '"', '\\');
    }
    fclose($output);
    exit;
}

// PENANGANAN PENGIRIMAN EMAIL REMINDER (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_single_reminder') {
        $idPengampu = (int)($_POST['id_pengampu'] ?? 0);
        
        $pStmt = $pdo->prepare("
            SELECT p.id_pengampu, p.semester, u.nama, u.email, m.kode_mk, m.nama_mk
            FROM pengampu p
            JOIN users u ON p.id_user = u.id_user
            JOIN mata_kuliah m ON p.id_mk = m.id_mk
            WHERE p.id_pengampu = ?
        ");
        $pStmt->execute([$idPengampu]);
        $target = $pStmt->fetch();

        if (!$target) {
            set_flash('error', 'Penugasan tidak ditemukan.');
            redirect('rekap_progress.php');
        }

        if (empty($target['email'])) {
            set_flash('error', "Gagal! Dosen {$target['nama']} belum memiliki alamat email yang tercatat di profil.");
            redirect('rekap_progress.php');
        }

        $insCount = (int)$pdo->query("SELECT COUNT(DISTINCT sub_kategori) FROM instrumen WHERE id_pengampu = $idPengampu")->fetchColumn();
        $pct = (int)round(($insCount / TOTAL_STANDAR_FILE) * 100);

        // Eksekusi Pengiriman Email Menggunakan SIMIA Mailer Engine
        $mailRes = send_instrument_reminder_email($target, [
            'persentase'       => $pct,
            'berkas_terkumpul' => $insCount,
            'standar_wajib'    => TOTAL_STANDAR_FILE,
            'tahun_ajaran'     => $activeTaSem['ta']['tahun'] ?? 'Tahun Berjalan',
        ]);

        if ($mailRes['success']) {
            log_activity('KIRIM_EMAIL', "Kirim email reminder kelengkapan ke {$target['nama']} ({$target['email']}) untuk MK {$target['nama_mk']}.");
            set_flash('success', "Email pengingat kelengkapan berhasil dikirimkan ke {$target['nama']} ({$target['email']}).");
        } else {
            log_activity('KIRIM_EMAIL_GAGAL', "Gagal kirim email reminder ke {$target['nama']} ({$target['email']}): {$mailRes['message']}");
            set_flash('error', "Gagal mengirim email ke {$target['nama']} ({$target['email']}): {$mailRes['message']}");
        }
        redirect('rekap_progress.php');

    } elseif ($action === 'send_mass_reminder') {
        // Broadcast ke semua pengampu yang persentasenya < 100% dan memiliki email
        $q = "
            SELECT p.id_pengampu, p.semester, u.nama, u.email, m.kode_mk, m.nama_mk
            FROM pengampu p
            JOIN users u ON p.id_user = u.id_user
            JOIN mata_kuliah m ON p.id_mk = m.id_mk
            WHERE u.email != '' AND u.email IS NOT NULL
        ";
        if ($filterTa !== 'ALL') $q .= " AND p.id_ta = " . (int)$filterTa;
        if ($filterSem !== 'ALL') $q .= " AND p.semester = " . $pdo->quote($filterSem);

        $targets = $pdo->query($q)->fetchAll();
        $sentCount = 0;
        $failCount = 0;

        foreach ($targets as $t) {
            $cnt = (int)$pdo->query("SELECT COUNT(DISTINCT sub_kategori) FROM instrumen WHERE id_pengampu = " . (int)$t['id_pengampu'])->fetchColumn();
            $pct = (int)round(($cnt / TOTAL_STANDAR_FILE) * 100);

            if ($pct < 100) {
                $mRes = send_instrument_reminder_email($t, [
                    'persentase'       => $pct,
                    'berkas_terkumpul' => $cnt,
                    'standar_wajib'    => TOTAL_STANDAR_FILE,
                    'tahun_ajaran'     => $activeTaSem['ta']['tahun'] ?? 'Tahun Berjalan',
                ]);
                if ($mRes['success']) {
                    $sentCount++;
                } else {
                    $failCount++;
                }
            }
        }

        log_activity('KIRIM_EMAIL', "Broadcast reminder massal: {$sentCount} berhasil, {$failCount} gagal.");
        if ($sentCount > 0) {
            set_flash('success', "Berhasil mengirimkan notifikasi email pengingat ke {$sentCount} dosen pengampu yang berkasnya belum lengkap.");
        } else {
            set_flash('warning', "Tidak ada email yang terkirim. " . ($failCount > 0 ? "($failCount pengiriman gagal - periksa setelan SMTP)." : "Seluruh dosen telah melengkapi berkas 100%."));
        }
        redirect('rekap_progress.php');
    }
}

// Query Data Rekapitulasi untuk Tabel
$rekapSql = "
    SELECT p.id_pengampu, p.semester, u.nama as nama_dosen, u.nip, u.email,
           m.kode_mk, m.nama_mk, m.sks, t.tahun
    FROM pengampu p
    JOIN users u ON p.id_user = u.id_user
    JOIN mata_kuliah m ON p.id_mk = m.id_mk
    JOIN tahun_ajaran t ON p.id_ta = t.id_ta
    WHERE 1=1
";
$params = [];
if ($filterTa !== 'ALL') { $rekapSql .= " AND p.id_ta = ?"; $params[] = $filterTa; }
if ($filterSem !== 'ALL') { $rekapSql .= " AND p.semester = ?"; $params[] = $filterSem; }
$rekapSql .= " ORDER BY u.nama ASC, m.nama_mk ASC";

$stmt = $pdo->prepare($rekapSql);
$stmt->execute($params);
$rekapRows = $stmt->fetchAll();

// Kalkulasi tiap baris
$dataProgress = [];
$totalBelumLengkap = 0;
foreach ($rekapRows as $row) {
    $insStmt = $pdo->prepare("SELECT DISTINCT sub_kategori FROM instrumen WHERE id_pengampu = ?");
    $insStmt->execute([$row['id_pengampu']]);
    $cnt = count($insStmt->fetchAll());
    $pct = (int)round(($cnt / TOTAL_STANDAR_FILE) * 100);
    if ($pct > 100) $pct = 100;
    if ($pct < 100) $totalBelumLengkap++;

    $row['count'] = $cnt;
    $row['persentase'] = $pct;
    $dataProgress[] = $row;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header Halaman & Aksi Cepat -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">
                        Rekapitulasi & Monitoring Kelengkapan
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Pantau persentase pemenuhan 28 dokumen wajib instrumen akademik per dosen dan mata kuliah.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Export CSV Button (UTF-8 BOM) -->
                    <a href="<?= url("rekap_progress.php?export=csv&filter_ta=$filterTa&filter_sem=$filterSem") ?>"
                       class="px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 shadow-sm transition flex items-center gap-2">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-600"></i>
                        Ekspor CSV (Excel)
                    </a>

                    <!-- Broadcast Massal Reminder Modal Button -->
                    <?php if ($totalBelumLengkap > 0): ?>
                    <form action="<?= url('rekap_progress.php') ?>" method="POST"
                          onsubmit="return confirm('Kirimkan email peringatan ke <?= $totalBelumLengkap ?> pengampu yang kelengkapannya belum mencapai 100%?')" class="inline m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_mass_reminder">
                        <button type="submit" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 rounded-xl text-xs font-bold shadow-sm transition flex items-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            Broadcast Reminder (<?= $totalBelumLengkap ?> Dosen)
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="<?= url('rekap_progress.php') ?>" class="flex flex-wrap items-center gap-3">
                    <span class="text-xs font-bold text-slate-600 flex items-center gap-1.5">
                        <i data-lucide="filter" class="w-3.5 h-3.5 text-maritime-700"></i>
                        Filter Periode:
                    </span>

                    <select name="filter_ta" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="ALL" <?= $filterTa === 'ALL' ? 'selected' : '' ?>>Semua Tahun Ajaran</option>
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
                    Menampilkan <span class="font-bold text-slate-800"><?= count($dataProgress) ?></span> data penugasan
                </div>
            </div>

            <!-- Tabel Rekapitulasi -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4 text-center w-12">No</th>
                                <th class="py-3 px-4">Dosen Pengampu</th>
                                <th class="py-3 px-4">Mata Kuliah & SKS</th>
                                <th class="py-3 px-4">Periode</th>
                                <th class="py-3 px-4 min-w-[180px]">Progres (28 File)</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($dataProgress)): ?>
                                <?php foreach ($dataProgress as $index => $row): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 text-center text-slate-400 font-mono"><?= $index + 1 ?></td>
                                    
                                    <!-- Dosen -->
                                    <td class="py-3 px-4">
                                        <p class="font-bold text-slate-900"><?= e($row['nama_dosen']) ?></p>
                                        <p class="text-[10px] text-slate-400 font-mono">NIP: <?= e($row['nip']) ?></p>
                                        <?php if (!empty($row['email'])): ?>
                                            <p class="text-[10px] text-cyan-600 truncate"><?= e($row['email']) ?></p>
                                        <?php else: ?>
                                            <span class="text-[9px] text-rose-500 italic">Email belum diisi</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- MK -->
                                    <td class="py-3 px-4">
                                        <p class="font-bold text-slate-800"><?= e($row['nama_mk']) ?></p>
                                        <span class="font-mono text-[10px] text-slate-500"><?= e($row['kode_mk']) ?> • <?= e($row['sks']) ?> SKS</span>
                                    </td>

                                    <!-- Periode -->
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                            <?= e($row['tahun']) ?> • <?= e($row['semester']) ?>
                                        </span>
                                    </td>

                                    <!-- Progres -->
                                    <td class="py-3 px-4">
                                        <div class="flex items-center justify-between text-[11px] mb-1 font-semibold">
                                            <span class="text-slate-500"><?= $row['count'] ?> / <?= TOTAL_STANDAR_FILE ?> Berkas</span>
                                            <span class="<?= $row['persentase'] === 100 ? 'text-emerald-600' : 'text-slate-800' ?>">
                                                <?= $row['persentase'] ?>%
                                            </span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div class="h-full rounded-full <?= $row['persentase'] === 100 ? 'bg-emerald-500' : ($row['persentase'] >= 50 ? 'bg-amber-500' : 'bg-rose-500') ?>"
                                                 style="width: <?= $row['persentase'] ?>%;"></div>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3 px-4 text-center">
                                        <?php if ($row['persentase'] === 100): ?>
                                            <span class="px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                Lengkap
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                                Belum Lengkap
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- Buka Ruang Unggah -->
                                            <a href="<?= url('upload_instrumen.php?id_pengampu=' . $row['id_pengampu']) ?>"
                                               title="Buka Ruang Dokumen"
                                               class="p-1.5 rounded-lg bg-blue-50 text-maritime-700 hover:bg-maritime-100 transition">
                                                <i data-lucide="folder-open" class="w-4 h-4"></i>
                                            </a>

                                            <!-- Kirim Reminder Single -->
                                            <?php if ($row['persentase'] < 100 && !empty($row['email'])): ?>
                                            <form action="<?= url('rekap_progress.php') ?>" method="POST"
                                                  onsubmit="return confirm('Kirim email peringatan ke <?= e($row['nama_dosen']) ?>?')" class="inline m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="send_single_reminder">
                                                <input type="hidden" name="id_pengampu" value="<?= $row['id_pengampu'] ?>">
                                                <button type="submit" title="Kirim Email Reminder"
                                                        class="p-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                                                    <i data-lucide="mail" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
