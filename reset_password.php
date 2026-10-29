<?php
declare(strict_types=1);

/**
 * SIMIA - Eksekusi Reset Kata Sandi Pengguna
 * Acuan: skills.md & PRD SIMIA
 */

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Atur Ulang Kata Sandi';
$branding = get_branding_info();
$pdo = Database::getConnection();

$token = trim((string)($_GET['token'] ?? ($_POST['token'] ?? '')));

// Validasi Keberadaan Token di Database
$resetRecord = null;
$tokenError = null;

if (empty($token)) {
    $tokenError = 'Tautan reset tidak memiliki token verifikasi yang valid.';
} else {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.nama, u.nip, u.email as user_email, u.status as user_status
        FROM password_resets pr
        JOIN users u ON pr.id_user = u.id_user
        WHERE pr.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch();

    if (!$resetRecord) {
        $tokenError = 'Token reset tidak ditemukan atau tidak valid.';
    } elseif (!empty($resetRecord['used_at'])) {
        $tokenError = 'Tautan reset ini telah digunakan sebelumnya. Untuk keamanan, silakan ajukan permintaan reset baru.';
    } elseif (strtotime($resetRecord['expires_at']) < time()) {
        $tokenError = 'Masa berlaku tautan reset ini telah habis (kadaluarsa lebih dari 60 menit). Silakan ajukan permintaan baru.';
    } elseif (isset($resetRecord['user_status']) && $resetRecord['user_status'] === 'Nonaktif') {
        $tokenError = 'Akun Anda saat ini berstatus Nonaktif. Silakan hubungi Administrator untuk bantuan pemulihan akun.';
    }
}

// Penanganan Form Simpan Kata Sandi Baru (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tokenError && $resetRecord) {
    validate_csrf_or_abort();

    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (strlen($newPassword) < 6) {
        set_flash('error', 'Kata sandi baru minimal 6 karakter.');
        redirect('reset_password.php?token=' . urlencode($token));
    }

    if ($newPassword !== $confirmPassword) {
        set_flash('error', 'Konfirmasi kata sandi tidak cocok. Pastikan kedua kolom terisi sama.');
        redirect('reset_password.php?token=' . urlencode($token));
    }

    // Hash kata sandi baru dengan standar bcrypt
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    // 1. Update password di tabel users
    $upUser = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
    $upUser->execute([$newHash, $resetRecord['id_user']]);

    // 2. Tandai token sudah digunakan
    $upToken = $pdo->prepare("UPDATE password_resets SET used_at = datetime('now') WHERE id_reset = ?");
    $upToken->execute([$resetRecord['id_reset']]);

    log_activity('RESET_PASS_BERHASIL', "Kata sandi untuk {$resetRecord['nama']} ({$resetRecord['nip']}) berhasil diperbarui via token email.");

    set_flash('success', "Kata sandi berhasil diperbarui! Silakan masuk menggunakan kata sandi baru Anda.");
    redirect('login.php');
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e($branding['app_name']) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(get_favicon_url()) ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        heading: ['Outfit', 'sans-serif'],
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    colors: {
                        abyssal: { 950: '#060D18', 900: '#0A192F', 800: '#0F172A' },
                        maritime: { 900: '#1E3A8A', 700: '#1D4ED8', 600: '#2563EB', 500: '#0284C7' },
                        ocean: { DEFAULT: '#06B6D4', 400: '#38BDF8', cyan: '#22D3EE' }
                    }
                }
            }
        }
    </script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= url('public/assets/css/style.css') ?>">
