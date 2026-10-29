<?php
declare(strict_types=1);

/**
 * SIMIA - Log Aktivitas & Audit Trail Sistem
 * Acuan: skills.md Bagian 5.5
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Audit Log Aktivitas Sistem';

// Filter
$filterTipe = $_GET['tipe'] ?? 'ALL';
$search = trim((string)($_GET['q'] ?? ''));

// EKSPOR CSV DENGAN UTF-8 BOM
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Audit_Log_SIMIA_' . date('Ymd_His') . '.csv"');

    // UTF-8 BOM
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID Log', 'Waktu', 'Pengguna', 'NIP', 'Peran', 'Aktivitas', 'Deskripsi', 'IP Address', 'Simulasi', 'ID Admin Simulasi', 'User-Agent'], ',', '"', '\\');

    $q = "SELECT * FROM log_aktivitas WHERE 1=1";
    $prms = [];
    if ($filterTipe !== 'ALL') { $q .= " AND tipe_aktivitas = ?"; $prms[] = $filterTipe; }
    if (!empty($search)) {
        $q .= " AND (nama LIKE ? OR username LIKE ? OR deskripsi LIKE ?)";
        $prms[] = "%$search%"; $prms[] = "%$search%"; $prms[] = "%$search%";
    }
    $q .= " ORDER BY id_log DESC LIMIT 5000";

    $st = $pdo->prepare($q);
    $st->execute($prms);
    while ($row = $st->fetch()) {
        fputcsv($output, [
            $row['id_log'],
            $row['created_at'],
            $row['nama'],
            $row['username'],
            $row['role'],
            $row['tipe_aktivitas'],
            $row['deskripsi'],
            $row['ip_address'],
            $row['is_impersonated'] ? 'YA' : 'TIDAK',
            $row['impersonator_id'] ?: '-',
            $row['user_agent']
        ], ',', '"', '\\');
    }
    fclose($output);
    exit;
}

// Query Tampil Tabel
$sql = "SELECT * FROM log_aktivitas WHERE 1=1";
$params = [];

if ($filterTipe !== 'ALL') {
    $sql .= " AND tipe_aktivitas = ?";
    $params[] = $filterTipe;
}

if (!empty($search)) {
    $sql .= " AND (nama LIKE ? OR username LIKE ? OR deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY id_log DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Tipe list untuk dropdown
$tipeList = [
    'ALL'               => 'Semua Aktivitas',
    'LOGIN'             => 'Login Berhasil',
    'LOGIN_GAGAL'       => 'Login Gagal',
    'LOGOUT'            => 'Logout',
    'UPLOAD_INSTRUMEN'  => 'Unggah Berkas',
    'HAPUS_INSTRUMEN'   => 'Hapus Berkas',
    'LOGIN_AS'          => 'Simulasi Login-As',
    'REVERT_ADMIN'      => 'Kembali ke Admin',
    'TAMBAH_MK'         => 'Tambah MK',
    'IMPORT_MK'         => 'Import MK Massal',
    'ASSIGN_DOSEN'      => 'Penugasan Pengampu',
    'UPDATE_PROFIL'     => 'Ubah Profil',
    'GANTI_PASSWORD'    => 'Ganti Password',
    'KIRIM_EMAIL'       => 'Kirim Email Reminder',
    'DOWNLOAD_ZIP'      => 'Unduh Arsip ZIP'
];

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
                        Audit Log Aktivitas Sistem
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Rekam jejak komprehensif seluruh transaksi data, autentikasi, dan aksi pengguna.
                    </p>
                </div>

                <a href="<?= url("log_aktivitas.php?export=csv&tipe=$filterTipe&q=" . urlencode($search)) ?>"
                   class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                    Ekspor CSV (Excel BOM)
                </a>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="<?= url('log_aktivitas.php') ?>" class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400"></i>
                        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari Nama / NIP / Deskripsi..."
                               class="pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl text-slate-800 focus:outline-none focus:ring-1 focus:ring-maritime-700 w-56 sm:w-72">
                    </div>

                    <select name="tipe" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <?php foreach ($tipeList as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $filterTipe === $key ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="text-xs text-slate-500 font-medium">
                    Menampilkan 200 log terbaru
                </div>
            </div>

            <!-- Tabel Log -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4 w-36">Waktu Kejadian</th>
                                <th class="py-3 px-4">Pengguna & Peran</th>
                                <th class="py-3 px-4">Aktivitas</th>
                                <th class="py-3 px-4">Deskripsi Rinci</th>
                                <th class="py-3 px-4">IP & Browser</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-sans">
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <!-- Timestamp -->
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                        <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                    </td>

                                    <!-- User -->
                                    <td class="py-3 px-4">
                                        <p class="font-bold text-slate-900"><?= e($log['nama']) ?></p>
                                        <p class="text-[10px] text-slate-400 font-mono">
                                            <?= e($log['username']) ?> • <?= e($log['role']) ?>
                                        </p>
                                        <?php if ($log['is_impersonated']): ?>
                                            <span class="inline-block mt-0.5 text-[9px] font-bold bg-amber-100 text-amber-800 px-1.5 py-0.2 rounded border border-amber-300">
                                                Simulasi Admin
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Tipe -->
                                    <td class="py-3 px-4">
                                        <?php
                                        $badgeClass = match($log['tipe_aktivitas']) {
                                            'LOGIN', 'UPLOAD_INSTRUMEN', 'TAMBAH_MK', 'ASSIGN_DOSEN' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'LOGIN_GAGAL', 'HAPUS_INSTRUMEN', 'HAPUS_MK', 'HAPUS_DOSEN' => 'bg-rose-50 text-rose-800 border-rose-200',
                                            'LOGIN_AS', 'REVERT_ADMIN' => 'bg-amber-50 text-amber-900 border-amber-200',
                                            default => 'bg-blue-50 text-maritime-800 border-blue-200'
                                        };
                                        ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold font-mono border <?= $badgeClass ?>">
                                            <?= e($log['tipe_aktivitas']) ?>
                                        </span>
                                    </td>

                                    <!-- Deskripsi -->
                                    <td class="py-3 px-4 text-slate-700 leading-relaxed max-w-md">
                                        <?= e($log['deskripsi']) ?>
                                    </td>

                                    <!-- IP & UA -->
                                    <td class="py-3 px-4 font-mono text-[10px] text-slate-400">
                                        <p class="text-slate-600 font-semibold"><?= e($log['ip_address']) ?></p>
                                        <p class="truncate max-w-[150px]" title="<?= e($log['user_agent']) ?>">
                                            <?= e($log['user_agent']) ?>
                                        </p>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400">
                                        Tidak ada catatan aktivitas yang sesuai dengan kriteria filter.
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
