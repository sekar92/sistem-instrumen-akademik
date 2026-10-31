# Changelog
Semua perubahan penting pada proyek **SIMIA (Sistem Instrumen Akademik) - Kelautan.ID** akan dicatat di file ini.

Format dokumen ini mengacu pada [Keep a Changelog](https://keepachangelog.com/id/1.0.0/) dan proyek ini mematuhi [Semantic Versioning](https://semver.org/lang/id/).

Untuk dokumentasi lengkap fitur, arsitektur, dan panduan instalasi, silakan rujuk ke dokumen utama:
👉 **[DOKUMENTASI_FITUR_DAN_VERSI.md](file:///Users/budhiagungp/Documents/web/sistem-instrumentasi-kuliah/DOKUMENTASI_FITUR_DAN_VERSI.md)**

---

## [v2.5.1] - 2026-10-31
### Diperbaiki (Fixed)
- **Perbaikan Pencatatan Audit Log pada MariaDB / MySQL**:
  - Memperbaiki kueri `INSERT INTO log_aktivitas` pada helper `log_activity()` di `includes/functions.php` yang sebelumnya menggunakan fungsi spesifik SQLite `datetime('now', 'localtime')`. Pada server produksi berbasis MariaDB/MySQL (VPS), fungsi tersebut memicu error `FUNCTION datetime does not exist` yang menyebabkan pencatatan audit log (login, tambah mata kuliah, pengampu, user, dll.) gagal secara senyap (*silent failure*).
  - Mengubah penulisan `created_at` menjadi parameter terikat aman dengan format `date('Y-m-d H:i:s')` yang 100% kompatibel di kedua mesin basis data (SQLite & MariaDB/MySQL).
  - Inisialisasi zona waktu resmi (`date_default_timezone_set`) dari konfigurasi `config/env.php` agar catatan waktu audit log selalu akurat sesuai zona waktu institusi (Waktu Indonesia Timur / Jayapura atau Jakarta).
  - Memperbaiki pembaruan `used_at` pada tabel `password_resets` di `lupa_password.php` dan `reset_password.php` yang sebelumnya juga menggunakan `datetime('now')`.
  - Memperkaya dropdown filter tipe aktivitas dan pewarnaan lencana (*badge*) pada halaman `log_aktivitas.php` mencakup seluruh aksi sistem (tambah/hapus pengguna, mata kuliah, tahun ajaran, semester, email, login gagal/ditolak, dll.).

---

## [v2.5.0] - 2026-10-28
### Ditambahkan (Added)
- **Pengelompokan Otomatis Mata Kuliah Periode Aktif vs Lampau (`/matkul-saya`)**:
  - Saat Administrator menambah atau mengganti Tahun Ajaran baru menjadi *Aktif* di Master Tahun Ajaran (`/master-ta`), seluruh mata kuliah dari tahun ajaran terdahulu secara otomatis terkelompokkan ke dalam kategori **Kelompok Mata Kuliah Lampau (Arsip Perkuliahan)** secara dinamis tanpa perlu migrasi basis data.
  - Penandaan lencana visual yang jelas: *Periode Aktif* (lencana emerald) dan *Matkul Lampau* (lencana amber dengan ikon arsip).
  - Pembagian section visual pada tampilan Grid bawaan: Bagian *Mata Kuliah Periode Aktif* di atas dan *Kelompok Mata Kuliah Lampau* di bawah dengan instruksi pemenuhan akreditasi.
- **Akses Pelengkapan Instrumen Mata Kuliah Lampau Tetap Aktif (`/upload-instrumen`)**:
  - Dosen pengampu tetap dapat mengakses ruang unggah dan melengkapi sisa dokumen instrumen perkuliahan yang belum lengkap (< 100%) untuk seluruh mata kuliah lampau.
  - Banner informasi ramah pada ruang unggah mata kuliah lampau yang menegaskan bahwa ruang berkas tetap dibuka secara penuh demi pemenuhan dokumen akreditasi program studi.
  - Tombol aksi kontekstual: "Lengkapi Instrumen Lampau" (jika berkas belum lengkap) dan "Lihat Ruang Unggah" (jika sudah 100%).
- **Filter & Sortir Komprehensif per Semester dan per Tahun Akademik**:
  - **Tab Navigasi Cepat**: *Semua Mata Kuliah*, *Periode Aktif (T.A. Aktif)*, dan *Mata Kuliah Lampau (Arsip)* dengan penghitung jumlah kelas real-time dan indikator titik merah jika ada kelas lampau yang belum lengkap.
  - **Filter Interaktif**:
    - Filter Tahun Akademik (Semua TA, atau per tahun ajaran spesifik).
    - Filter Semester (Semua Semester, Ganjil, Genap, Sisipan).
    - Filter Status Kelengkapan (Semua Status, Perlu Dilengkapi < 100%, Lengkap 100%).
    - Tombol *Reset Filter* instan jika filter sedang aktif.
  - **Menu Sortir Lengkap**:
    - T.A. Terbaru & Terlama.
    - Nama Mata Kuliah (A-Z & Z-A).
    - Kode Mata Kuliah (A-Z).
    - Kelengkapan Terendah (Prioritas Dilengkapi) & Kelengkapan Tertinggi.
- **Tampilan Tabel Rinci (Table View Switcher)**:
  - Tombol beralih mode antarmuka: **Mode Kartu Grid (Cards)** vs **Mode Tabel Rinci (Table View)**.
  - Tabel terstruktur dengan 9 kolom lengkap: Nomor, Kode MK, Nama MK & SKS, Tahun Akademik, Semester, Kategori Periode, Progress Berkas (mini bar + rasio), Status Kelengkapan (Lengkap vs Kurang X berkas), dan Tombol Aksi Akses Ruang Unggah.
  - Header kolom tabel interaktif yang mendukung pengurutan langsung (sortable column headers).
- **Panduan Deployment Komprehensif VPS & cPanel (`PANDUAN_DEPLOYMENT.md`)**:
  - Panduan instalasi dan deployment mendalam untuk **VPS (Ubuntu 22.04.5 LTS, Apache2, PHP 8.5 via PPA Ondřej Surý, MariaDB Server)** dan **cPanel Shared Hosting**.
  - Penyediaan file konfigurasi template produksi `config/env.example.php`.
  - Skrip otomatisasi backup terjadwal basis data & berkas dokumen via Cron Job.
  - Dokumentasi solusi penanganan kendala umum (FAQ troubleshooting).

---

## [v2.4.0] - 2026-10-28
### Ditambahkan (Added)
- **Fitur Lupa Kata Sandi via Email Token (`/lupa-password` & `/reset-password`)**:
  - Alur pemulihan akun mandiri dengan verifikasi email terdaftar.
  - Pembuatan token kriptografi aman 64-karakter dengan masa berlaku 60 menit yang tercatat di tabel `password_resets`.
  - Formulir pembuatan kata sandi baru dengan konfirmasi dan hashing bcrypt otomatis.
  - Tautan "Lupa Kata Sandi?" terpasang rapi di sebelah checkbox "Ingat saya" pada halaman masuk.
- **PHP Mailer Engine Mandiri (`includes/mailer.php`)**:
  - Implementasi socket SMTP murni PHP (RFC 5321) mendukung koneksi TLS (STARTTLS), SSL, otentikasi AUTH LOGIN, dan fallback ke fungsi PHP `mail()`.
  - Template email HTML responsif resmi bertema maritim Kelautan.ID dengan tombol aksi dan teks alternatif plain-text.
- **Panel Pengaturan Konfigurasi Email & Pengingat Dosen (`/pengaturan`)**:
  - Pengaturan lengkap driver email (SMTP vs `mail()`), host, port, enkripsi, username, password/App Password, nama pengirim, dan alamat email pengirim.
  - **Uji Coba Pengiriman Email (Test Email)**: Fitur kirim email simulasi instan untuk menguji konektivitas server SMTP secara langsung dari dashboard pengaturan.
  - **Pengingat Kelengkapan Berkas Dosen (Reminder Broadcast)**: Menampilkan counter dosen yang belum melengkapi instrumen (< 100%) dan tombol kirim notifikasi email massal ke seluruh dosen terkait.
- **Pembaruan Modul Monitoring (`/rekap-progress`)**:
  - Integrasi pengiriman email pengingat instrumen per dosen (`send_single_reminder`) dan broadcast massal (`send_mass_reminder`) dengan engine mailer baru.

### Dihapus (Removed)
- **Akses Cepat Pengujian (Demo)**: Menghapus kotak kredensial demo pada `login.php` agar tampilan antarmuka masuk menjadi bersih, profesional, dan siap produksi.

---

## [v2.3.0] - 2026-10-28
### Ditambahkan (Added)
- **Ekosistem Tautan Digital & Footer Dinamis Fleksibel**:
  - Administrator dapat mengelola daftar tautan footer di `/pengaturan` tanpa batas jumlah.
  - Judul label/field tautan kini dapat diubah bebas (contoh: *Portal Utama*, *Apps*, *Tracer Study*, *E-Journal*, *Dukungan IT*, *Repositori Riset*).
  - Tautan repeater dinamis dengan tombol *+ Tambah Tautan*, hapus baris instan, dan tombol *Reset Bawaan* (Kelautan.ID).
  - Renderer responsif pada `includes/footer.php` dengan `flex-wrap` dan deteksi otomatis protokol link (`target="_blank"` vs `target="_self"` untuk email `mailto:`).
  - Dokumentasi spesifikasi resmi diperbarui pada [skills.md](file:///Users/budhiagungp/Documents/web/sistem-instrumentasi-kuliah/skills.md) (Bagian 1.2 & 5.6).

### Diperbaiki (Fixed)
- **Centering Pop-up Modal**: Memperbaiki pemosisian modal dialog (Reset Kata Sandi, Edit Pengguna, Tambah Pengguna, Konfirmasi Hapus) agar berada presisi di tengah layar (horizontal & vertical center) dengan flex container terpadu.

---

## [v2.2.1] - 2026-10-28
### Diperbaiki (Fixed)
- **Restrukturisasi Layout Footer & Sidebar**:
  - Memindahkan posisi tag `<footer>` dari tingkat terluar `<body>` ke dalam kanvas konten kanan (`<main>`) dengan penataan `mt-auto`.
  - Mengubah ketinggian sidebar desktop di `includes/sidebar.php` menjadi `h-full shrink-0` sehingga membentang penuh dari bawah navbar hingga ke dasar layar tanpa terpotong atau tertutup bilah putih footer.
  - Memastikan seluruh menu navigasi bagian bawah (*Manajemen Pengguna*, *Simulasi Login-As*, *Audit Log*, *Pengaturan*, dan lencana profil pengguna) 100% terlihat dan dapat di-scroll dengan lancar di seluruh 16 modul aplikasi.

---

## [v2.2.0] - 2026-10-28
### Ditambahkan (Added)
- **Modul Manajemen Pengguna & Otoritas Sistem (`/manajemen-pengguna`)**:
  - Halaman khusus Super Admin & GKMP (`require_super_admin()`) untuk mengelola seluruh akun Dosen, Koordinator Prodi (Kaprodi), GKMP, dan Admin.
  - Penambahan kolom `status` (`Aktif` / `Nonaktif`) pada tabel `users`.
  - Fitur 1-klik *Toggle Status*: Nonaktifkan akun dosen/kaprodi tanpa menghapus histori data.
  - Tombol pembuat kata sandi acak (*Random Password Generator*) 9-karakter aman.
  - Tampilan instan kata sandi baru pasca-reset agar mudah disalin untuk dibagikan.
  - Tombol simulasi login langsung (*1-Click Impersonation*) ke akun pengguna target.
  - Fitur import massal via salin-tempel spreadsheet TSV/Excel.
  - Ekspor seluruh daftar pengguna ke format CSV berstandar UTF-8 BOM untuk Microsoft Excel.
- **Badge Navigasi "SUPER"**: Penanda visual khusus pada sidebar untuk menu Manajemen Pengguna.
- **Banner Integrasi**: Tautan cepat dari modul `master_dosen.php` menuju `manajemen_pengguna.php` saat login sebagai Super Admin.

---

## [v2.1.0] - 2026-10-28
### Ditambahkan (Added)
- **Pretty URLs**: URL bersih kebab-case (`/login`, `/dashboard`, `/matkul-saya`, `/upload-instrumen`, dll.) via `router.php` dan `.htaccess`.
- **ANSI-SQL Compatibility**: Penggantian fungsi spesifik MySQL `ORDER BY FIELD(...)` menjadi `ORDER BY CASE WHEN ... END` yang kompatibel 100% di SQLite dan MySQL.
- **PHP 8.4 Support**: Penambahan parameter eksplisit lengkap pada `fputcsv()` untuk meniadakan peringatan *deprecation*.

---

## [v2.0.0] - 2026-10-27
### Ditambahkan (Added)
- **Migrasi Total dari Google Apps Script (GAS) ke PHP Native Multi-Page Application (MPA)**:
  - Ekstraksi 17 berkas skrip legacy GAS ke folder `GAS/`.
  - Arsitektur berbasis PHP 8.4/8.5 murni dengan native session management.
  - Abstraksi Dual Database PDO (`config/database.php`): Otomatis SQLite lokal dan MySQL produksi.
  - Skema matriks instrumen 8 Kategori & 28 Sub-Kategori berkas akreditasi perkuliahan.
  - Sistem pengarsipan ZIP otomatis berbasis ekstensi PHP `ZipArchive`.
  - Sistem Audit Trail komprehensif (`log_aktivitas`).
  - Sistem Simulasi Akun (`login_as`) dengan bilah peringatan sticky pulse.
  - Sistem Pengaturan & Branding Maritim Dinamis (`pengaturan`).