</head>
<body class="min-h-full bg-gradient-to-br from-slate-50 via-sky-50 to-blue-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 selection:bg-cyan-500 selection:text-white">

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center px-4">
        <div class="inline-flex items-center justify-center mb-4 p-3 bg-white rounded-3xl shadow-md border border-slate-200/80">
            <img src="<?= e(get_logo_url()) ?>" alt="Logo" class="w-12 h-12 object-contain">
        </div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-abyssal-900 tracking-tight">
            <?= e($branding['app_name']) ?>
        </h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-600 font-medium">
            <?= e($branding['study_program']) ?> • <?= e($branding['institution']) ?>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4">
        <!-- Flash Alert Message -->
        <?php if ($flash): ?>
            <?php
            $bg = match($flash['type']) {
                'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'error'   => 'bg-rose-50 text-rose-800 border-rose-200',
                'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
                default   => 'bg-blue-50 text-blue-800 border-blue-200',
            };
            $icon = match($flash['type']) {
                'success' => 'check-circle',
                'error'   => 'alert-circle',
                'warning' => 'alert-triangle',
                default   => 'info',
            };
            ?>
            <div class="mb-5 p-4 rounded-2xl border text-xs flex items-start gap-3 shadow-sm <?= $bg ?>">
                <i data-lucide="<?= $icon ?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
                <div class="flex-1 leading-relaxed">
                    <?= e($flash['message']) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tokenError): ?>
            <!-- Card Token Tidak Valid / Kadaluarsa -->
            <div class="bg-white py-8 px-6 sm:px-10 rounded-3xl shadow-xl border border-rose-200 text-center space-y-4">
                <div class="w-14 h-14 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center mx-auto">
                    <i data-lucide="alert-octagon" class="w-7 h-7"></i>
                </div>
                <div>
                    <h2 class="font-heading font-bold text-lg text-slate-900">Tautan Tidak Berlaku</h2>
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                        <?= e($tokenError) ?>
                    </p>
                </div>
                <div class="pt-3">
                    <a href="<?= url('lupa_password.php') ?>" class="inline-flex items-center justify-center gap-2 w-full py-2.5 px-4 bg-maritime-700 hover:bg-maritime-800 text-white font-semibold text-xs rounded-xl shadow transition">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        Ajukan Permintaan Reset Baru
                    </a>
                </div>
                <div>
                    <a href="<?= url('login.php') ?>" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-800 transition">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        Kembali ke Halaman Masuk
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Form Buat Kata Sandi Baru -->
            <div class="bg-white py-8 px-6 sm:px-10 rounded-3xl shadow-xl border border-slate-200/80">
                <div class="mb-6 flex items-start gap-3">
                    <div class="p-2.5 bg-blue-50 rounded-2xl text-maritime-700 shrink-0">
                        <i data-lucide="shield-check" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h2 class="font-heading font-bold text-lg text-slate-900">Buat Kata Sandi Baru</h2>
                        <p class="text-xs text-slate-500 mt-1">
                            Akun: <strong class="text-slate-700"><?= e($resetRecord['nama']) ?></strong> (NIP: <?= e($resetRecord['nip']) ?>)
                        </p>
                    </div>
                </div>

                <form action="<?= url('reset_password.php') ?>" method="POST" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <!-- Input Password Baru -->
                    <div>
                        <label for="new_password" class="block text-xs font-bold text-slate-700 mb-1">
                            Kata Sandi Baru <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                            </div>
                            <input type="password" id="new_password" name="new_password" required minlength="6"
                                   placeholder="Minimal 6 karakter"
                                   class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-maritime-700 focus:bg-white transition font-mono">
                            <button type="button" onclick="togglePass('new_password', 'eye1')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                                <i data-lucide="eye" id="eye1" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Input Konfirmasi Password -->
                    <div>
                        <label for="confirm_password" class="block text-xs font-bold text-slate-700 mb-1">
                            Konfirmasi Kata Sandi Baru <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="check-check" class="w-4 h-4"></i>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                   placeholder="Ulangi kata sandi baru"
                                   class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-maritime-700 focus:bg-white transition font-mono">
                            <button type="button" onclick="togglePass('confirm_password', 'eye2')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                                <i data-lucide="eye" id="eye2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" class="w-full py-3 px-4 bg-maritime-700 hover:bg-maritime-800 text-white font-heading font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Simpan Kata Sandi Baru
                        </button>
                    </div>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                    <a href="<?= url('login.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-maritime-700 transition">
                        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                        Batal & Kembali ke Halaman Masuk
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <p class="mt-8 text-center text-xs text-slate-500">
            <?= e($branding['copyright']) ?>
        </p>
    </div>

    <script>
        function togglePass(id, eyeId) {
            const input = document.getElementById(id);
            const icon = document.getElementById(eyeId);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
