# Dokumentasi Pembaharuan, Fitur, dan Rilis Sistem
## SIMIA (Sistem Instrumen Akademik) - Kelautan.ID
**Platform:** Multi-Page Web Application (MPA)  
**Teknologi:** PHP 8.4/8.5, SQLite (Local Dev) / MySQL 8.0+ (Production), Tailwind CSS, Vanilla JS, Lucide Icons, Chart.js  
**Versi Rilis Terbaru:** **v2.5.1 (Stable - Kelautan.ID Edition)**  
**Tanggal Rilis:** Oktober 2026  
**Pengembang & Inisiator:** Budhi Agung Prasetyo, S.Pi., M.Si.  
**Institusi:** Program Studi Teknologi Kelautan, Kelautan.ID  

---

## 1. Ringkasan Versi Sistem

| Versi | Status | Tanggal | Sorotan Pembaruan |
| :--- | :---: | :---: | :--- |
| **v2.5.1** | **Aktif (Terbaru)** | **31 Okt 2026** | **Perbaikan Dual-Engine Audit Log & Timezone:** Menghilangkan fungsi spesifik SQLite `datetime()` pada `log_activity()` yang memicu kegagalan pencatatan audit log di MariaDB/MySQL VPS; standardisasi parameterized query `date('Y-m-d H:i:s')`; inisialisasi zona waktu dinamis `config/env.php`; serta penambahan dropdown filter aktivitas komprehensif. |
| **v2.5.0** | Rilis Fitur | 28 Okt 2026 | **Pengelompokan Otomatis Mata Kuliah Lampau, Pelengkapan Instrumen & Tabel Sortir:** Pengelompokan dinamis kelas perkuliahan ke kelompok *Mata Kuliah Lampau* saat Admin mengaktifkan/menambah Tahun Ajaran Baru; hak akses pelengkapan berkas instrumen tetap terbuka penuh pada matkul lampau; penambahan mode Tampilan Tabel Rinci dengan sorting per semester, per tahun akademik, kode/nama MK, dan progress kelengkapan dokumen. |
| **v2.4.0** | Rilis Fitur | 28 Okt 2026 | **Fitur Lupa Kata Sandi via Email, Mailer Engine PHP & Pengingat Dosen:** Alur pemulihan akun mandiri berbasis token email kadaluarsa 60 menit (`/lupa-password` & `/reset-password`); penghapusan kredensial demo pada `login.php`; modul pengirim email pure PHP SMTP/mail; panel konfigurasi email, test email, dan broadcast pengingat kelengkapan instrumen dosen pada `/pengaturan`. |

---

## 2. Catatan Pembaharuan Detail (Changelog)

### [v2.5.1] - 31 Oktober 2026 (Perbaikan Dual-Engine Audit Log & Timezone)
* **Penyebab Kendala Audit Log Sebelumnya**:
  * Pada kode lama `includes/functions.php`, pencatatan audit log menggunakan klausa SQL `VALUES (..., datetime('now', 'localtime'))`.
  * Fungsi `datetime()` adalah fungsi khusus SQLite dan **tidak tersedia di MariaDB / MySQL**. Saat aplikasi dideploy ke server VPS berbasis MariaDB, kueri tersebut menghasilkan error sintaks `FUNCTION datetime does not exist`. Karena fungsi `log_activity()` dibungkus dalam blok `try ... catch (Throwable $e)`, error tersebut ditangkap tanpa menghentikan halaman, namun log aktivitas (seperti login dan penambahan mata kuliah) tidak tersimpan ke tabel `log_aktivitas`.
