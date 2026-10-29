<?php
declare(strict_types=1);

/**
 * SIMIA - Responsive Left Sidebar Navigation
 * Acuan: skills.md Bagian 4.2
 */

require_once __DIR__ . '/functions.php';

$user = auth_user();
$branding = get_branding_info();
$currentScript = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF']);
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';

// Helper menu aktif (Mendukung Pretty URLs dan .php)
function nav_active(string $targetScript, string $currentScript): string
{
    global $currentUri;
    $targetSlug = '/' . str_replace('_', '-', pathinfo($targetScript, PATHINFO_FILENAME));
    $isMatch = ($targetScript === $currentScript)
            || ($currentUri === $targetSlug)
            || ($currentUri === '/' . $targetScript)
            || ($currentUri === '/' . pathinfo($targetScript, PATHINFO_FILENAME));

    return $isMatch ? 'nav-active' : 'text-slate-400 hover:text-slate-100 hover:bg-abyssal-800/60 border-l-4 border-transparent';
}
?>

<!-- SIDEBAR DESKTOP (Permanent sticky w-64) -->
<aside class="hidden md:flex flex-col w-64 bg-abyssal-900 border-r border-slate-800 h-full z-30 select-none overflow-y-auto shrink-0">
    <!-- Konten Navigasi -->
    <div class="flex-1 py-4 px-3 space-y-6">
        <!-- 1. Grup Menu Utama -->
        <div>
            <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                Menu Utama
            </p>
            <nav class="space-y-1">
                <a href="<?= url('dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('dashboard.php', $currentScript) ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>Dashboard</span>
                </a>

                <a href="<?= url('profil.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('profil.php', $currentScript) ?>">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    <span>Profil Saya</span>
                </a>

                <a href="<?= url('matkul_saya.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('matkul_saya.php', $currentScript) ?>">
                    <i data-lucide="book-open" class="w-4 h-4"></i>
                    <span>Mata Kuliah Saya</span>
                </a>

                <a href="<?= url('informasi.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('informasi.php', $currentScript) ?>">
                    <i data-lucide="megaphone" class="w-4 h-4"></i>
                    <span>Papan Informasi</span>
                </a>
            </nav>
        </div>

        <?php if (is_admin()): ?>
        <!-- 2. Grup Manajemen Mutu & Data (Khusus Admin/GKMP/Super Admin) -->
        <div>
            <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-cyan-500/80 mb-2">
                Manajemen Instrumen & Mutu
            </p>
            <nav class="space-y-1">
                <a href="<?= url('rekap_progress.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('rekap_progress.php', $currentScript) ?>">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                    <span>Rekapitulasi Progress</span>
                </a>

                <a href="<?= url('assign_pengampu.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('assign_pengampu.php', $currentScript) ?>">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Penugasan Dosen</span>
                </a>

                <a href="<?= url('master_mk.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('master_mk.php', $currentScript) ?>">
                    <i data-lucide="library" class="w-4 h-4"></i>
                    <span>Master Mata Kuliah</span>
                </a>

                <a href="<?= url('master_ta.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('master_ta.php', $currentScript) ?>">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                    <span>Master Tahun Ajaran</span>
                </a>

                <a href="<?= url('master_semester.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('master_semester.php', $currentScript) ?>">
                    <i data-lucide="layers" class="w-4 h-4"></i>
                    <span>Master Semester</span>
                </a>

                <a href="<?= url('master_dosen.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('master_dosen.php', $currentScript) ?>">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>Sivitas & Akun Dosen</span>
                </a>

                <a href="<?= url('unduh_berkas.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('unduh_berkas.php', $currentScript) ?>">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                    <span>Unduh Arsip ZIP</span>
                </a>
            </nav>
        </div>

        <!-- 3. Grup Sistem & Keamanan -->
        <div>
            <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                Sistem & Keamanan
            </p>
                <?php if (is_super_admin()): ?>
                <a href="<?= url('manajemen_pengguna.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('manajemen_pengguna.php', $currentScript) ?>">
                    <i data-lucide="shield-alert" class="w-4 h-4 text-cyan-400"></i>
                    <div class="flex-1 flex items-center justify-between">
                        <span class="font-medium text-cyan-300">Manajemen Pengguna</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 font-bold tracking-wider uppercase border border-cyan-500/30">Super</span>
                    </div>
                </a>
                <?php endif; ?>

                <a href="<?= url('login_as.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('login_as.php', $currentScript) ?>">
                    <i data-lucide="user-check" class="w-4 h-4"></i>
                    <span>Simulasi Login-As</span>
                </a>

                <a href="<?= url('log_aktivitas.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('log_aktivitas.php', $currentScript) ?>">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span>Audit Log Aktivitas</span>
                </a>

                <a href="<?= url('pengaturan.php') ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition <?= nav_active('pengaturan.php', $currentScript) ?>">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span>Pengaturan Sistem</span>
                </a>
            </nav>
        </div>
        <?php endif; ?>

        <!-- 4. Ekosistem Tautan Digital -->
        <div class="pt-2 border-t border-slate-800/80">
            <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                Ekosistem Kelautan.ID
            </p>
            <nav class="space-y-1">
                <a href="<?= e($branding['portal_url']) ?>" target="_blank" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-cyan-300 hover:bg-abyssal-800 transition">
                    <span class="flex items-center gap-2">
                        <i data-lucide="globe" class="w-3.5 h-3.5"></i>
                        Portal Kelautan.ID
                    </span>
                    <i data-lucide="external-link" class="w-3 h-3 opacity-60"></i>
                </a>
                <a href="<?= e($branding['apps_url']) ?>" target="_blank" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-cyan-300 hover:bg-abyssal-800 transition">
                    <span class="flex items-center gap-2">
                        <i data-lucide="grid" class="w-3.5 h-3.5"></i>
                        Portal Apps
                    </span>
                    <i data-lucide="external-link" class="w-3 h-3 opacity-60"></i>
                </a>
            </nav>
        </div>
    </div>

    <!-- User Mini Badge Footer di Sidebar -->
    <?php if ($user): ?>
    <div class="p-3 bg-abyssal-950/70 border-t border-slate-800/80 flex items-center justify-between">
        <div class="flex items-center gap-2.5 overflow-hidden">
            <div class="w-7 h-7 rounded-full bg-cyan-700/50 text-cyan-300 flex items-center justify-center font-bold text-xs shrink-0">
                <?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="truncate">
                <p class="text-[11px] font-semibold text-slate-200 truncate"><?= e($user['nama']) ?></p>
                <p class="text-[9px] text-slate-400 truncate"><?= e($user['nip']) ?></p>
            </div>
        </div>
        <a href="<?= url('logout.php') ?>" title="Keluar" class="text-slate-400 hover:text-rose-400 p-1.5 rounded-lg hover:bg-abyssal-800 transition">
            <i data-lucide="power" class="w-4 h-4"></i>
        </a>
    </div>
    <?php endif; ?>
