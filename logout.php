<?php
declare(strict_types=1);

/**
 * SIMIA - Logout Script
 */

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    log_activity('LOGOUT', 'Pengguna keluar secara manual.');
}

// Hapus sesi & remember token
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

if (isset($_COOKIE['simia_remember_token'])) {
    setcookie('simia_remember_token', '', time() - 3600, '/');
}

session_start();
set_flash('info', 'Anda telah berhasil keluar dari sistem.');
redirect('login.php');
