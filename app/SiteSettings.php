<?php

declare(strict_types=1);

final class SiteSettings
{
    public const NETWORKS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'twitter' => 'X',
        'whatsapp' => 'WhatsApp',
        'pinterest' => 'Pinterest',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
    ];

    /** @return array<string, mixed> */
    public static function current(): array
    {
        $socials = [];
        $stored = setting('socials', []);
        if (is_array($stored)) {
            foreach ($stored as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $network = (string) ($item['network'] ?? '');
                if (isset(self::NETWORKS[$network])) {
                    $socials[$network] = (string) ($item['url'] ?? '');
                }
            }
        }

        $button = setting('contact_button', []);
        if (!is_array($button)) {
            $button = [];
        }

        return [
            'site_name' => (string) setting('site_name', 'Tuana Arıcılık'),
            'meta_title' => (string) setting('meta_title', 'Doğal Bal ve Arı Ürünleri'),
            'meta_description' => (string) setting('meta_description', ''),
            'theme_color' => (string) setting('theme_color', '#e2a93b'),
            'site_url' => (string) setting('site_url', ''),
            'logo' => (string) setting('logo', ''),
            'favicon' => (string) setting('favicon', ''),
            'email' => (string) setting('email', ''),
            'phone' => (string) setting('phone', ''),
            'whatsapp' => (string) setting('whatsapp', ''),
            'address' => (string) setting('address', ''),
            'hours' => (string) setting('hours', ''),
            'contact_title' => (string) setting('contact_title', 'İletişim'),
            'contact_label' => (string) ($button['label'] ?? 'İletişim'),
            'contact_url' => (string) ($button['url'] ?? '#iletisim'),
            'copyright' => (string) setting('copyright', ''),
            'footer_about' => (string) setting('footer_about', ''),
            'photo_credit' => (string) setting('photo_credit', ''),
            'upload_dir' => (string) setting('upload_dir', 'assets/uploads'),
            'theme_dir' => (string) setting('theme_dir', 'assets'),
            'maintenance' => setting('maintenance', false) === true || setting('maintenance', false) === 1,
            'maintenance_message' => (string) setting('maintenance_message', 'Sitemiz kısa bir bakımda. Birazdan yeniden buradayız.'),
            'socials' => $socials,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return list<string>
     */
    public static function save(array $post, array $files): array
    {
        if (!db_pdo() instanceof PDO) {
            return ['Veritabanına bağlanılamadı.'];
        }

        $errors = [];
        $siteName = self::clip((string) ($post['site_name'] ?? ''), 80);
        if ($siteName === '') {
            $errors[] = 'Site adı gerekli.';
        }

        $metaTitle = self::clip((string) ($post['meta_title'] ?? ''), 140);
        $description = self::clip((string) ($post['meta_description'] ?? ''), 320);
        $color = strtolower(trim((string) ($post['theme_color'] ?? '')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            $errors[] = 'Tema rengi # ile başlayan 6 haneli bir renk olmalı.';
        }

        $siteUrl = self::clip((string) ($post['site_url'] ?? ''), 180);
        if ($siteUrl !== '' && !self::httpUrl($siteUrl)) {
            $errors[] = 'Site adresi http veya https ile başlamalı.';
        }

        $email = self::clip((string) ($post['email'] ?? ''), 120);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'İletişim e-postası geçerli bir adres olmalı.';
        }

        $phone = self::phone((string) ($post['phone'] ?? ''));
        $whatsapp = self::phone((string) ($post['whatsapp'] ?? ''));
        if ($phone === null || $whatsapp === null) {
            $errors[] = 'Telefon ve WhatsApp yalnızca rakam, boşluk ve + içerebilir.';
        }

        $address = self::clip((string) ($post['address'] ?? ''), 180);
        $hours = self::clip((string) ($post['hours'] ?? ''), 80);
        $contactTitle = self::clip((string) ($post['contact_title'] ?? ''), 40);
        if ($contactTitle === '') {
            $contactTitle = 'İletişim';
        }
        $contactLabel = self::clip((string) ($post['contact_label'] ?? ''), 40);
        if ($contactLabel === '') {
            $contactLabel = 'İletişim';
        }
        $contactUrl = self::clip((string) ($post['contact_url'] ?? ''), 180);
        if (!self::linkOk($contactUrl)) {
            $errors[] = 'İletişim düğmesinin adresi geçersiz.';
        }

        $copyright = self::clip((string) ($post['copyright'] ?? ''), 180);
        $about = self::clip((string) ($post['footer_about'] ?? ''), 600);
        $credit = self::clip((string) ($post['photo_credit'] ?? ''), 180);
        $message = self::clip((string) ($post['maintenance_message'] ?? ''), 240);
        if ($message === '') {
            $message = 'Sitemiz kısa bir bakımda. Birazdan yeniden buradayız.';
        }

        $uploadDir = self::prepareUpload((string) ($post['upload_dir'] ?? ''));
        if ($uploadDir === null) {
            $errors[] = 'Yükleme klasörü site içinde, güvenli ve yazılabilir bir yol olmalı.';
        }
        $themeDir = self::themeDir((string) ($post['theme_dir'] ?? ''));
        if ($themeDir === null) {
            $errors[] = 'Tema klasöründe css/main.css ve js/main.js dosyaları bulunmalı.';
        }

        $socials = [];
        $postedSocials = $post['socials'] ?? [];
        if (!is_array($postedSocials)) {
            $postedSocials = [];
        }
        foreach (self::NETWORKS as $network => $label) {
            $url = self::clip((string) ($postedSocials[$network] ?? ''), 180);
            if ($url === '' || $url === '#') {
                if ($url === '#') {
                    $socials[] = ['network' => $network, 'label' => $label, 'url' => '#'];
                }
                continue;
            }
            if (!self::httpUrl($url)) {
                $errors[] = $label . ' adresi http veya https ile başlamalı.';
                continue;
            }
            $socials[] = ['network' => $network, 'label' => $label, 'url' => $url];
        }

        $logoFile = self::inspectImage($files['logo'] ?? null, 2_000_000);
        $faviconFile = self::inspectImage($files['favicon'] ?? null, 512_000, true);
        if ($logoFile === false) {
            $errors[] = 'Logo PNG, JPG, WEBP, GIF veya SVG olmalı ve 2 MB altında kalmalı.';
        }
        if ($faviconFile === false) {
            $errors[] = 'Favicon PNG, JPG, WEBP, GIF, SVG veya ICO olmalı ve 512 KB altında kalmalı.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $logo = is_array($logoFile) ? self::storeImage($logoFile, (string) $uploadDir, 'logo') : null;
        $favicon = is_array($faviconFile) ? self::storeImage($faviconFile, (string) $uploadDir, 'favicon') : null;
        if ($logo === false) {
            return ['Logo kaydedilemedi.'];
        }
        if ($favicon === false) {
            return ['Favicon kaydedilemedi.'];
        }

        $current = self::current();
        $logoPath = (string) $current['logo'];
        $faviconPath = (string) $current['favicon'];
        if (isset($post['remove_logo'])) {
            self::deleteManaged($logoPath);
            $logoPath = '';
        }
        if (isset($post['remove_favicon'])) {
            self::deleteManaged($faviconPath);
            $faviconPath = '';
        }
        if (is_string($logo) && $logo !== '') {
            self::deleteManaged($logoPath);
            $logoPath = $logo;
        }
        if (is_string($favicon) && $favicon !== '') {
            self::deleteManaged($faviconPath);
            $faviconPath = $favicon;
        }

        $pairs = [
            'site_name' => $siteName,
            'meta_title' => $metaTitle,
            'meta_description' => $description,
            'theme_color' => $color,
            'site_url' => $siteUrl,
            'logo' => $logoPath,
            'favicon' => $faviconPath,
            'email' => $email,
            'phone' => $phone ?? '',
            'whatsapp' => $whatsapp ?? '',
            'address' => $address,
            'hours' => $hours,
            'contact_title' => $contactTitle,
            'contact_button' => ['label' => $contactLabel, 'url' => $contactUrl === '' ? '#iletisim' : $contactUrl],
            'copyright' => $copyright,
            'footer_about' => $about,
            'photo_credit' => $credit,
            'upload_dir' => $uploadDir,
            'theme_dir' => $themeDir,
            'maintenance' => isset($post['maintenance']),
            'maintenance_message' => $message,
            'socials' => $socials,
        ];
        foreach ($pairs as $key => $value) {
            save_setting($key, $value);
        }

        return [];
    }

    private static function clip(string $value, int $max): string
    {
        $value = trim(strip_tags($value));
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    private static function phone(string $value): ?string
    {
        $value = self::clip($value, 40);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^[0-9+\s().-]{7,40}$/', $value)) {
            return null;
        }

        return $value;
    }

    private static function httpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $url) === 1;
    }

    private static function linkOk(string $url): bool
    {
        if ($url === '' || $url === '#') {
            return true;
        }
        if (preg_match('/^\s*javascript:/i', $url) === 1) {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return true;
        }

        return self::httpUrl($url);
    }

    private static function root(): string
    {
        return dirname(__DIR__);
    }

    private static function relativeDir(string $value): ?string
    {
        $value = trim(str_replace('\\', '/', $value), '/');
        if ($value === '' || str_contains($value, '..') || preg_match('/^[a-z0-9][a-z0-9_\/-]*$/i', $value) !== 1) {
            return null;
        }
        $denied = ['app', 'config', 'data', 'sql', 'storage', 'admin', 'partials'];
        $first = strtolower(explode('/', $value)[0]);
        if (in_array($first, $denied, true)) {
            return null;
        }

        return $value;
    }

    private static function insideRoot(string $relative): ?string
    {
        $full = self::root() . '/' . ltrim(str_replace('\\', '/', $relative), '/');
        $root = realpath(self::root());
        $real = realpath($full);
        if ($root === false || $real === false) {
            return null;
        }
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $real = str_replace('\\', '/', $real);
        if ($real !== $root && !str_starts_with($real, $root . '/')) {
            return null;
        }

        return $real;
    }

    private static function prepareUpload(string $value): ?string
    {
        $relative = self::relativeDir($value);
        if ($relative === null) {
            return null;
        }
        $full = self::root() . '/' . $relative;
        if (!is_dir($full) && !mkdir($full, 0755, true) && !is_dir($full)) {
            return null;
        }
        $real = self::insideRoot($relative);

        return $real !== null && is_dir($real) ? $relative : null;
    }

    private static function themeDir(string $value): ?string
    {
        $relative = self::relativeDir($value);
        if ($relative === null) {
            return null;
        }
        $css = self::insideRoot($relative . '/css/main.css');
        $js = self::insideRoot($relative . '/js/main.js');
        if ($css === null || $js === null || !is_file($css) || !is_file($js)) {
            return null;
        }

        return $relative;
    }

    /**
     * @param mixed $file
     * @return array{tmp:string,ext:string}|false|null
     */
    private static function inspectImage(mixed $file, int $maxBytes, bool $allowIcon = false): array|false|null
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return false;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || $size < 1 || $size > $maxBytes || !is_uploaded_file($tmp)) {
            return false;
        }

        $mime = '';
        if (class_exists(finfo::class)) {
            $info = new finfo(FILEINFO_MIME_TYPE);
            $detected = $info->file($tmp);
            $mime = is_string($detected) ? $detected : '';
        }
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
        ];
        if ($allowIcon) {
            $map['image/x-icon'] = 'ico';
            $map['image/vnd.microsoft.icon'] = 'ico';
        }
        if (!isset($map[$mime])) {
            return false;
        }
        if ($map[$mime] === 'svg' && !self::svgIsSafe($tmp)) {
            return false;
        }

        return ['tmp' => $tmp, 'ext' => $map[$mime]];
    }

    /** @param array{tmp:string,ext:string} $image */
    private static function storeImage(array $image, string $dir, string $prefix): string|false
    {
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $image['ext'];
        $target = self::root() . '/' . $dir . '/' . $name;
        if (!move_uploaded_file($image['tmp'], $target)) {
            return false;
        }

        return $dir . '/' . $name;
    }

    private static function svgIsSafe(string $tmp): bool
    {
        $svg = file_get_contents($tmp);
        if (!is_string($svg) || $svg === '' || strlen($svg) > 512_000) {
            return false;
        }
        if (stripos($svg, '<svg') === false) {
            return false;
        }

        return preg_match('/<script|on[a-z]+\s*=|javascript:|foreignObject|<iframe|<embed|<object/i', $svg) !== 1;
    }

    private static function deleteManaged(string $relative): void
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');
        if ($relative === '' || $upload === '' || !str_starts_with($relative, $upload . '/')) {
            return;
        }
        $full = self::insideRoot($relative);
        if ($full !== null && is_file($full)) {
            unlink($full);
        }
    }
}
