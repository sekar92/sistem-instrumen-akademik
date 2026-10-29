<?php
declare(strict_types=1);

/**
 * SIMIA - Core Helper Functions & Security Engine
 * Acuan: skills.md Bagian 4, 5, 6 & PRD SIMIA
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/branding.php';
require_once __DIR__ . '/mailer.php';

// Inisialisasi Sesi Aman
function init_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = [
            'lifetime' => 0, // Session cookie by default
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ];

        // Cek jika pengguna memiliki cookie remember_me
        if (isset($_COOKIE['simia_remember_token'])) {
            $cookieParams['lifetime'] = 30 * 86400; // 30 hari
        }

        session_set_cookie_params($cookieParams);
        session_start();

        // Regenerasi session ID berkala untuk proteksi session fixation
        if (!isset($_SESSION['_created_time'])) {
            $_SESSION['_created_time'] = time();
        } elseif (time() - $_SESSION['_created_time'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created_time'] = time();
        }

        // Idle Timeout 30 menit jika tidak ada remember me
        if (!isset($_COOKIE['simia_remember_token']) && isset($_SESSION['user'])) {
            $idleLimit = 1800; // 30 menit
            if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity'] > $idleLimit)) {
                // Sesi kadaluarsa karena idle
                log_activity('LOGOUT', 'Sesi berakhir otomatis karena idle lebih dari 30 menit.');
                $_SESSION = [];
                session_destroy();
                session_start();
                set_flash('warning', 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 30 menit. Silakan login kembali.');
                header('Location: ' . url('login.php'));
                exit;
            }
            $_SESSION['_last_activity'] = time();
        }
    }
}

// Inisialisasi sesi langsung
init_session();

/**
 * Escape string untuk pencegahan XSS
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Helper Base URL & Pretty URL Generator
 */
function url(string $path = ''): string
{
    // Pemetaan nama file skrip ke Pretty URL (URL Cantik)
    static $prettyMap = [
        'login.php'            => 'login',
        'logout.php'           => 'logout',
        'dashboard.php'        => 'dashboard',
        'profil.php'           => 'profil',
        'matkul_saya.php'      => 'matkul-saya',
        'upload_instrumen.php' => 'upload-instrumen',
        'rekap_progress.php'   => 'rekap-progress',
        'assign_pengampu.php'  => 'assign-pengampu',
        'master_mk.php'        => 'master-mk',
        'master_ta.php'        => 'master-ta',
        'master_semester.php'  => 'master-semester',
        'master_dosen.php'     => 'master-dosen',
        'unduh_berkas.php'     => 'unduh-berkas',
        'informasi.php'        => 'informasi',
        'login_as.php'         => 'login-as',
        'log_aktivitas.php'       => 'log-aktivitas',
        'pengaturan.php'          => 'pengaturan',
        'manajemen_pengguna.php'  => 'manajemen-pengguna',
    ];

    $cleanPath = ltrim($path, '/');
    $parts = explode('?', $cleanPath, 2);
    $baseTarget = $parts[0];
    $queryString = isset($parts[1]) ? '?' . $parts[1] : '';

    if (isset($prettyMap[$baseTarget])) {
        $cleanPath = $prettyMap[$baseTarget] . $queryString;
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = rtrim(dirname($scriptName), '/\\');
    if ($dir === '' || $dir === '/' || $dir === '\\') {
        return '/' . $cleanPath;
    }
    return $dir . '/' . $cleanPath;
}

/**
 * Redirect helper
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * CSRF Protection
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function validate_csrf_or_abort(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
            http_response_code(403);
            die('
                <div style="font-family:sans-serif;padding:30px;max-width:500px;margin:50px auto;border:1px solid #f87171;background:#fef2f2;border-radius:12px;color:#991b1b;">
                    <h3 style="margin-top:0;">403 - Akses Ditolak (CSRF Token Tidak Valid)</h3>
                    <p>Permintaan Anda dibatalkan demi keamanan karena sesi token kadaluarsa atau tidak valid.</p>
                    <a href="' . url('login.php') . '" style="display:inline-block;padding:8px 16px;background:#dc2626;color:#fff;text-decoration:none;border-radius:6px;">Kembali ke Aplikasi</a>
                </div>
            ');
        }
    }
}

/**
 * Flash Message System (Pola Post/Redirect/Get)
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'] = [
        'type'    => in_array($type, ['success', 'error', 'warning', 'info']) ? $type : 'info',
        'message' => $message
    ];
}

function get_flash(): ?array
{
    if (isset($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }
    return null;
}

/**
 * Autentikasi Pengguna
 */
function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return auth_user() !== null;
}

