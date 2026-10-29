<?php
/**
 * SIMIA - Partial Card Mata Kuliah
 * Digunakan oleh matkul_saya.php untuk rendering kartu mata kuliah
 */
?>
<div class="bg-white rounded-2xl border <?= $k['is_lampau'] ? 'border-amber-200/80 bg-gradient-to-b from-white to-amber-50/20' : 'border-slate-200' ?> shadow-sm hover:shadow-md transition p-5 flex flex-col justify-between group relative overflow-hidden">
    
    <!-- Top Corner Glow Accent for Lampau -->
    <?php if ($k['is_lampau']): ?>
    <div class="absolute top-0 right-0 w-24 h-24 bg-amber-100/40 rounded-full blur-2xl -mr-8 -mt-8 pointer-events-none"></div>
    <?php endif; ?>

    <div>
        <!-- Header Card: Kode MK, SKS, dan Periode Badge -->
        <div class="flex items-start justify-between gap-2 mb-3">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="font-mono text-[11px] font-bold px-2.5 py-1 rounded-lg bg-blue-50 text-maritime-700 border border-blue-100">
                    <?= e($k['kode_mk']) ?>
                </span>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600">
                    <?= (int)$k['sks'] ?> SKS
                </span>
            </div>

            <?php if ($k['is_aktif']): ?>
            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <?= e($k['tahun']) ?> • <?= e($k['semester']) ?>
            </span>
            <?php else: ?>
            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-200 flex items-center gap-1" title="Mata Kuliah Periode Lampau">
                <i data-lucide="archive" class="w-3 h-3 text-amber-600"></i>
                Lampau • <?= e($k['tahun']) ?> • <?= e($k['semester']) ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- Judul & Deskripsi MK -->
        <h3 class="font-heading font-bold text-base text-slate-900 group-hover:text-maritime-700 transition leading-snug">
            <?= e($k['nama_mk']) ?>
        </h3>

        <?php if (!empty($k['deskripsi'])): ?>
        <p class="text-xs text-slate-500 mt-2 line-clamp-2 leading-relaxed">
            <?= e($k['deskripsi']) ?>
        </p>
        <?php endif; ?>

        <!-- Info Periode Tambahan untuk Lampau -->
        <?php if ($k['is_lampau'] && !$k['is_complete']): ?>
        <div class="mt-3 p-2.5 rounded-xl bg-amber-50 border border-amber-200/80 text-[11px] text-amber-800 flex items-center gap-2">
            <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0"></i>
            <span>Ruang unggah tetap aktif untuk melengkapi dokumen.</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Progress Bar & Link Aksi -->
    <div class="mt-6 pt-4 border-t border-slate-100">
        <div class="flex items-center justify-between text-xs mb-1.5">
            <span class="text-slate-500 font-medium text-[11px]">Kelengkapan Instrumen:</span>
            <div class="flex items-center gap-1.5">
                <span class="font-bold text-xs <?= $k['percentage'] === 100 ? 'text-emerald-600' : ($k['percentage'] >= 50 ? 'text-amber-600' : 'text-rose-600') ?>">
                    <?= $k['uploaded_count'] ?>/<?= TOTAL_STANDAR_FILE ?> (<?= $k['percentage'] ?>%)
                </span>
            </div>
        </div>

        <!-- Bar Progress -->
        <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden mb-3">
            <div class="h-full rounded-full transition-all duration-500 <?= $k['percentage'] === 100 ? 'bg-emerald-500' : ($k['percentage'] >= 50 ? 'bg-amber-500' : 'bg-rose-500') ?>"
                 style="width: <?= $k['percentage'] ?>%;"></div>
        </div>

        <!-- Sub Status Badge -->
        <div class="flex items-center justify-between gap-2 mb-3">
            <?php if ($k['is_complete']): ?>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100 flex items-center gap-1">
                <i data-lucide="check-circle" class="w-3 h-3 text-emerald-600"></i>
                Instrumen Lengkap (100%)
            </span>
            <?php else: ?>
            <span class="text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-100 flex items-center gap-1">
                <i data-lucide="clock" class="w-3 h-3 text-amber-600"></i>
                Perlu <?= $k['missing_count'] ?> berkas lagi
            </span>
            <?php endif; ?>

            <span class="text-[10px] text-slate-400 font-medium">
                <?= $k['is_aktif'] ? 'Periode Berjalan' : 'Arsip Lampau' ?>
            </span>
        </div>

        <!-- Button Buka Ruang Unggah -->
        <a href="<?= url('upload_instrumen.php?id_pengampu=' . $k['id_pengampu']) ?>"
           class="w-full py-2.5 px-4 <?= $k['is_complete'] ? 'bg-slate-100 hover:bg-slate-200 text-slate-800' : 'bg-maritime-700 hover:bg-maritime-800 text-white shadow-sm' ?> font-bold text-xs rounded-xl transition flex items-center justify-center gap-2 group-hover:shadow">
            <i data-lucide="folder-open" class="w-4 h-4"></i>
            <span><?= $k['is_complete'] ? 'Lihat Ruang Unggah' : ($k['is_lampau'] ? 'Lengkapi Instrumen Lampau' : 'Buka Ruang Unggah') ?></span>
        </a>
    </div>
</div>