* **Solusi & Perbaikan Kompatibilitas**:
  * Mengganti klausa SQL `datetime('now', 'localtime')` menjadi parameterized query aman dengan nilai string waktu standar `date('Y-m-d H:i:s')` yang didukung penuh baik oleh SQLite maupun MariaDB/MySQL.
  * Menambahkan inisialisasi zona waktu resmi (`date_default_timezone_set`) dari file konfigurasi `config/env.php` (misal `Asia/Jayapura` atau `Asia/Jakarta`) agar catatan waktu pada audit trail selalu sinkron dan presisi.
  * Memperbaiki pembaruan `used_at` pada tabel `password_resets` di `lupa_password.php` dan `reset_password.php` agar bebas dari pemanggilan `datetime('now')`.
  * Memperkaya daftar dropdown filter pada `log_aktivitas.php` mencakup 40+ variasi aksi sistem dan menambahkan pembacaan dinamis dari tabel `log_aktivitas`.

### [v2.5.0] - 28 Oktober 2026 (Pengelompokan Otomatis Matkul Lampau, Pelengkapan Instrumen & Tabel Sortir)
* **Pengelompokan Otomatis Periode Aktif vs Mata Kuliah Lampau (`/matkul-saya`)**:
  * Saat Administrator menambah atau mengaktifkan Tahun Ajaran Baru di menu Master Tahun Ajaran (`/master-ta`), sistem secara otomatis mengelompokkan kelas-kelas dari tahun ajaran sebelumnya ke dalam kategori **Kelompok Mata Kuliah Lampau (Arsip Perkuliahan)**.
  * Proses pengelompokan ini berjalan secara dinamis tanpa memerlukan migrasi atau perubahan skema data manual.
  * Tampilan default mode kartu menyajikan pembagian dua seksi visual yang jelas: **Mata Kuliah Periode Aktif** (dengan lencana hijau menyala) di bagian atas, dan **Kelompok Mata Kuliah Lampau** (dengan lencana amber dan ikon arsip) di bagian bawah.
* **Hak Akses & Fitur Pelengkapan Instrumen Lampau (`/upload-instrumen`)**:
  * Dosen pengampu tetap memiliki akses penuh untuk membuka ruang unggah dan melengkapi berkas instrumen mata kuliah lampau yang belum mencapai 100%.
  * Banner informatif ramah disematkan pada ruang unggah mata kuliah lampau untuk menegaskan bahwa ruang berkas tetap aktif demi pemenuhan kebutuhan akreditasi program studi.
  * Tombol aksi otomatis menyesuaikan status: tombol *"Lengkapi Instrumen Lampau"* disorot jelas jika berkas masih kurang, dan tombol *"Lihat Ruang Unggah"* jika berkas telah lengkap.
* **Fitur Filter & Menu Sortir Komprehensif**:
  * **Tab Kategori Cepat**: Tab *Semua Mata Kuliah*, *Periode Aktif*, dan *Mata Kuliah Lampau* lengkap dengan counter jumlah kelas dan indikator titik merah jika ada kelas lampau yang belum lengkap.
  * **Filter Interaktif**:
    * Filter per Tahun Akademik (Semua TA, atau tahun ajaran tertentu).
    * Filter per Semester (Semua Semester, Ganjil, Genap, Sisipan).
    * Filter Status Kelengkapan (Semua Status, Perlu Dilengkapi < 100%, Lengkap 100%).
    * Tombol *Reset Filter* instan jika filter sedang aktif.
  * **Pilihan Pengurutan (Sort)**:
    * Urutkan berdasarkan Tahun Akademik Terbaru & Terlama.
    * Urutkan berdasarkan Nama Mata Kuliah (A-Z & Z-A).
    * Urutkan berdasarkan Kode Mata Kuliah (A-Z).
    * Urutkan berdasarkan Kelengkapan Terendah (Prioritas Dilengkapi) & Kelengkapan Tertinggi.
* **Tampilan Tabel Rinci (Table View Switcher)**:
  * Tombol beralih mode antarmuka: **Mode Kartu Grid (Cards)** vs **Mode Tabel Rinci (Table View)**.
  * Tabel terstruktur dengan 9 kolom informatif: Nomor, Kode MK, Nama MK & SKS, Tahun Akademik, Semester, Kategori Periode, Progress Berkas (mini bar + rasio), Status Kelengkapan, dan Tombol Aksi Akses Ruang Unggah.
  * Header kolom tabel interaktif yang mendukung pengurutan langsung (sortable column headers).

