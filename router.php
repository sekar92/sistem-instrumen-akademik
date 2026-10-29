<?php
declare(strict_types=1);

/**
 * SIMIA - Router untuk Pretty URLs & Server PHP Built-in
 * Penggunaan: php -S localhost:8000 router.php
 */

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = urldecode(parse_url($rawUri, PHP_URL_PATH) ?? '/');

// 1. Root / arahkan ke index.php
if ($uriPath === '/' || $uriPath === '') {
    require __DIR__ . '/index.php';
    return true;
}

// 2. Pemetaan Pretty URLs (URL Cantik) ke File Script PHP
$routeMap = [
    '/login'            => 'login.php',
    '/logout'           => 'logout.php',
    '/lupa-password'    => 'lupa_password.php',
    '/lupa_password'    => 'lupa_password.php',
    '/reset-password'   => 'reset_password.php',
    '/reset_password'   => 'reset_password.php',
    '/dashboard'        => 'dashboard.php',
    '/profil'           => 'profil.php',
    '/matkul-saya'      => 'matkul_saya.php',
    '/matkul_saya'      => 'matkul_saya.php',
    '/upload-instrumen' => 'upload_instrumen.php',
    '/upload_instrumen' => 'upload_instrumen.php',
    '/rekap-progress'   => 'rekap_progress.php',
    '/rekap_progress'   => 'rekap_progress.php',
    '/assign-pengampu'  => 'assign_pengampu.php',
    '/assign_pengampu'  => 'assign_pengampu.php',
    '/master-mk'        => 'master_mk.php',
    '/master_mk'        => 'master_mk.php',
    '/master-ta'        => 'master_ta.php',
    '/master_ta'        => 'master_ta.php',
    '/master-semester'  => 'master_semester.php',
    '/master_semester'  => 'master_semester.php',
    '/master-dosen'     => 'master_dosen.php',
    '/master_dosen'     => 'master_dosen.php',
    '/unduh-berkas'     => 'unduh_berkas.php',
    '/unduh_berkas'     => 'unduh_berkas.php',
    '/informasi'        => 'informasi.php',
    '/login-as'         => 'login_as.php',
    '/login_as'         => 'login_as.php',
    '/log-aktivitas'        => 'log_aktivitas.php',
    '/log_aktivitas'        => 'log_aktivitas.php',
    '/pengaturan'           => 'pengaturan.php',
    '/manajemen-pengguna'   => 'manajemen_pengguna.php',
    '/manajemen_pengguna'   => 'manajemen_pengguna.php',
];

$cleanUri = rtrim($uriPath, '/');
if (isset($routeMap[$cleanUri])) {
    require __DIR__ . '/' . $routeMap[$cleanUri];
    return true;
}

// 3. Jika file fisik ada langsung di direktori
$filePath = __DIR__ . $uriPath;
if (file_exists($filePath) && is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // PENTING: Jika file .php, eksekusi kode PHP!
    if ($ext === 'php') {
        require $filePath;
        return true;
    }

    // Untuk file statis (CSS, JS, SVG, PNG, JPG, PDF, ZIP), kembalikan false agar disajikan server PHP
    return false;
}

// 4. Jika mengakses file .php tanpa ekstensi
if (file_exists($filePath . '.php') && is_file($filePath . '.php')) {
    require $filePath . '.php';
    return true;
}

// 5. Default fallback ke index.php
require __DIR__ . '/index.php';
return true;
