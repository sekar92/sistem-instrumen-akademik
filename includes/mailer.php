<?php
declare(strict_types=1);

/**
 * SIMIA - Mailer Engine (Pure PHP SMTP & Native Mail)
 * Acuan: skills.md & PRD SIMIA
 * 
 * Mendukung pengiriman email berbasis SMTP Socket (TLS/SSL/AUTH) 
 * dan fallback PHP mail() untuk reset password & notifikasi kelengkapan berkas dosen.
 */

require_once __DIR__ . '/../config/branding.php';

/**
 * Ambil Konfigurasi Pengiriman Email dari Database
 */
function get_mail_config(): array
{
    $branding = get_branding_info();
    return [
        'driver'       => get_setting('mail_driver', 'smtp'), // 'smtp' atau 'mail'
        'smtp_host'    => get_setting('smtp_host', 'smtp.gmail.com'),
        'smtp_port'    => (int)get_setting('smtp_port', '587'),
        'smtp_user'    => get_setting('smtp_user', ''),
        'smtp_pass'    => get_setting('smtp_pass', ''),
        'smtp_crypto'  => get_setting('smtp_crypto', 'tls'), // 'tls', 'ssl', atau 'none'
        'from_address' => get_setting('mail_from_address', $branding['support_email'] ?: 'no-reply@kelautan.id'),
        'from_name'    => get_setting('mail_from_name', $branding['app_name']),
    ];
}

/**
 * Fungsi Pengiriman Email Utama
 */
function send_email(string $to, string $subject, string $htmlBody, string $textBody = '', array $options = []): array
{
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => "Format alamat email penerima ($to) tidak valid."];
    }

    if (empty($textBody)) {
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
    }

    $config = get_mail_config();

    // Jika driver diset SMTP dan host terisi, gunakan pure-PHP SMTP
    if ($config['driver'] === 'smtp' && !empty($config['smtp_host'])) {
        return send_smtp_socket($config, $to, $subject, $htmlBody, $textBody, $options);
    }

    // Fallback atau jika driver = mail
    return send_native_php_mail($config, $to, $subject, $htmlBody, $textBody, $options);
}

/**
 * Pengiriman Email Menggunakan Socket SMTP Murni PHP (RFC 5321)
 */
