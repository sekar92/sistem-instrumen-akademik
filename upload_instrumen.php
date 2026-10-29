<?php
declare(strict_types=1);

/**
 * SIMIA - Ruang Unggah Kelengkapan Instrumen Perkuliahan
 * Acuan: PRD SIMIA FR-4.2, FR-4.3 & skills.md Bagian 5.3 (8 Kategori, 28 Sub-Kategori)
 */

require_once __DIR__ . '/includes/functions.php';
require_login();

$user = auth_user();
$pdo = Database::getConnection();
$branding = get_branding_info();

// Ambil ID Pengampu dari Query String
$idPengampu = (int)($_GET['id_pengampu'] ?? 0);
if ($idPengampu <= 0) {
    set_flash('error', 'Parameter penugasan kelas tidak valid.');
    redirect('matkul_saya.php');
}

// Ambil Detail Penugasan
$stmt = $pdo->prepare("
    SELECT p.id_pengampu, p.id_user, p.id_ta, p.semester, m.id_mk, m.kode_mk, m.nama_mk, m.sks, t.tahun, t.status as status_ta, u.nama as nama_dosen, u.nip
    FROM pengampu p
    JOIN mata_kuliah m ON p.id_mk = m.id_mk
    JOIN tahun_ajaran t ON p.id_ta = t.id_ta
    JOIN users u ON p.id_user = u.id_user
    WHERE p.id_pengampu = ?
");
$stmt->execute([$idPengampu]);
$pengampu = $stmt->fetch();

if (!$pengampu) {
    set_flash('error', 'Data penugasan kelas tidak ditemukan.');
    redirect('matkul_saya.php');
}

// Deteksi Periode Aktif vs Lampau
$activeTaSem = get_active_ta_semester();
$activeIdTa = (int)($activeTaSem['ta']['id_ta'] ?? 0);
$isLampau = ((int)$pengampu['id_ta'] !== $activeIdTa || $pengampu['status_ta'] !== 'Aktif');

// Cek hak akses: Dosen pemilik atau Admin/GKMP/Super Admin
if ($pengampu['id_user'] != $user['id_user'] && !is_admin()) {
    set_flash('error', 'Anda tidak memiliki wewenang untuk mengelola instrumen kelas ini.');
    redirect('matkul_saya.php');
}

$pageTitle = 'Instrumen: ' . $pengampu['nama_mk'];

// PENANGANAN POST: UPLOAD ATAU HAPUS INSTRUMEN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $kategori = trim((string)($_POST['kategori'] ?? ''));
        $subKategori = trim((string)($_POST['sub_kategori'] ?? ''));
        $linkEksternal = trim((string)($_POST['link_eksternal'] ?? ''));

        if (empty($kategori) || empty($subKategori)) {
            set_flash('error', 'Kategori dan sub-kategori wajib dipilih.');
            redirect("upload_instrumen.php?id_pengampu=$idPengampu");
        }

        $filePath = '';
        $fileUrl = '';
        $originalName = '';
        $fileSize = 0;

        // Opsi 1: File Fisik yang diunggah
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_dokumen'];
            $maxMb = (int)$branding['max_upload_mb'];
            if ($file['size'] > ($maxMb * 1024 * 1024)) {
                set_flash('error', "Ukuran file melebihi batas maksimal ($maxMb MB).");
                redirect("upload_instrumen.php?id_pengampu=$idPengampu");
            }

            // Validasi Ekstensi & MIME
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];
            if (!in_array($ext, $allowedExts, true)) {
                set_flash('error', 'Format file tidak didukung. Harap unggah dokumen PDF, Word, Excel, PPT, atau ZIP.');
                redirect("upload_instrumen.php?id_pengampu=$idPengampu");
            }

            // Sanitasi nama file unik: MK_Kategori_Sub_Timestamp.ext
            $cleanMk = preg_replace('/[^a-zA-Z0-9]/', '_', $pengampu['kode_mk']);
            $cleanKat = preg_replace('/[^a-zA-Z0-9]/', '_', $kategori);
            $cleanSub = preg_replace('/[^a-zA-Z0-9]/', '_', $subKategori);
            $safeFileName = "{$cleanMk}_{$cleanKat}_{$cleanSub}_" . time() . '.' . $ext;

            $destDir = __DIR__ . '/storage/uploads/instrumen/';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $targetPath = $destDir . $safeFileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $filePath = 'storage/uploads/instrumen/' . $safeFileName;
                $fileUrl = url($filePath);
                $originalName = $file['name'];
                $fileSize = (int)$file['size'];
            } else {
                set_flash('error', 'Gagal memindahkan file ke direktori server.');
                redirect("upload_instrumen.php?id_pengampu=$idPengampu");
            }
        } elseif (!empty($linkEksternal)) {
            // Opsi 2: Tautan Eksternal Cloud / Google Drive
            if (!filter_var($linkEksternal, FILTER_VALIDATE_URL)) {
                set_flash('error', 'Format tautan eksternal tidak valid.');
                redirect("upload_instrumen.php?id_pengampu=$idPengampu");
            }
            $filePath = $linkEksternal;
            $fileUrl = $linkEksternal;
            $originalName = 'Tautan Cloud / Google Drive';
            $fileSize = 0;
        } else {
            set_flash('error', 'Silakan pilih berkas dokumen fisik untuk diunggah atau isi tautan Google Drive / Cloud.');
            redirect("upload_instrumen.php?id_pengampu=$idPengampu");
        }

        // Hapus file lama jika sebelumnya sudah ada file di sub-kategori ini
        $oldStmt = $pdo->prepare("SELECT id_instrumen, file_path FROM instrumen WHERE id_pengampu = ? AND kategori = ? AND sub_kategori = ?");
        $oldStmt->execute([$idPengampu, $kategori, $subKategori]);
        $oldFile = $oldStmt->fetch();
        if ($oldFile) {
            if (!empty($oldFile['file_path']) && !str_starts_with($oldFile['file_path'], 'http')) {
                $oldDiskPath = __DIR__ . '/' . ltrim($oldFile['file_path'], '/');
                if (file_exists($oldDiskPath)) {
                    @unlink($oldDiskPath);
                }
            }
            $delOld = $pdo->prepare("DELETE FROM instrumen WHERE id_instrumen = ?");
            $delOld->execute([$oldFile['id_instrumen']]);
        }

        // Simpan Record Baru ke Database
        $insStmt = $pdo->prepare("
            INSERT INTO instrumen (id_pengampu, kategori, sub_kategori, file_path, file_url, original_name, file_size, tgl_upload)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $insStmt->execute([$idPengampu, $kategori, $subKategori, $filePath, $fileUrl, $originalName, $fileSize]);

        log_activity('UPLOAD_INSTRUMEN', "Unggah berkas {$kategori} - {$subKategori} untuk MK {$pengampu['nama_mk']} ({$originalName}).");
        set_flash('success', "Berkas '{$kategori} - {$subKategori}' berhasil diunggah!");
        redirect("upload_instrumen.php?id_pengampu=$idPengampu");

    } elseif ($action === 'delete') {
        $idInstrumen = (int)($_POST['id_instrumen'] ?? 0);
        $getStmt = $pdo->prepare("SELECT * FROM instrumen WHERE id_instrumen = ? AND id_pengampu = ?");
        $getStmt->execute([$idInstrumen, $idPengampu]);
        $insData = $getStmt->fetch();

        if ($insData) {
            // Hapus file dari penyimpanan lokal jika bukan URL eksternal
            if (!empty($insData['file_path']) && !str_starts_with($insData['file_path'], 'http')) {
                $diskPath = __DIR__ . '/' . ltrim($insData['file_path'], '/');
                if (file_exists($diskPath)) {
                    @unlink($diskPath);
                }
            }
            $delStmt = $pdo->prepare("DELETE FROM instrumen WHERE id_instrumen = ?");
            $delStmt->execute([$idInstrumen]);

            log_activity('HAPUS_INSTRUMEN', "Hapus berkas {$insData['kategori']} - {$insData['sub_kategori']} pada MK {$pengampu['nama_mk']}.");
            set_flash('success', "Berkas '{$insData['kategori']} - {$insData['sub_kategori']}' berhasil dihapus.");
        }
        redirect("upload_instrumen.php?id_pengampu=$idPengampu");
    }
}

