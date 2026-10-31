<?php
declare(strict_types=1);

/**
 * SIMIA - Lupa Kata Sandi
 * Acuan: skills.md & PRD SIMIA
 */

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Lupa Kata Sandi';
$branding = get_branding_info();

// Penanganan Form Permintaan Reset (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();

    $email = trim((string)($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Silakan masukkan alamat email yang valid.');
        redirect('lupa_password.php');
    }

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Cek status akun
        if (isset($user['status']) && $user['status'] === 'Nonaktif') {
            log_activity('RESET_PASS_DITOLAK', "Permintaan reset password ditolak: Akun {$user['nama']} ($email) berstatus Nonaktif.");
            set_flash('error', 'Akun Anda berstatus Nonaktif. Silakan hubungi Administrator untuk aktivasi.');
            redirect('lupa_password.php');
        }

        // Buat token acak aman (64 karakter hex)
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 Jam

        // Batalkan token lama yang belum digunakan untuk email ini
        $cancelStmt = $pdo->prepare("UPDATE password_resets SET used_at = ? WHERE id_user = ? AND used_at IS NULL");
        $cancelStmt->execute([date('Y-m-d H:i:s'), $user['id_user']]);

        // Simpan token baru
        $insStmt = $pdo->prepare("
            INSERT INTO password_resets (id_user, email, token, expires_at)
            VALUES (?, ?, ?, ?)
        ");
        $insStmt->execute([$user['id_user'], $user['email'], $token, $expiresAt]);

        // Kirim email via Mailer Engine
        $mailResult = send_password_reset_email($user, $token);

        log_activity('REQUEST_RESET_PASS', "Permintaan reset kata sandi untuk {$user['nama']} ($email). Pengiriman: " . ($mailResult['success'] ? 'Sukses' : 'Gagal: ' . $mailResult['message']));

        if ($mailResult['success']) {
            set_flash('success', "Tautan reset kata sandi berhasil dikirimkan ke <strong>" . e($email) . "</strong>. Silakan periksa kotak masuk atau folder spam Anda.");
        } else {
            // Jika mail gagal (misal server SMTP lokal belum dikonfigurasi), berikan feedback informatif
            set_flash('warning', "Tautan reset dibuat, namun server email melaporkan kendala: {$mailResult['message']}. Silakan hubungi Administrator atau coba sesaat lagi.");
        }

        redirect('login.php');
    } else {
        // Demi alasan keamanan (pencegahan user enumeration), tampilkan pesan netral
        log_activity('REQUEST_RESET_PASS_GAGAL', "Permintaan reset kata sandi gagal: Email '$email' tidak terdaftar.");
        set_flash('info', "Jika alamat email Anda terdaftar di sistem SIMIA, tautan pengaturan ulang kata sandi telah dikirimkan ke kotak masuk Anda.");
        redirect('login.php');
    }
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
                    <?= $flash['message'] ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Card Bersih & Elegan -->
        <div class="bg-white py-8 px-6 sm:px-10 rounded-3xl shadow-xl border border-slate-200/80">
            <div class="mb-6 flex items-start gap-3">
                <div class="p-2.5 bg-blue-50 rounded-2xl text-maritime-700 shrink-0">
                    <i data-lucide="key-round" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="font-heading font-bold text-lg text-slate-900">Lupa Kata Sandi?</h2>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Masukkan alamat email akun Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.
                    </p>
                </div>
            </div>

            <form action="<?= url('lupa_password.php') ?>" method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <!-- Input Email -->
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                        Alamat Email Terdaftar <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </div>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               placeholder="nama@kelautan.ac.id"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-maritime-700 focus:bg-white transition">
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1.5">
                        Tautan reset akan berlaku selama 60 menit sejak email dikirimkan.
                    </p>
                </div>

                <!-- Tombol Submit -->
                <div>
                    <button type="submit" class="w-full py-3 px-4 bg-maritime-700 hover:bg-maritime-800 text-white font-heading font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        Kirim Tautan Reset Sandi
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                <a href="<?= url('login.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-maritime-700 transition">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Kembali ke Halaman Masuk
                </a>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">
            <?= e($branding['copyright']) ?>
        </p>
    </div>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
