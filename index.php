<?php
declare(strict_types=1);

/**
 * SIMIA - Front Controller / Entry Point
 */

require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