// Ambil seluruh file instrumen yang sudah terunggah pada kelas ini
$insMapStmt = $pdo->prepare("SELECT * FROM instrumen WHERE id_pengampu = ?");
$insMapStmt->execute([$idPengampu]);
$uploadedList = $insMapStmt->fetchAll();

$uploadedMap = [];
foreach ($uploadedList as $uItem) {
    $key = $uItem['kategori'] . '|||' . $uItem['sub_kategori'];
    $uploadedMap[$key] = $uItem;
}

// Hitung persentase progres
$uniqueUploaded = count($uploadedMap);
$percentage = (int)round(($uniqueUploaded / TOTAL_STANDAR_FILE) * 100);
if ($percentage > 100) $percentage = 100;

// Matriks 8 Kategori & 28 Sub-Kategori
$matrix = get_standard_instrumen_matrix();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="flex-1 flex overflow-hidden">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto bg-slate-50 flex flex-col justify-between min-w-0">
        <div class="p-4 sm:px-6 lg:px-8 py-8 flex-1">
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Breadcrumb & Navigasi Balik -->
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <a href="<?= url('matkul_saya.php') ?>" class="hover:text-maritime-700 font-medium flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    Kembali ke Mata Kuliah Saya
                </a>
                <span>/</span>
                <span class="text-slate-800 font-semibold truncate"><?= e($pengampu['nama_mk']) ?></span>
            </div>

            <?php if ($isLampau): ?>
            <!-- Alert Informasi Mata Kuliah Lampau -->
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex items-start gap-3 shadow-sm">
                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                </div>
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-bold text-[11px] uppercase tracking-wider text-amber-800 bg-amber-200/60 px-2 py-0.5 rounded-md">Mata Kuliah Periode Lampau</span>
                        <span class="text-xs text-amber-700 font-medium">Tahun Ajaran <?= e($pengampu['tahun']) ?> • Semester <?= e($pengampu['semester']) ?></span>
                    </div>
                    <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                        Kelas ini merupakan arsip perkuliahan periode sebelumnya. <strong>Ruang unggah tetap dibuka secara penuh</strong> agar Anda dapat melengkapi seluruh instrumen perkuliahan yang belum lengkap demi pemenuhan dokumen akreditasi program studi.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Banner Header Mata Kuliah & Progress -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="font-mono text-xs font-bold px-3 py-1 rounded-lg bg-blue-50 text-maritime-700 border border-blue-200">
                                <?= e($pengampu['kode_mk']) ?>
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700">
                                <?= e($pengampu['sks']) ?> SKS
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-lg <?= $isLampau ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-800' ?>">
                                <?= e($pengampu['tahun']) ?> • <?= e($pengampu['semester']) ?>
                            </span>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg <?= $isLampau ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                <?= $isLampau ? 'Periode Lampau' : 'Periode Aktif' ?>
                            </span>
                        </div>
                        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-slate-900 tracking-tight leading-tight">
                            <?= e($pengampu['nama_mk']) ?>
                        </h1>
                        <p class="text-xs text-slate-500 mt-1">
                            Dosen Pengampu: <span class="font-semibold text-slate-800"><?= e($pengampu['nama_dosen']) ?></span> (NIP: <?= e($pengampu['nip']) ?>)
                        </p>
                    </div>

                    <!-- Progress Gauge Box -->
                    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 min-w-[240px]">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-semibold text-slate-600">Total Kelengkapan:</span>
                            <span class="font-extrabold font-heading text-sm <?= $percentage === 100 ? 'text-emerald-600' : 'text-maritime-700' ?>">
                                <?= $uniqueUploaded ?> / <?= TOTAL_STANDAR_FILE ?> Berkas (<?= $percentage ?>%)
                            </span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden my-2">
                            <div class="h-full rounded-full transition-all duration-700 <?= $percentage === 100 ? 'bg-emerald-500' : ($percentage >= 50 ? 'bg-amber-500' : 'bg-maritime-600') ?>"
                                 style="width: <?= $percentage ?>%;"></div>
                        </div>
                        <p class="text-[10px] text-slate-400 text-center">
                            Standar akreditasi wajib: 28 dokumen lengkap
                        </p>
                    </div>
                </div>
            </div>

            <!-- Matriks 8 Kategori Standar Instrumen -->
            <div class="space-y-6">
                <?php foreach ($matrix as $catIdx => $cat): ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <!-- Kategori Header -->
                    <div class="bg-slate-50 px-6 py-3.5 border-b border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-blue-100 text-maritime-700 flex items-center justify-center shrink-0">
                                <i data-lucide="<?= $cat['icon'] ?>" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h2 class="font-heading font-bold text-sm text-slate-900"><?= e($cat['kategori']) ?></h2>
                                <p class="text-[11px] text-slate-500"><?= e($cat['deskripsi']) ?></p>
                            </div>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-500 bg-white px-2.5 py-1 rounded-lg border border-slate-200">
                            <?= count($cat['sub']) ?> Dokumen
                        </span>
                    </div>

                    <!-- Sub-Kategori Grid -->
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($cat['sub'] as $sub): 
                            $mapKey = $cat['kategori'] . '|||' . $sub;
                            $hasUpload = isset($uploadedMap[$mapKey]);
                            $fileData = $hasUpload ? $uploadedMap[$mapKey] : null;
                        ?>
                        <div class="p-4 rounded-xl border flex flex-col justify-between transition <?= $hasUpload ? 'bg-emerald-50/50 border-emerald-200' : 'bg-slate-50/60 border-slate-200 hover:border-slate-300' ?>">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-heading font-bold text-xs text-slate-800"><?= e($sub) ?></h3>
                                    <?php if ($hasUpload): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 flex items-center gap-1">
                                        <i data-lucide="check" class="w-3 h-3"></i> Terunggah
                                    </span>
                                    <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-200 text-slate-600">
                                        Belum Ada
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($hasUpload): ?>
                                <p class="text-[11px] text-slate-600 truncate font-mono" title="<?= e($fileData['original_name']) ?>">
                                    <?= e($fileData['original_name']) ?>
                                </p>
                                <p class="text-[10px] text-slate-400 mt-0.5">
                                    <?= tanggal_indo($fileData['tgl_upload'], true) ?>
                                    <?= $fileData['file_size'] > 0 ? ' • ' . format_bytes((int)$fileData['file_size']) : '' ?>
                                </p>
                                <?php else: ?>
                                <p class="text-[11px] text-slate-400 italic">Dokumen belum diunggah.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-4 pt-3 border-t <?= $hasUpload ? 'border-emerald-200' : 'border-slate-200' ?> flex items-center justify-between gap-2">
                                <?php if ($hasUpload): ?>
                                    <a href="<?= e($fileData['file_url']) ?>" target="_blank"
                                       class="text-xs font-bold text-emerald-700 hover:text-emerald-800 flex items-center gap-1.5 hover:underline">
                                        <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                        Unduh / Buka
                                    </a>

                                    <div class="flex items-center gap-1">
                                        <!-- Ganti File -->
                                        <button type="button"
                                                onclick="openUploadModal('<?= e($cat['kategori']) ?>', '<?= e($sub) ?>')"
                                                class="p-1.5 rounded-lg text-slate-500 hover:text-maritime-700 hover:bg-white transition" title="Ganti File">
                                            <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                        </button>

                                        <!-- Hapus File -->
                                        <form action="<?= url("upload_instrumen.php?id_pengampu=$idPengampu") ?>" method="POST"
                                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus dokumen ini?')" class="inline m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_instrumen" value="<?= $fileData['id_instrumen'] ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition" title="Hapus File">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <button type="button"
                                            onclick="openUploadModal('<?= e($cat['kategori']) ?>', '<?= e($sub) ?>')"
                                            class="w-full py-1.5 px-3 bg-white hover:bg-maritime-50 border border-slate-300 hover:border-maritime-300 text-maritime-700 font-bold text-xs rounded-lg transition flex items-center justify-center gap-1.5 shadow-sm">
                                        <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                                        Unggah Dokumen
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        </div>