### [v2.4.0] - 28 Oktober 2026 (Fitur Lupa Kata Sandi, Mailer Engine & Pengingat Dosen)
* **Fitur Lupa Kata Sandi Akun Mandiri (`/lupa-password` & `/reset-password`)**:
  * Pengguna yang lupa kata sandi dapat memasukkan email terdaftar mereka pada halaman lupa password.
  * Sistem membuat token kriptografi aman 64-karakter dengan waktu kedaluwarsa 60 menit yang disimpan pada tabel `password_resets`.
  * Email HTML resmi bertema maritim dikirimkan ke kotak masuk pengguna berisi tombol aksi aman menuju halaman reset kata sandi.
  * Validasi token kadaluarsa, pencegahan penggunaan berulang (*one-time use*), dan enkripsi sandi baru menggunakan bcrypt otomatis.
  * Tautan "Lupa Kata Sandi?" ditempatkan rapi di sisi kanan checkbox "Ingat saya" pada halaman masuk.
* **Pembersihan Halaman Masuk (`login.php`)**:
  * Menghapus seluruh kotak Akses Cepat Pengujian (Demo) beserta tombol pengisian otomatisnya agar antarmuka login bersih, aman, dan siap digunakan di lingkungan produksi (*production-ready*).
* **SIMIA Mailer Engine Mandiri (`includes/mailer.php`)**:
  * Engine pengirim email pure PHP berbasis socket RFC 5321 tanpa dependensi library eksternal.
  * Mendukung koneksi terenkripsi TLS (STARTTLS port 587), SSL (port 465), otentikasi SMTP AUTH LOGIN, dan fallback ke fungsi PHP `mail()`.
  * Dilengkapi template email HTML maritim yang konsisten dengan identitas visual Kelautan.ID.
* **Panel Konfigurasi Email & Pengingat Berkas Dosen (`/pengaturan`)**:
  * Konfigurasi lengkap server email: Driver (SMTP vs `mail()`), Host, Port, Enkripsi, Username, Password/App Password, Nama Pengirim, dan Alamat Pengirim.
  * **Fitur Uji Coba Email (Test Email)**: Mengirimkan email tes secara instan ke alamat tujuan untuk memvalidasi keberhasilan koneksi SMTP.
  * **Broadcast Pengingat Kelengkapan Berkas Dosen**: Menampilkan indikator jumlah dosen pengampu yang belum melengkapi instrumen (< 100%) dan tombol kirim email pengingat massal secara langsung dari panel pengaturan.
* **Integrasi Modul Rekapitulasi (`/rekap-progress`)**:
  * Tombol kirim pengingat per dosen maupun broadcast massal kini terintegrasi langsung dengan mailer engine baru, lengkap dengan laporan status pengiriman dan pencatatan audit log.

### [v2.3.0] - 28 Oktober 2026 (Ekosistem Tautan Digital Fleksibel & Perbaikan Modal)
* **Ekosistem Tautan Digital & Footer Dinamis (`/pengaturan`)**:
  * **Kustomisasi Judul Label & URL Sepenuhnya**: Administrator kini bebas mengubah nama label tautan (misal: *Portal Utama*, *Portal Apps*, *Tracer Study*, *E-Journal Kelautan*, *Dukungan IT*, dll.) dan tautan tujuannya.
  * **Repeater Tautan Tak Terbatas**: Administrator dapat menambahkan link sebanyak-banyaknya melalui tombol `+ Tambah Tautan` dan menghapus tautan dengan 1-klik ikon tong sampah.
  * **Tombol Reset Bawaan**: Menyediakan tombol pemulihan instan ke konfigurasi standar Kelautan.ID (`Portal`, `Apps`, `Pemendek Tautan`, `Dukungan`).
  * **Penyimpanan JSON Terstruktur**: Tautan disimpan dalam tabel `pengaturan` dengan kunci `footer_links_json`.
  * **Renderer Footer Adaptif (`includes/footer.php`)**: Menampilkan seluruh tautan aktif dengan tata letak `flex-wrap`, jarak renggang `gap-x-3 gap-y-1.5`, serta pemisah titik yang rapi dan deteksi protokol otomatis (`target="_blank"` untuk web URL dan `target="_self"` untuk protokol `mailto:`).
  * **Pembaruan Acuan Desain**: Spesifikasi resmi tautan dinamis ini telah didokumentasikan di `skills.md` Bagian 1.2 dan 5.6.
