<?php
declare(strict_types=1);

/**
 * SIMIA - Contoh Konfigurasi Lingkungan (Environment Configuration Template)
 * Salin file ini menjadi 'config/env.php' dan sesuaikan nilainya dengan konfigurasi server Anda.
 */

return [
    'app' => [
        'name'          => 'SIMIA - Sistem Instrumen Akademik',
        'sub_name'      => 'Kelautan.ID',
        'tagline'       => 'Portal Informasi, Riset & Pembelajaran Maritim Terpadu Indonesia',
        'institution'   => 'Kelautan.ID',
        'study_program' => 'Program Studi Teknologi Kelautan',
        'department'    => 'Teknik Kelautan dan Perikanan',
        'developer'     => 'Budhi Agung Prasetyo, S.Pi., M.Si.',
        'copyright'     => '© 2026 Kelautan.ID - Portal Maritim Indonesia.',
        'timezone'      => 'Asia/Jayapura', // Pilihan: 'Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'
        'env'           => 'production',   // 'development' untuk lokal | 'production' untuk server publik
        'debug'         => false,          // false pada server produksi untuk keamanan
        'base_url'      => '',             // Kosongkan agar terdeteksi otomatis, atau isi misal: 'https://simia.kelautan.id'
    ],

    'database' => [
        // Driver database aktif: 'mysql' (rekomendasi produksi) atau 'sqlite' (lokal / fallback)
        'default' => 'mysql',

        // Konfigurasi SQLite (Lokal / Fallback)
        'sqlite' => [
            'path' => __DIR__ . '/../storage/db_simia_kelautan.sqlite',
        ],

        // Konfigurasi MySQL / MariaDB (Server VPS / Shared Hosting)
        'mysql' => [
            'host'      => '127.0.0.1',
            'port'      => 3306,
            'database'  => 'db_simia_kelautan', // Ganti dengan nama database Anda
            'username'  => 'simia_user',        // Ganti dengan username database Anda
            'password'  => 'PasswordKuatAnda_123!', // Ganti dengan password database Anda
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],

    'mail' => [
        'driver'       => 'smtp',             // 'smtp' atau 'mail'
        'host'         => 'smtp.gmail.com',   // Host SMTP server Anda (misal: mail.domain.com atau smtp.gmail.com)
        'port'         => 587,                // Port: 587 (TLS/STARTTLS) atau 465 (SSL)
        'encryption'   => 'tls',              // 'tls', 'ssl', atau 'none'
        'username'     => 'emailanda@gmail.com',
        'password'     => 'xxxx xxxx xxxx xxxx', // Gunakan App Password jika menggunakan Gmail
        'from_address' => 'noreply@kelautan.id',
        'from_name'    => 'SIMIA - Kelautan.ID',
    ],

    'storage' => [
        'max_file_size_mb'   => 20, // Batas maksimal ukuran dokumen per berkas (MB)
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png'],
        'allowed_mimes'      => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/x-zip-compressed',
            'image/jpeg',
            'image/png',
            'image/webp'
        ],
    ],

    'ecosystem' => [
        'portal'   => 'https://kelautan.id',
        'apps'     => 'https://apps.kelautan.id',
        'go_links' => 'https://go.kelautan.id',
        'support'  => 'teknologikelautanid@gmail.com',
    ],
];
