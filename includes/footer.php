<?php
declare(strict_types=1);

/**
 * SIMIA - Page Footer, Toast Alerts & Client Assets
 * Acuan: skills.md Bagian 1 & 4
 */

require_once __DIR__ . '/functions.php';

$branding = get_branding_info();
$flash = get_flash();
?>

<!-- Official Footer (Inside Main Canvas, Never Blocking Left Sidebar) -->
<footer class="mt-auto bg-white border-t border-slate-200 py-6 px-4 sm:px-6 lg:px-8 text-xs text-slate-500">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
        <div>
            <p class="font-semibold text-slate-700">
                <?= e($branding['copyright']) ?>
            </p>
            <p class="text-[11px] text-slate-400 mt-0.5">
                Inisiator & Pengembang: <span class="font-medium text-slate-600"><?= e($branding['developer']) ?></span>
            </p>
        </div>
        <div class="flex flex-wrap items-center justify-center sm:justify-end gap-x-3 gap-y-1.5 text-slate-400">
            <?php foreach ($branding['footer_links'] as $idx => $link): ?>
                <?php if ($idx > 0): ?><span class="hidden sm:inline text-slate-300">•</span><?php endif; ?>
                <a href="<?= e($link['url']) ?>" 
                   target="<?= str_starts_with($link['url'], 'mailto:') ? '_self' : '_blank' ?>" 
                   rel="noopener noreferrer" 
                   class="hover:text-cyan-600 transition font-medium">
                    <?= e($link['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</footer>

    </main>
</div>

<!-- Toast / Flash Message Overlay -->
<?php if ($flash): ?>
<div class="fixed bottom-5 right-5 z-50 max-w-md w-full px-4 flash-alert">
    <?php
    $type = $flash['type'];
    $bg = match($type) {
        'success' => 'bg-emerald-600 text-white border-emerald-700',
        'error'   => 'bg-rose-600 text-white border-rose-700',
        'warning' => 'bg-amber-500 text-slate-900 border-amber-600',
        default   => 'bg-maritime-700 text-white border-maritime-800',
    };
    $icon = match($type) {
        'success' => 'check-circle',
        'error'   => 'alert-circle',
        'warning' => 'alert-triangle',
        default   => 'info',
    };
    ?>
    <div class="flex items-start gap-3 p-4 rounded-2xl shadow-xl border <?= $bg ?> animate-bounce-in">
        <i data-lucide="<?= $icon ?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
        <div class="flex-1 text-xs">
            <p class="font-bold text-sm mb-0.5"><?= ucfirst($type) ?></p>
            <p class="leading-relaxed"><?= e($flash['message']) ?></p>
        </div>
        <button onclick="this.closest('.flash-alert').remove()" class="text-white/80 hover:text-white p-1 rounded-lg">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
</div>
<?php endif; ?>

<!-- Core JavaScript -->
<script src="<?= url('public/assets/js/app.js') ?>"></script>
<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
</body>
</html>
