<?php
declare(strict_types=1);

/**
 * SIMIA - Papan Informasi & Microblog Pengumuman
 * Acuan: PRD SIMIA FR-2.1 & skills.md
 */

require_once __DIR__ . '/includes/functions.php';
require_login();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Papan Informasi & Pengumuman';

// Handle POST (Tambah Pengumuman, Hapus Pengumuman)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && is_admin()) {
        $judul = trim((string)($_POST['judul'] ?? ''));
        $isi = trim((string)($_POST['isi'] ?? ''));
        $deadline = !empty($_POST['deadline']) ? trim((string)$_POST['deadline']) : null;

        if (empty($judul) || empty($isi)) {
            set_flash('error', 'Judul dan isi pengumuman tidak boleh kosong.');
            redirect('informasi.php');
        }

        $ins = $pdo->prepare("
            INSERT INTO informasi (judul, isi, deadline, pembuat_id, pembuat_nama, tgl_posting)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $ins->execute([$judul, $isi, $deadline, $user['id_user'], $user['nama']]);

        log_activity('TAMBAH_KONTEN', "Posting pengumuman baru: {$judul}.");
        set_flash('success', 'Pengumuman baru berhasil dipublikasikan!');
        redirect('informasi.php');

    } elseif ($action === 'delete' && is_admin()) {
        $idInfo = (int)($_POST['id_info'] ?? 0);
        $del = $pdo->prepare("DELETE FROM informasi WHERE id_info = ?");
        $del->execute([$idInfo]);

        log_activity('HAPUS_KONTEN', "Hapus pengumuman ID: {$idInfo}.");
        set_flash('success', 'Pengumuman berhasil dihapus.');
        redirect('informasi.php');
    }
}

// Ambil Seluruh Informasi
$infoList = $pdo->query("SELECT * FROM informasi ORDER BY id_info DESC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Header Halaman -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">
                        Papan Informasi & Pengumuman
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Pemberitahuan akademik, tenggat waktu pengumpulan instrumen, dan koordinasi program studi.
                    </p>
                </div>
                <?php if (is_admin()): ?>
                <button type="button" onclick="openModal('modalAddInfo')"
                        class="px-4 py-2 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Tulis Pengumuman
                </button>
                <?php endif; ?>
            </div>

            <!-- List Pengumuman -->
            <div class="space-y-4">
                <?php if (!empty($infoList)): ?>
                    <?php foreach ($infoList as $info): ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 mb-3">
                            <div>
                                <h2 class="font-heading font-bold text-base text-slate-900 leading-snug">
                                    <?= e($info['judul']) ?>
                                </h2>
                                <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-2">
                                    <span>Diposting oleh <strong class="text-slate-700"><?= e($info['pembuat_nama']) ?></strong></span>
                                    <span>•</span>
                                    <span><?= tanggal_indo($info['tgl_posting'], true) ?></span>
                                </p>
                            </div>

                            <!-- Deadline Badge jika ada -->
                            <?php if (!empty($info['deadline']) && $info['deadline'] !== '-'): ?>
                            <div class="px-3 py-1.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-1.5 shrink-0">
                                <i data-lucide="calendar" class="w-4 h-4 text-rose-600"></i>
                                <span>Batas Akhir: <?= tanggal_indo($info['deadline']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Isi Pengumuman -->
                        <div class="text-xs text-slate-700 leading-relaxed whitespace-pre-line py-2 border-t border-slate-100">
                            <?= e($info['isi']) ?>
                        </div>

                        <!-- Footer Aksi jika Admin -->
                        <?php if (is_admin()): ?>
                        <div class="mt-4 pt-3 border-t border-slate-100 flex justify-end">
                            <form action="<?= url('informasi.php') ?>" method="POST"
                                  onsubmit="return confirm('Hapus pengumuman ini?')" class="inline m-0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id_info" value="<?= $info['id_info'] ?>">
                                <button type="submit" class="text-xs text-rose-600 hover:text-rose-700 font-bold flex items-center gap-1">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    Hapus
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
                        <i data-lucide="megaphone" class="w-10 h-10 mx-auto mb-2 text-slate-300"></i>
                        Belum ada informasi pengumuman yang dipublikasikan.
                    </div>
                <?php endif; ?>
            </div>

        </div>
        </div>

<!-- MODAL TAMBAH PENGUMUMAN (Khusus Admin) -->
<?php if (is_admin()): ?>
<div id="modalAddInfo" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <button type="button" onclick="closeModal('modalAddInfo')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <h3 class="font-heading font-extrabold text-base text-slate-900 mb-1">Tulis Pengumuman Baru</h3>
        <p class="text-xs text-slate-500 mb-4">Pesan akan ditampilkan di Dashboard dan Papan Informasi.</p>

        <form action="<?= url('informasi.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Judul Pengumuman</label>
                <input type="text" name="judul" required placeholder="Contoh: Pemberitahuan Unggah RPS Semester Ganjil"
                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tenggat Waktu / Deadline (Opsional)</label>
                <input type="date" name="deadline"
                       class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Isi Pesan / Instruksi</label>
                <textarea name="isi" required rows="5" placeholder="Tuliskan detail pengumuman secara rinci..."
                          class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700"></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalAddInfo')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" class="px-5 py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    Publikasikan
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