function is_impersonating(): bool
{
    return !empty($_SESSION['impersonator']);
}

function impersonator_user(): ?array
{
    return $_SESSION['impersonator'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
        redirect('login.php');
    }
}

function has_role(array|string $roles): bool
{
    $user = auth_user();
    if (!$user) return false;
    $roles = (array)$roles;
    return in_array($user['role'], $roles, true);
}

function is_super_admin(): bool
{
    return has_role(['Super Admin', 'GKMP']);
}

function is_admin(): bool
{
    return has_role(['Super Admin', 'Admin', 'GKMP', 'Koor.Prodi', 'Kaprodi']);
}

function require_role(array|string $roles): void
{
    require_login();
    if (!has_role($roles)) {
        set_flash('error', 'Anda tidak memiliki hak akses untuk halaman tersebut.');
        redirect('dashboard.php');
    }
}

function require_super_admin(): void
{
    require_login();
    if (!is_super_admin()) {
        set_flash('error', 'Akses ditolak. Fitur Manajemen Pengguna hanya dapat diakses oleh Super Admin / GKMP.');
        redirect('dashboard.php');
    }
}

function require_admin(): void
{
    require_role(['Super Admin', 'Admin', 'GKMP', 'Koor.Prodi', 'Kaprodi']);
}

/**
 * Deteksi IP Address Klien
 */
function get_client_ip(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ipList = explode(',', $_SERVER[$header]);
            return trim($ipList[0]);
        }
    }
    return '127.0.0.1';
}

/**
 * Log Aktivitas / Audit Trail Sistem
 */
function log_activity(string $tipe, string $deskripsi = ''): void
{
    try {
        $pdo = Database::getConnection();
        $user = auth_user();
        $isImpersonated = is_impersonating() ? 1 : 0;
        $impersonatorId = is_impersonating() ? ($_SESSION['impersonator']['id_user'] ?? null) : null;

        $stmt = $pdo->prepare("
            INSERT INTO log_aktivitas (
                id_user, username, nama, role, tipe_aktivitas, deskripsi,
                ip_address, user_agent, is_impersonated, impersonator_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))
        ");

        $stmt->execute([
            $user['id_user'] ?? null,
            $user['nip'] ?? 'GUEST',
            $user['nama'] ?? 'Tamu',
            $user['role'] ?? 'Guest',
            $tipe,
            $deskripsi,
            get_client_ip(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 250),
            $isImpersonated,
            $impersonatorId
        ]);
    } catch (Throwable $e) {
        error_log('Gagal mencatat log_aktivitas: ' . $e->getMessage());
    }
}

/**
 * Mengambil Tahun Ajaran & Semester Aktif
 */
function get_active_ta_semester(): array
{
    $pdo = Database::getConnection();
    
    // Cari TA Aktif
    $taStmt = $pdo->query("SELECT * FROM tahun_ajaran WHERE status = 'Aktif' LIMIT 1");
    $activeTa = $taStmt->fetch();
    if (!$activeTa) {
        $activeTa = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY id_ta DESC LIMIT 1")->fetch();
    }

    $activeSemester = null;
    if ($activeTa) {
        $semStmt = $pdo->prepare("SELECT * FROM semesters WHERE id_ta = ? AND status = 'Berjalan' LIMIT 1");
        $semStmt->execute([$activeTa['id_ta']]);
        $activeSemester = $semStmt->fetch();
        if (!$activeSemester) {
            $semStmt = $pdo->prepare("SELECT * FROM semesters WHERE id_ta = ? ORDER BY id_semester ASC LIMIT 1");
            $semStmt->execute([$activeTa['id_ta']]);
            $activeSemester = $semStmt->fetch();
        }
    }

    return [
        'ta'       => $activeTa,
        'semester' => $activeSemester,
        'label'    => ($activeTa ? $activeTa['tahun'] : '-') . ' ' . ($activeSemester ? $activeSemester['jenis_semester'] : '-')
    ];
}

