<?php
declare(strict_types=1);

/**
 * SIMIA - Manajemen Pengguna & Otoritas Sistem
 * Khusus Super Admin & Tim Penjaminan Mutu (GKMP)
 * Acuan: PRD SIMIA FR-3.1, FR-3.2 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_super_admin();

$currentUser = auth_user();
$pdo = Database::getConnection();
$branding = get_branding_info();
$pageTitle = 'Manajemen Pengguna & Otoritas Sistem';

// ==========================================
// 1. Export CSV (Jika diminta via GET)
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search = trim((string)($_GET['q'] ?? ''));
    $filterRole = $_GET['role'] ?? 'ALL';
    $filterStatus = $_GET['status'] ?? 'ALL';

    $sql = "SELECT id_user, nip, nama, email, role, keahlian, status, created_at FROM users WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (nama LIKE ? OR nip LIKE ? OR email LIKE ? OR keahlian LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filterRole !== 'ALL') {
        if ($filterRole === 'Kaprodi') {
            $sql .= " AND (role = 'Kaprodi' OR role = 'Koor.Prodi')";
        } else {
            $sql .= " AND role = ?";
            $params[] = $filterRole;
        }
    }

    if ($filterStatus !== 'ALL') {
        $sql .= " AND status = ?";
        $params[] = $filterStatus;
    }

    $sql .= " ORDER BY CASE role
        WHEN 'Super Admin' THEN 1
        WHEN 'GKMP' THEN 2
        WHEN 'Kaprodi' THEN 3
        WHEN 'Koor.Prodi' THEN 3
        WHEN 'Admin' THEN 4
        ELSE 5
    END, nama ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exportUsers = $stmt->fetchAll();

    $filename = 'simia_pengguna_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM untuk Microsoft Excel
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['No', 'NIP / NIDN', 'Nama Lengkap', 'Email', 'Role / Peran', 'Bidang Keahlian', 'Status Akun', 'Tanggal Terdaftar'], ',', '"', '\\');

    $no = 1;
    foreach ($exportUsers as $u) {
        fputcsv($out, [
            $no++,
            $u['nip'],
            $u['nama'],
            $u['email'] ?: '-',
            $u['role'],
            $u['keahlian'] ?: '-',
            $u['status'] ?? 'Aktif',
            $u['created_at'] ?? '-'
        ], ',', '"', '\\');
    }
    fclose($out);
    exit;
}

// ==========================================
// 2. Handle POST Actions
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    // A. Tambah Pengguna Baru
    if ($action === 'add') {
        $nip = trim((string)($_POST['nip'] ?? ''));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = trim((string)($_POST['role'] ?? 'Dosen'));
        $keahlian = trim((string)($_POST['keahlian'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Aktif'));

        if (empty($nip) || empty($nama) || empty($password)) {
            set_flash('error', 'NIP/NIDN, Nama Lengkap, dan Kata Sandi wajib diisi.');
            redirect('manajemen_pengguna.php');
        }

        // Cek duplikasi NIP
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE nip = ?");
        $chk->execute([$nip]);
        if ((int)$chk->fetchColumn() > 0) {
            set_flash('error', "NIP/NIDN '$nip' sudah terdaftar dalam sistem.");
            redirect('manajemen_pengguna.php');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare("
            INSERT INTO users (nip, nama, password, email, role, keahlian, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$nip, $nama, $hash, $email, $role, $keahlian, $status]);

        log_activity('TAMBAH_PENGGUNA', "Super Admin menambahkan pengguna baru: {$nama} ({$role}, NIP: {$nip}).");
        set_flash('success', "Akun pengguna {$nama} ({$role}) berhasil dibuat!");
        redirect('manajemen_pengguna.php');

    // B. Edit Pengguna
    } elseif ($action === 'edit') {
        $targetId = (int)($_POST['id_user'] ?? 0);
        $nip = trim((string)($_POST['nip'] ?? ''));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'Dosen'));
        $keahlian = trim((string)($_POST['keahlian'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Aktif'));

        if ($targetId <= 0 || empty($nip) || empty($nama)) {
            set_flash('error', 'Data pengguna tidak lengkap.');
            redirect('manajemen_pengguna.php');
        }

        // Cek pengguna ada
        $userCheck = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
        $userCheck->execute([$targetId]);
        $existing = $userCheck->fetch();
        if (!$existing) {
            set_flash('error', 'Pengguna tidak ditemukan.');
            redirect('manajemen_pengguna.php');
        }

        // Proteksi akun sendiri
        if ($targetId === (int)$currentUser['id_user']) {
            if ($status === 'Nonaktif') {
                set_flash('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
                redirect('manajemen_pengguna.php');
            }
            if ($role !== 'Super Admin') {
                set_flash('error', 'Anda tidak dapat menurunkan peran (role) akun Anda sendiri.');
                redirect('manajemen_pengguna.php');
            }
        }

        // Cek duplikasi NIP pada user lain
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE nip = ? AND id_user != ?");
        $chk->execute([$nip, $targetId]);
        if ((int)$chk->fetchColumn() > 0) {
            set_flash('error', "NIP/NIDN '$nip' sudah digunakan oleh pengguna lain.");
            redirect('manajemen_pengguna.php');
        }

        $up = $pdo->prepare("
            UPDATE users
            SET nip = ?, nama = ?, email = ?, role = ?, keahlian = ?, status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id_user = ?
        ");
        $up->execute([$nip, $nama, $email, $role, $keahlian, $status, $targetId]);

        log_activity('EDIT_PENGGUNA', "Super Admin memperbarui data pengguna ID {$targetId} ({$nama}, Role: {$role}, Status: {$status}).");
        set_flash('success', "Data pengguna {$nama} berhasil diperbarui!");
        redirect('manajemen_pengguna.php');

    // C. Reset Kata Sandi
    } elseif ($action === 'reset_password') {
        $targetId = (int)($_POST['id_user'] ?? 0);
        $newPass = (string)($_POST['new_password'] ?? '');

        if ($targetId <= 0 || strlen($newPass) < 6) {
            set_flash('error', 'Kata sandi baru minimal 6 karakter.');
            redirect('manajemen_pengguna.php');
        }

        $targetUser = $pdo->query("SELECT nama, nip FROM users WHERE id_user = $targetId")->fetch();
        if (!$targetUser) {
            set_flash('error', 'Pengguna tidak ditemukan.');
            redirect('manajemen_pengguna.php');
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $up = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id_user = ?");
        $up->execute([$newHash, $targetId]);

        log_activity('RESET_PASSWORD_PENGGUNA', "Super Admin mereset kata sandi pengguna ID {$targetId} ({$targetUser['nama']}).");
        set_flash('success', "Kata sandi untuk {$targetUser['nama']} (NIP: {$targetUser['nip']}) berhasil direset menjadi: <span class='font-mono font-bold bg-slate-900 px-2 py-0.5 rounded text-amber-300 select-all'>" . e($newPass) . "</span>");
        redirect('manajemen_pengguna.php');

    // D. Toggle Status (Aktif / Nonaktif)
    } elseif ($action === 'toggle_status') {
        $targetId = (int)($_POST['id_user'] ?? 0);

        if ($targetId === (int)$currentUser['id_user']) {
            set_flash('error', 'Anda tidak dapat mengubah status akun Anda sendiri.');
            redirect('manajemen_pengguna.php');
        }

        $target = $pdo->query("SELECT nama, status FROM users WHERE id_user = $targetId")->fetch();
        if ($target) {
            $newStatus = ($target['status'] === 'Nonaktif') ? 'Aktif' : 'Nonaktif';
            $up = $pdo->prepare("UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id_user = ?");
            $up->execute([$newStatus, $targetId]);

            log_activity('UBAH_STATUS_PENGGUNA', "Super Admin mengubah status {$target['nama']} menjadi {$newStatus}.");
            set_flash('success', "Status akun {$target['nama']} diubah menjadi '{$newStatus}'.");
        }
        redirect('manajemen_pengguna.php');

    // E. Hapus Pengguna
    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['id_user'] ?? 0);

        if ($targetId === (int)$currentUser['id_user']) {
            set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
            redirect('manajemen_pengguna.php');
        }

        $targetUser = $pdo->query("SELECT nama, role FROM users WHERE id_user = $targetId")->fetch();
        if (!$targetUser) {
            set_flash('error', 'Pengguna tidak ditemukan.');
            redirect('manajemen_pengguna.php');
        }

        // Cek jika Super Admin terakhir
        if ($targetUser['role'] === 'Super Admin') {
            $saCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Super Admin'")->fetchColumn();
            if ($saCount <= 1) {
                set_flash('error', 'Tidak dapat menghapus Super Admin terakhir di sistem.');
                redirect('manajemen_pengguna.php');
            }
        }

        // Cek keterkaitan dengan penugasan kuliah
        $chkPengampu = $pdo->prepare("SELECT COUNT(*) FROM pengampu WHERE id_user = ?");
        $chkPengampu->execute([$targetId]);
        $cntPengampu = (int)$chkPengampu->fetchColumn();

        if ($cntPengampu > 0) {
            // Beri peringatan dan opsi hapus penugasan
            $pdo->prepare("DELETE FROM pengampu WHERE id_user = ?")->execute([$targetId]);
        }

        $del = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
        $del->execute([$targetId]);

        log_activity('HAPUS_PENGGUNA', "Super Admin menghapus akun: {$targetUser['nama']} ({$targetUser['role']}).");
        set_flash('success', "Akun pengguna {$targetUser['nama']} berhasil dihapus dari sistem.");
        redirect('manajemen_pengguna.php');

    // F. Bulk Import via TSV / Excel Copy-Paste
    } elseif ($action === 'bulk_import') {
        $rawTsv = trim((string)($_POST['tsv_data'] ?? ''));
        $defaultPass = trim((string)($_POST['default_password'] ?? 'dosen123'));
        if (strlen($defaultPass) < 6) $defaultPass = 'dosen123';

        if (empty($rawTsv)) {
            set_flash('error', 'Silakan tempel (paste) data pengguna dalam format tabel TSV/Excel.');
            redirect('manajemen_pengguna.php');
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $rawTsv));
        $created = 0;
        $updated = 0;
        $defaultHash = password_hash($defaultPass, PASSWORD_BCRYPT);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $cols = explode("\t", $line);
            if (count($cols) < 2) {
                // Coba pisahkan dengan koma jika CSV
                $cols = str_getcsv($line);
            }
            if (count($cols) < 2) continue;

            $nip = trim($cols[0]);
            $nama = trim($cols[1]);
            $email = isset($cols[2]) ? trim($cols[2]) : '';
            $role = isset($cols[3]) ? trim($cols[3]) : 'Dosen';
            $keahlian = isset($cols[4]) ? trim($cols[4]) : '';

            // Abaikan baris header jika ada
            if (stripos($nip, 'NIP') !== false || stripos($nama, 'Nama') !== false) {
                continue;
            }

            // Normalisasi role
            $validRoles = ['Super Admin', 'GKMP', 'Kaprodi', 'Koor.Prodi', 'Admin', 'Dosen'];
            if (!in_array($role, $validRoles, true)) {
                $role = 'Dosen';
            }

            // Cek apakah NIP sudah ada
            $chk = $pdo->prepare("SELECT id_user FROM users WHERE nip = ?");
            $chk->execute([$nip]);
            $existId = $chk->fetchColumn();

            if ($existId) {
                $up = $pdo->prepare("
                    UPDATE users
                    SET nama = ?, email = ?, role = ?, keahlian = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id_user = ?
                ");
                $up->execute([$nama, $email, $role, $keahlian, $existId]);
                $updated++;
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO users (nip, nama, password, email, role, keahlian, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'Aktif')
                ");
                $ins->execute([$nip, $nama, $defaultHash, $email, $role, $keahlian]);
                $created++;
            }
        }

        log_activity('IMPORT_BULK_PENGGUNA', "Super Admin mengimpor massal: {$created} akun baru dibuat, {$updated} akun diperbarui.");
        set_flash('success', "Proses impor selesai! {$created} pengguna baru ditambahkan dan {$updated} pengguna diperbarui.");
        redirect('manajemen_pengguna.php');
    }
}

// ==========================================
// 3. Query & Statistik Pengguna
// ==========================================
$search = trim((string)($_GET['q'] ?? ''));
$filterRole = $_GET['role'] ?? 'ALL';
$filterStatus = $_GET['status'] ?? 'ALL';

// Ringkasan Statistik
$statTotal = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$statDosen = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Dosen'")->fetchColumn();
$statKaprodi = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Kaprodi' OR role = 'Koor.Prodi'")->fetchColumn();
$statGkmpAdmin = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('Super Admin', 'GKMP', 'Admin')")->fetchColumn();
$statNonaktif = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Nonaktif'")->fetchColumn();

// List Pengguna Berdasarkan Filter
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (nama LIKE ? OR nip LIKE ? OR email LIKE ? OR keahlian LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterRole !== 'ALL') {
    if ($filterRole === 'Kaprodi') {
        $sql .= " AND (role = 'Kaprodi' OR role = 'Koor.Prodi')";
    } else {
        $sql .= " AND role = ?";
        $params[] = $filterRole;
    }
}

if ($filterStatus !== 'ALL') {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}

$sql .= " ORDER BY CASE role
    WHEN 'Super Admin' THEN 1
    WHEN 'GKMP' THEN 2
    WHEN 'Kaprodi' THEN 3
    WHEN 'Koor.Prodi' THEN 3
    WHEN 'Admin' THEN 4
    ELSE 5
END, nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usersList = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header Halaman -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cyan-600 to-abyssal-800 flex items-center justify-center text-white shadow-md shadow-cyan-500/20">
                        <i data-lucide="shield-alert" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold font-heading text-slate-800">Manajemen Pengguna & Otoritas</h1>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-cyan-100 text-cyan-800 border border-cyan-200">Super Admin / GKMP</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola seluruh akun sivitas akademika, Koordinator Prodi (Kaprodi), Gugus Kendali Mutu (GKMP), dan Administrator.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 flex-wrap">
                    <a href="<?= url('manajemen_pengguna.php') ?>?export=csv&q=<?= urlencode($search) ?>&role=<?= urlencode($filterRole) ?>&status=<?= urlencode($filterStatus) ?>"
                       class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        <span>Ekspor CSV</span>
                    </a>
                    <button type="button" onclick="openModal('modal-bulk-import')"
                            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                        <span>Import Massal</span>
                    </button>
                    <button type="button" onclick="openModal('modal-add-user')"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-cyan-600 hover:bg-cyan-700 text-white shadow-md shadow-cyan-600/20 transition">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        <span>Tambah Pengguna</span>
                    </button>
                </div>
            </div>

            <!-- Kartu Statistik Pengguna -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Sivitas -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Total Sivitas</p>
                        <h3 class="text-2xl font-bold font-heading text-slate-800 mt-1"><?= number_format($statTotal) ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5"><?= $statNonaktif > 0 ? "($statNonaktif nonaktif)" : "Semua aktif" ?></p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                </div>

                <!-- Dosen Pengampu -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Dosen Pengampu</p>
                        <h3 class="text-2xl font-bold font-heading text-emerald-600 mt-1"><?= number_format($statDosen) ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Pengampu mata kuliah</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i data-lucide="graduation-cap" class="w-5 h-5"></i>
                    </div>
                </div>

                <!-- Kaprodi / Koor.Prodi -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 font-medium">Koordinator Prodi (Kaprodi)</p>
                        <h3 class="text-2xl font-bold font-heading text-amber-600 mt-1"><?= number_format($statKaprodi) ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Pimpinan kurikulum</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <i data-lucide="award" class="w-5 h-5"></i>
                    </div>
                </div>

                <!-- Tim Penjamin Mutu & Admin -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 font-medium">GKMP & Super Admin</p>
                        <h3 class="text-2xl font-bold font-heading text-cyan-600 mt-1"><?= number_format($statGkmpAdmin) ?></h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Pengendali mutu & sistem</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>

            <!-- Filter & Pencarian -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <form method="GET" action="<?= url('manajemen_pengguna.php') ?>" class="flex flex-col lg:flex-row items-center justify-between gap-4">
                    <!-- Search Input -->
                    <div class="relative w-full lg:w-96">
                        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama, NIP/NIDN, email, atau bidang..."
                               class="w-full pl-9 pr-4 py-2 rounded-xl text-xs border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                    </div>

                    <!-- Role & Status Filter Pills -->
                    <div class="flex items-center gap-2 flex-wrap w-full lg:w-auto">
                        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl text-xs overflow-x-auto">
                            <?php
                            $rolesList = [
                                'ALL'         => 'Semua Peran',
                                'Dosen'       => 'Dosen',
                                'Kaprodi'     => 'Kaprodi',
                                'GKMP'        => 'GKMP',
                                'Admin'       => 'Admin',
                                'Super Admin' => 'Super Admin'
                            ];
                            foreach ($rolesList as $rKey => $rLabel):
                                $isActive = ($filterRole === $rKey);
                            ?>
                            <a href="<?= url('manajemen_pengguna.php') ?>?q=<?= urlencode($search) ?>&role=<?= urlencode($rKey) ?>&status=<?= urlencode($filterStatus) ?>"
                               class="px-3 py-1.5 rounded-lg font-medium transition <?= $isActive ? 'bg-white text-slate-800 shadow-sm font-bold' : 'text-slate-600 hover:text-slate-900' ?>">
                                <?= $rLabel ?>
                            </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Status Filter Dropdown -->
                        <select name="status" onchange="this.form.submit()" class="py-1.5 px-3 rounded-xl text-xs border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-white">
                            <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="Aktif" <?= $filterStatus === 'Aktif' ? 'selected' : '' ?>>Hanya Aktif</option>
                            <option value="Nonaktif" <?= $filterStatus === 'Nonaktif' ? 'selected' : '' ?>>Hanya Nonaktif</option>
                        </select>

                        <input type="hidden" name="role" value="<?= e($filterRole) ?>">
                        <button type="submit" class="px-3 py-1.5 bg-slate-800 text-white rounded-xl text-xs hover:bg-slate-900 transition">Filter</button>
                        <?php if (!empty($search) || $filterRole !== 'ALL' || $filterStatus !== 'ALL'): ?>
                        <a href="<?= url('manajemen_pengguna.php') ?>" class="px-2 py-1.5 text-xs text-rose-600 hover:underline">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabel Daftar Pengguna -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-wider">
                                <th class="py-3 px-4">Pengguna & Identitas</th>
                                <th class="py-3 px-4">Peran / Otoritas</th>
                                <th class="py-3 px-4">Bidang Keahlian / Homebase</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Tindakan Cepat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($usersList)): ?>
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    <i data-lucide="user-x" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
                                    <p class="font-medium text-slate-600">Tidak ada pengguna yang sesuai dengan kriteria.</p>
                                    <p class="text-[11px] mt-1">Coba sesuaikan kata kunci pencarian atau reset filter peran.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($usersList as $u):
                                $role = $u['role'];
                                $isSelf = ($u['id_user'] == $currentUser['id_user']);
                                $status = $u['status'] ?? 'Aktif';

                                // Konfigurasi Badge Peran
                                $roleBadgeClass = match($role) {
                                    'Super Admin' => 'bg-purple-50 text-purple-700 border-purple-200',
                                    'GKMP'        => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                                    'Kaprodi', 'Koor.Prodi' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'Admin'       => 'bg-sky-50 text-sky-700 border-sky-200',
                                    default       => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                };

                                $roleIcon = match($role) {
                                    'Super Admin' => 'shield-alert',
                                    'GKMP'        => 'shield-check',
                                    'Kaprodi', 'Koor.Prodi' => 'award',
                                    'Admin'       => 'user-cog',
                                    default       => 'graduation-cap',
                                };
                            ?>
                            <tr class="hover:bg-slate-50/80 transition <?= $status === 'Nonaktif' ? 'opacity-60 bg-slate-50/40' : '' ?>">
                                <!-- Identitas -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-slate-700 text-xs shrink-0">
                                            <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 flex items-center gap-1.5">
                                                <span><?= e($u['nama']) ?></span>
                                                <?php if ($isSelf): ?>
                                                <span class="px-1.5 py-0.2 rounded text-[9px] bg-slate-200 text-slate-700 font-bold uppercase">Anda</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[11px] text-slate-500 font-mono mt-0.5">
                                                <span>NIP: <?= e($u['nip']) ?></span>
                                                <?php if (!empty($u['email'])): ?>
                                                <span class="text-slate-300 mx-1">•</span>
                                                <span class="text-slate-400"><?= e($u['email']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Role -->
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border <?= $roleBadgeClass ?>">
                                        <i data-lucide="<?= $roleIcon ?>" class="w-3.5 h-3.5"></i>
                                        <?= e($role) ?>
                                    </span>
                                </td>

                                <!-- Keahlian -->
                                <td class="py-3 px-4">
                                    <p class="text-slate-600 line-clamp-1"><?= !empty($u['keahlian']) ? e($u['keahlian']) : '<span class="text-slate-300 italic">- Belum ditentukan -</span>' ?></p>
                                </td>

                                <!-- Status -->
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <?php if ($status === 'Aktif'): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Nonaktif
                                    </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">

                                        <!-- Tombol Simulasi Login (Login-As) Langsung -->
                                        <?php if (!$isSelf): ?>
                                        <form method="POST" action="<?= url('login_as.php') ?>" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="start_impersonation">
                                            <input type="hidden" name="target_user_id" value="<?= $u['id_user'] ?>">
                                            <button type="submit" title="Simulasi Login Sebagai <?= e($u['nama']) ?>"
                                                    class="p-1.5 rounded-lg text-slate-500 hover:text-cyan-700 hover:bg-cyan-50 transition">
                                                <i data-lucide="user-check" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <!-- Edit Modal Button -->
                                        <button type="button" onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)"
                                                title="Edit Profil & Otoritas"
                                                class="p-1.5 rounded-lg text-slate-500 hover:text-amber-700 hover:bg-amber-50 transition">
                                            <i data-lucide="edit-3" class="w-4 h-4"></i>
                                        </button>

                                        <!-- Reset Password Modal Button -->
                                        <button type="button" onclick="openResetModal(<?= $u['id_user'] ?>, '<?= e(addslashes($u['nama'])) ?>', '<?= e(addslashes($u['nip'])) ?>')"
                                                title="Reset Kata Sandi"
                                                class="p-1.5 rounded-lg text-slate-500 hover:text-blue-700 hover:bg-blue-50 transition">
                                            <i data-lucide="key" class="w-4 h-4"></i>
                                        </button>

                                        <!-- Toggle Status Button -->
                                        <?php if (!$isSelf): ?>
                                        <form method="POST" action="<?= url('manajemen_pengguna.php') ?>" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif pengguna ini?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                                            <button type="submit" title="<?= $status === 'Aktif' ? 'Nonaktifkan Pengguna' : 'Aktifkan Pengguna' ?>"
                                                    class="p-1.5 rounded-lg <?= $status === 'Aktif' ? 'text-slate-400 hover:text-rose-600 hover:bg-rose-50' : 'text-emerald-600 hover:bg-emerald-50' ?> transition">
                                                <i data-lucide="<?= $status === 'Aktif' ? 'power-off' : 'power' ?>" class="w-4 h-4"></i>
                                            </button>
                                        </form>

                                        <!-- Hapus Button -->
                                        <button type="button" onclick="confirmDelete(<?= $u['id_user'] ?>, '<?= e(addslashes($u['nama'])) ?>', '<?= e($u['role']) ?>')"
                                                title="Hapus Akun Pengguna"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        </div>
<!-- ==========================================
     MODAL 1: TAMBAH PENGGUNA BARU
=========================================== -->
<div id="modal-add-user" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm p-4 items-center justify-center" onclick="if(event.target===this)closeModal('modal-add-user')">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-200 my-auto">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="p-2 bg-cyan-600/30 rounded-lg text-cyan-400">
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold font-heading text-sm text-white">Tambah Pengguna Baru</h3>
                    <p class="text-[11px] text-slate-400">Buat akun Dosen, Kaprodi, GKMP, atau Administrator</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-add-user')" class="text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="<?= url('manajemen_pengguna.php') ?>" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">NIP / NIDN <span class="text-rose-500">*</span></label>
                    <input type="text" name="nip" required placeholder="Contoh: 198501012010121001"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Peran / Otoritas <span class="text-rose-500">*</span></label>
                    <select name="role" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-white">
                        <option value="Dosen" selected>Dosen Pengampu</option>
                        <option value="Kaprodi">Koordinator Prodi (Kaprodi)</option>
                        <option value="GKMP">Gugus Kendali Mutu (GKMP)</option>
                        <option value="Admin">Staf Administrasi Akademik</option>
                        <option value="Super Admin">Super Administrator</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap & Gelar <span class="text-rose-500">*</span></label>
                <input type="text" name="nama" required placeholder="Contoh: Dr. Ir. H. Ahmad Fauzi, M.Sc."
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email</label>
                    <input type="email" name="email" placeholder="nama@kelautan.ac.id"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Akun</label>
                    <select name="status" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-white">
                        <option value="Aktif" selected>Aktif (Bisa Login)</option>
                        <option value="Nonaktif">Nonaktif (Akses Ditangguhkan)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Bidang Keahlian / Fokus Laboratorium</label>
                <input type="text" name="keahlian" placeholder="Contoh: Oseanografi Fisika, Akustik Bawah Air"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-slate-700">Kata Sandi Awal <span class="text-rose-500">*</span></label>
                    <button type="button" onclick="generateRandomPass('add_password')" class="text-[11px] text-cyan-600 hover:text-cyan-800 font-medium">
                        <i data-lucide="refresh-cw" class="w-3 h-3 inline mr-0.5"></i> Buat Sandi Acak
                    </button>
                </div>
                <div class="relative">
                    <input type="text" id="add_password" name="password" required value="dosen123"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono">
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Default sandi awal: <code class="bg-slate-100 px-1 py-0.5 rounded">dosen123</code> (Pengguna disarankan mengganti saat pertama masuk).</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-add-user')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold bg-cyan-600 hover:bg-cyan-700 text-white shadow-md shadow-cyan-600/20 transition">
                    Simpan Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL 2: EDIT PENGGUNA
=========================================== -->
<div id="modal-edit-user" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm p-4 items-center justify-center" onclick="if(event.target===this)closeModal('modal-edit-user')">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-200 my-auto">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="p-2 bg-amber-600/30 rounded-lg text-amber-400">
                    <i data-lucide="edit-3" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold font-heading text-sm text-white">Edit Data & Otoritas Pengguna</h3>
                    <p class="text-[11px] text-slate-400">Perbarui identitas, peran sistem, dan status akun</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-edit-user')" class="text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="<?= url('manajemen_pengguna.php') ?>" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_id_user" name="id_user" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">NIP / NIDN <span class="text-rose-500">*</span></label>
                    <input type="text" id="edit_nip" name="nip" required
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Peran / Otoritas <span class="text-rose-500">*</span></label>
                    <select id="edit_role" name="role" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-white">
                        <option value="Dosen">Dosen Pengampu</option>
                        <option value="Kaprodi">Koordinator Prodi (Kaprodi)</option>
                        <option value="GKMP">Gugus Kendali Mutu (GKMP)</option>
                        <option value="Admin">Staf Administrasi Akademik</option>
                        <option value="Super Admin">Super Administrator</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap & Gelar <span class="text-rose-500">*</span></label>
                <input type="text" id="edit_nama" name="nama" required
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email</label>
                    <input type="email" id="edit_email" name="email"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Status Akun</label>
                    <select id="edit_status" name="status" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-white">
                        <option value="Aktif">Aktif (Bisa Login)</option>
                        <option value="Nonaktif">Nonaktif (Akses Ditangguhkan)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Bidang Keahlian / Fokus Laboratorium</label>
                <input type="text" id="edit_keahlian" name="keahlian"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-edit-user')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white shadow-md shadow-amber-600/20 transition">
                    Perbarui Data
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL 3: RESET PASSWORD
=========================================== -->
<div id="modal-reset-pass" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm p-4 items-center justify-center" onclick="if(event.target===this)closeModal('modal-reset-pass')">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-200 my-auto">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="p-2 bg-blue-600/30 rounded-lg text-blue-400">
                    <i data-lucide="key" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold font-heading text-sm text-white">Reset Kata Sandi Pengguna</h3>
                    <p class="text-[11px] text-slate-400" id="reset_user_label">Pengguna: -</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-reset-pass')" class="text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="<?= url('manajemen_pengguna.php') ?>" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" id="reset_id_user" name="id_user" value="">

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-xs font-semibold text-slate-700">Kata Sandi Baru <span class="text-rose-500">*</span></label>
                    <button type="button" onclick="generateRandomPass('reset_new_password')" class="text-[11px] text-cyan-600 hover:text-cyan-800 font-medium">
                        <i data-lucide="refresh-cw" class="w-3 h-3 inline mr-0.5"></i> Buat Sandi Acak
                    </button>
                </div>
                <input type="text" id="reset_new_password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono">
                <p class="text-[10px] text-slate-400 mt-1">Kata sandi baru akan langsung ditampilkan di layar setelah disimpan agar mudah dibagikan kepada pengguna.</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-reset-pass')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-600/20 transition">
                    Reset Sandi Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL 4: BULK IMPORT EXCEL / TSV
=========================================== -->
<div id="modal-bulk-import" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm p-4 items-center justify-center" onclick="if(event.target===this)closeModal('modal-bulk-import')">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-200 my-auto">
        <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="p-2 bg-emerald-600/30 rounded-lg text-emerald-400">
                    <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold font-heading text-sm text-white">Import Massal Pengguna (Excel / TSV)</h3>
                    <p class="text-[11px] text-slate-400">Salin dan tempel daftar dosen/kaprodi langsung dari spreadsheet</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('modal-bulk-import')" class="text-slate-400 hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="<?= url('manajemen_pengguna.php') ?>" class="p-6 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bulk_import">

            <div class="p-3 bg-cyan-50 rounded-xl border border-cyan-200 text-xs text-cyan-900 space-y-1">
                <p class="font-bold">Format Kolom Spreadsheet (Pisahkan Tab):</p>
                <p class="font-mono text-[11px] bg-white p-2 rounded border border-cyan-100 text-slate-700">
                    NIP [Tab] Nama Lengkap [Tab] Email [Tab] Role [Tab] Bidang Keahlian
                </p>
                <p class="text-[10px] text-cyan-700">Contoh Peran: <code class="font-bold">Dosen</code>, <code class="font-bold">Kaprodi</code>, <code class="font-bold">GKMP</code>, <code class="font-bold">Admin</code>.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Tempel (Paste) Data Tabel Disini</label>
                <textarea name="tsv_data" rows="8" required placeholder="199001012015011001	Dr. Ir. Budi Santoso, M.Si.	budi@kelautan.ac.id	Kaprodi	Oseanografi Fisika
199202022018022002	Siti Fatimah, S.Pi., M.Si.	siti@kelautan.ac.id	Dosen	Biologi Laut & Plankton"
                          class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono"></textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi Default untuk Akun Baru</label>
                <input type="text" name="default_password" value="dosen123" required
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-cyan-500 font-mono">
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal('modal-bulk-import')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-600/20 transition">
                    Mulai Import Data
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL 5: KONFIRMASI HAPUS
=========================================== -->
<div id="modal-delete-user" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm p-4 items-center justify-center" onclick="if(event.target===this)closeModal('modal-delete-user')">
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden border border-slate-200 animate-in fade-in zoom-in duration-200 my-auto">
        <div class="p-6 text-center space-y-4">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
                <i data-lucide="alert-triangle" class="w-6 h-6"></i>
            </div>
            <div>
                <h3 class="font-bold font-heading text-base text-slate-800">Hapus Akun Pengguna?</h3>
                <p class="text-xs text-slate-500 mt-1" id="delete_desc">Akun pengguna ini akan dihapus secara permanen dari sistem.</p>
            </div>

            <form method="POST" action="<?= url('manajemen_pengguna.php') ?>" class="flex items-center justify-center gap-2 pt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_id_user" name="id_user" value="">

                <button type="button" onclick="closeModal('modal-delete-user')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold bg-rose-600 hover:bg-rose-700 text-white shadow-md shadow-rose-600/20 transition">
                    Ya, Hapus Akun
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('hidden');
        el.classList.add('flex');
        document.body.style.overflow = 'hidden';
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
        document.body.style.overflow = '';
    }
}

function editUser(user) {
    document.getElementById('edit_id_user').value = user.id_user;
    document.getElementById('edit_nip').value = user.nip || '';
    document.getElementById('edit_nama').value = user.nama || '';
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_keahlian').value = user.keahlian || '';
    document.getElementById('edit_status').value = user.status || 'Aktif';

    const roleSelect = document.getElementById('edit_role');
    if (roleSelect) {
        // Normalisasi Koor.Prodi ke Kaprodi jika ada
        const r = (user.role === 'Koor.Prodi') ? 'Kaprodi' : user.role;
        roleSelect.value = r;
    }

    openModal('modal-edit-user');
}

function openResetModal(id, nama, nip) {
    document.getElementById('reset_id_user').value = id;
    document.getElementById('reset_user_label').textContent = `Pengguna: ${nama} (${nip})`;
    document.getElementById('reset_new_password').value = '';
    openModal('modal-reset-pass');
}

function confirmDelete(id, nama, role) {
    document.getElementById('delete_id_user').value = id;
    document.getElementById('delete_desc').textContent = `Akun ${nama} (${role}) beserta relasi penugasannya akan dihapus permanen.`;
    openModal('modal-delete-user');
}

function generateRandomPass(targetInputId) {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%';
    let pass = '';
    for (let i = 0; i < 9; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input = document.getElementById(targetInputId);
    if (input) {
        input.value = pass;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