* **Perbaikan Pemosisian Pop-up Modal (Center Viewport)**:
  * Memperbaiki pemosisian dialog modal (Reset Kata Sandi, Edit Pengguna, Tambah Pengguna, Konfirmasi Hapus) yang sebelumnya muncul di sudut kiri bawah layar akibat ketiadaan kelas `flex` pada container saat dibuka.
  * Mengintegrasikan `items-center justify-center` pada backdrop fixed dan `w-full my-auto` pada kartu modal agar dialog selalu tampil terpusat sempurna di tengah layar pengguna.
* **Tata Letak 2-Tier Navigation Standar Industri**:
  * Menyelaraskan struktur halaman dengan spesifikasi arsitektur pada `skills.md` Bagian 4.
  * **Sidebar Desktop Bebas Hambatan**: Sidebar navigasi kiri (`w-64 bg-abyssal-900`) kini memiliki tinggi penuh (`h-full shrink-0`) dengan area gulir independen (`overflow-y-auto`) dari bawah top header hingga batas bawah layar. Seluruh menu (termasuk *Manajemen Pengguna*, *Simulasi Login-As*, *Audit Log*, *Pengaturan*, dan lencana profil) kini 100% terlihat tanpa terpotong.
  * **Footer Terisolasi di Kolom Kanan**: Elemen `<footer>` dipindahkan dari tingkat terluar `<body>` ke dalam kolom kanvas konten kanan (`<main class="flex-1 flex flex-col justify-between ...">`). Footer dengan kelas `mt-auto` menempel rapi di dasar konten halaman tanpa pernah membentang atau menutupi kolom sidebar kiri.
  * **Pembaruan Serentak pada 16 Modul**: Penataan ulang ini diterapkan konsisten pada seluruh 16 modul PHP aplikasi.

### [v2.2.0] - 28 Oktober 2026 (Modul Manajemen Pengguna & Hak Akses RBAC)
* **Modul Baru `manajemen_pengguna.php` (`/manajemen-pengguna`)**:
  * Didedikasikan khusus untuk **Super Admin** dan **Tim Penjaminan Mutu (GKMP)** melalui pengamanan `require_super_admin()`.
  * **Cakupan Peran Lengkap**: Mendukung pengelolaan akun `Dosen`, `Kaprodi` (Koordinator Program Studi), `GKMP`, `Admin`, dan `Super Admin`.
  * **Pengendali Status Akun (Aktif / Nonaktif)**: Penambahan kolom `status` pada tabel database `users`. Akun nonaktif secara otomatis ditolak saat mencoba masuk di `login.php`.
  * **Pembuat Sandi Acak (Random Password Generator)**: Fitur 1-klik untuk men-generate kata sandi aman 9-karakter saat pembuatan atau reset akun.
  * **Reset Kata Sandi Instan**: Sandi baru langsung ditampilkan di layar dalam banner notifikasi aman yang siap disalin untuk diserahkan kepada dosen/kaprodi bersangkutan.
  * **Simulasi Login (Login-As) 1-Klik**: Tombol aksi cepat di tabel pengguna untuk langsung menguji antarmuka dosen/kaprodi tanpa harus membuka modul simulasi terpisah.
  * **Import Massal Spreadsheet (TSV/Excel)**: Mendukung salin-tempel puluhan baris data pengguna sekaligus dari Google Sheets atau Microsoft Excel.
  * **Ekspor Data Pengguna CSV**: Ekspor seluruh daftar pengguna dengan format berstandar UTF-8 BOM agar langsung terbaca rapi di Microsoft Excel.
  * **Proteksi Akun Sendiri & Super Admin Terakhir**: Super Admin dicegah menonaktifkan akun sendiri, menurunkan peran sendiri, atau menghapus akun Super Admin terakhir di sistem.

