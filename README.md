# SIMIA - Sistem Instrumen Perkuliahan & Akreditasi
### Portal Informasi, Riset & Pembelajaran Maritim Terpadu - Kelautan.ID

[![PHP Version](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MariaDB%20%7C%20SQLite-003545?logo=mariadb&logoColor=white)](https://mariadb.org/)
[![CSS](https://img.shields.io/badge/Styling-Tailwind%20CSS-38B2AC?logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![Version](https://img.shields.io/badge/Release-v2.5.0-0284C7)](CHANGELOG.md)
[![License](https://img.shields.io/badge/License-Academic%20Use-emerald)](LICENSE)

SIMIA (**Sistem Instrumen Akademik**) adalah platform berbasis web (*Multi-Page Application*) mandiri yang dirancang untuk mengelola, mengaudit, dan mengarsipkan **28 standar berkas instrumen perkuliahan** (terbagi dalam 8 kategori mutu) guna pemenuhan akreditasi Program Studi Teknologi Kelautan di lingkungan Kelautan.ID.

---

## 🌟 Fitur Utama Sistem

1. **Dashboard & Monitoring Mutu Real-time**:
   - Visualisasi gauge progres kelengkapan instrumen program studi.
   - Distribusi status dokumen per semester dan tahun ajaran berjalan.
2. **Ruang Dosen - Mata Kuliah Saya (`/matkul-saya`)**:
   - **Pengelompokan Otomatis**: Kelas dari tahun ajaran sebelumnya otomatis masuk ke **Mata Kuliah Lampau (Arsip Perkuliahan)** saat Admin mengaktifkan Tahun Ajaran Baru.
   - **Pelengkapan Instrumen Lampau**: Ruang unggah tetap dibuka secara penuh bagi dosen untuk melengkapi berkas yang belum tuntas (< 100%).
   - **Tampilan Ganda & Sortir**: Mendukung mode **Kartu Grid** dan **Tabel Rinci** dengan filter per semester, tahun akademik, dan pengurutan multi-kriteria.
3. **Ruang Unggah 28 Standar Instrumen Perkuliahan (`/upload-instrumen`)**:
   - Skema matriks 8 kategori resmi: Perencanaan, Bahan Ajar (16 pertemuan), Evaluasi, Sampel Asesmen, Presensi, Portofolio CPL/CPMK, EDOM, dan Dokumen Penunjang.
   - Dukungan berkas fisik (PDF, DOCX, XLSX, PPTX, ZIP) dan tautan penyimpanan awan eksternal (Google Drive / Cloud).
4. **Rekapitulasi Progres & Broadcast Pengingat Dosen (`/rekap-progress`)**:
   - Tabel audit kelengkapan per dosen dan per mata kuliah.
   - Fitur kirim email pengingat instrumen per dosen maupun *broadcast massal* via socket PHP SMTP mandiri.
5. **Pusat Unduh Berkas ZIP Terstruktur (`/unduh-berkas`)**:
   - Ekstraksi dan pengemasan otomatis seluruh instrumen perkuliahan menjadi file ZIP rapi terstruktur untuk visitasi akreditasi (BAN-PT / LAM-Teknik).
6. **Manajemen Pengguna & Otoritas Sistem (`/manajemen-pengguna`)**:
   - Pengelolaan akun Dosen, Kaprodi, GKMP, dan Super Admin.
   - Reset password instan, generator sandi acak, toggle status aktif, dan impor/ekspor CSV.
7. **Keamanan & Pemulihan Akun Mandiri**:
   - Fitur **Lupa Kata Sandi** via email token kriptografi kadaluarsa 60 menit.
   - Proteksi CSRF, sanitasi XSS, enkripsi bcrypt, dan audit trail log aktivitas pengguna.

---

## 🚀 Persyaratan Sistem

- **PHP**: Versi 8.2, 8.3, 8.4, atau **PHP 8.5** (Rekomendasi).
- **Ekstensi PHP Wajib**: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `curl`, `zip`, `xml`, `fileinfo`, `gd`, `intl`, `openssl`.
- **Web Server**: Apache 2.4.x (dengan modul `mod_rewrite` dan `mod_ssl`) atau LiteSpeed.
- **Basis Data**: MariaDB 10.6+ / MySQL 8.0+ (Produksi) atau SQLite (Lokal).

---

## 🛠️ Instalasi Cepat (Pengembangan Lokal)

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/username/sistem-instrumentasi-kuliah.git
   cd sistem-instrumentasi-kuliah
   ```

2. **Salin File Konfigurasi**:
   ```bash
   cp config/env.example.php config/env.php
   ```
   *Secara default pada lokal, sistem akan menggunakan SQLite (`storage/db_simia_kelautan.sqlite`) yang otomatis terbuat saat pertama kali dijalankan.*

3. **Jalankan Server Lokal**:
   ```bash
   php -S localhost:8000 router.php
   ```

4. **Buka di Browser**:
   Kunjungi [http://localhost:8000](http://localhost:8000) dan masuk menggunakan kredensial bawaan administrator:
   - **NIP**: `198501012010121001`
   - **Password**: `AdminKelautan2026!`

---

## 🌐 Panduan Deployment Server Produksi

Panduan instalasi dan deployment mendalam langkah-demi-langkah tersedia pada:  
👉 **[PANDUAN_DEPLOYMENT.md](PANDUAN_DEPLOYMENT.md)**

Mencakup:
- **Panduan VPS Ubuntu Server 22.04.5 LTS** (Apache2, PHP 8.5 PPA Ondřej Surý, MariaDB, UFW, Let's Encrypt SSL).
- **Panduan cPanel Shared Hosting** (MySQL Database Wizard, MultiPHP, File Manager, AutoSSL).
- **Otomatisasi Backup Terjadwal (Cron Job)** untuk basis data dan berkas dokumen.
- **Solusi Kendala Umum (Troubleshooting FAQ)**.

---

## 📚 Dokumentasi Versi & Changelog

- Catatan rilis tiap versi: **[CHANGELOG.md](CHANGELOG.md)**
- Ringkasan fitur dan arsitektur lengkap: **[DOKUMENTASI_FITUR_DAN_VERSI.md](DOKUMENTASI_FITUR_DAN_VERSI.md)**

---

## 📄 Lisensi & Hak Cipta

© 2026 **Program Studi Teknologi Kelautan - Kelautan.ID**  
Dikembangkan oleh **Budhi Agung Prasetyo, S.Pi., M.Si.**  
Semua hak dilindungi undang-undang.