/**
 * Matriks Standar 8 Kategori & 28 Sub-Kategori File Instrumen
 * Sesuai PRD SIMIA Bagian 4.4 & File Upload GAS asli
 */
function get_standard_instrumen_matrix(): array
{
    $bahanAjarSub = [];
    for ($i = 1; $i <= 16; $i++) {
        $bahanAjarSub[] = "Pertemuan $i";
    }

    return [
        [
            'kategori' => 'Absensi',
            'sub'      => ['Pertemuan 1-8', 'Pertemuan 9-16'],
            'icon'     => 'user-check',
            'deskripsi'=> 'Presensi mahasiswa paruh semester pertama dan kedua.'
        ],
        [
            'kategori' => 'Berita Acara',
            'sub'      => ['BAP'],
            'icon'     => 'file-text',
            'deskripsi'=> 'Berita Acara Perkuliahan (BAP) kumulatif.'
        ],
        [
            'kategori' => 'RPS',
            'sub'      => ['RPS', 'Portofolio Kuliah'],
            'icon'     => 'book-open',
            'deskripsi'=> 'Rencana Pembelajaran Semester (RPS) & Portofolio capaian matakuliah.'
        ],
        [
            'kategori' => 'Kontrak Kuliah',
            'sub'      => ['Kontrak Kuliah'],
            'icon'     => 'award',
            'deskripsi'=> 'Kontrak perkuliahan yang disepakati mahasiswa.'
        ],
        [
            'kategori' => 'Soal Ujian',
            'sub'      => ['UTS', 'UAS'],
            'icon'     => 'help-circle',
            'deskripsi'=> 'Naskah soal Ujian Tengah Semester & Ujian Akhir Semester.'
        ],
        [
            'kategori' => 'Daftar Nilai',
            'sub'      => ['Nilai Tugas & Formatif', 'Nilai Akhir'],
            'icon'     => 'clipboard-check',
            'deskripsi'=> 'Rekapitulasi nilai tugas, formatif, dan nilai akhir mata kuliah.'
        ],
        [
            'kategori' => 'Modul',
            'sub'      => ['Modul Perkuliahan', 'Modul Praktikum'],
            'icon'     => 'layers',
            'deskripsi'=> 'Buku ajar / modul teori dan petunjuk praktikum.'
        ],
        [
            'kategori' => 'Bahan Ajar',
            'sub'      => $bahanAjarSub,
            'icon'     => 'presentation',
            'deskripsi'=> 'Slide presentasi / materi perkuliahan 16 pertemuan.'
        ],
    ];
}

/**
 * Total file standar kelengkapan per mata kuliah (28 berkas)
 */
const TOTAL_STANDAR_FILE = 28;

/**
 * Format ukuran berkas (bytes to KB/MB)
 */
function format_bytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Format tanggal Indonesia ramah pengguna
 */
function tanggal_indo(string $datetime, bool $withTime = false): string
{
    if (empty($datetime)) return '-';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return $datetime;

    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $hari = date('d', $timestamp);
    $bulan = $bulanIndo[(int)date('m', $timestamp)] ?? date('m', $timestamp);
    $tahun = date('Y', $timestamp);

    $hasil = "$hari $bulan $tahun";
    if ($withTime) {
        $hasil .= ' pukul ' . date('H:i', $timestamp) . ' WIT';
    }
    return $hasil;
}
