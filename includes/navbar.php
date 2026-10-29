<?php
declare(strict_types=1);

/**
 * SIMIA - Top Header & 2-Tier Navigation Bar
 * Acuan: skills.md Bagian 4.1
 */

require_once __DIR__ . '/functions.php';

$user = auth_user();
$impersonator = impersonator_user();
$branding = get_branding_info();
$activeTaSem = get_active_ta_semester();
?>

<!-- 1. Bilah Mode Simulasi (Login-As Alert Bar) -->
<?php if (is_impersonating()): ?>
<div class="sticky top-0 z-50 bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 text-slate-900 px-4 py-2 text-xs font-bold shadow-md flex items-center justify-between">
    <div class="flex items-center gap-2">
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-600 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-600"></span>
        </span>
        <span>
            Mode Simulasi Aktif: Anda sedang mengakses sebagai 
            <span class="underline uppercase tracking-wider"><?= e($user['nama']) ?> (<?= e($user['role']) ?>)</span>
            oleh Admin: <span class="italic font-semibold"><?= e($impersonator['nama'] ?? 'Admin') ?></span>.
        </span>
    </div>
    <form action="<?= url('login_as.php') ?>" method="POST" class="inline m-0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="revert">
        <button type="submit" class="bg-abyssal-900 hover:bg-black text-amber-300 hover:text-white px-3 py-1 rounded-md text-xs font-bold transition flex items-center gap-1 shadow-sm">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
            Kembali ke Akun Admin
        </button>
    </form>
</div>
<?php endif; ?>

<!-- 2. Top Header (h-16, bg-abyssal-900) -->
<header class="sticky <?= is_impersonating() ? 'top-8' : 'top-0' ?> z-40 h-16 bg-abyssal-900 border-b border-slate-800 text-white flex items-center justify-between px-4 sm:px-6 lg:px-8 shadow-sm">
    <!-- Left: Hamburger (mobile) + Logo & Brand -->
    <div class="flex items-center gap-3 sm:gap-4">
        <!-- Hamburger Button Mobile -->
        <button type="button" id="btnMobileMenu" class="md:hidden text-slate-300 hover:text-white focus:outline-none p-1.5 rounded-lg hover:bg-abyssal-800">
            <i data-lucide="menu" class="w-6 h-6"></i>
        </button>

        <!-- Brand Identity -->
        <a href="<?= url('dashboard.php') ?>" class="flex items-center gap-3 group">
            <img src="<?= e(get_logo_url()) ?>" alt="Logo" class="w-9 h-9 rounded-xl shadow-md transition-transform group-hover:scale-105 object-contain">
            <div class="hidden sm:block">
                <div class="flex items-center gap-2">
                    <span class="font-heading font-extrabold text-base tracking-tight text-white group-hover:text-cyan-300 transition-colors">SIMIA</span>
                    <span class="text-[10px] font-semibold bg-cyan-950/80 text-cyan-300 border border-cyan-800/80 px-2 py-0.5 rounded-full">Kelautan.ID</span>
                </div>
                <p class="text-[10px] text-slate-400 font-medium tracking-tight truncate max-w-xs md:max-w-md">
                    <?= e($branding['study_program']) ?>
                </p>
            </div>
        </a>
    </div>

    <!-- Center/Right: Active Semester Badge & Profile Dropdown -->
    <div class="flex items-center gap-3 sm:gap-4">
        <!-- Badge Semester Aktif -->
        <div class="hidden md:flex items-center gap-2 bg-abyssal-800/90 border border-slate-700/80 px-3 py-1.5 rounded-full text-xs text-slate-200">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span class="text-[11px] font-medium text-slate-300">T.A. Aktif:</span>
            <span class="font-bold text-white"><?= e($activeTaSem['label']) ?></span>
        </div>

        <?php if ($user): ?>
        <!-- User Profile Dropdown -->
        <div class="relative">
            <button type="button" id="profileDropdownBtn" class="flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-abyssal-800 transition focus:outline-none">
                <!-- Avatar User -->
                <?php if (!empty($user['foto_path']) && file_exists(__DIR__ . '/../' . ltrim($user['foto_path'], '/'))): ?>
                    <img src="<?= url($user['foto_path']) ?>" alt="Foto" class="w-8 h-8 rounded-full object-cover border border-cyan-500/50 shadow-sm">
                <?php else: ?>
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-cyan-600 to-blue-700 text-white flex items-center justify-center font-heading font-bold text-xs shadow-sm">
                        <?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div class="hidden lg:block text-left">
                    <p class="text-xs font-semibold text-slate-200 leading-tight max-w-[150px] truncate">
                        <?= e($user['nama']) ?>
                    </p>
                    <span class="text-[10px] text-cyan-400 font-medium">
                        <?= e($user['role']) ?>
                    </span>
                </div>
                <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
            </button>

            <!-- Dropdown Menu -->
            <div id="profileDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-abyssal-900 border border-slate-700/80 rounded-2xl shadow-xl py-2 z-50 text-slate-200">
                <div class="px-4 py-2 border-b border-slate-800">
                    <p class="text-xs font-bold text-white truncate"><?= e($user['nama']) ?></p>
                    <p class="text-[11px] text-slate-400 truncate"><?= e($user['email'] ?: $user['nip']) ?></p>
                    <span class="inline-block mt-1 text-[9px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-blue-950 text-blue-300 border border-blue-800">
                        <?= e($user['role']) ?>
                    </span>
                </div>

                <div class="py-1 text-xs">
                    <a href="<?= url('profil.php') ?>" class="flex items-center gap-2.5 px-4 py-2 hover:bg-abyssal-800 text-slate-300 hover:text-white transition">
                        <i data-lucide="user" class="w-4 h-4 text-cyan-400"></i>
                        <span>Profil Saya</span>
                    </a>
                    
                    <a href="<?= url('matkul_saya.php') ?>" class="flex items-center gap-2.5 px-4 py-2 hover:bg-abyssal-800 text-slate-300 hover:text-white transition">
                        <i data-lucide="book-open" class="w-4 h-4 text-cyan-400"></i>
                        <span>Mata Kuliah Saya</span>
                    </a>

                    <?php if (is_admin()): ?>
                    <a href="<?= url('log_aktivitas.php') ?>" class="flex items-center gap-2.5 px-4 py-2 hover:bg-abyssal-800 text-slate-300 hover:text-white transition">
                        <i data-lucide="shield-alert" class="w-4 h-4 text-amber-400"></i>
                        <span>Audit Log Sistem</span>
                    </a>
                    <a href="<?= url('pengaturan.php') ?>" class="flex items-center gap-2.5 px-4 py-2 hover:bg-abyssal-800 text-slate-300 hover:text-white transition">
                        <i data-lucide="settings" class="w-4 h-4 text-slate-400"></i>
                        <span>Pengaturan Sistem</span>
                    </a>
                    <?php endif; ?>
                </div>

                <div class="border-t border-slate-800 pt-1">
                    <a href="<?= url('logout.php') ?>" class="flex items-center gap-2.5 px-4 py-2 text-rose-400 hover:bg-rose-950/40 hover:text-rose-300 transition text-xs font-semibold">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        <span>Keluar Sistem</span>
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <a href="<?= url('login.php') ?>" class="bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition">
            Masuk
        </a>
        <?php endif; ?>
    </div>
</header>
