<?php
declare(strict_types=1);

/**
 * SIMIA - Branding & Visual Identity Manager
 * Acuan: skills.md Bagian 1.3
 */

require_once __DIR__ . '/database.php';

function get_setting(string $key, string $default = ''): string
{
    static $settingsCache = null;
    if ($settingsCache === null) {
        $settingsCache = [];
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SELECT key_name, value_data FROM pengaturan");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settingsCache[$row['key_name']] = (string)$row['value_data'];
            }
        } catch (Throwable $e) {
            // Ignore if DB not ready
        }
    }
    return $settingsCache[$key] ?? $default;
}

function get_logo_url(): string
{
    $customLogo = get_setting('custom_logo_url');
    if (!empty($customLogo) && file_exists(__DIR__ . '/../' . ltrim($customLogo, '/'))) {
        return $customLogo;
    }
    // Return Data URI SVG default (Gelombang samudra & jangkar maritim gradient Ocean Cyan ke Maritime Blue)
    return 'data:image/svg+xml;utf8,' . rawurlencode('
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none">
            <defs>
                <linearGradient id="oceanGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#06B6D4"/>
                    <stop offset="100%" stop-color="#1D4ED8"/>
                </linearGradient>
            </defs>
            <rect width="100" height="100" rx="24" fill="url(#oceanGrad)"/>
            <!-- Simbol Gelombang Samudra & Jangkar -->
            <path d="M50 20 V42 M40 32 H60 M50 28 A4 4 0 1 0 50 20" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M30 46 C32 66, 68 66, 70 46" stroke="#FFFFFF" stroke-width="5" stroke-linecap="round" fill="none"/>
            <path d="M22 72 Q36 62, 50 72 T78 72" stroke="#22D3EE" stroke-width="5" stroke-linecap="round" fill="none"/>
            <path d="M25 82 Q37.5 74, 50 82 T75 82" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-opacity="0.8" fill="none"/>
        </svg>
    ');
}

function get_favicon_url(): string
{
    $customFavicon = get_setting('custom_favicon_url');
    if (!empty($customFavicon) && file_exists(__DIR__ . '/../' . ltrim($customFavicon, '/'))) {
        return $customFavicon;
    }
    return get_logo_url();
}

function get_default_footer_links(): array
{
    return [
        ['label' => 'Portal', 'url' => get_setting('portal_url', 'https://kelautan.id')],
        ['label' => 'Apps', 'url' => get_setting('apps_url', 'https://apps.kelautan.id')],
        ['label' => 'Pemendek Tautan', 'url' => get_setting('go_links_url', 'https://go.kelautan.id')],
        ['label' => 'Dukungan', 'url' => 'mailto:' . get_setting('support_email', 'teknologikelautanid@gmail.com')],
    ];
}

function get_footer_links(): array
{
    $raw = get_setting('footer_links_json', '');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && !empty($decoded)) {
            $links = [];
            foreach ($decoded as $item) {
                if (is_array($item)) {
                    $label = trim((string)($item['label'] ?? ''));
                    $url = trim((string)($item['url'] ?? ''));
                    if ($label !== '' && $url !== '') {
                        $links[] = [
                            'label' => $label,
                            'url'   => $url,
                        ];
                    }
                }
            }
            if (!empty($links)) {
                return $links;
            }
        }
    }
    return get_default_footer_links();
}

function get_branding_info(): array
{
    $footerLinks = get_footer_links();

    // Tentukan fallback untuk portal, apps, dan email jika ada di daftar footer_links
    $portalUrl = get_setting('portal_url', 'https://kelautan.id');
    $appsUrl = get_setting('apps_url', 'https://apps.kelautan.id');
    $goLinksUrl = get_setting('go_links_url', 'https://go.kelautan.id');
    $supportEmail = get_setting('support_email', 'teknologikelautanid@gmail.com');

    return [
        'app_name'      => get_setting('app_name', 'SIMIA - Sistem Instrumen Akademik'),
        'sub_name'      => 'Kelautan.ID',
        'tagline'       => get_setting('app_tagline', 'Portal Informasi, Riset & Pembelajaran Maritim Terpadu Indonesia'),
        'institution'   => get_setting('institution_name', 'Kelautan.ID'),
        'study_program' => get_setting('study_program', 'Program Studi Teknologi Kelautan'),
        'department'    => get_setting('department', 'Teknik Kelautan dan Perikanan'),
        'developer'     => get_setting('developer', 'Budhi Agung Prasetyo, S.Pi., M.Si.'),
        'copyright'     => get_setting('footer_copyright', '© 2026 Kelautan.ID - Portal Maritim Indonesia.'),
        'portal_url'    => $portalUrl,
        'apps_url'      => $appsUrl,
        'go_links_url'  => $goLinksUrl,
        'support_email' => $supportEmail,
        'max_upload_mb' => (int)get_setting('max_upload_size_mb', '10'),
        'footer_links'  => $footerLinks,
    ];
}
