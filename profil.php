<?php
declare(strict_types=1);

/**
 * SIMIA - Pengelolaan Profil Pengguna
 * Acuan: PRD SIMIA FR-1.3 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_login();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Profil Saya';

// Handle Update Profil (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();

    $action = $_POST['action'] ?? 'update_info';

    if ($action === 'update_info') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $keahlian = trim((string)($_POST['keahlian'] ?? ''));

        if (empty($nama)) {
            set_flash('error', 'Nama lengkap tidak boleh kosong.');
            redirect('profil.php');
        }

        // Upload Foto jika ada
        $fotoPath = $user['foto_path'];
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto'];
            $maxBytes = 2 * 1024 * 1024; // 2MB
            if ($file['size'] > $maxBytes) {
                set_flash('error', 'Ukuran foto profil maksimal 2 MB.');
                redirect('profil.php');
            }

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            if (!in_array($mime, $allowedMimes, true)) {
                set_flash('error', 'Format foto harus berupa JPG, PNG, atau WebP.');
                redirect('profil.php');
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'avatar_' . $user['id_user'] . '_' . time() . '.' . $ext;
            $destination = __DIR__ . '/storage/uploads/avatars/' . $fileName;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $fotoPath = 'storage/uploads/avatars/' . $fileName;
            }
        }

        $stmt = $pdo->prepare("
            UPDATE users 
            SET nama = ?, email = ?, keahlian = ?, foto_path = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id_user = ?
        ");
        $stmt->execute([$nama, $email, $keahlian, $fotoPath, $user['id_user']]);

        // Perbarui sesi
        $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
        $stmtUser->execute([$user['id_user']]);
        $_SESSION['user'] = $stmtUser->fetch();
        unset($_SESSION['user']['password']);

        log_activity('UPDATE_PROFIL', "Pengguna {$nama} memperbarui informasi profil.");
        set_flash('success', 'Informasi profil berhasil diperbarui.');
        redirect('profil.php');

    } elseif ($action === 'change_password') {
        $oldPass = (string)($_POST['old_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        if (strlen($newPass) < 6) {
            set_flash('error', 'Kata sandi baru minimal 6 karakter.');
            redirect('profil.php');
        }

        if ($newPass !== $confirmPass) {
            set_flash('error', 'Konfirmasi kata sandi baru tidak cocok.');
            redirect('profil.php');
        }

        // Cek password lama dari DB
        $stmtPass = $pdo->prepare("SELECT password FROM users WHERE id_user = ?");
        $stmtPass->execute([$user['id_user']]);
        $currentHashed = (string)$stmtPass->fetchColumn();

        if (!password_verify($oldPass, $currentHashed) && $oldPass !== $currentHashed) {
            set_flash('error', 'Kata sandi saat ini tidak tepat.');
            redirect('profil.php');
        }

        $newHashed = password_hash($newPass, PASSWORD_BCRYPT);
        $upStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id_user = ?");
        $upStmt->execute([$newHashed, $user['id_user']]);

        log_activity('GANTI_PASSWORD', "Pengguna {$user['nama']} berhasil mengganti kata sandi.");
        set_flash('success', 'Kata sandi berhasil diubah! Gunakan kata sandi baru untuk login berikutnya.');
        redirect('profil.php');
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Header Halaman -->
            <div>
                <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">Profil Pengguna</h1>
                <p class="text-xs text-slate-500 mt-1">Kelola data diri, keahlian akademik, dan keamanan akun Anda.</p>
            </div>

            <!-- Kartu Info Ringkas -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center gap-6">
                <!-- Avatar Preview -->
                <div class="relative">
                    <?php if (!empty($user['foto_path']) && file_exists(__DIR__ . '/' . ltrim($user['foto_path'], '/'))): ?>
                        <img src="<?= url($user['foto_path']) ?>" alt="Avatar" class="w-24 h-24 rounded-full object-cover border-4 border-cyan-100 shadow-md">
                    <?php else: ?>
                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-cyan-600 to-blue-800 text-white flex items-center justify-center font-heading font-extrabold text-3xl shadow-md border-4 border-cyan-100">
                            <?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-center sm:text-left flex-1">
                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <h2 class="font-heading font-bold text-lg text-slate-900"><?= e($user['nama']) ?></h2>
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-blue-100 text-maritime-700">
                            <?= e($user['role']) ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-mono mt-0.5">NIP: <?= e($user['nip']) ?></p>
                    <p class="text-xs text-slate-600 mt-2">
                        Bidang Keahlian: <span class="font-medium text-slate-800"><?= e($user['keahlian'] ?: 'Belum diisi') ?></span>
                    </p>
                </div>
            </div>

            <!-- Form Edit Profil & Ganti Password -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- 1. Form Biodata Diri -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                    <h3 class="font-heading font-bold text-sm text-slate-900 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <i data-lucide="user-pen" class="w-4 h-4 text-maritime-700"></i>
                        Biodata & Kontak
                    </h3>

                    <form action="<?= url('profil.php') ?>" method="POST" enctype="multipart/form-data" class="space-y-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_info">

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">NIP (Terkunci)</label>
                            <input type="text" value="<?= e($user['nip']) ?>" disabled
                                   class="w-full bg-slate-100 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-500 cursor-not-allowed">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap & Gelar</label>
                            <input type="text" name="nama" required value="<?= e($user['nama']) ?>"
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Email</label>
                            <input type="email" name="email" value="<?= e($user['email']) ?>" placeholder="contoh@kelautan.id"
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 focus:bg-white transition">
                            <p class="text-[10px] text-slate-400 mt-0.5">Digunakan untuk menerima notifikasi & email reminder kelengkapan berkas.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Bidang Keahlian / Minat Riset</label>
                            <input type="text" name="keahlian" value="<?= e($user['keahlian']) ?>" placeholder="Misal: Oseanografi, SIG Maritim, Akustik"
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Ubah Foto Profil (Maks 2 MB)</label>
                            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp"
                                   class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100">
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i>
                                Simpan Perubahan Profil
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 2. Form Ganti Kata Sandi -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="font-heading font-bold text-sm text-slate-900 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                            <i data-lucide="key-round" class="w-4 h-4 text-maritime-700"></i>
                            Ganti Kata Sandi
                        </h3>

                        <form action="<?= url('profil.php') ?>" method="POST" class="space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_password">

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi Saat Ini</label>
                                <input type="password" name="old_password" required placeholder="••••••••"
                                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 focus:bg-white transition">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi Baru</label>
                                <input type="password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter"
                                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 focus:bg-white transition">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Konfirmasi Kata Sandi Baru</label>
                                <input type="password" name="confirm_password" required minlength="6" placeholder="Ketik ulang kata sandi baru"
                                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 focus:bg-white transition">
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center gap-2">
                                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                                    Perbarui Kata Sandi
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="mt-6 p-3 bg-blue-50 rounded-xl text-[11px] text-maritime-700 flex items-start gap-2">
                        <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
                        <span>Pastikan menggunakan kombinasi kata sandi yang aman dan tidak membagikannya kepada pihak lain.</span>
                    </div>
                </div>

            </div>

        </div>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