</aside>

<!-- MOBILE DRAWER NAVIGATION (Slide-out) -->
<div id="mobileDrawer" class="hidden fixed inset-0 z-50 transition-all duration-300 ease-in-out md:hidden">
    <!-- Backdrop Blur -->
    <div id="mobileDrawerBackdrop" class="fixed inset-0 bg-abyssal-950/80 backdrop-blur-sm transition-opacity"></div>

    <!-- Drawer Content -->
    <div class="relative flex flex-col w-72 max-w-[85vw] h-full bg-abyssal-900 border-r border-slate-800 shadow-2xl overflow-y-auto">
        <!-- Drawer Header -->
        <div class="flex items-center justify-between p-4 border-b border-slate-800">
            <div class="flex items-center gap-2">
                <img src="<?= e(get_logo_url()) ?>" alt="Logo" class="w-8 h-8 rounded-lg object-contain">
                <span class="font-heading font-extrabold text-base text-white">SIMIA</span>
            </div>
            <button id="btnCloseDrawer" class="text-slate-400 hover:text-white p-1 rounded-lg">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Navigation Links -->
        <div class="p-3 space-y-4">
            <nav class="space-y-1">
                <a href="<?= url('dashboard.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('dashboard.php', $currentScript) ?>">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= url('profil.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('profil.php', $currentScript) ?>">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    <span>Profil Saya</span>
                </a>
                <a href="<?= url('matkul_saya.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('matkul_saya.php', $currentScript) ?>">
                    <i data-lucide="book-open" class="w-4 h-4"></i>
                    <span>Mata Kuliah Saya</span>
                </a>
                <a href="<?= url('informasi.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('informasi.php', $currentScript) ?>">
                    <i data-lucide="megaphone" class="w-4 h-4"></i>
                    <span>Papan Informasi</span>
                </a>

                <?php if (is_admin()): ?>
                <div class="pt-2 border-t border-slate-800">
                    <p class="px-3 text-[10px] font-bold uppercase tracking-wider text-cyan-400 mb-1">Manajemen</p>
                    <a href="<?= url('rekap_progress.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('rekap_progress.php', $currentScript) ?>">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                        <span>Rekapitulasi Progress</span>
                    </a>
                    <a href="<?= url('assign_pengampu.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('assign_pengampu.php', $currentScript) ?>">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        <span>Penugasan Dosen</span>
                    </a>
                    <a href="<?= url('master_mk.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('master_mk.php', $currentScript) ?>">
                        <i data-lucide="library" class="w-4 h-4"></i>
                        <span>Master Mata Kuliah</span>
                    </a>
                    <a href="<?= url('master_ta.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('master_ta.php', $currentScript) ?>">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                        <span>Tahun Ajaran</span>
                    </a>
                    <a href="<?= url('master_semester.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('master_semester.php', $currentScript) ?>">
                        <i data-lucide="layers" class="w-4 h-4"></i>
                        <span>Semester</span>
                    </a>
                    <a href="<?= url('master_dosen.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('master_dosen.php', $currentScript) ?>">
                        <i data-lucide="users" class="w-4 h-4"></i>
                        <span>Sivitas Dosen</span>
                    </a>
                    <a href="<?= url('unduh_berkas.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('unduh_berkas.php', $currentScript) ?>">
                        <i data-lucide="archive" class="w-4 h-4"></i>
                        <span>Unduh Arsip ZIP</span>
                    </a>
                    <?php if (is_super_admin()): ?>
                    <a href="<?= url('manajemen_pengguna.php') ?>" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs <?= nav_active('manajemen_pengguna.php', $currentScript) ?>">
                        <span class="flex items-center gap-3">
                            <i data-lucide="shield-alert" class="w-4 h-4 text-cyan-400"></i>
                            <span class="text-cyan-300 font-medium">Manajemen Pengguna</span>
                        </span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 font-bold uppercase border border-cyan-500/30">Super</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= url('login_as.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('login_as.php', $currentScript) ?>">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                        <span>Simulasi Login-As</span>
                    </a>
                    <a href="<?= url('log_aktivitas.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('log_aktivitas.php', $currentScript) ?>">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        <span>Audit Log</span>
                    </a>
                    <a href="<?= url('pengaturan.php') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs <?= nav_active('pengaturan.php', $currentScript) ?>">
                        <i data-lucide="settings" class="w-4 h-4"></i>
                        <span>Pengaturan</span>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
        </div>

        <div class="mt-auto p-4 border-t border-slate-800">
            <a href="<?= url('logout.php') ?>" class="flex items-center justify-center gap-2 w-full py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-xs transition">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                Keluar
            </a>
        </div>
    </div>
</div>