<!-- MODAL UNGGAH INSTRUMEN DENGAN ANIMASI CEKLIST PROGRESIF (skills.md 5.3) -->
<div id="modalUpload" class="hidden fixed inset-0 z-50 overflow-y-auto bg-abyssal-950/75 backdrop-blur-sm items-center justify-center p-4">
    <div class="relative bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200">
        <!-- Close Button -->
        <button type="button" onclick="closeModal('modalUpload')" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 p-1 rounded-xl">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-2xl bg-cyan-50 text-cyan-700 flex items-center justify-center shrink-0">
                <i data-lucide="file-up" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="font-heading font-extrabold text-base text-slate-900">Unggah Berkas Instrumen</h3>
                <p id="modalSubTitle" class="text-xs text-slate-500 font-medium"></p>
            </div>
        </div>

        <form id="formUploadDoc" action="<?= url("upload_instrumen.php?id_pengampu=$idPengampu") ?>" method="POST" enctype="multipart/form-data"
              onsubmit="return submitWithProgressiveAnimation(this, 'btnSubmitUpload', 'uploadProgressStatus')" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload">
            <input type="hidden" id="modalInputKategori" name="kategori" value="">
            <input type="hidden" id="modalInputSubKategori" name="sub_kategori" value="">

            <!-- Tab / Pilihan Metode Upload: Berkas Lokal atau Tautan Eksternal Cloud -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Pilih File Dokumen Fisik (Maks <?= (int)$branding['max_upload_mb'] ?> MB)
                </label>
                <input type="file" id="inputFileDoc" name="file_dokumen" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip"
                       class="w-full text-xs text-slate-600 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-maritime-50 file:text-maritime-700 hover:file:bg-maritime-100 border border-slate-300 rounded-xl p-1 bg-slate-50">
                <p class="text-[10px] text-slate-400 mt-1">
                    Format didukung: PDF, Word (DOC/DOCX), Excel (XLS/XLSX), PPT, ZIP.
                </p>
            </div>

            <div class="relative flex py-1 items-center">
                <div class="flex-grow border-t border-slate-200"></div>
                <span class="flex-shrink mx-3 text-slate-400 text-[10px] uppercase font-bold tracking-wider">ATAU TAUTAN CLOUD</span>
                <div class="flex-grow border-t border-slate-200"></div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Tautan Google Drive / Cloud Storage (Opsional)
                </label>
                <div class="relative rounded-xl shadow-sm">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="link-2" class="w-4 h-4"></i>
                    </div>
                    <input type="url" name="link_eksternal" placeholder="https://drive.google.com/..."
                           class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700 focus:bg-white transition">
                </div>
                <p class="text-[10px] text-slate-400 mt-1">
                    Gunakan jika berkas tersimpan di Google Drive bersama (pastikan akses publik 'Viewer').
                </p>
            </div>

            <!-- Kontainer Animasi Status Progresif (Acuan: skills.md Bagian 5.3) -->
            <div id="uploadProgressStatus" class="hidden p-3 rounded-xl bg-sky-50 border border-sky-200 text-center">
            </div>

            <!-- Tombol Aksi -->
            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal('modalUpload')" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Batal
                </button>
                <button type="submit" id="btnSubmitUpload" class="px-5 py-2.5 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Mulai Unggah
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openUploadModal(kategori, subKategori) {
        document.getElementById('modalInputKategori').value = kategori;
        document.getElementById('modalInputSubKategori').value = subKategori;
        document.getElementById('modalSubTitle').innerText = `${kategori} → ${subKategori}`;
        document.getElementById('inputFileDoc').value = '';
        document.getElementById('uploadProgressStatus').classList.add('hidden');
        document.getElementById('uploadProgressStatus').innerHTML = '';
        document.getElementById('btnSubmitUpload').disabled = false;
        document.getElementById('btnSubmitUpload').innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Mulai Unggah';
        openModal('modalUpload');
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