function send_smtp_socket(array $config, string $to, string $subject, string $htmlBody, string $textBody, array $options = []): array
{
    $host = $config['smtp_host'];
    $port = (int)$config['smtp_port'];
    $crypto = strtolower($config['smtp_crypto']);
    $timeout = (int)($options['timeout'] ?? 12);

    $remoteHost = ($crypto === 'ssl') ? 'ssl://' . $host : 'tcp://' . $host;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $socket = @stream_socket_client($remoteHost . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['success' => false, 'message' => "Gagal terhubung ke SMTP ($host:$port) - [$errno] $errstr"];
    }

    stream_set_timeout($socket, $timeout);

    $readResponse = function() use ($socket): string {
        $data = '';
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return trim($data);
    };

    $sendCommand = function(string $cmd, string $expectedCode) use ($socket, $readResponse): array {
        fwrite($socket, $cmd . "\r\n");
        $res = $readResponse();
        $code = substr($res, 0, 3);
        if ($code !== $expectedCode) {
            return ['ok' => false, 'code' => $code, 'msg' => $res];
        }
        return ['ok' => true, 'code' => $code, 'msg' => $res];
    };

    // 1. Baca Greeting
    $res = $readResponse();
    if (substr($res, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'message' => "SMTP Greeting gagal: $res"];
    }

    // 2. EHLO
    $hello = $sendCommand("EHLO " . (gethostname() ?: 'localhost'), '250');
    if (!$hello['ok']) {
        fclose($socket);
        return ['success' => false, 'message' => "SMTP EHLO gagal: {$hello['msg']}"];
    }

    // 3. STARTTLS jika port non-SSL dan crypto = tls
    if ($crypto === 'tls' && $port !== 465) {
        $starttls = $sendCommand("STARTTLS", '220');
        if ($starttls['ok']) {
            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                fclose($socket);
                return ['success' => false, 'message' => 'Gagal negosiasi enkripsi STARTTLS dengan server SMTP.'];
            }
            // Kirim EHLO lagi setelah TLS aktif
            $hello2 = $sendCommand("EHLO " . (gethostname() ?: 'localhost'), '250');
            if (!$hello2['ok']) {
                fclose($socket);
                return ['success' => false, 'message' => "SMTP EHLO pasca-TLS gagal: {$hello2['msg']}"];
            }
        }
    }

    // 4. Autentikasi Pengguna jika ada
    if (!empty($config['smtp_user']) && !empty($config['smtp_pass'])) {
        $auth = $sendCommand("AUTH LOGIN", '334');
        if (!$auth['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => "SMTP AUTH LOGIN ditolak: {$auth['msg']}"];
        }
        $userResp = $sendCommand(base64_encode($config['smtp_user']), '334');
        if (!$userResp['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => "Username SMTP ditolak: {$userResp['msg']}"];
        }
        $passResp = $sendCommand(base64_encode($config['smtp_pass']), '235');
        if (!$passResp['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => "Kata sandi SMTP salah / ditolak: {$passResp['msg']}"];
        }
    }

    // 5. MAIL FROM & RCPT TO
    $fromAddr = $config['from_address'];
    $mailFrom = $sendCommand("MAIL FROM:<$fromAddr>", '250');
    if (!$mailFrom['ok']) {
        fclose($socket);
        return ['success' => false, 'message' => "MAIL FROM ditolak: {$mailFrom['msg']}"];
    }

    $rcptTo = $sendCommand("RCPT TO:<$to>", '250');
    if (!$rcptTo['ok']) {
        fclose($socket);
        return ['success' => false, 'message' => "Alamat penerima RCPT TO ditolak: {$rcptTo['msg']}"];
    }

    // 6. DATA
    $dataCmd = $sendCommand("DATA", '354');
    if (!$dataCmd['ok']) {
        fclose($socket);
        return ['success' => false, 'message' => "Perintah DATA ditolak: {$dataCmd['msg']}"];
    }

    // 7. Bangun MIME Multipart Content
    $boundary = '=_simia_bound_' . md5((string)microtime(true));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encodedFromName = '=?UTF-8?B?' . base64_encode($config['from_name']) . '?=';

    $msg = "Date: " . date('r') . "\r\n";
    $msg .= "From: {$encodedFromName} <{$fromAddr}>\r\n";
    $msg .= "To: <{$to}>\r\n";
    $msg .= "Subject: {$encodedSubject}\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $msg .= "X-Mailer: SIMIA-PHP-Mailer-v2.3\r\n\r\n";

    // Plain text
    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($textBody)) . "\r\n";

    // HTML
    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($htmlBody)) . "\r\n";

    $msg .= "--{$boundary}--\r\n";
    $msg .= "\r\n.";

    fwrite($socket, $msg . "\r\n");
    $sentResp = $readResponse();

    $sendCommand("QUIT", '221');
    fclose($socket);

    if (substr($sentResp, 0, 3) !== '250') {
        return ['success' => false, 'message' => "Pengiriman pesan ditolak server: $sentResp"];
    }

    return ['success' => true, 'message' => "Email berhasil dikirimkan via SMTP ($host)."];
}

/**
 * Pengiriman Email Menggunakan Fungsi Bawaan PHP mail()
 */
function send_native_php_mail(array $config, string $to, string $subject, string $htmlBody, string $textBody, array $options = []): array
{
    $boundary = '=_simia_bound_' . md5((string)microtime(true));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromAddr = $config['from_address'];
    $fromName = $config['from_name'];
    $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers = [
        "From: {$encodedFromName} <{$fromAddr}>",
        "Reply-To: {$fromAddr}",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "X-Mailer: SIMIA-PHP-Mail-v2.3"
    ];

    $msg = "--{$boundary}\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($textBody)) . "\r\n";

    $msg .= "--{$boundary}\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($htmlBody)) . "\r\n";

    $msg .= "--{$boundary}--";

    $sent = @mail($to, $encodedSubject, $msg, implode("\r\n", $headers));
    if ($sent) {
        return ['success' => true, 'message' => 'Email berhasil dikirimkan melalui PHP mail().'];
    }

    return ['success' => false, 'message' => 'Fungsi PHP mail() gagal mengirim email. Pastikan MTA/Sendmail lokal aktif atau konfigurasikan SMTP di pengaturan.'];
}

