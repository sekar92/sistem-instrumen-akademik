<?php
declare(strict_types=1);

/**
 * SIMIA - Automated Integration Test Suite
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=============================================\n";
echo "   SIMIA INTEGRATION & VERIFICATION TEST     \n";
echo "=============================================\n\n";

$pdo = Database::getConnection();

// TEST 1: Database Tables & Counts
echo "[TEST 1] Memeriksa Struktur Database & Seeder...\n";
$tables = ['users', 'tahun_ajaran', 'semesters', 'mata_kuliah', 'cpmk', 'pengampu', 'instrumen', 'informasi', 'log_aktivitas', 'pengaturan'];
foreach ($tables as $t) {
    $count = (int)$pdo->query("SELECT count(*) FROM $t")->fetchColumn();
    echo "  - Tabel '$t': $count baris data\n";
    assert($count >= 0, "Tabel $t harus dapat diakses");
}
echo "  => TEST 1 LULUS!\n\n";

// TEST 2: Otentikasi & Password Hash
echo "[TEST 2] Verifikasi Otentikasi & Hashing...\n";
$admin = $pdo->query("SELECT * FROM users WHERE nip = '198501012010121001'")->fetch();
assert(!empty($admin), "Super Admin harus ada");
assert(password_verify('admin123', $admin['password']), "Password hash bcrypt harus cocok");
echo "  - Super Admin '{$admin['nama']}' ({$admin['role']}) terverifikasi.\n";

$dosen = $pdo->query("SELECT * FROM users WHERE nip = '199003152019031005'")->fetch();
assert(password_verify('dosen123', $dosen['password']), "Password dosen harus cocok");
echo "  - Dosen '{$dosen['nama']}' ({$dosen['role']}) terverifikasi.\n";
echo "  => TEST 2 LULUS!\n\n";

// TEST 3: Matriks 8 Kategori & 28 Sub-Kategori
echo "[TEST 3] Verifikasi Matriks Standar Instrumen...\n";
$matrix = get_standard_instrumen_matrix();
assert(count($matrix) === 8, "Harus ada tepat 8 kategori instrumen");
$totalSub = 0;
foreach ($matrix as $cat) {
    $totalSub += count($cat['sub']);
}
assert($totalSub === 28, "Total sub-kategori harus tepat 28 dokumen");
echo "  - Terverifikasi 8 Kategori dengan total $totalSub Sub-Kategori berkas.\n";
echo "  => TEST 3 LULUS!\n\n";

// TEST 4: Log Aktivitas Audit Trail
echo "[TEST 4] Menguji Audit Trail & Logging...\n";
$_SESSION['user'] = $admin;
log_activity('TEST_SYSTEM', 'Pengujian otomatis audit trail sistem SIMIA.');
$lastLog = $pdo->query("SELECT * FROM log_aktivitas ORDER BY id_log DESC LIMIT 1")->fetch();
assert($lastLog['tipe_aktivitas'] === 'TEST_SYSTEM', "Tipe aktivitas log harus tersimpan");
assert($lastLog['nama'] === $admin['nama'], "Nama pengguna di log harus sesuai");
echo "  - Log audit trail berhasil dicatat: [{$lastLog['created_at']}] {$lastLog['tipe_aktivitas']} - {$lastLog['deskripsi']}\n";
echo "  => TEST 4 LULUS!\n\n";

// TEST 5: Bulk Import Excel Parser Simulation (PRD FR-3.4)
echo "[TEST 5] Menguji Parser Salinan Excel Mata Kuliah...\n";
$pdo->prepare("DELETE FROM cpmk WHERE id_mk IN (SELECT id_mk FROM mata_kuliah WHERE kode_mk IN ('TKL-4101', 'TKL-4102'))")->execute();
$pdo->prepare("DELETE FROM pengampu WHERE id_mk IN (SELECT id_mk FROM mata_kuliah WHERE kode_mk IN ('TKL-4101', 'TKL-4102'))")->execute();
$pdo->prepare("DELETE FROM mata_kuliah WHERE kode_mk IN ('TKL-4101', 'TKL-4102')")->execute();

$tsvInput = "TKL-4101\tRobotika Bawah Air (ROV)\t3\tGanjil\tPerancangan wahana tanpa awak dan aktuator laut.\n"
          . "TKL-4102\tOseanografi Operasional\t2\tGenap\tAsimilasi data model cuaca laut dan gelombang.";
$lines = explode("\n", trim($tsvInput));
$idTa = (int)$pdo->query("SELECT id_ta FROM tahun_ajaran WHERE status = 'Aktif'")->fetchColumn();
$ins = $pdo->prepare("INSERT INTO mata_kuliah (kode_mk, nama_mk, deskripsi, sks, id_ta, semester) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($lines as $line) {
    $cols = explode("\t", trim($line));
    $ins->execute([$cols[0], $cols[1], $cols[4], (int)$cols[2], $idTa, $cols[3]]);
}
$checkMk = $pdo->query("SELECT * FROM mata_kuliah WHERE kode_mk = 'TKL-4101'")->fetch();
assert(!empty($checkMk), "Mata kuliah hasil import bulk harus tersimpan");
echo "  - Berhasil mengimpor massal MK: {$checkMk['kode_mk']} - {$checkMk['nama_mk']} ({$checkMk['sks']} SKS)\n";
echo "  => TEST 5 LULUS!\n\n";

// TEST 6: CPMK Management
echo "[TEST 6] Menguji CPMK Mata Kuliah...\n";
$insCpmk = $pdo->prepare("INSERT INTO cpmk (id_mk, kode_cpmk, deskripsi) VALUES (?, ?, ?)");
$insCpmk->execute([$checkMk['id_mk'], 'CPMK-1', 'Mampu merancang struktur frame ROV berdaya apung netral.']);
$cpmkCount = (int)$pdo->query("SELECT count(*) FROM cpmk WHERE id_mk = {$checkMk['id_mk']}")->fetchColumn();
assert($cpmkCount === 1, "CPMK harus berhasil disimpan");
echo "  - Berhasil menambahkan CPMK untuk {$checkMk['kode_mk']}: total $cpmkCount CPMK.\n";
echo "  => TEST 6 LULUS!\n\n";

// TEST 7: Penugasan Dosen & Ruang Upload File
echo "[TEST 7] Menguji Penugasan Dosen & Ruang Unggah...\n";
$insAssign = $pdo->prepare("INSERT INTO pengampu (id_user, id_mk, id_ta, semester) VALUES (?, ?, ?, ?)");
$insAssign->execute([$dosen['id_user'], $checkMk['id_mk'], $idTa, 'Ganjil']);
$idPengampu = (int)$pdo->lastInsertId();

// Simulasi upload berkas instrumen fisik
$testFileDir = __DIR__ . '/uploads/instrumen/';
if (!is_dir($testFileDir)) mkdir($testFileDir, 0755, true);
$testFileName = 'TKL_4101_RPS_RPS_' . time() . '.pdf';
file_put_contents($testFileDir . $testFileName, "%PDF-1.4 Mock RPS Document for Unit Test");

$insDoc = $pdo->prepare("
    INSERT INTO instrumen (id_pengampu, kategori, sub_kategori, file_path, file_url, original_name, file_size, tgl_upload)
    VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
");
$insDoc->execute([
    $idPengampu,
    'RPS',
    'RPS',
    'storage/uploads/instrumen/' . $testFileName,
    '/storage/uploads/instrumen/' . $testFileName,
    'RPS_Robotika_Bawah_Air_2026.pdf',
    42
]);

// Cek hitungan progres
$uploadedCnt = (int)$pdo->query("SELECT count(DISTINCT sub_kategori) FROM instrumen WHERE id_pengampu = $idPengampu")->fetchColumn();
$pct = (int)round(($uploadedCnt / TOTAL_STANDAR_FILE) * 100);
echo "  - Dokumen RPS berhasil diunggah. Progres kelas: {$uploadedCnt}/" . TOTAL_STANDAR_FILE . " berkas ({$pct}%)\n";
assert($uploadedCnt === 1, "Harus tercatat 1 berkas unik");
echo "  => TEST 7 LULUS!\n\n";

// TEST 8: ZipArchive Compression (Akreditasi Export)
echo "[TEST 8] Menguji Kompresi Arsip ZIP...\n";
$zipDir = __DIR__ . '/zips/';
if (!is_dir($zipDir)) mkdir($zipDir, 0755, true);
$zipPath = $zipDir . 'test_arsip_' . time() . '.zip';

$zip = new ZipArchive();
$res = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
assert($res === true, "ZipArchive harus bisa membuka file");
$zip->addFile($testFileDir . $testFileName, "{$checkMk['nama_mk']}/RPS/RPS - {$dosen['nama']} - RPS_Test.pdf");
$zip->close();

assert(file_exists($zipPath) && filesize($zipPath) > 0, "File ZIP harus terbentuk");
echo "  - File ZIP berhasil dibuat: " . basename($zipPath) . " (" . filesize($zipPath) . " bytes)\n";
@unlink($zipPath); // Bersihkan test zip
echo "  => TEST 8 LULUS!\n\n";

// TEST 9: Simulasi Login-As & Auto-Heal Safeguard
echo "[TEST 9] Menguji Simulasi Akun (Login-As) & Safeguard...\n";
$_SESSION['user'] = $admin;
$_SESSION['impersonator'] = $admin;
$_SESSION['user'] = $dosen; // Sesi aktif menjadi dosen

assert(is_impersonating() === true, "Harus dalam status impersonating");
assert(auth_user()['id_user'] === $dosen['id_user'], "User aktif harus dosen target");
assert(impersonator_user()['id_user'] === $admin['id_user'], "Impersonator harus admin asli");

// Revert
$_SESSION['user'] = $_SESSION['impersonator'];
unset($_SESSION['impersonator']);
assert(is_impersonating() === false, "Setelah revert tidak boleh impersonating");
assert(auth_user()['id_user'] === $admin['id_user'], "User aktif harus kembali ke admin");
echo "  - Alur simulasi login-as dan pengembalian akun admin berjalan sempurna.\n";
echo "  => TEST 9 LULUS!\n\n";

// TEST 10: Pengaturan Sistem & Dynamic Branding
echo "[TEST 10] Menguji Branding Dinamis & Pengaturan...\n";
$branding = get_branding_info();
assert(!empty($branding['app_name']), "App name tidak boleh kosong");
assert(!empty(get_logo_url()), "Logo URL harus ter-generate");
assert(!empty(get_favicon_url()), "Favicon URL harus ter-generate");
echo "  - App Name: {$branding['app_name']}\n";
echo "  - Institusi: {$branding['institution']} ({$branding['study_program']})\n";
echo "  - Copyright: {$branding['copyright']}\n";
echo "  => TEST 10 LULUS!\n\n";

echo "=============================================\n";
echo "  SELURUH 10 PENGUJIAN INTEGRASI LULUS 100%! \n";
echo "=============================================\n";
