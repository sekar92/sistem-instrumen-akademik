<?php
declare(strict_types=1);

/**
 * SIMIA - Panel Pengaturan Sistem & Branding Dinamis
 * Acuan: skills.md Bagian 5.6
 */

require_once __DIR__ . '/includes/functions.php';
require_admin();

$user = auth_user();
$pdo = Database::getConnection();
$pageTitle = 'Pengaturan Sistem & Branding';

// Handle POST Actions Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_abort();
    $postAction = $_POST['action'] ?? 'save_settings';

    // 1. Aksi Uji Coba Pengiriman Email (Test Email)
    if ($postAction === 'send_test_email') {
        $testEmail = trim((string)($_POST['test_email'] ?? ''));
        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Masukkan alamat email tujuan uji coba yang valid.');
            redirect('pengaturan.php');
        }

        $branding = get_branding_info();
        $mailCfg = get_mail_config();
        $subject = "Uji Coba Pengiriman Email SIMIA - " . $branding['app_name'];
        $body = build_email_template(
            "Uji Coba Sistem Pengiriman Email",
            "Halo, " . ($user['nama'] ?? 'Administrator'),
            "Email ini dikirimkan untuk memverifikasi bahwa konfigurasi server email (SMTP / PHP mail) pada sistem SIMIA telah terhubung dan dapat mengirimkan pesan dengan sukses.",
            [
                'Status Pengiriman' => 'Koneksi Berhasil & Aktif',
                'Metode Pengiriman' => strtoupper($mailCfg['driver']),
                'Server Host:Port'  => ($mailCfg['driver'] === 'smtp') ? "{$mailCfg['smtp_host']}:{$mailCfg['smtp_port']} ({$mailCfg['smtp_crypto']})" : 'PHP mail() lokal',
                'Waktu Pengujian'   => date('d M Y, H:i:s T')
            ],
            [
                'text' => 'Buka Dashboard SIMIA',
                'url'  => url('dashboard.php')
            ],
            "Jika email ini sampai di kotak masuk Anda, fitur Lupa Kata Sandi dan Notifikasi Pengingat Dosen siap beroperasi secara optimal."
        );

        $testRes = send_email($testEmail, $subject, $body);
        if ($testRes['success']) {
            log_activity('TEST_EMAIL_SUKSES', "Uji coba pengiriman email ke $testEmail berhasil.");
            set_flash('success', "Email uji coba berhasil dikirimkan ke <strong>" . e($testEmail) . "</strong>! Silakan periksa kotak masuk atau spam.");
        } else {
            log_activity('TEST_EMAIL_GAGAL', "Uji coba email ke $testEmail gagal: {$testRes['message']}");
            set_flash('error', "Uji coba pengiriman email gagal: {$testRes['message']}");
        }
        redirect('pengaturan.php');
    }

    // 2. Aksi Broadcast Pengingat Kelengkapan Instrumen ke Seluruh Dosen Incomplete
    if ($postAction === 'broadcast_instrument_reminders') {
        $q = "
            SELECT p.id_pengampu, p.semester, u.nama, u.email, m.kode_mk, m.nama_mk
            FROM pengampu p
            JOIN users u ON p.id_user = u.id_user
            JOIN mata_kuliah m ON p.id_mk = m.id_mk
            WHERE u.email != '' AND u.email IS NOT NULL
        ";
        $targets = $pdo->query($q)->fetchAll();
        $sentCount = 0;
        $failCount = 0;

        foreach ($targets as $t) {
            $cnt = (int)$pdo->query("SELECT COUNT(DISTINCT sub_kategori) FROM instrumen WHERE id_pengampu = " . (int)$t['id_pengampu'])->fetchColumn();
            $pct = (int)round(($cnt / 28) * 100);

            if ($pct < 100) {
                $mRes = send_instrument_reminder_email($t, [
                    'persentase'       => $pct,
                    'berkas_terkumpul' => $cnt,
                    'standar_wajib'    => 28,
                    'tahun_ajaran'     => 'Tahun Ajaran Aktif',
                ]);
                if ($mRes['success']) {
                    $sentCount++;
                } else {
                    $failCount++;
                }
            }
        }

        log_activity('BROADCAST_PENGINGAT', "Broadcast pengingat dosen via Pengaturan: $sentCount terkirim, $failCount gagal.");
        if ($sentCount > 0) {
            set_flash('success', "Berhasil mengirimkan email pengingat kelengkapan instrumen ke <strong>{$sentCount} dosen pengampu</strong>!");
        } else {
            set_flash('warning', "Tidak ada email pengingat yang terkirim. " . ($failCount > 0 ? "($failCount pengiriman gagal - periksa setelan SMTP)." : "Seluruh dosen telah melengkapi berkas 100%."));
        }
        redirect('pengaturan.php');
    }

    // 3. Simpan Seluruh Pengaturan Sistem, Branding, dan Mailer
    $settingsToSave = [
        'app_name'           => trim((string)($_POST['app_name'] ?? 'SIMIA - Sistem Instrumen Akademik')),
        'app_tagline'        => trim((string)($_POST['app_tagline'] ?? '')),
        'institution_name'   => trim((string)($_POST['institution_name'] ?? 'Kelautan.ID')),
        'study_program'      => trim((string)($_POST['study_program'] ?? '')),
        'department'         => trim((string)($_POST['department'] ?? '')),
        'developer'          => trim((string)($_POST['developer'] ?? '')),
        'footer_copyright'   => trim((string)($_POST['footer_copyright'] ?? '')),
        'max_upload_size_mb' => (string)((int)($_POST['max_upload_size_mb'] ?? 10)),

        // Pengaturan Mailer Engine
        'mail_driver'        => in_array($_POST['mail_driver'] ?? '', ['smtp', 'mail'], true) ? $_POST['mail_driver'] : 'smtp',
        'smtp_host'          => trim((string)($_POST['smtp_host'] ?? '')),
        'smtp_port'          => (string)((int)($_POST['smtp_port'] ?? 587)),
        'smtp_user'          => trim((string)($_POST['smtp_user'] ?? '')),
        'smtp_crypto'        => in_array($_POST['smtp_crypto'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_crypto'] : 'tls',
        'mail_from_address'  => trim((string)($_POST['mail_from_address'] ?? '')),
        'mail_from_name'     => trim((string)($_POST['mail_from_name'] ?? '')),
    ];

    $newSmtpPass = (string)($_POST['smtp_pass'] ?? '');
    if ($newSmtpPass !== '') {
        $settingsToSave['smtp_pass'] = $newSmtpPass;
    }

    // Proses Tautan Footer Dinamis (Ekosistem Tautan Digital & Dukungan)
    $linkLabels = $_POST['link_labels'] ?? [];
    $linkUrls   = $_POST['link_urls'] ?? [];
    $footerLinks = [];

    if (is_array($linkLabels) && is_array($linkUrls)) {
        foreach ($linkLabels as $idx => $rawLabel) {
            $lbl = trim((string)$rawLabel);
            $url = trim((string)($linkUrls[$idx] ?? ''));
            if ($lbl !== '' && $url !== '') {
                $footerLinks[] = [
                    'label' => $lbl,
                    'url'   => $url,
                ];

                // Sinkronisasi kompatibilitas mundur untuk helper lama jika ada kata kunci spesifik
                $lowLbl = strtolower($lbl);
                if ($lowLbl === 'portal' || $lowLbl === 'portal utama') {
                    $settingsToSave['portal_url'] = $url;
                } elseif ($lowLbl === 'apps' || $lowLbl === 'portal apps') {
                    $settingsToSave['apps_url'] = $url;
                } elseif (str_contains($lowLbl, 'pemendek') || str_contains($lowLbl, 'go link')) {
                    $settingsToSave['go_links_url'] = $url;
                } elseif (str_contains($lowLbl, 'dukungan') || str_contains($lowLbl, 'email') || str_starts_with($url, 'mailto:')) {
                    $settingsToSave['support_email'] = ltrim(str_replace('mailto:', '', $url), ':');
                }
            }
        }
    }

    $settingsToSave['footer_links_json'] = json_encode($footerLinks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Upload Logo Kustom jika ada
    if (isset($_FILES['custom_logo']) && $_FILES['custom_logo']['error'] === UPLOAD_ERR_OK) {
        $logoFile = $_FILES['custom_logo'];
        $ext = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['svg', 'png', 'jpg', 'jpeg', 'webp'], true) && $logoFile['size'] <= 4 * 1024 * 1024) {
            $destDir = __DIR__ . '/storage/branding/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $logoName = 'logo_custom_' . time() . '.' . $ext;
            if (move_uploaded_file($logoFile['tmp_name'], $destDir . $logoName)) {
                $settingsToSave['custom_logo_url'] = 'storage/branding/' . $logoName;
            }
        }
    }

    // Upload Favicon Kustom jika ada
    if (isset($_FILES['custom_favicon']) && $_FILES['custom_favicon']['error'] === UPLOAD_ERR_OK) {
        $favFile = $_FILES['custom_favicon'];
        $ext = strtolower(pathinfo($favFile['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['ico', 'png', 'svg'], true) && $favFile['size'] <= 2 * 1024 * 1024) {
            $destDir = __DIR__ . '/storage/branding/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $favName = 'favicon_custom_' . time() . '.' . $ext;
            if (move_uploaded_file($favFile['tmp_name'], $destDir . $favName)) {
                $settingsToSave['custom_favicon_url'] = 'storage/branding/' . $favName;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT OR REPLACE INTO pengaturan (key_name, value_data) VALUES (?, ?)");
    foreach ($settingsToSave as $k => $v) {
        $stmt->execute([$k, $v]);
    }

    log_activity('UPDATE_PENGATURAN', 'Memperbarui konfigurasi sistem, identitas visual, dan konfigurasi email aplikasi.');
    set_flash('success', 'Pengaturan sistem, branding, dan konfigurasi email berhasil disimpan!');
    redirect('pengaturan.php');
}

$branding = get_branding_info();
$mailConfig = get_mail_config();

// Hitung dosen yang berkas instrumennya belum lengkap (< 100%)
$pengampuRows = $pdo->query("SELECT id_pengampu FROM pengampu")->fetchAll();
$incompleteLecturersCount = 0;
foreach ($pengampuRows as $pr) {
    $c = (int)$pdo->query("SELECT COUNT(DISTINCT sub_kategori) FROM instrumen WHERE id_pengampu = " . (int)$pr['id_pengampu'])->fetchColumn();
    if ($c < 28) $incompleteLecturersCount++;
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
                <h1 class="font-heading font-extrabold text-2xl text-slate-900 tracking-tight">
                    Pengaturan Sistem & Branding
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Sesuaikan identitas lembaga, logo institusi, batas ukuran upload, dan tautan ekosistem platform.
                </p>
            </div>

            <form action="<?= url('pengaturan.php') ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                <?= csrf_field() ?>

                <!-- 1. Identitas Aplikasi & Lembaga -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h2 class="font-heading font-bold text-sm text-slate-900 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <i data-lucide="building-2" class="w-4 h-4 text-maritime-700"></i>
                        Identitas Aplikasi & Program Studi
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Aplikasi</label>
                            <input type="text" name="app_name" value="<?= e($branding['app_name']) ?>" required
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Institusi Afiliasi</label>
                            <input type="text" name="institution_name" value="<?= e($branding['institution']) ?>" required
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Program Studi</label>
                            <input type="text" name="study_program" value="<?= e($branding['study_program']) ?>" required
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Jurusan / Departemen</label>
                            <input type="text" name="department" value="<?= e($branding['department']) ?>"
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Tagline Platform</label>
                        <input type="text" name="app_tagline" value="<?= e($branding['tagline']) ?>"
                               class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    </div>
                </div>

                <!-- 2. Logo & Favicon Kustom -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h2 class="font-heading font-bold text-sm text-slate-900 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <i data-lucide="image" class="w-4 h-4 text-maritime-700"></i>
                        Aset Visual (Logo & Favicon)
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Logo Kustom (Maks 4 MB)</label>
                            <input type="file" name="custom_logo" accept=".svg,.png,.jpg,.jpeg,.webp"
                                   class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100">
                            <p class="text-[10px] text-slate-400 mt-1">Format: SVG, PNG, WebP (Rasio 1:1 direkomendasikan).</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-slate-500 font-semibold">Logo Aktif:</span>
                            <img src="<?= e(get_logo_url()) ?>" alt="Preview" class="w-12 h-12 object-contain rounded-2xl p-1 bg-slate-100 border border-slate-200">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center pt-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Favicon Kustom (Maks 2 MB)</label>
                            <input type="file" name="custom_favicon" accept=".ico,.png,.svg"
                                   class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-cyan-50 file:text-cyan-700 hover:file:bg-cyan-100">
                            <p class="text-[10px] text-slate-400 mt-1">Format: ICO, PNG, atau SVG.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-slate-500 font-semibold">Favicon Aktif:</span>
                            <img src="<?= e(get_favicon_url()) ?>" alt="Favicon Preview" class="w-8 h-8 object-contain rounded-lg p-1 bg-slate-100 border border-slate-200">
                        </div>
                    </div>
                </div>

                <!-- 3. Batas Berkas & Hak Cipta -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <h2 class="font-heading font-bold text-sm text-slate-900 pb-2 border-b border-slate-100 flex items-center gap-2">
                        <i data-lucide="hard-drive" class="w-4 h-4 text-maritime-700"></i>
                        Penyimpanan & Atribusi
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Batas Maksimal Upload (MB)</label>
                            <input type="number" name="max_upload_size_mb" value="<?= e($branding['max_upload_mb']) ?>" min="1" max="50" required
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Inisiator & Pengembang</label>
                            <input type="text" name="developer" value="<?= e($branding['developer']) ?>" required
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Teks Hak Cipta Footer Resmi</label>
                        <input type="text" name="footer_copyright" value="<?= e($branding['copyright']) ?>" required
                               class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-maritime-700">
                    </div>
                </div>

                <!-- 4. Ekosistem Tautan Digital & Footer Dinamis -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-2">
                        <div>
                            <h2 class="font-heading font-bold text-sm text-slate-900 flex items-center gap-2">
                                <i data-lucide="globe" class="w-4 h-4 text-cyan-600"></i>
                                Ekosistem Tautan Digital & Dukungan (Tautan Footer Fleksibel)
                            </h2>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Bebas tentukan judul field dan URL sebanyak-banyaknya. Seluruh tautan ini akan langsung tampil di Footer aplikasi.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="resetToDefaultLinks()" class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 border border-slate-200 transition flex items-center gap-1.5">
                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                                Reset Bawaan
                            </button>
                            <button type="button" onclick="addLinkRow('', '')" class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-cyan-600 hover:bg-cyan-700 text-white shadow-sm transition flex items-center gap-1.5">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                Tambah Tautan
                            </button>
                        </div>
                    </div>

                    <!-- Daftar Baris Tautan Dinamis -->
                    <div id="footerLinksContainer" class="space-y-3">
                        <!-- Diisi secara dinamis melalui script JavaScript -->
                    </div>

                    <div id="emptyLinksNotice" class="hidden p-6 text-center border-2 border-dashed border-slate-200 rounded-xl">
                        <i data-lucide="link-2-off" class="w-8 h-8 text-slate-300 mx-auto mb-2"></i>
                        <p class="text-xs text-slate-500 font-medium">Belum ada tautan footer yang ditambahkan.</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Klik tombol <strong>Tambah Tautan</strong> di atas atau tombol <strong>Reset Bawaan</strong>.</p>
                    </div>

                    <p class="text-[10px] text-slate-400 italic">
                        Tip: Tautan dapat berupa alamat website (contoh: <code>https://kelautan.id</code>) maupun kontak email (contoh: <code>mailto:admin@kelautan.id</code>).
                    </p>
                </div>

                <!-- 5. Konfigurasi Pengiriman Email (SMTP / Mailer PHP) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h2 class="font-heading font-bold text-sm text-slate-900 flex items-center gap-2">
                                <i data-lucide="mail" class="w-4 h-4 text-cyan-600"></i>
                                Konfigurasi Pengiriman Email (SMTP / Mailer PHP)
                            </h2>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Digunakan untuk pengiriman link Reset Kata Sandi pengguna dan broadcast Notifikasi Pengingat Instrumen Dosen.
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold <?= $mailConfig['driver'] === 'smtp' ? 'bg-cyan-50 text-cyan-700 border border-cyan-200' : 'bg-slate-100 text-slate-600' ?>">
                            <?= strtoupper($mailConfig['driver']) ?> ACTIVE
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Metode Pengiriman Email (Driver)</label>
                            <select name="mail_driver" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                                <option value="smtp" <?= $mailConfig['driver'] === 'smtp' ? 'selected' : '' ?>>SMTP Socket (Direkomendasikan - Aman & Terenkripsi)</option>
                                <option value="mail" <?= $mailConfig['driver'] === 'mail' ? 'selected' : '' ?>>Fungsi Bawaan PHP mail() Server</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Enkripsi Keamanan (Crypto)</label>
                            <select name="smtp_crypto" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                                <option value="tls" <?= $mailConfig['smtp_crypto'] === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS - Port 587 Standard)</option>
                                <option value="ssl" <?= $mailConfig['smtp_crypto'] === 'ssl' ? 'selected' : '' ?>>SSL (Port 465 SMTPS)</option>
                                <option value="none" <?= $mailConfig['smtp_crypto'] === 'none' ? 'selected' : '' ?>>Tanpa Enkripsi (Port 25 / Localhost)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 mb-1">Host Server SMTP</label>
                            <input type="text" name="smtp_host" value="<?= e($mailConfig['smtp_host']) ?>" placeholder="Contoh: smtp.gmail.com atau mail.kelautan.id"
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Port SMTP</label>
                            <input type="number" name="smtp_port" value="<?= e((string)$mailConfig['smtp_port']) ?>" placeholder="587"
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Username / Alamat Email SMTP</label>
                            <input type="text" name="smtp_user" value="<?= e($mailConfig['smtp_user']) ?>" placeholder="notifikasi@kelautan.id"
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Password / App Password SMTP</label>
                            <div class="relative">
                                <input type="password" id="smtp_pass" name="smtp_pass" value="" placeholder="<?= !empty($mailConfig['smtp_pass']) ? '•••••••• (Tersimpan - kosongkan jika tidak diubah)' : 'Masukkan password atau App Password' ?>"
                                       class="w-full bg-slate-50 border border-slate-300 rounded-xl pl-3 pr-10 py-2 text-xs font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                                <button type="button" onclick="toggleSmtpPass()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                                    <i data-lucide="eye" id="smtpEye" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Pengirim Resmi (From Name)</label>
                            <input type="text" name="mail_from_name" value="<?= e($mailConfig['from_name']) ?>" required
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Email Pengirim (From Address)</label>
                            <input type="email" name="mail_from_address" value="<?= e($mailConfig['from_address']) ?>" required
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-maritime-700 hover:bg-maritime-800 text-white font-bold text-sm rounded-xl shadow-lg transition flex items-center gap-2">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        Simpan Semua Pengaturan
                    </button>
                </div>
            </form>

            <!-- Action Cards Terpisah untuk Email: Uji Coba & Broadcast Pengingat -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Card 1: Uji Coba Pengiriman Email -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="p-2 bg-blue-50 text-maritime-700 rounded-xl">
                                <i data-lucide="send" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h3 class="font-heading font-bold text-sm text-slate-900">Uji Coba Pengiriman Email</h3>
                                <p class="text-[11px] text-slate-500">Kirim email simulasi untuk memvalidasi setelan SMTP.</p>
                            </div>
                        </div>
                    </div>

                    <form action="<?= url('pengaturan.php') ?>" method="POST" class="space-y-3 pt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send_test_email">

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Kirim Ke Alamat Email:</label>
                            <input type="email" name="test_email" required value="<?= e($user['email'] ?? 'teknologikelautanid@gmail.com') ?>"
                                   class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-1 focus:ring-cyan-600">
                        </div>

                        <button type="submit" class="w-full py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs rounded-xl shadow transition flex items-center justify-center gap-2">
                            <i data-lucide="mail-check" class="w-4 h-4 text-cyan-400"></i>
                            Kirim Email Uji Coba Sekarang
                        </button>
                    </form>
                </div>

                <!-- Card 2: Pengingat Berkas Dosen Belum Lengkap -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-3 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="p-2 bg-amber-50 text-amber-600 rounded-xl">
                                <i data-lucide="bell-ring" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <h3 class="font-heading font-bold text-sm text-slate-900">Pengingat Berkas Dosen (Reminder)</h3>
                                <p class="text-[11px] text-slate-500">Notifikasi otomatis ke dosen yang instrumennya belum 100%.</p>
                            </div>
                        </div>

                        <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900 flex items-center justify-between gap-3">
                            <div>
                                <span class="font-bold text-amber-800"><?= $incompleteLecturersCount ?> Dosen Pengampu</span>
                                <p class="text-[10px] text-amber-700 mt-0.5">Memiliki berkas instrumen perkuliahan belum lengkap.</p>
                            </div>
                            <a href="<?= url('rekap_progress.php') ?>" class="text-[11px] font-bold text-cyan-700 hover:text-cyan-900 underline shrink-0">
                                Buka Rekap &rarr;
                            </a>
                        </div>
                    </div>

                    <form action="<?= url('pengaturan.php') ?>" method="POST" onsubmit="return confirm('Kirimkan email notifikasi pengingat ke seluruh dosen yang berkasnya belum lengkap (<?= $incompleteLecturersCount ?> dosen)?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="broadcast_instrument_reminders">

                        <button type="submit" <?= $incompleteLecturersCount === 0 ? 'disabled' : '' ?> class="w-full py-2.5 px-4 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-semibold text-xs rounded-xl shadow transition flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            Kirim Pengingat ke <?= $incompleteLecturersCount ?> Dosen Belum Lengkap
                        </button>
                    </form>
                </div>
            </div>

<script>
const defaultLinks = <?= json_encode(get_default_footer_links(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const initialLinks = <?= json_encode($branding['footer_links'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function updateEmptyNotice() {
    const container = document.getElementById('footerLinksContainer');
    const notice = document.getElementById('emptyLinksNotice');
    if (!container || !notice) return;
    const count = container.querySelectorAll('.footer-link-row').length;
    if (count === 0) {
        notice.classList.remove('hidden');
    } else {
        notice.classList.add('hidden');
    }
}

function addLinkRow(label = '', url = '') {
    const container = document.getElementById('footerLinksContainer');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'footer-link-row flex flex-col sm:flex-row items-stretch sm:items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl transition hover:border-slate-300';
    row.innerHTML = `
        <div class="sm:w-1/3">
            <label class="block sm:hidden text-[10px] font-bold text-slate-500 mb-1">Judul / Label Tautan</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                </span>
                <input type="text" name="link_labels[]" value="${escapeHtml(label)}" placeholder="Contoh: Portal, Tracer Study, E-Journal" required
                       class="w-full bg-white border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-1 focus:ring-cyan-600">
            </div>
        </div>
        <div class="flex-1">
            <label class="block sm:hidden text-[10px] font-bold text-slate-500 mb-1">URL / Tautan Tujuan</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="link" class="w-3.5 h-3.5"></i>
                </span>
                <input type="text" name="link_urls[]" value="${escapeHtml(url)}" placeholder="https://... atau mailto:..." required
                       class="w-full bg-white border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs font-mono text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600">
            </div>
        </div>
        <div class="flex items-center justify-end sm:justify-center shrink-0">
            <button type="button" onclick="removeLinkRow(this)" title="Hapus Tautan Ini"
                    class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
        </div>
    `;

    container.appendChild(row);
    updateEmptyNotice();

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function removeLinkRow(btn) {
    const row = btn.closest('.footer-link-row');
    if (row) {
        row.remove();
        updateEmptyNotice();
    }
}

function resetToDefaultLinks() {
    if (!confirm('Apakah Anda yakin ingin memulihkan daftar tautan footer ke konfigurasi bawaan Kelautan.ID?')) {
        return;
    }
    const container = document.getElementById('footerLinksContainer');
    if (container) {
        container.innerHTML = '';
        defaultLinks.forEach(item => {
            addLinkRow(item.label, item.url);
        });
    }
}

function toggleSmtpPass() {
    const input = document.getElementById('smtp_pass');
    const eye = document.getElementById('smtpEye');
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// Inisialisasi baris tautan saat halaman dimuat
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('footerLinksContainer');
    if (container) {
        container.innerHTML = '';
        const listToRender = (initialLinks && initialLinks.length > 0) ? initialLinks : defaultLinks;
        listToRender.forEach(item => {
            addLinkRow(item.label, item.url);
        });
    }
});
</script>

        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
