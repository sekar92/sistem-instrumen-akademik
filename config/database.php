<?php
declare(strict_types=1);

/**
 * SIMIA - Database Connection & Dual-Engine PDO Manager (SQLite & MySQL)
 * Acuan: skills.md Bagian 6.1 & PRD SIMIA Bagian 5
 */

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require __DIR__ . '/env.php';
        $driver = $config['database']['default'] ?? 'sqlite';

        try {
            if ($driver === 'mysql') {
                $my = $config['database']['mysql'];
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $my['host'],
                    $my['port'],
                    $my['database'],
                    $my['charset']
                );
                self::$instance = new PDO($dsn, $my['username'], $my['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } else {
                // Default SQLite
                $sqlitePath = $config['database']['sqlite']['path'];
                $sqliteDir = dirname($sqlitePath);
                if (!is_dir($sqliteDir)) {
                    mkdir($sqliteDir, 0755, true);
                }

                $dsn = 'sqlite:' . $sqlitePath;
                self::$instance = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$instance->exec('PRAGMA foreign_keys = ON;');
                self::$instance->exec('PRAGMA journal_mode = WAL;');
            }

            // Jalankan migrasi tabel otomatis jika belum ada
            self::runMigrations(self::$instance, $driver);

        } catch (PDOException $e) {
            // Jika koneksi MySQL gagal, fallback otomatis ke SQLite
            if ($driver === 'mysql') {
                error_log('Koneksi MySQL gagal: ' . $e->getMessage() . '. Fallback ke SQLite...');
                $sqlitePath = $config['database']['sqlite']['path'];
                $sqliteDir = dirname($sqlitePath);
                if (!is_dir($sqliteDir)) {
                    mkdir($sqliteDir, 0755, true);
                }
                self::$instance = new PDO('sqlite:' . $sqlitePath, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$instance->exec('PRAGMA foreign_keys = ON;');
                self::runMigrations(self::$instance, 'sqlite');
            } else {
                throw new RuntimeException('Gagal menyambung ke database: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private static function runMigrations(PDO $pdo, string $driver): void
    {
        $isSqlite = ($driver === 'sqlite');

        // 1. Tabel users
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id_user " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                nip VARCHAR(50) NOT NULL UNIQUE,
                nama VARCHAR(150) NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) DEFAULT '',
                role VARCHAR(30) NOT NULL DEFAULT 'Dosen',
                keahlian VARCHAR(255) DEFAULT '',
                foto_path VARCHAR(255) DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'Aktif',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Pastikan kolom status tersedia jika tabel sudah dibuat sebelumnya
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Aktif'");
        } catch (Throwable $e) {
            // Kolom sudah ada
        }

        // 2. Tabel tahun_ajaran
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tahun_ajaran (
                id_ta " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                tahun VARCHAR(30) NOT NULL UNIQUE,
                status VARCHAR(20) NOT NULL DEFAULT 'Aktif'
            )
        ");

        // 3. Tabel semesters
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS semesters (
                id_semester " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                id_ta INT NOT NULL,
                jenis_semester VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Berjalan',
                FOREIGN KEY (id_ta) REFERENCES tahun_ajaran(id_ta) ON DELETE CASCADE
            )
        ");

        // 4. Tabel mata_kuliah
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS mata_kuliah (
                id_mk " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                kode_mk VARCHAR(30) NOT NULL,
                nama_mk VARCHAR(150) NOT NULL,
                deskripsi TEXT,
                sks INT NOT NULL DEFAULT 2,
                id_ta INT,
                semester VARCHAR(20) NOT NULL DEFAULT 'Ganjil',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_ta) REFERENCES tahun_ajaran(id_ta) ON DELETE SET NULL
            )
        ");

        // 5. Tabel cpmk
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cpmk (
                id_cpmk " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                id_mk INT NOT NULL,
                kode_cpmk VARCHAR(30) NOT NULL,
                deskripsi TEXT NOT NULL,
                FOREIGN KEY (id_mk) REFERENCES mata_kuliah(id_mk) ON DELETE CASCADE
            )
        ");

        // 6. Tabel pengampu
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pengampu (
                id_pengampu " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                id_user INT NOT NULL,
                id_mk INT NOT NULL,
                id_ta INT NOT NULL,
                semester VARCHAR(20) NOT NULL DEFAULT 'Ganjil',
                FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE,
                FOREIGN KEY (id_mk) REFERENCES mata_kuliah(id_mk) ON DELETE CASCADE,
                FOREIGN KEY (id_ta) REFERENCES tahun_ajaran(id_ta) ON DELETE CASCADE
            )
        ");

        // 7. Tabel instrumen
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS instrumen (
                id_instrumen " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                id_pengampu INT NOT NULL,
                kategori VARCHAR(50) NOT NULL,
                sub_kategori VARCHAR(80) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_url VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) DEFAULT '',
                file_size INT DEFAULT 0,
                tgl_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_pengampu) REFERENCES pengampu(id_pengampu) ON DELETE CASCADE
            )
        ");

        // 8. Tabel informasi (Microblog / Pengumuman)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS informasi (
                id_info " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                judul VARCHAR(200) NOT NULL,
                isi TEXT NOT NULL,
                deadline DATE DEFAULT NULL,
                pembuat_id INT,
                pembuat_nama VARCHAR(150) DEFAULT 'Admin',
                tgl_posting DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (pembuat_id) REFERENCES users(id_user) ON DELETE SET NULL
            )
        ");

        // 9. Tabel log_aktivitas (Audit Trail Sistem)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS log_aktivitas (
                id_log " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                id_user INT DEFAULT NULL,
                username VARCHAR(100) DEFAULT '',
                nama VARCHAR(150) DEFAULT '',
                role VARCHAR(50) DEFAULT '',
                tipe_aktivitas VARCHAR(50) NOT NULL,
                deskripsi TEXT,
                ip_address VARCHAR(50) DEFAULT '',
                user_agent VARCHAR(255) DEFAULT '',
                is_impersonated INT DEFAULT 0,
                impersonator_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // 10. Tabel pengaturan (Dynamic System & Branding Settings)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pengaturan (
                key_name VARCHAR(100) PRIMARY KEY,
                value_data TEXT
            )
        ");

        // 11. Tabel password_resets (Token Reset Sandi Pengguna)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id_reset " . ($isSqlite ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY") . ",
                id_user INT NOT NULL,
                email VARCHAR(150) NOT NULL,
                token VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
            )
        ");

        if ($isSqlite) {
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pwd_resets_token ON password_resets(token)");
        }

        // Seeder Awal Otomatis jika users kosong
        self::seedInitialData($pdo);
    }

    private static function seedInitialData(PDO $pdo): void
    {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            // Password default: 'admin123' & 'dosen123'
            $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
            $dosenPass = password_hash('dosen123', PASSWORD_BCRYPT);

            // 1. Users Default
            $userIns = $pdo->prepare("
                INSERT INTO users (nip, nama, password, email, role, keahlian)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            // Super Admin
            $userIns->execute([
                '198501012010121001',
                'Budhi Agung Prasetyo, S.Pi., M.Si.',
                $adminPass,
                'teknologikelautanid@gmail.com',
                'Super Admin',
                'Oseanografi, Instrumentasi Kelautan & Sistem Informasi'
            ]);

            // GKMP / Admin
            $userIns->execute([
                '198705122015041002',
                'Dra. Nurhayati, M.Si.',
                $adminPass,
                'nurhayati@kelautan.id',
                'Admin',
                'Manajemen Mutu Akademik & Kurikulum'
            ]);

            // Dosen 1
            $userIns->execute([
                '199003152019031005',
                'Ahmad Fauzi, S.T., M.T.',
                $dosenPass,
                'ahmad.fauzi@kelautan.id',
                'Dosen',
                'Akustik Bawah Air & Elektronika Maritim'
            ]);

            // Dosen 2
            $userIns->execute([
                '199208202020122011',
                'Siti Rahmawati, S.Kel., M.Si.',
                $dosenPass,
                'siti.rahma@kelautan.id',
                'Dosen',
                'Penginderaan Jauh Kelautan & SIG'
            ]);

            // 2. Tahun Ajaran Default
            $taIns = $pdo->prepare("INSERT INTO tahun_ajaran (tahun, status) VALUES (?, ?)");
            $taIns->execute(['2025/2026', 'Aktif']);
            $taIns->execute(['2024/2025', 'Nonaktif']);
            $idTaAktif = (int)$pdo->lastInsertId(); // ID dari 2024/2025 or get first
            $idTa1 = (int)$pdo->query("SELECT id_ta FROM tahun_ajaran WHERE tahun = '2025/2026'")->fetchColumn();

            // 3. Semesters Default
            $semIns = $pdo->prepare("INSERT INTO semesters (id_ta, jenis_semester, status) VALUES (?, ?, ?)");
            $semIns->execute([$idTa1, 'Ganjil', 'Berjalan']);
            $semIns->execute([$idTa1, 'Genap', 'Selesai']);

            // 4. Mata Kuliah Default
            $mkIns = $pdo->prepare("
                INSERT INTO mata_kuliah (kode_mk, nama_mk, deskripsi, sks, id_ta, semester)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $mkIns->execute(['TKL-2101', 'Instrumentasi Kelautan & Sensor', 'Prinsip kerja sensor oseanografi, kalibrasi, akuisisi data lapangan, dan telemetri.', 3, $idTa1, 'Ganjil']);
            $idMk1 = (int)$pdo->lastInsertId();

            $mkIns->execute(['TKL-2102', 'Akustik Kelautan', 'Propagasi gelombang suara dalam medium air laut, sonar bathimetri, dan fish finder.', 3, $idTa1, 'Ganjil']);
            $idMk2 = (int)$pdo->lastInsertId();

            $mkIns->execute(['TKL-3105', 'Sistem Informasi Geografis Maritim', 'Pemetaan pesisir, analisis spasial bathimetri, dan citra satelit kelautan.', 3, $idTa1, 'Ganjil']);
            $idMk3 = (int)$pdo->lastInsertId();

            // 5. CPMK Default
            $cpmkIns = $pdo->prepare("INSERT INTO cpmk (id_mk, kode_cpmk, deskripsi) VALUES (?, ?, ?)");
            $cpmkIns->execute([$idMk1, 'CPMK-1', 'Mampu memahami prinsip dasar transduksi sinyal pada sensor oseanografi fisik.']);
            $cpmkIns->execute([$idMk1, 'CPMK-2', 'Mampu melakukan kalibrasi dan perakitan sistem akuisisi data berbasis mikrokontroler.']);
            $cpmkIns->execute([$idMk2, 'CPMK-1', 'Memahami persamaan gelombang suara air laut dan parameter transmisi akustik.']);

            // 6. Pengampu Default (Assign)
            $pengampuIns = $pdo->prepare("
                INSERT INTO pengampu (id_user, id_mk, id_ta, semester)
                VALUES (?, ?, ?, ?)
            ");
            $u1 = (int)$pdo->query("SELECT id_user FROM users WHERE nip = '198501012010121001'")->fetchColumn();
            $u2 = (int)$pdo->query("SELECT id_user FROM users WHERE nip = '199003152019031005'")->fetchColumn();
            $u3 = (int)$pdo->query("SELECT id_user FROM users WHERE nip = '199208202020122011'")->fetchColumn();

            $pengampuIns->execute([$u1, $idMk1, $idTa1, 'Ganjil']); // Super Admin ngajar MK1
            $pengampuIns->execute([$u2, $idMk2, $idTa1, 'Ganjil']); // Dosen Fauzi ngajar MK2
            $pengampuIns->execute([$u3, $idMk3, $idTa1, 'Ganjil']); // Dosen Siti ngajar MK3

            // 7. Informasi / Pengumuman Awal
            $infoIns = $pdo->prepare("
                INSERT INTO informasi (judul, isi, deadline, pembuat_id, pembuat_nama, tgl_posting)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $infoIns->execute([
                'Pemberitahuan Kelengkapan Instrumen Perkuliahan Semester Ganjil 2025/2026',
                'Kepada seluruh Bapak/Ibu Dosen pengampu mata kuliah Semester Ganjil TA 2025/2026, dimohon untuk melengkapi instrumen perkuliahan (RPS, Kontrak Kuliah, Modul, Soal Ujian, serta Bahan Ajar Pertemuan 1-16) melalui sistem SIMIA sebelum batas waktu yang telah ditentukan demi kelancaran akreditasi program studi.',
                date('Y-m-d', strtotime('+14 days')),
                $u1,
                'Budhi Agung Prasetyo, S.Pi., M.Si.',
                date('Y-m-d H:i:s')
            ]);

            // 8. Pengaturan Sistem Default
            $setIns = $pdo->prepare("INSERT INTO pengaturan (key_name, value_data) VALUES (?, ?)");
            $defaultSettings = [
                'app_name'           => 'SIMIA - Sistem Instrumen Akademik',
                'app_tagline'        => 'Portal Informasi, Riset & Pembelajaran Maritim Terpadu Indonesia',
                'institution_name'   => 'Kelautan.ID',
                'study_program'      => 'Program Studi Teknologi Kelautan',
                'department'         => 'Teknik Kelautan dan Perikanan',
                'developer'          => 'Budhi Agung Prasetyo, S.Pi., M.Si.',
                'footer_copyright'   => '© 2026 Kelautan.ID - Portal Maritim Indonesia.',
                'max_upload_size_mb' => '10',
                'portal_url'         => 'https://kelautan.id',
                'apps_url'           => 'https://apps.kelautan.id',
                'go_links_url'       => 'https://go.kelautan.id',
                'support_email'      => 'teknologikelautanid@gmail.com',
                'custom_logo_url'    => '',
                'custom_favicon_url' => '',
            ];
            foreach ($defaultSettings as $k => $v) {
                $setIns->execute([$k, $v]);
            }
        }
    }
}