### [v2.1.0] - 28 Oktober 2026 (Pretty URLs & Kompatibilitas Dual-Engine)
* **Arsitektur URL Bersih (Pretty URLs)**:
  * Seluruh tautan internal diubah dari ekstensi file `.php` menjadi URL bersih kebab-case:
    * `/login`, `/logout`
    * `/dashboard`
    * `/profil`
    * `/matkul-saya`
    * `/upload-instrumen`
    * `/rekap-progress`
    * `/assign-pengampu`
    * `/master-mk`, `/master-ta`, `/master-semester`, `/master-dosen`
    * `/manajemen-pengguna`
    * `/unduh-berkas`
    * `/informasi`
    * `/login-as`
    * `/log-aktivitas`
    * `/pengaturan`
  * Disediakan `router.php` untuk server pengembangan lokal (`php -S localhost:8000 router.php`) dan konfigurasi `.htaccess` berbasis `mod_rewrite` untuk Apache produksi.
* **Perbaikan Kompatibilitas SQLite & MySQL**:
  * Menghilangkan penggunaan fungsi khusus MySQL seperti `ORDER BY FIELD(...)` yang memicu error pada SQLite.
  * Menggantinya dengan sintaks standar ANSI-SQL: `ORDER BY CASE role WHEN ... THEN ... END`.
  * Menambahkan parameter pemisah lengkap pada fungsi `fputcsv()` untuk kompatibilitas penuh PHP 8.4/8.5 tanpa peringatan *deprecation*.

### [v2.0.0] - 27 Oktober 2026 (Major Release: Migrasi Penuh Arsitektur)
* **Transformasi Arsitektur Menyeluruh**:
  * Kode legacy Google Apps Script (17 file modul) dikloning ke direktori `GAS/` untuk referensi audit logis.
  * Aplikasi dibangun ulang menjadi **Multi-Page Application (MPA)** berbasis PHP murni dengan session server-side native (menghilangkan ketergantungan `sessionStorage` browser yang rentan *blank screen*).
* **Dual Database Engine Abstraction (`config/database.php`)**:
  * Mendukung SQLite otomatis untuk kemudahan pengembangan lokal tanpa instalasi server database eksternal.
  * Mendukung MySQL / MariaDB dengan *connection pooling* dan auto-migration untuk lingkungan server produksi.
* **Standarisasi Dokumen Akreditasi 8 Kategori & 28 Sub-Kategori**:
  * Absensi (2 berkas: Pertemuan 1-8, Pertemuan 9-16)
  * Berita Acara Perkuliahan / BAP (1 berkas)
  * Rencana Pembelajaran Semester / RPS & Portofolio (2 berkas)
  * Kontrak Perkuliahan (1 berkas)
  * Soal Ujian (2 berkas: UTS, UAS)
  * Daftar Nilai Mahasiswa (2 berkas: Nilai Formatif & Tugas, Nilai Akhir)
  * Modul & Panduan (2 berkas: Modul Kuliah, Modul Praktikum)
  * Bahan Ajar & Handout (16 berkas: Pertemuan 1 s.d. 16)
  * Total instrumen wajib per kelas pengampu: **28 dokumen**.

---

## 3. Matriks Fitur Lengkap Berdasarkan Hak Akses

