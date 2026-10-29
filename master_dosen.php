<?php
declare(strict_types=1);

/**
 * SIMIA - Master Sivitas & Akun Pengguna (Dosen/Admin/GKMP)
 * Acuan: PRD SIMIA FR-3.1 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Sivitas & Akun Dosen';

// Handle POST (Tambah Akun, Reset Password, Hapus Akun)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nip = trim((string)($_POST['nip'] ?? ''));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = trim((string)($_POST['role'] ?? 'Dosen'));
        $keahlian = trim((string)($_POST['keahlian'] ?? ''));

        if (empty($nip) || empty($nama) || empty($password)) {
            set_flash('error', 'NIP, Nama Lengkap, dan Kata Sandi wajib diisi.');
            redirect('master_dosen.php');
        }

        // Cek NIP duplikat
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE nip = ?");
        $chk->execute([$nip]);
        if ((int)$chk->fetchColumn() > 0) {
            set_flash('error', "NIP '$nip' sudah digunakan oleh pengguna lain.");
            redirect('master_dosen.php');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare("INSERT INTO users (nip, nama, password, email, role, keahlian) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->execute([$nip, $nama, $hash, $email, $role, $keahlian]);

        log_activity('TAMBAH_DOSEN', "Tambah akun baru: {$nama} ({$role}, NIP: {$nip}).");
        set_flash('success', "Akun {$nama} ({$role}) berhasil dibuat!");
        redirect('master_dosen.php');

    } elseif ($action === 'reset_password') {
        $targetId = (int)($_POST['id_user'] ?? 0);
        $newPass = (string)($_POST['new_password'] ?? '');

        if (strlen($newPass) < 6) {
            set_flash('error', 'Kata sandi baru minimal 6 karakter.');
            redirect('master_dosen.php');
        }

        $newHash = password_hash($newPass, PASSWORD_BCRYPT);
        $up = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id_user = ?");
        $up->execute([$newHash, $targetId]);

        $targetName = $pdo->query("SELECT nama FROM users WHERE id_user = $targetId")->fetchColumn();
        log_activity('RESET_PASSWORD', "Admin mereset kata sandi pengguna ID {$targetId} ({$targetName}).");
        set_flash('success', "Kata sandi untuk {$targetName} berhasil direset!");
        redirect('master_dosen.php');

    } elseif ($action === 'delete') {
        $targetId = (int)($_POST['id_user'] ?? 0);

        if ($targetId === (int)$user['id_user']) {
            set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
            redirect('master_dosen.php');
        }

        $targetRole = (string)$pdo->query("SELECT role FROM users WHERE id_user = $targetId")->fetchColumn();
        if ($targetRole === 'Super Admin' && $user['role'] !== 'Super Admin') {
            set_flash('error', 'Hanya Super Admin yang berhak menghapus akun Super Admin.');
            redirect('master_dosen.php');
        }

        $del = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
        $del->execute([$targetId]);

        log_activity('HAPUS_DOSEN', "Hapus akun pengguna ID: {$targetId}.");
        set_flash('success', 'Akun pengguna berhasil dihapus.');
        redirect('master_dosen.php');
    }
}

// Search & List Users
$search = trim((string)($_GET['q'] ?? ''));
$filterRole = $_GET['role'] ?? 'ALL';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (nama LIKE ? OR nip LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterRole !== 'ALL') {
    $sql .= " AND role = ?";
    $params[] = $filterRole;
}

$sql .= " ORDER BY CASE role
    WHEN 'Super Admin' THEN 1
    WHEN 'Admin' THEN 2
    WHEN 'GKMP' THEN 3
    WHEN 'Koor.Prodi' THEN 4
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
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">
                        Sivitas & Akun Dosen
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Manajemen kredensial pengguna, peran akses (RBAC), reset kata sandi, dan simulasi login.
                    </p>
                </div>
                <button type="button" onclick="openModal('modalAddUser')"
                        class="px-4 py-2 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Tambah Pengguna Baru
                </button>
            </div>

            <!-- Super Admin Notification Banner -->
            <?php if (is_super_admin()): ?>
            <div class="bg-gradient-to-r from-cyan-900 to-abyssal-900 text-white p-4 rounded-2xl shadow-sm border border-cyan-800/60 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-300 flex items-center justify-center shrink-0 border border-cyan-500/30">
                        <i data-lucide="shield-alert" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-xs text-cyan-200">Mode Otoritas Super Admin / GKMP Aktif</h4>
                        <p class="text-[11px] text-slate-300 mt-0.5">Anda dapat mengelola seluruh hak akses dosen, Kaprodi, aktivasi status, dan import massal di modul Manajemen Pengguna.</p>
                    </div>
                </div>
                <a href="<?= url('manajemen_pengguna.php') ?>" class="px-3.5 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold shrink-0 transition flex items-center gap-1.5 shadow-md shadow-cyan-900/50">
                    <span>Buka Manajemen Pengguna</span>
                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                </a>
            </div>
            <?php endif; ?>

            <!-- Search & Filter Bar -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="<?= url('master_dosen.php') ?>" class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400"></i>
                        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari Nama / NIP / Email..."
                               class="pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl text-slate-800 focus:outline-none focus:ring-1 focus:ring-maritime-700 w-48 sm:w-64">
                    </div>

                    <select name="role" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="ALL" <?= $filterRole === 'ALL' ? 'selected' : '' ?>>Semua Peran</option>
                        <option value="Dosen" <?= $filterRole === 'Dosen' ? 'selected' : '' ?>>Dosen</option>
                        <option value="Kaprodi" <?= ($filterRole === 'Kaprodi' || $filterRole === 'Koor.Prodi') ? 'selected' : '' ?>>Kaprodi / Koor. Prodi</option>
                        <option value="GKMP" <?= $filterRole === 'GKMP' ? 'selected' : '' ?>>GKMP</option>
                        <option value="Admin" <?= $filterRole === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Super Admin" <?= $filterRole === 'Super Admin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                </form>

                <div class="text-xs text-slate-500 font-medium">
                    Total: <span class="font-bold text-slate-800"><?= count($usersList) ?></span> Pengguna
                </div>
            </div>

            <!-- Tabel Data Pengguna -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                            <tr>
                                <th class="py-3 px-4 text-center w-12">No</th>
                                <th class="py-3 px-4">Pengguna & NIP</th>
                                <th class="py-3 px-4">Kontak Email</th>
                                <th class="py-3 px-4">Peran (Role)</th>
                                <th class="py-3 px-4">Bidang Keahlian</th>
                                <th class="py-3 px-4 text-center">Aksi & Simulasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($usersList)): ?>
                                <?php foreach ($usersList as $idx => $uRow): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 text-center text-slate-400 font-mono"><?= $idx + 1 ?></td>
                                    
                                    <!-- Profil Mini -->
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            <?php if (!empty($uRow['foto_path']) && file_exists(__DIR__ . '/' . ltrim($uRow['foto_path'], '/'))): ?>
                                                <img src="<?= url($uRow['foto_path']) ?>" alt="Foto" class="w-8 h-8 rounded-full object-cover border border-slate-200">
                                            <?php else: ?>
                                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-cyan-600 to-blue-700 text-white flex items-center justify-center font-bold text-xs">
                                                    <?= strtoupper(substr($uRow['nama'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <p class="font-bold text-slate-900 leading-snug"><?= e($uRow['nama']) ?></p>
                                                <p class="text-[10px] text-slate-400 font-mono">NIP: <?= e($uRow['nip']) ?></p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Email -->
                                    <td class="py-3 px-4 text-slate-600">
                                        <?= e($uRow['email'] ?: '-') ?>
                                    </td>

                                    <!-- Role -->
                                    <td class="py-3 px-4">
                                        <?php
                                        $badgeBg = match($uRow['role']) {
                                            'Super Admin' => 'bg-purple-100 text-purple-800 border-purple-200',
                                            'Admin'       => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'GKMP'        => 'bg-cyan-100 text-cyan-800 border-cyan-200',
                                            'Koor.Prodi'  => 'bg-amber-100 text-amber-800 border-amber-200',
                                            default       => 'bg-slate-100 text-slate-700 border-slate-200'
                                        };
                                        ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?= $badgeBg ?>">
                                            <?= e($uRow['role']) ?>
                                        </span>
                                    </td>

                                    <!-- Keahlian -->
                                    <td class="py-3 px-4 text-slate-600 max-w-xs truncate" title="<?= e($uRow['keahlian']) ?>">
                                        <?= e($uRow['keahlian'] ?: '-') ?>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- Simulasi Login-As (Hanya untuk target selain diri sendiri) -->
                                            <?php if ((int)$uRow['id_user'] !== (int)$user['id_user']): ?>
                                            <form action="<?= url('login_as.php') ?>" method="POST" class="inline m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="impersonate">
                                                <input type="hidden" name="target_id" value="<?= $uRow['id_user'] ?>">
                                                <button type="submit" title="Simulasi Akses sebagai <?= e($uRow['nama']) ?>"
                                                        class="p-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition">
                                                    <i data-lucide="user-check" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>

                                            <!-- Reset Password -->
                                            <button type="button"
                                                    onclick="openResetModal(<?= $uRow['id_user'] ?>, '<?= e(addslashes($uRow['nama'])) ?>')"
                                                    title="Reset Kata Sandi"
                                                    class="p-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                                                <i data-lucide="key" class="w-3.5 h-3.5"></i>
                                            </button>

                                            <!-- Hapus User -->
                                            <?php if ((int)$uRow['id_user'] !== (int)$user['id_user']): ?>
                                            <form action="<?= url('master_dosen.php') ?>" method="POST"
                                                  onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun <?= e($uRow['nama']) ?>?')" class="inline m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_user" value="<?= $uRow['id_user'] ?>">
                                                <button type="submit" title="Hapus Akun" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-slate-400">
                                        Tidak ada akun pengguna yang sesuai dengan filter.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        </div>

<!-- MODAL TAMBAH USER -->
<div id="modalAddUser" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <button type="button" onclick="closeModal('modalAddUser')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h3 class="font-heading font-extrabold text-base text-slate-900 mb-1">Tambah Pengguna / Dosen Baru</h3>
        <p class="text-xs text-slate-500 mb-4">Lengkapi informasi biodata dan kredensial akun.</p>

        <form action="<?= url('master_dosen.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">NIP (Username)</label>
                    <input type="text" name="nip" required placeholder="Contoh: 19900101..."
                           class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Peran Akun (Role)</label>
                    <select name="role" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="Dosen">Dosen</option>
                        <option value="Admin">Admin</option>
                        <option value="GKMP">GKMP</option>
                        <option value="Koor.Prodi">Koor. Prodi</option>
                        <?php if ($user['role'] === 'Super Admin'): ?>
                        <option value="Super Admin">Super Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap & Gelar</label>
                <input type="text" name="nama" required placeholder="Nama lengkap beserta gelar akademik"
                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" placeholder="contoh@kelautan.id"
                           class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi Awal</label>
                    <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter"
                           class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Bidang Keahlian / Riset</label>
                <input type="text" name="keahlian" placeholder="Misal: Oseanografi Kimia, Sensor Kelautan"
                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalAddUser')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition">
                    Buat Akun
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL RESET PASSWORD -->
<div id="modalResetPassword" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-sm w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <button type="button" onclick="closeModal('modalResetPassword')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h3 class="font-heading font-extrabold text-base text-slate-900 mb-1">Reset Kata Sandi</h3>
        <p id="resetTargetName" class="text-xs text-slate-500 mb-4"></p>

        <form action="<?= url('master_dosen.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" id="resetUserId" name="id_user" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi Baru</label>
                <input type="password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter"
                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalResetPassword')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs rounded-xl shadow-md transition">
                    Reset Sandi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openResetModal(userId, userName) {
        document.getElementById('resetUserId').value = userId;
        document.getElementById('resetTargetName').innerText = `Atur kata sandi baru untuk: ${userName}`;
        openModal('modalResetPassword');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
