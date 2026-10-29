<?php
declare(strict_types=1);

/**
 * SIMIA - Header Document & Asset Inclusion
 * Acuan: skills.md Bagian 1, 2, 3 & 4
 */

require_once __DIR__ . '/functions.php';

$branding = get_branding_info();
$pageTitle = $pageTitle ?? $branding['app_name'];
$activeTaSem = get_active_ta_semester();
$currentUser = auth_user();
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= e($pageTitle) ?> | <?= e($branding['sub_name']) ?></title>
    
    <!-- Favicon Dinamis -->
    <link rel="icon" type="image/svg+xml" href="<?= e(get_favicon_url()) ?>">

    <!-- Google Fonts: Outfit (Heading) & Plus Jakarta Sans (Body) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        heading: ['Outfit', 'sans-serif'],
                        sans: ['Plus Jakarta Sans', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace'],
                    },
                    colors: {
                        abyssal: {
                            950: '#060D18',
                            900: '#0A192F',
                            800: '#0F172A',
                            700: '#1E293B',
                        },
                        maritime: {
                            900: '#1E3A8A',
                            800: '#1E40AF',
                            700: '#1D4ED8',
                            600: '#2563EB',
                            500: '#0284C7',
                        },
                        ocean: {
                            DEFAULT: '#06B6D4',
                            400: '#38BDF8',
                            500: '#0EA5E9',
                            cyan: '#22D3EE',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Chart.js CDN untuk Visualisasi Statistik -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= url('public/assets/css/style.css') ?>">
</head>
<body class="h-full flex flex-col font-sans text-slate-800 bg-slate-50 antialiased selection:bg-cyan-500 selection:text-white">
