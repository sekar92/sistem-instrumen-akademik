<?php
declare(strict_types=1);

/**
 * SIMIA - Halaman Masuk (Login)
 * Acuan: skills.md Bagian 5.1 & PRD SIMIA FR-1.1
 */

require_once __DIR__ . '/includes/functions.php';

// Jika sudah login, langsung ke dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Masuk ke Sistem';
$branding = get_branding_info();

// Penanganan Form Login (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();

    $nip = trim((string)($_POST['nip'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember_me']);

    if (empty($nip) || empty($password)) {
        set_flash('error', 'Silakan masukkan NIP dan Kata Sandi Anda.');
        redirect('login.php');
    }

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ? LIMIT 1");
    $stmt->execute([$nip]);
    $user = $stmt->fetch();

    if (!$user) {
        log_activity('LOGIN_GAGAL', "Percobaan login gagal: NIP '$nip' tidak ditemukan.");
        set_flash('error', 'NIP tidak terdaftar dalam sistem. Periksa kembali atau hubungi Administrator.');
        redirect('login.php');
    }

    // Cek Status Akun Pengguna
    if (isset($user['status']) && $user['status'] === 'Nonaktif') {
        log_activity('LOGIN_DITOLAK', "Percobaan login ditolak: Akun NIP '$nip' ({$user['nama']}) berstatus Nonaktif.", $user);
        set_flash('error', 'Akun Anda berstatus Nonaktif. Silakan hubungi Super Admin (GKMP) untuk aktivasi.');
        redirect('login.php');
    }

    // Verifikasi Password (Mendukung password_hash bcrypt atau fallback plaintext jika migrasi dari GAS)
    $passwordValid = false;
    if (password_verify($password, $user['password'])) {
        $passwordValid = true;
    } elseif ($user['password'] === $password) {
        // Fallback untuk password lama dari Sheets yang belum di-hash, lalu otomatis re-hash ke bcrypt
        $passwordValid = true;
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $upStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id_user = ?");
        $upStmt->execute([$newHash, $user['id_user']]);
    }

    if (!$passwordValid) {
        log_activity('LOGIN_GAGAL', "Percobaan login gagal: Password salah untuk NIP '$nip' ({$user['nama']}).", $user);
        set_flash('error', 'Kata sandi salah. Silakan coba kembali.');
        redirect('login.php');
    }

    // Login Berhasil
    unset($user['password']); // Bersihkan password dari memori sesi
    $_SESSION['user'] = $user;
    $_SESSION['_last_activity'] = time();

    // Penanganan Remember Me (30 Hari)
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie('simia_remember_token', $token, [
            'expires'  => time() + (30 * 86400),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
        ]);
    } else {
        // Hapus remember token jika tidak dicentang
        if (isset($_COOKIE['simia_remember_token'])) {
            setcookie('simia_remember_token', '', time() - 3600, '/');
        }
    }

    log_activity('LOGIN', "Pengguna {$user['nama']} ({$user['role']}) berhasil masuk.");
    set_flash('success', "Selamat datang kembali, {$user['nama']}!");
    redirect('dashboard.php');
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
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= url('public/assets/css/style.css') ?>">
</head>
<body class="min-h-full bg-gradient-to-br from-slate-50 via-sky-50 to-blue-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 selection:bg-cyan-500 selection:text-white">

    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center px-4">
        <!-- Logo & Brand Header -->
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

        <!-- Form Card Bersih & Kontras Tinggi -->
        <div class="bg-white py-8 px-6 sm:px-10 rounded-3xl shadow-xl border border-slate-200/80">
            <div class="mb-6">
                <h2 class="font-heading font-bold text-lg text-slate-900">Masuk ke Akun Anda</h2>
                <p class="text-xs text-slate-500 mt-0.5">Gunakan NIP dan kata sandi yang telah terdaftar.</p>
            </div>

            <form action="<?= url('login.php') ?>" method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <!-- Input NIP -->
                <div>
                    <label for="nip" class="block text-xs font-bold text-slate-700 mb-1">
                        Nomor Induk Pegawai (NIP)
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="badge-check" class="w-4 h-4"></i>
                        </div>
                        <input type="text" id="nip" name="nip" required autocomplete="username"
                               placeholder="Contoh: 198501012010121001"
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-maritime-700 focus:bg-white transition">
                    </div>
                </div>

                <!-- Input Password -->
                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 mb-1">
                        Kata Sandi
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-maritime-700 focus:bg-white transition">
                        <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                            <i data-lucide="eye" id="eyeIcon" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Checkbox Ingat Saya & Lupa Kata Sandi -->
                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center gap-2 cursor-pointer select-none text-slate-600">
                        <input type="checkbox" name="remember_me" value="1" class="w-4 h-4 rounded text-maritime-700 focus:ring-maritime-700 border-slate-300">
                        <span>Ingat saya (30 Hari)</span>
                    </label>
                    <a href="<?= url('lupa_password.php') ?>" class="font-semibold text-maritime-700 hover:text-cyan-600 transition">
                        Lupa Kata Sandi?
                    </a>
                </div>

                <!-- Tombol Submit -->
                <div>
                    <button type="submit" class="w-full py-3 px-4 bg-maritime-700 hover:bg-maritime-800 text-white font-heading font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Masuk Sekarang
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer Copyright -->
        <p class="mt-8 text-center text-xs text-slate-500">
            <?= e($branding['copyright']) ?>
        </p>
    </div>

    <script>
        function togglePasswordVisibility() {
            const pass = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pass.type === 'password') {
                pass.type = 'text';
            } else {
                pass.type = 'password';
            }
        }

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