/**
 * Template Email HTML Responsif Bertema Maritim Kelautan.ID
 */
function build_email_template(string $title, string $greeting, string $leadText, array $detailRows = [], ?array $ctaButton = null, string $closingNote = ''): string
{
    $branding = get_branding_info();

    $detailsHtml = '';
    if (!empty($detailRows)) {
        $detailsHtml .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 13px; background-color: #F8FAFC; border-radius: 12px; overflow: hidden; border: 1px solid #E2E8F0;">';
        foreach ($detailRows as $label => $val) {
            $detailsHtml .= '<tr>';
            $detailsHtml .= '<td style="padding: 10px 14px; font-weight: bold; color: #475569; width: 35%; border-bottom: 1px solid #E2E8F0;">' . htmlspecialchars((string)$label) . '</td>';
            $detailsHtml .= '<td style="padding: 10px 14px; color: #0F172A; border-bottom: 1px solid #E2E8F0;">' . htmlspecialchars((string)$val) . '</td>';
            $detailsHtml .= '</tr>';
        }
        $detailsHtml .= '</table>';
    }

    $ctaHtml = '';
    if ($ctaButton && !empty($ctaButton['url'])) {
        $btnText = htmlspecialchars($ctaButton['text'] ?? 'Buka Tautan');
        $btnUrl = htmlspecialchars($ctaButton['url']);
        $ctaHtml = '
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $btnUrl . '" target="_blank" style="display: inline-block; background-color: #1D4ED8; color: #FFFFFF; font-weight: bold; font-size: 14px; padding: 12px 28px; text-decoration: none; border-radius: 10px; box-shadow: 0 4px 12px rgba(29, 78, 216, 0.3);">
                ' . $btnText . ' &rarr;
            </a>
            <p style="margin-top: 10px; font-size: 11px; color: #94A3B8;">
                Atau salin tautan berikut ke browser Anda:<br>
                <a href="' . $btnUrl . '" style="color: #0284C7; word-break: break-all;">' . $btnUrl . '</a>
            </p>
        </div>';
    }

    $closingHtml = $closingNote ? '<p style="font-size: 12px; color: #64748B; margin-top: 20px; line-height: 1.5;">' . nl2br(htmlspecialchars($closingNote)) . '</p>' : '';

    return '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #F1F5F9; color: #1E293B;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 30px 15px;">
                <table role="presentation" style="width: 100%; max-width: 600px; background-color: #FFFFFF; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 25px rgba(10, 25, 47, 0.08); border: 1px solid #E2E8F0;">
                    
                    <!-- Header Maritim -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0A192F 0%, #1E3A8A 100%); padding: 25px 30px; text-align: left;">
                            <table style="width: 100%;">
                                <tr>
                                    <td>
                                        <div style="font-size: 18px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.5px;">' . htmlspecialchars($branding['app_name']) . '</div>
                                        <div style="font-size: 12px; color: #38BDF8; margin-top: 4px;">' . htmlspecialchars($branding['study_program']) . ' • ' . htmlspecialchars($branding['institution']) . '</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Konten -->
                    <tr>
                        <td style="padding: 35px 30px;">
                            <h2 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0 0 14px 0;">' . htmlspecialchars($title) . '</h2>
                            <p style="font-size: 14px; font-weight: 600; color: #334155; margin: 0 0 10px 0;">' . htmlspecialchars($greeting) . '</p>
                            <p style="font-size: 13px; line-height: 1.6; color: #475569; margin: 0;">' . nl2br(htmlspecialchars($leadText)) . '</p>
                            
                            ' . $detailsHtml . '
                            ' . $ctaHtml . '
                            ' . $closingHtml . '

                            <div style="margin-top: 35px; padding-top: 20px; border-top: 1px solid #F1F5F9; font-size: 12px; color: #64748B;">
                                Hormat kami,<br>
                                <strong style="color: #0F172A;">Gugus Kendali Mutu Prodi (GKMP) & Administrator</strong><br>
                                ' . htmlspecialchars($branding['study_program']) . '<br>
                                ' . htmlspecialchars($branding['institution']) . '
                            </div>
                        </td>
                    </tr>

                    <!-- Footer Email -->
                    <tr>
                        <td style="background-color: #F8FAFC; padding: 20px 30px; text-align: center; font-size: 11px; color: #94A3B8; border-top: 1px solid #E2E8F0;">
                            <p style="margin: 0 0 5px 0;">' . htmlspecialchars($branding['copyright']) . '</p>
                            <p style="margin: 0;">Email ini dikirimkan secara otomatis oleh sistem. Mohon tidak membalas langsung ke alamat ini.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

/**
 * Kirim Email Link Reset Password
 */
function send_password_reset_email(array $user, string $resetToken): array
{
    $branding = get_branding_info();
    $resetUrl = url('reset_password.php?token=' . urlencode($resetToken));

    $subject = "Permintaan Reset Kata Sandi - " . $branding['app_name'];
    $greeting = "Halo, " . $user['nama'];
    $leadText = "Kami menerima permintaan untuk mengatur ulang kata sandi akun SIMIA Anda. Silakan klik tombol di bawah ini untuk membuat kata sandi baru. Tautan ini hanya berlaku selama 60 menit.";

    $detailRows = [
        'Nama Pengguna' => $user['nama'],
        'NIP / NIDN'    => $user['nip'],
        'Alamat Email'  => $user['email'],
        'Masa Berlaku'  => '60 Menit sejak permintaan dibuat'
    ];

    $ctaButton = [
        'text' => 'Atur Ulang Kata Sandi Saya',
        'url'  => $resetUrl
    ];

    $closingNote = "Jika Anda tidak pernah meminta pengaturan ulang kata sandi ini, abaikan email ini. Akun Anda tetap aman dan kata sandi Anda tidak akan berubah.";

    $html = build_email_template(
        'Pengaturan Ulang Kata Sandi',
        $greeting,
        $leadText,
        $detailRows,
        $ctaButton,
        $closingNote
    );

    return send_email($user['email'], $subject, $html);
}

/**
 * Kirim Email Pengingat Kelengkapan Instrumen Perkuliahan
 */
function send_instrument_reminder_email(array $target, array $details): array
{
    $branding = get_branding_info();
    $loginUrl = url('matkul_saya.php');

    $subject = "[PENGINGAT INSTRUMEN] Kelengkapan Berkas MK " . $target['nama_mk'];
    $greeting = "Yth. Bapak/Ibu " . $target['nama'];
    $leadText = "Berdasarkan hasil monitoring instrumen perkuliahan pada sistem SIMIA, berkas instrumen untuk mata kuliah yang Anda ampu saat ini belum lengkap. Mohon kesediaannya untuk segera melengkapi berkas sebelum batas waktu yang ditentukan.";

    $pct = $details['persentase'] ?? 0;
    $count = $details['berkas_terkumpul'] ?? 0;
    $total = $details['standar_wajib'] ?? 28;

    $detailRows = [
        'Mata Kuliah'      => $target['kode_mk'] . ' - ' . $target['nama_mk'],
        'Semester'         => $target['semester'] ?? 'Ganjil',
        'Tahun Ajaran'     => $details['tahun_ajaran'] ?? '-',
        'Progress Terkini' => "{$pct}% ({$count} dari {$total} berkas wajib)",
        'Status'           => ($pct === 0) ? 'Belum Ada Berkas' : 'Belum Lengkap'
    ];

    $ctaButton = [
        'text' => 'Buka SIMIA & Upload Berkas',
        'url'  => $loginUrl
    ];

    $closingNote = "Kelengkapan instrumen perkuliahan (RPS, Kontrak, Modul, Soal Ujian, Berkas Pertemuan 1-16, dll.) merupakan salah satu standar penjaminan mutu perkuliahan dan akreditasi program studi kita. Terima kasih atas kerja sama dan dedikasi Bapak/Ibu Dosen.";

    $html = build_email_template(
        'Pengingat Kelengkapan Instrumen Perkuliahan',
        $greeting,
        $leadText,
        $detailRows,
        $ctaButton,
        $closingNote
    );

    return send_email($target['email'], $subject, $html);
}