| No | Modul & Fitur | Rute URL | Dosen | Kaprodi | Admin | Super Admin / GKMP |
| :-: | :--- | :--- | :---: | :---: | :---: | :---: |
| 1 | **Autentikasi & Sesi** | `/login`, `/logout` | ✅ | ✅ | ✅ | ✅ |
| 2 | **Dashboard & Pengumuman** | `/dashboard` | ✅ | ✅ | ✅ | ✅ |
| 3 | **Profil Pribadi & Ganti Sandi** | `/profil` | ✅ | ✅ | ✅ | ✅ |
| 4 | **Daftar Mata Kuliah Diampu** | `/matkul-saya` | ✅ | ✅ | ✅ | ✅ |
| 5 | **Ruang Unggah 28 Instrumen** | `/upload-instrumen` | ✅ | ✅ | ✅ | ✅ |
| 6 | **Papan Informasi (Microblog)** | `/informasi` | Baca | Baca/Tulis | Baca/Tulis | Penuh |
| 7 | **Rekapitulasi & Peringatan Dosen** | `/rekap-progress` | ❌ | ✅ | ✅ | Penuh |
| 8 | **Penugasan Dosen Pengampu** | `/assign-pengampu` | ❌ | ✅ | ✅ | Penuh |
| 9 | **Master Mata Kuliah & CPMK** | `/master-mk` | ❌ | ✅ | ✅ | Penuh |
| 10 | **Master Tahun Ajaran** | `/master-ta` | ❌ | ❌ | ✅ | Penuh |
| 11 | **Master Semester (Ganjil/Genap)** | `/master-semester` | ❌ | ❌ | ✅ | Penuh |
| 12 | **Daftar Sivitas Dosen** | `/master-dosen` | ❌ | ❌ | ✅ | Penuh |
| 13 | **Unduh Arsip Dokumen ZIP** | `/unduh-berkas` | ❌ | ✅ | ✅ | Penuh |
| 14 | **Manajemen Pengguna & Otoritas** | `/manajemen-pengguna` | ❌ | ❌ | ❌ | **Eksklusif** |
| 15 | **Simulasi Akun (Login-As)** | `/login-as` | ❌ | ❌ | ❌ | **Eksklusif** |
| 16 | **Audit Trail & Log Aktivitas** | `/log-aktivitas` | ❌ | ❌ | ❌ | **Eksklusif** |
| 17 | **Pengaturan Sistem & Branding** | `/pengaturan` | ❌ | ❌ | ❌ | **Eksklusif** |

---

## 4. Keunggulan Arsitektur & Keamanan Sistem

1. **Keamanan Bertingkat (Defense in Depth)**:
   * **Hash Kredensial Mutakhir**: Menggunakan algoritma Bcrypt via `password_hash()` dan `password_verify()`.
   * **Anti-CSRF Protection**: Seluruh request mutasi data (`POST`) diverifikasi dengan token unik per sesi (`_csrf_token`).
   * **Proteksi Brute-Force & Session Fixation**: Regenerasi ID sesi berkala dan isolasi cookie HTTPOnly + SameSite.
   * **Audit Trail Komprehensif**: Pencatatan aktivitas sensitif (Login, Upload, Hapus, Mutasi Akun, Simulasi Login) lengkap dengan alamat IP klien, user-agent, dan penanda impersonasi.
2. **Desain Maritim Modern (Design Tokens)**:
   * Mengikuti pedoman branding `skills.md` dengan palet maritim:
     * **Abyssal Navy** (`#0A192F`, `#060D18`) sebagai fondasi latar dan navigasi.
     * **Maritime Blue** (`#1D4ED8`, `#1E3A8A`) sebagai penanda aksi utama.
     * **Ocean Cyan** (`#06B6D4`, `#22D3EE`) sebagai aksen status dan progres.
   * Tipografi ganda profesional: **Outfit** untuk *heading* dan **Plus Jakarta Sans** untuk antarmuka teks.
