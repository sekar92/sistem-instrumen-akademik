# Panduan Deployment SIMIA (Sistem Instrumen Akademik)
## Panduan Lengkap Instalasi & Konfigurasi Server Produksi (VPS & cPanel Shared Hosting)

**Platform:** SIMIA - Kelautan.ID  
**Versi Rilis:** v2.5.0 (Stable - Kelautan.ID Edition)  
**Target Server VPS:** Ubuntu Server 22.04.5 LTS, Apache 2.4, PHP 8.5, MariaDB Server  
**Target Shared Hosting:** cPanel (CloudLinux / CentOS / AlmaLinux), Apache/LiteSpeed, MariaDB/MySQL  
**Pengembang:** Budhi Agung Prasetyo, S.Pi., M.Si.  
**Institusi:** Program Studi Teknologi Kelautan, Kelautan.ID  

---

## Daftar Isi
1. [Spesifikasi Sistem & Prasyarat](#1-spesifikasi-sistem--prasyarat)
2. [BAGIAN I: Panduan Deployment di VPS (Ubuntu Server 22.04.5 LTS)](#bagian-i-panduan-deployment-di-vps-ubuntu-server-22045-lts)
   - [Langkah 1: Update Sistem & Konfigurasi Firewall UFW](#langkah-1-update-sistem--konfigurasi-firewall-ufw)
   - [Langkah 2: Instalasi & Konfigurasi MariaDB Server](#langkah-2-instalasi--konfigurasi-mariadb-server)
   - [Langkah 3: Instalasi Apache2 & PHP 8.5 (via PPA Ondřej Surý)](#langkah-3-instalasi-apache2--php-85-via-ppa-ondřej-surý)
   - [Langkah 4: Konfigurasi Parameter PHP 8.5 (php.ini)](#langkah-4-konfigurasi-parameter-php-85-phpini)
   - [Langkah 5: Penempatan Berkas Aplikasi di /var/www/simia](#langkah-5-penempatan-berkas-aplikasi-di-varwwwsimia)
   - [Langkah 6: Pengaturan Kepemilikan & Hak Akses Direktori (Permissions)](#langkah-6-pengaturan-kepemilikan--hak-akses-direktori-permissions)
   - [Langkah 7: Konfigurasi Lingkungan Produksi (config/env.php)](#langkah-7-konfigurasi-lingkungan-produksi-configenvphp)
   - [Langkah 8: Konfigurasi Virtual Host Apache2 & Mod Rewrite](#langkah-8-konfigurasi-virtual-host-apache2--mod-rewrite)
   - [Langkah 9: Pemasangan Sertifikat SSL Gratis (Let's Encrypt / Certbot)](#langkah-9-pemasangan-sertifikat-ssl-gratis-lets-encrypt--certbot)
3. [BAGIAN II: Panduan Deployment di cPanel Shared Hosting](#bagian-ii-panduan-deployment-di-cpanel-shared-hosting)
   - [Langkah 1: Pembuatan Basis Data MySQL di cPanel](#langkah-1-pembuatan-basis-data-mysql-di-cpanel)
   - [Langkah 2: Pemilihan Versi PHP & Pengaktifan Ekstensi](#langkah-2-pemilihan-versi-php--pengaktifan-ekstensi)
   - [Langkah 3: Pengaturan Parameter PHP di MultiPHP INI Editor](#langkah-3-pengaturan-parameter-php-di-multiphp-ini-editor)
   - [Langkah 4: Unggah Berkas & Ekstrak di File Manager](#langkah-4-unggah-berkas--ekstrak-di-file-manager)
   - [Langkah 5: Memastikan File .htaccess Aktif](#langkah-5-memastikan-file-htaccess-aktif)
   - [Langkah 6: Konfigurasi config/env.php di cPanel](#langkah-6-konfigurasi-configenvphp-di-cpanel)
   - [Langkah 7: Pemasangan SSL di cPanel (AutoSSL / Let's Encrypt)](#langkah-7-pemasangan-ssl-di-cpanel-autossl--lets-encrypt)
4. [Inisialisasi Otomatis & Kredensial Default](#4-inisialisasi-otomatis--kredensial-default)
5. [Skrip Backup Otomatis Basis Data & Berkas](#5-skrip-backup-otomatis-basis-data--berkas)
6. [Panduan Pemecahan Masalah (Troubleshooting FAQ)](#6-panduan-pemecahan-masalah-troubleshooting-faq)

---

## 1. Spesifikasi Sistem & Prasyarat

### Rekomendasi Kebutuhan Minimum
| Komponen | Spesifikasi Minimum (VPS) | Shared Hosting |
| :--- | :--- | :--- |
| **OS** | Ubuntu Server 22.04.5 LTS (Jammy Jellyfish) | CloudLinux / AlmaLinux / CentOS |
| **CPU** | 1 vCPU (2 vCPU disarankan) | Shared cPanel |
| **RAM** | 1 GB (2 GB disarankan jika MariaDB aktif) | Minimal paket cPanel 1 GB RAM |
| **Penyimpanan**| 20 GB SSD (disesuaikan volume dokumen akreditasi) | 5 GB+ Disk Space |
| **Web Server** | Apache 2.4.x dengan modul `mod_rewrite`, `mod_ssl`, `mod_headers` | Apache / LiteSpeed |
| **PHP Engine** | **PHP 8.5** (atau PHP 8.4/8.3) | PHP 8.2 / 8.3 / 8.4+ |
| **Basis Data** | **MariaDB Server 10.6+** atau MySQL 8.0+ | MySQL / MariaDB |

### Ekstensi PHP yang Wajib Diaktifkan
- `pdo` & `pdo_mysql` (Driver utama basis data produksi)
- `pdo_sqlite` (Driver cadangan / fallback)
- `mbstring` (Manipulasi string UTF-8 multibyte)
- `curl` (Koneksi HTTP & API eksternal)
- `zip` (Ekstraksi dan pembuatan bundel arsip ZipArchive)
- `xml` & `dom` (Parsing dokumen dan komponen sistem)
- `fileinfo` (Verifikasi tipe MIME dokumen yang diunggah)
- `gd` (Pemrosesan gambar, avatar pengguna, dan logo)
- `intl` (Internasionalisasi dan format tanggal)
- `openssl` (Enkripsi token kata sandi & koneksi socket TLS/SSL mailer)

---

## BAGIAN I: Panduan Deployment di VPS (Ubuntu Server 22.04.5 LTS)

Ikuti langkah-langkah terstruktur berikut melalui sesi SSH server VPS Anda sebagai user `root` atau user dengan hak akses `sudo`.

```bash
# Masuk ke server VPS via SSH
ssh root@ip-address-vps-anda
```

---

### Langkah 1: Update Sistem & Konfigurasi Firewall UFW

Perbarui seluruh repositori paket sistem Ubuntu dan konfigurasikan firewall UFW agar port SSH, HTTP, dan HTTPS terbuka dengan aman.

```bash
# 1. Update paket sistem
sudo apt update && sudo apt upgrade -y

# 2. Pasang paket utilitas umum
sudo apt install -y curl wget git unzip zip software-properties-common ufw ca-certificates lsb-release

# 3. Konfigurasi Firewall UFW
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Aktifkan UFW (ketik 'y' saat konfirmasi)
sudo ufw enable
sudo ufw status
```

---

### Langkah 2: Instalasi & Konfigurasi MariaDB Server

```bash
# 1. Pasang MariaDB Server
sudo apt install -y mariadb-server mariadb-client

# 2. Pastikan layanan MariaDB aktif dan berjalan saat booting
sudo systemctl enable mariadb
sudo systemctl start mariadb

# 3. Jalankan pengamanan instalasi basis data
sudo mysql_secure_installation
```
Saat menjalankan `mysql_secure_installation`, ikuti panduan rekomendasi berikut:
- *Enter current password for root:* Tekan **Enter** (kosong saat pertama kali).
- *Switch to unix_socket authentication:* Ketik **Y**.
- *Change the root password?* Ketik **Y** lalu masukkan password root MariaDB yang kuat.
- *Remove anonymous users?* Ketik **Y**.
- *Disallow root login remotely?* Ketik **Y**.
- *Remove test database and access to it?* Ketik **Y**.
- *Reload privilege tables now?* Ketik **Y**.

#### Membuat Database & User Khusus SIMIA
Masuk ke CLI MariaDB:
```bash
sudo mysql -u root -p
```
Jalankan query SQL berikut di dalam shell MariaDB (ganti `PasswordKuatAnda_123!` dengan kata sandi rahasia yang aman):

```sql
-- 1. Buat database dengan encoding utf8mb4 standar internasional
CREATE DATABASE db_simia_kelautan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Buat user database khusus
CREATE USER 'simia_user'@'localhost' IDENTIFIED BY 'PasswordKuatAnda_123!';

-- 3. Berikan hak akses penuh ke database SIMIA
GRANT ALL PRIVILEGES ON db_simia_kelautan.* TO 'simia_user'@'localhost';

-- 4. Terapkan perubahan privileges
FLUSH PRIVILEGES;

-- 5. Keluar dari MariaDB
EXIT;
```

---

### Langkah 3: Instalasi Apache2 & PHP 8.5 (via PPA Ondřej Surý)

Ubuntu 22.04 LTS secara bawaan membawa PHP 8.1. Untuk menggunakan **PHP 8.5** (atau PHP 8.4/8.3), tambahkan repositori resmi PPA Ondřej Surý:

```bash
# 1. Tambahkan PPA PHP Ondřej Surý
sudo add-apt-repository ppa:ondrej/php -y
sudo add-apt-repository ppa:ondrej/apache2 -y
sudo apt update

# 2. Pasang Web Server Apache2
sudo apt install -y apache2

# 3. Pasang PHP 8.5 beserta seluruh modul pendukung SIMIA
sudo apt install -y php8.5 libapache2-mod-php8.5 php8.5-common \
    php8.5-mysql php8.5-sqlite3 php8.5-mbstring php8.5-xml \
    php8.5-curl php8.5-zip php8.5-gd php8.5-fileinfo \
    php8.5-intl php8.5-bcmath php8.5-opcache

# 4. Verifikasi versi PHP yang terpasang
php -v
```

---

### Langkah 4: Konfigurasi Parameter PHP 8.5 (php.ini)

Buka file konfigurasi `php.ini` untuk modul Apache:

```bash
sudo nano /etc/php/8.5/apache2/php.ini
```

Cari parameter berikut (gunakan `Ctrl + W` di nano) dan sesuaikan nilainya:

```ini
; Tingkatkan batas ukuran upload dokumen akreditasi
upload_max_filesize = 50M
post_max_size = 60M

; Tingkatkan batas memori dan waktu eksekusi untuk arsip ZipArchive
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; Zona waktu resmi (Waktu Indonesia Timur / Jayapura atau Jakarta)
date.timezone = Asia/Jayapura

; Keamanan di lingkungan produksi (sembunyikan error dari publik)
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
```
Simpan file dengan menekan `Ctrl + O` lalu `Enter`, kemudian keluar dengan `Ctrl + X`.

---

### Langkah 5: Penempatan Berkas Aplikasi di /var/www/simia

Buat direktori proyek dan salin berkas aplikasi Anda:

```bash
# 1. Buat direktori aplikasi
sudo mkdir -p /var/www/simia

# 2. Opsi A: Jika menggunakan Git (direkomendasikan)
sudo git clone https://github.com/username/sistem-instrumentasi-kuliah.git /var/www/simia

# 2. Opsi B: Jika menyalin langsung dari komputer lokal Anda via SCP / Rsync:
# (Jalankan perintah ini DARI KOMPUTER LOKAL Anda)
# rsync -avz --exclude='.git' /path/ke/sistem-instrumentasi-kuliah/ root@ip-vps-anda:/var/www/simia/
```

Pastikan struktur direktori `/var/www/simia` memuat file-file utama:
- `index.php`, `login.php`, `dashboard.php`, `matkul_saya.php`, dll.
- `.htaccess`
- `config/`
- `includes/`
- `storage/`

---

### Langkah 6: Pengaturan Kepemilikan & Hak Akses Direktori (Permissions)

Apache berjalan dengan pengguna `www-data`. Berikan hak akses baca untuk berkas kode dan hak akses tulis (*writeable*) khusus untuk folder penyimpanan dokumen `storage/`:

```bash
# 1. Atur kepemilikan seluruh berkas ke web server (www-data)
sudo chown -R www-data:www-data /var/www/simia

# 2. Atur permission standar direktori (755) dan file (644)
sudo find /var/www/simia -type d -exec chmod 755 {} \;
sudo find /var/www/simia -type f -exec chmod 644 {} \;

# 3. Berikan hak akses tulis penuh untuk direktori storage & uploads
sudo chmod -R 775 /var/www/simia/storage
sudo mkdir -p /var/www/simia/storage/uploads/instrumen
sudo mkdir -p /var/www/simia/storage/uploads/branding
sudo chown -R www-data:www-data /var/www/simia/storage
```

---

### Langkah 7: Konfigurasi Lingkungan Produksi (config/env.php)

Salin file template [config/env.example.php](file:///Users/budhiagungp/Documents/web/sistem-instrumentasi-kuliah/config/env.example.php) menjadi `config/env.php`:

```bash
cd /var/www/simia
sudo cp config/env.example.php config/env.php
sudo nano config/env.php
```

Sesuaikan konfigurasi berikut untuk server produksi Anda:

```php
<?php
declare(strict_types=1);

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
        'timezone'      => 'Asia/Jayapura',
        'env'           => 'production',   // Set ke 'production'
        'debug'         => false,          // Nonaktifkan debug untuk keamanan
        'base_url'      => 'https://simia.kelautan.id', // URL domain Anda
    ],

    'database' => [
        'default' => 'mysql', // AKTIFKAN DRIVER MYSQL UNTUK PRODUKSI

        'sqlite' => [
            'path' => __DIR__ . '/../storage/db_simia_kelautan.sqlite',
        ],

        'mysql' => [
            'host'      => '127.0.0.1',
            'port'      => 3306,
            'database'  => 'db_simia_kelautan', // Database yang dibuat di Langkah 2
            'username'  => 'simia_user',        // User MariaDB yang dibuat
            'password'  => 'PasswordKuatAnda_123!', // Password MariaDB yang dibuat
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],

    'mail' => [
        'driver'       => 'smtp',
        'host'         => 'smtp.gmail.com', // Atau mail.kelautan.id
        'port'         => 587,
        'encryption'   => 'tls',
        'username'     => 'teknologikelautanid@gmail.com',
        'password'     => 'xxxx xxxx xxxx xxxx', // App Password SMTP
        'from_address' => 'teknologikelautanid@gmail.com',
        'from_name'    => 'SIMIA - Kelautan.ID',
    ],

    'storage' => [
        'max_file_size_mb'   => 20,
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png'],
        // ...
    ],
    // ...
];
```
Simpan file (`Ctrl + O`, `Enter`, `Ctrl + X`).

---

### Langkah 8: Konfigurasi Virtual Host Apache2 & Mod Rewrite

Aktifkan modul Apache yang diperlukan untuk URL Cantik (*Pretty URLs*) dan SSL:

```bash
# Aktifkan modul penting
sudo a2enmod rewrite ssl headers env
```

Buat file virtual host Apache untuk domain Anda:

```bash
sudo nano /etc/apache2/sites-available/simia.conf
```

Tambahkan konfigurasi berikut (ganti `simia.kelautan.id` dengan domain/subdomain Anda):

```apache
<VirtualHost *:80>
    ServerName simia.kelautan.id
    ServerAdmin webmaster@kelautan.id
    DocumentRoot /var/www/simia

    # Log akses dan error
    ErrorLog ${APACHE_LOG_DIR}/simia_error.log
    CustomLog ${APACHE_LOG_DIR}/simia_access.log combined

    # Konfigurasi Direktori & Izin .htaccess
    <Directory /var/www/simia>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Proteksi file sensitif
    <FilesMatch "\.(sqlite|sqlite3|db|env|json|md|log)$">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

Aktifkan situs dan muat ulang Apache:

```bash
# Nonaktifkan virtual host default jika tidak digunakan
sudo a2dissite 000-default.conf

# Aktifkan virtual host SIMIA
sudo a2ensite simia.conf

# Uji konfigurasi sintaks Apache
sudo apache2ctl configtest
# Output wajib: "Syntax OK"

# Muat ulang Apache
sudo systemctl reload apache2
```

---

### Langkah 9: Pemasangan Sertifikat SSL Gratis (Let's Encrypt / Certbot)

Gunakan Certbot untuk mengamankan koneksi HTTPS secara otomatis:

```bash
# 1. Pasang Certbot & plugin Apache
sudo apt install -y certbot python3-certbot-apache

# 2. Pasang sertifikat SSL untuk domain Anda
sudo certbot --apache -d simia.kelautan.id

# Saat ditanya, masukkan alamat email administrator dan pilih opsi 'Redirect' (Redirect HTTP to HTTPS).

# 3. Uji perpanjangan otomatis sertifikat
sudo certbot renew --dry-run
```

Sekarang buka peramban Anda dan akses:  
👉 **`https://simia.kelautan.id`**

---

## BAGIAN II: Panduan Deployment di cPanel Shared Hosting

Jika Anda menyewa shared hosting dengan panel kontrol cPanel, ikuti panduan grafis berikut:

---

### Langkah 1: Pembuatan Basis Data MySQL di cPanel

1. Masuk ke dashboard cPanel Anda.
2. Cari dan klik menu **"MySQL® Database Wizard"**.
3. **Langkah 1 (Create A Database)**:
   - Masukkan nama database, misalnya: `simia` (nama lengkap otomatis menjadi `usercpanel_simia`).
   - Klik **Next Step**.
4. **Langkah 2 (Create Database Users)**:
   - Buat username database, misalnya: `simia_user` (nama lengkap: `usercpanel_simia_user`).
   - Gunakan *Password Generator* untuk menghasilkan password acak yang kuat.
   - **Salin dan simpan nama database, username, dan password tersebut**.
   - Klik **Create User**.
5. **Langkah 3 (Add User to Database)**:
   - Centang opsi **ALL PRIVILEGES**.
   - Klik **Make Changes**.

---

### Langkah 2: Pemilihan Versi PHP & Pengaktifan Ekstensi

1. Di cPanel, cari menu **"Select PHP Version"** (atau **"MultiPHP Manager"**).
2. Pilih versi PHP: **PHP 8.2**, **PHP 8.3**, atau **PHP 8.4/8.5** (tergantung ketersediaan di hosting Anda).
3. Klik tombol **Set as current**.
4. Pindah ke tab **Extensions** dan pastikan ekstensi berikut dicentang/aktif:
   - `pdo_mysql`
   - `pdo_sqlite`
   - `mbstring`
   - `curl`
   - `zip`
   - `fileinfo`
   - `gd`
   - `xml` / `dom`
   - `intl`
   - `openssl`

---

### Langkah 3: Pengaturan Parameter PHP di MultiPHP INI Editor

1. Buka menu **"MultiPHP INI Editor"** di cPanel.
2. Pilih domain atau direktori instalasi Anda.
3. Sesuaikan batas nilai berikut:
   - `upload_max_filesize` = **50M**
   - `post_max_size` = **60M**
   - `memory_limit` = **256M**
   - `max_execution_time` = **300**
   - `max_input_time` = **300**
4. Klik **Apply**.

---

### Langkah 4: Unggah Berkas & Ekstrak di File Manager

1. Di komputer lokal Anda, kompres seluruh isi folder proyek `sistem-instrumentasi-kuliah` menjadi berkas ZIP (misal: `simia_deploy.zip`).
2. Buka cPanel **"File Manager"**.
3. Navigasikan ke:
   - Jika domain utama: `public_html/`
   - Jika subdomain (misal: `simia.kelautan.id`): folder root subdomain tersebut (misal `public_html/simia/` atau direktori terpisah).
4. Klik tombol **Upload** di bilah atas, pilih file `simia_deploy.zip`.
5. Setelah upload selesai (100% hijau), klik kanan pada file ZIP di File Manager dan pilih **Extract**.
6. Hapus file `simia_deploy.zip` setelah terekstrak untuk menghemat ruang disk.

---

### Langkah 5: Memastikan File .htaccess Aktif

1. Di File Manager cPanel, klik menu **Settings** (ikon gerigi di sudut kanan atas).
2. Centang opsi **"Show Hidden Files (dotfiles)"** lalu klik **Save**.
3. Pastikan file `.htaccess` berada di root direktori instalasi Anda. File `.htaccess` ini berisi aturan *Mod Rewrite* untuk URL Cantik (`/dashboard`, `/matkul-saya`, `/rekap-progress`) dan proteksi file konfigurasi.

---

### Langkah 6: Konfigurasi config/env.php di cPanel

1. Masuk ke folder `config/` melalui File Manager.
2. Jika belum ada `env.php`, klik kanan pada `env.example.php` dan pilih **Copy** menjadi `env.php`.
3. Klik kanan pada `env.php` dan pilih **Edit**.
4. Ubah pengaturan database menjadi MySQL dan masukkan kredensial yang dibuat pada Langkah 1:

```php
    'app' => [
        // ...
        'env'      => 'production',
        'debug'    => false,
        'base_url' => '', // Biarkan kosong agar auto-detect
    ],

    'database' => [
        'default' => 'mysql',

        'mysql' => [
            'host'      => 'localhost', // Di cPanel gunakan 'localhost'
            'port'      => 3306,
            'database'  => 'usercpanel_simia',      // Nama database cPanel Anda
            'username'  => 'usercpanel_simia_user', // Username database cPanel Anda
            'password'  => 'PasswordDatabaseAnda',  // Password yang dibuat
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
```
5. Simpan perubahan dengan mengklik **Save Changes**.

---

### Langkah 7: Pemasangan SSL di cPanel (AutoSSL / Let's Encrypt)

1. Buka menu **"SSL/TLS Status"** di cPanel.
2. Cari domain atau subdomain Anda.
3. Klik tombol **"Run AutoSSL"**.
4. Tunggu beberapa menit hingga status sertifikat menampilkan gembok hijau (*Domain Validated*).
5. Kunjungi website Anda melalui peramban: `https://simia.domain-anda.com`.

---

## 4. Inisialisasi Otomatis & Kredensial Default

SIMIA dirancang dengan **Dual-Engine Auto-Migration**:
Saat aplikasi pertama kali dibuka melalui web browser di lingkungan produksi, kelas `Database::getConnection()` akan **secara otomatis membuat seluruh tabel basis data MariaDB/MySQL** dan mengisi data awal (*seeding*) secara mandiri!

### Akun Administrator Bawaan Awal
Setelah instalasi selesai, masuk menggunakan kredensial bawaan berikut:

| Peran | NIP / Akun Masuk | Kata Sandi Bawaan | Wewenang |
| :--- | :--- | :--- | :--- |
| **Super Admin / GKMP** | `198501012010121001` | `AdminKelautan2026!` | Manajemen penuh seluruh dosen, matkul, pengguna, & sistem |
| **Dosen Pengampu** | `198705022015041002` | `DosenKelautan2026!` | Pengampu matkul & unggah instrumen |

> [!IMPORTANT]
> **Tindakan Keamanan Pasca-Instalasi:**  
> Segera masuk ke akun Administrator, buka menu **Profil**, dan ubah kata sandi bawaan menjadi kata sandi baru yang unik dan aman!

---

## 5. Skrip Backup Otomatis Basis Data & Berkas

Untuk memastikan kelangsungan data akreditasi jurusan, pasang backup terjadwal melalui Cron Job.

### Backup di VPS (Ubuntu 22.04 LTS)
Buka crontab root:
```bash
sudo crontab -e
```
Tambahkan perintah backup harian setiap pukul 02:00 dini hari:
```bash
# Backup harian MariaDB SIMIA & rotasi otomatis 14 hari
0 2 * * * mysqldump -u simia_user -p'PasswordKuatAnda_123!' db_simia_kelautan | gzip > /var/backups/simia_db_$(date +\%Y\%m\%d).sql.gz && find /var/backups/simia_db_* -mtime +14 -delete

# Backup mingguan berkas dokumen instrumen (setiap Minggu pukul 03:00)
0 3 * * 0 tar -czf /var/backups/simia_uploads_$(date +\%Y\%m\%d).tar.gz /var/www/simia/storage/uploads/ && find /var/backups/simia_uploads_* -mtime +30 -delete
```

### Backup di cPanel Shared Hosting
1. Di cPanel, buka menu **"Cron Jobs"**.
2. Pilih frekuensi: **Once a Day (0 2 * * *)**.
3. Masukkan perintah:
```bash
mysqldump -u usercpanel_simia_user -p'PasswordDatabaseAnda' usercpanel_simia | gzip > /home/usercpanel/backups_simia/db_$(date +\%Y\%m\%d).sql.gz
```

---

## 6. Panduan Pemecahan Masalah (Troubleshooting FAQ)

### Q1: Error 404 Not Found saat mengakses URL (/dashboard, /matkul-saya, dll.)
* **Penyebab**: Modul Apache `mod_rewrite` belum aktif atau direktif `AllowOverride All` belum diizinkan.
* **Solusi di VPS**:
  1. Jalankan: `sudo a2enmod rewrite`
  2. Pastikan di `/etc/apache2/sites-available/simia.conf` terdapat:
     ```apache
     <Directory /var/www/simia>
         AllowOverride All
         Require all granted
     </Directory>
     ```
  3. Muat ulang Apache: `sudo systemctl restart apache2`
* **Solusi di cPanel**: Pastikan file `.htaccess` ada di folder root instalasi dan tidak terhapus.

---

### Q2: Error 403 Forbidden / Permission Denied saat Mengunggah Berkas
* **Penyebab**: Web server tidak memiliki izin tulis ke direktori `storage/uploads/`.
* **Solusi di VPS**:
  ```bash
  sudo chown -R www-data:www-data /var/www/simia/storage
  sudo chmod -R 775 /var/www/simia/storage
  ```
* **Solusi di cPanel**: Pastikan permission folder `storage/` bernilai `755` atau `775` di File Manager.

---

### Q3: File Unggahan Gagal Karena Ukuran Terlalu Besar (Upload Max Exceeded)
* **Penyebab**: Nilai `upload_max_filesize` dan `post_max_size` pada `php.ini` server masih bawaan kecil (2MB / 8MB).
* **Solusi**: Tingkatkan nilai pada `php.ini` VPS atau *MultiPHP INI Editor* cPanel menjadi:
  ```ini
  upload_max_filesize = 50M
  post_max_size = 60M
  ```
  Lalu restart Apache di VPS (`sudo systemctl restart apache2`).

---

### Q4: Koneksi Basis Data Gagal / Menggunakan Fallback SQLite
* **Penyebab**: Kredensial MySQL pada `config/env.php` tidak cocok atau MariaDB belum berjalan.
* **Solusi**:
  1. Uji koneksi via terminal: `mysql -u simia_user -p db_simia_kelautan`
  2. Periksa kembali host, database, user, dan password di `config/env.php`.
  3. Pastikan port 3306 MariaDB berjalan di localhost: `sudo systemctl status mariadb`.

---

### Q5: Pengiriman Email Pengingat Dosen atau Reset Password Timeout
* **Penyebab**: Port SMTP diblokir oleh provider VPS/Hosting atau kredensial akun email keliru.
* **Solusi**:
  1. Buka menu **Pengaturan** di dashboard SIMIA (`/pengaturan`).
  2. Jika menggunakan Gmail, pastikan Anda menggunakan **App Password** 16 karakter (bukan password login biasa).
  3. Gunakan port `587` dengan enkripsi `tls` (STARTTLS) atau port `465` dengan `ssl`.
  4. Manfaatkan fitur **"Uji Coba Pengiriman Email"** di halaman Pengaturan untuk memverifikasi respon koneksi socket server secara real-time.

---

*Dokumen panduan ini disusun resmi untuk standarisasi penerapan infrastruktur SIMIA (Sistem Instrumen Akademik) di Program Studi Teknologi Kelautan - Kelautan.ID.*
