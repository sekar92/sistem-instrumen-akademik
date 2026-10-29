<?php
declare(strict_types=1);

/**
 * SIMIA - Pusat Simulasi Akun / Login-As
 * Acuan: skills.md Bagian 5.4
 */

require_once __DIR__ . '/includes/functions.php';

// Pastikan user sedang login
require_login();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Pusat Simulasi Akun (Login-As)';

// Auto-Heal Safeguard: Jika user_id aktif sama dengan impersonator['user_id'], hapus status simulasi
if (is_impersonating() && isset($_SESSION['impersonator']['id_user']) && (int)$user['id_user'] === (int)$_SESSION['impersonator']['id_user']) {
    unset($_SESSION['impersonator']);
}

// Handle POST: Impersonate atau Revert
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'revert' && is_impersonating()) {
        $realAdmin = $_SESSION['impersonator'];
        $simulatedName = $user['nama'];
        
        // Kembalikan sesi asli
        $_SESSION['user'] = $realAdmin;
        unset($_SESSION['impersonator']);

        log_activity('REVERT_ADMIN', "Admin {$realAdmin['nama']} mengakhiri simulasi akun {$simulatedName} dan kembali ke akun asli.");
        set_flash('info', "Anda telah kembali ke akun Administrator ({$realAdmin['nama']}).");
        redirect('dashboard.php');

    } elseif ($action === 'impersonate') {
        // Hanya admin yang boleh memulai impersonasi
        if (!is_admin() && !is_impersonating()) {
            set_flash('error', 'Hanya Administrator yang memiliki wewenang simulasi akun.');
            redirect('dashboard.php');
        }

        $targetId = (int)($_POST['target_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            set_flash('error', 'Pengguna target tidak ditemukan.');
            redirect('login_as.php');
        }

        if ((int)$targetUser['id_user'] === (int)$user['id_user']) {
            set_flash('warning', 'Anda sudah berada di akun tersebut.');
            redirect('login_as.php');
        }

        // Arsipkan snapshot admin asli jika belum sedang simulasi
        if (!is_impersonating()) {
            $_SESSION['impersonator'] = $user;
        }

        // Alihkan sesi ke target user
        unset($targetUser['password']);
        $_SESSION['user'] = $targetUser;

        log_activity('LOGIN_AS', "Simulasi akun aktif: Masuk sebagai {$targetUser['nama']} ({$targetUser['role']}).");
        set_flash('warning', "Mode Simulasi Aktif: Anda sekarang menjelajah sebagai {$targetUser['nama']} ({$targetUser['role']}).");
        redirect('dashboard.php');
    }
}

// Cek otorisasi untuk membuka halaman pusat simulasi
if (!is_admin() && !is_impersonating()) {
    set_flash('error', 'Halaman ini khusus untuk Administrator.');
    redirect('dashboard.php');
}

// Ambil daftar pengguna yang bisa disimulasikan
$search = trim((string)($_GET['q'] ?? ''));
$filterRole = $_GET['role'] ?? 'ALL';

$sql = "SELECT id_user, nip, nama, email, role, keahlian, foto_path FROM users WHERE 1=1";
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

$sql .= " ORDER BY nama ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$targets = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-5xl mx-auto space-y-6">

            <!-- Header Halaman -->
            <div>
                <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">
                    Pusat Simulasi Akun (Login-As)
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Gunakan fitur ini untuk melihat tampilan antarmuka dan menguji alur kerja persis seperti yang dialami dosen atau staf lain.
                </p>
            </div>

            <!-- Petunjuk Mode Simulasi -->
            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 text-xs text-amber-900 flex items-start gap-3">
                <i data-lucide="shield-alert" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                <div class="leading-relaxed">
                    <p class="font-bold">Keamanan & Audit Trail:</p>
                    <p class="mt-0.5 text-amber-800">
                        Seluruh aktivitas yang dilakukan saat berada dalam mode simulasi akan tetap tercatat di tabel audit trail (<code class="bg-amber-100 px-1 py-0.5 rounded font-mono">log_aktivitas</code>) dengan identitas Anda sebagai <em>impersonator</em>. Anda dapat kembali ke akun admin kapan saja melalui tombol kuning di bilah navigasi atas.
                    </p>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <form method="GET" action="<?= url('login_as.php') ?>" class="flex flex-wrap items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400"></i>
                        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari Nama / NIP..."
                               class="pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-300 rounded-xl text-slate-800 focus:outline-none focus:ring-1 focus:ring-maritime-700 w-64">
                    </div>

                    <select name="role" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        <option value="ALL" <?= $filterRole === 'ALL' ? 'selected' : '' ?>>Semua Peran</option>
                        <option value="Dosen" <?= $filterRole === 'Dosen' ? 'selected' : '' ?>>Dosen</option>
                        <option value="GKMP" <?= $filterRole === 'GKMP' ? 'selected' : '' ?>>GKMP</option>
                        <option value="Koor.Prodi" <?= $filterRole === 'Koor.Prodi' ? 'selected' : '' ?>>Koor. Prodi</option>
                    </select>
                </form>

                <div class="text-xs text-slate-500 font-medium">
                    Pilih target akun untuk disimulasikan
                </div>
            </div>

            <!-- Grid Pengguna Target -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($targets as $t): 
                    $isSelf = ((int)$t['id_user'] === (int)$user['id_user']);
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition flex flex-col justify-between <?= $isSelf ? 'ring-2 ring-maritime-700 bg-blue-50/20' : '' ?>">
                    <div>
                        <div class="flex items-center gap-3 mb-3">
                            <?php if (!empty($t['foto_path']) && file_exists(__DIR__ . '/' . ltrim($t['foto_path'], '/'))): ?>
                                <img src="<?= url($t['foto_path']) ?>" alt="Foto" class="w-10 h-10 rounded-full object-cover border border-slate-200 shadow-sm">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-cyan-600 to-blue-700 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                                    <?= strtoupper(substr($t['nama'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>

                            <div class="overflow-hidden">
                                <h3 class="font-heading font-bold text-sm text-slate-900 truncate" title="<?= e($t['nama']) ?>">
                                    <?= e($t['nama']) ?>
                                </h3>
                                <p class="text-[10px] text-slate-400 font-mono">NIP: <?= e($t['nip']) ?></p>
                            </div>
                        </div>

                        <div class="space-y-1 text-xs">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Peran:</span>
                                <span class="font-bold text-slate-700"><?= e($t['role']) ?></span>
                            </div>
                            <?php if (!empty($t['email'])): ?>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-slate-400">Email:</span>
                                <span class="text-slate-600 truncate max-w-[140px]"><?= e($t['email']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <?php if ($isSelf): ?>
                            <button disabled class="w-full py-2 bg-slate-100 text-slate-400 text-xs font-bold rounded-xl cursor-not-allowed">
                                Akun Anda Saat Ini
                            </button>
                        <?php else: ?>
                            <form action="<?= url('login_as.php') ?>" method="POST" class="m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="impersonate">
                                <input type="hidden" name="target_id" value="<?= $t['id_user'] ?>">
                                <button type="submit" class="w-full py-2 px-3 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs rounded-xl shadow-sm transition flex items-center justify-center gap-2">
                                    <i data-lucide="user-check" class="w-4 h-4"></i>
                                    Simulasikan Akun Ini
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