3. **Penyimpanan Berkas Terstruktur**:
   * Seluruh dokumen perkuliahan tersimpan pada direktori aman `storage/uploads/instrumen/`.
   * Penamaan berkas standar otomatis: `[KODE_MK]_[SUB_KATEGORI]_[TIMESTAMP].[EXT]`.
   * Validasi tipe file ketat: Dokumen PDF, DOC, DOCX, XLS, XLSX dengan batasan ukuran dinamis (default: 10 MB per berkas).
4. **Pencadangan Berkas Otomatis (ZIP Archiving)**:
   * Modul `/unduh-berkas` memanfaatkan ekstensi PHP `ZipArchive` asli untuk mengompresi ribuan dokumen instrumen perkuliahan per semester ke dalam satu paket `.zip` terstruktur tanpa membebani memori server.

---

## 5. Panduan Menjalankan Sistem

### A. Lingkungan Pengembangan Lokal (Local Development)
1. Buka Terminal pada direktori proyek:
   ```bash
   cd /Users/budhiagungp/Documents/web/sistem-instrumentasi-kuliah
   ```
2. Jalankan built-in server PHP menggunakan *router*:
   ```bash
   php -S localhost:8000 router.php
   ```
3. Buka peramban pada alamat:
   ```
   http://localhost:8000/login
   ```

### B. Lingkungan Server Produksi (VPS & cPanel Shared Hosting)
Untuk panduan instalasi dan deployment mendalam langkah-demi-langkah pada **VPS (Ubuntu 22.04 LTS, Apache2, PHP 8.5, MariaDB)** dan **cPanel Shared Hosting**, silakan rujuk ke dokumen resmi:  
👉 **[PANDUAN_DEPLOYMENT.md](file:///Users/budhiagungp/Documents/web/sistem-instrumentasi-kuliah/PANDUAN_DEPLOYMENT.md)**

1. Pastikan modul Apache `mod_rewrite`, `mod_ssl`, `mod_headers` dan ekstensi PHP `pdo_mysql`, `zip`, `mbstring`, `fileinfo`, `gd`, `intl` aktif.
2. Salin seluruh isi direktori ke `public_html` atau direktori VirtualHost (`/var/www/simia`).
3. Konfigurasikan kredensial database MariaDB/MySQL pada file `config/env.php` (contoh template pada `config/env.example.php`).
4. Pastikan direktori `storage/` dan `storage/uploads/` memiliki izin tulis (`chmod -R 775 storage/`).

---

## 6. Kredensial Akun Bawaan (Default Seeders)

| Peran (Role) | NIP / Username | Kata Sandi Default | Nama Pengguna | Wewenang Utama |
| :--- | :---: | :---: | :--- | :--- |
| **Super Admin** | `198501012010121001` | `admin123` | Budhi Agung Prasetyo, S.Pi., M.Si. | Manajemen Pengguna Penuh, Master Data, Audit Trail, Pengaturan Sistem |
| **Kaprodi** | `197801012005011009` | `kaprodi123` | Dr. Ir. Hendra Saputra, M.Si. | Monitoring Mutu Prodi, Penugasan Dosen, Master MK/CPMK, Rekapitulasi |
| **Admin / GKMP** | `198705122015041002` | `admin123` | Dra. Nurhayati, M.Si. | Penugasan Kelas, Papan Pengumuman, Rekapitulasi Progress, Unduh ZIP |
| **Dosen 1** | `199003152019031005` | `dosen123` | Ahmad Fauzi, S.T., M.T. | Mengunggah 28 Berkas Perkuliahan, Kelola Profil |
| **Dosen 2** | `199208202020122011` | `dosen123` | Siti Rahmawati, S.Kel., M.Si. | Mengunggah 28 Berkas Perkuliahan, Kelola Profil |

---
*Dokumen ini diperbarui secara berkala mengikuti iterasi dan penyempurnaan sistem SIMIA Kelautan.ID.*
