<?php

declare(strict_types=1);

require_once __DIR__ . '/HtmlSanitizer.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if (is_string($base)) {
        return $base;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $base = ($dir === '' || $dir === '.' || $dir === '/') ? '' : $dir;

    return $base;
}

function url(string $path): string
{
    $path = trim($path);
    if ($path === '' || preg_match('/^\s*javascript:/i', $path)) {
        return '#';
    }

    if (
        preg_match('#^(https?:)?//#i', $path)
        || str_starts_with($path, '#')
        || str_starts_with($path, 'mailto:')
        || str_starts_with($path, 'tel:')
    ) {
        return e($path);
    }

    if (str_starts_with($path, '/')) {
        return e(base_path() . $path);
    }

    return e(base_path() . '/' . $path);
}

function asset(string $path): string
{
    return url($path);
}

function load_seed(): array
{
    static $seed = null;
    if (is_array($seed)) {
        return $seed;
    }

    $loaded = require dirname(__DIR__) . '/data/seed.php';
    if (!is_array($loaded)) {
        throw new RuntimeException('İçerik dosyası okunamadı.');
    }

    $seed = $loaded;

    return $seed;
}

function db_connect(array $db): PDO
{
    $name = (string) ($db['name'] ?? '');
    if (!preg_match('/^[a-z0-9_]+$/', $name)) {
        throw new RuntimeException('Geçersiz veritabanı adı.');
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], (int) $db['port']);
    $server = new PDO($dsn, (string) $db['user'], (string) $db['pass'], $options);
    $server->exec(
        "CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    return new PDO($dsn . ';dbname=' . $name, (string) $db['user'], (string) $db['pass'], $options);
}

function db_set(?PDO $pdo): void
{
    $GLOBALS['tuana_pdo'] = $pdo;
}

function db_pdo(): ?PDO
{
    $pdo = $GLOBALS['tuana_pdo'] ?? null;

    return $pdo instanceof PDO ? $pdo : null;
}

function decode_value(string $value): mixed
{
    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $value;
}

function encode_value(mixed $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('İçerik JSON formatına çevrilemedi.');
    }

    return $json;
}

function settings(): array
{
    static $cache = null;
    if (!empty($GLOBALS['tuana_settings_refresh'])) {
        $cache = null;
        unset($GLOBALS['tuana_settings_refresh']);
    }
    if (is_array($cache)) {
        return $cache;
    }

    $pdo = db_pdo();
    if (!$pdo instanceof PDO) {
        $cache = load_seed()['settings'] ?? [];

        return $cache;
    }

    $rows = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
    $cache = [];
    foreach ($rows as $row) {
        $cache[$row['setting_key']] = decode_value((string) $row['setting_value']);
    }

    return $cache;
}

function setting(string $key, mixed $default = null): mixed
{
    $all = settings();

    return $all[$key] ?? $default;
}

function save_setting(string $key, mixed $value): void
{
    $pdo = db_pdo();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Ayar kaydedilemedi.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, encode_value($value)]);
    $GLOBALS['tuana_settings_refresh'] = true;
}

function theme_dir(): string
{
    $dir = trim(str_replace('\\', '/', (string) setting('theme_dir', 'assets')), '/');
    if ($dir === '' || str_contains($dir, '..')) {
        return 'assets';
    }

    return $dir;
}

function theme_file(string $file): string
{
    $relative = theme_dir() . '/' . ltrim($file, '/');
    $absolute = dirname(__DIR__) . '/' . $relative;
    $version = is_file($absolute) ? (string) filemtime($absolute) : '';

    return asset($relative) . ($version === '' ? '' : '?v=' . $version);
}

function favicon_file(): string
{
    $path = trim(str_replace('\\', '/', (string) setting('favicon', '')), '/');
    if ($path === '' || str_contains($path, '..')) {
        return 'assets/favicon.svg';
    }

    return $path;
}

function favicon_type(string $path): string
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return match ($ext) {
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/png',
    };
}

function copyright_text(): string
{
    $text = (string) setting('copyright', '');

    return str_replace('{yil}', date('Y'), $text);
}

function public_maintenance(): void
{
    $flag = setting('maintenance', false);
    if ($flag !== true && $flag !== 1 && $flag !== '1') {
        return;
    }

    http_response_code(503);
    header('Retry-After: 3600');
    require dirname(__DIR__) . '/partials/maintenance.php';
    exit;
}

function sections_for(string $page): array
{
    $pdo = db_pdo();
    if (!$pdo instanceof PDO) {
        $items = [];
        foreach (load_seed()['sections'] ?? [] as $section) {
            if (($section['page_slug'] ?? '') === $page && (int) ($section['is_active'] ?? 1) === 1) {
                $items[] = $section;
            }
        }
        usort(
            $items,
            static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order'])
        );
        remember_page_sections($items);

        return $items;
    }

    $stmt = $pdo->prepare(
        'SELECT section_key, section_type, title, sort_order, content
         FROM sections
         WHERE page_slug = ? AND is_active = 1
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute([$page]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $content = decode_value((string) $row['content']);
        $row['content'] = is_array($content) ? $content : [];
    }
    unset($row);

    remember_page_sections($rows);

    return $rows;
}

/** @param list<array<string, mixed>> $sections */
function remember_page_sections(array $sections): void
{
    $bank = [];
    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }
        $key = (string) ($section['section_key'] ?? '');
        if ($key !== '') {
            $bank[$key] = $section;
        }
    }
    $GLOBALS['tuana_page_sections'] = $bank;
}

/** @return array<string, mixed>|null */
function page_section(string $key): ?array
{
    $bank = $GLOBALS['tuana_page_sections'] ?? [];
    $row = is_array($bank) ? ($bank[$key] ?? null) : null;

    return is_array($row) ? $row : null;
}

function absolute_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    if ($path !== '/') {
        $path = rtrim($path, '/') ?: '/';
    }
    $configured = rtrim((string) setting('site_url', ''), '/');
    if ($configured !== '') {
        return $configured . $path;
    }

    return site_url($path);
}

function category_url(string $key): string
{
    return '/blog/kategori/' . $key;
}

function site_url(string $path): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $path = '/' . ltrim($path, '/');

    return ($https ? 'https' : 'http') . '://' . $host . base_path() . $path;
}

function root_path(): string
{
    $base = base_path();
    if (preg_match('#/admin$#', $base) === 1) {
        $root = substr($base, 0, -strlen('/admin'));

        return $root === '/' ? '' : $root;
    }

    return $base;
}

function root_url(string $path): string
{
    $path = trim($path);
    if ($path === '' || preg_match('/^\s*javascript:/i', $path)) {
        return '#';
    }

    if (
        preg_match('#^(https?:)?//#i', $path)
        || str_starts_with($path, '#')
        || str_starts_with($path, 'mailto:')
        || str_starts_with($path, 'tel:')
    ) {
        return e($path);
    }

    $root = root_path();
    if (str_starts_with($path, '/')) {
        return e($root . $path);
    }

    return e($root . '/' . $path);
}

function request_post_filters(): array
{
    $query = trim((string) ($_GET['q'] ?? ''));
    if (function_exists('mb_substr')) {
        $query = mb_substr($query, 0, 80);
    } else {
        $query = substr($query, 0, 80);
    }
    $category = strtolower(trim((string) ($_GET['kategori'] ?? '')));
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category)) {
        $category = '';
    }

    return ['q' => $query, 'category' => $category];
}

function post_categories(): array
{
    $categories = [];
    foreach (blocks_of_type('category') as $category) {
        $key = (string) ($category['_key'] ?? '');
        $label = trim((string) ($category['title'] ?? ''));
        if ($key === '' || $label === '' || isset($categories[$key])) {
            continue;
        }
        $categories[$key] = $label;
    }

    return $categories;
}

function product_categories(): array
{
    $categories = [];
    foreach (blocks_of_type('product_category') as $category) {
        $key = (string) ($category['_key'] ?? '');
        $label = trim((string) ($category['title'] ?? ''));
        if ($key === '' || $label === '' || isset($categories[$key])) {
            continue;
        }
        $categories[$key] = $label;
    }

    return $categories;
}

function filter_posts(array $posts, array $filters): array
{
    $query = function_exists('mb_strtolower')
        ? mb_strtolower((string) ($filters['q'] ?? ''))
        : strtolower((string) ($filters['q'] ?? ''));
    $category = (string) ($filters['category'] ?? '');
    $matched = [];
    foreach ($posts as $post) {
        if (!is_array($post)) {
            continue;
        }
        if ($category !== '' && (string) ($post['category_key'] ?? '') !== $category) {
            continue;
        }
        if ($query !== '') {
            $haystack = (string) ($post['title'] ?? '') . ' ' . (string) ($post['excerpt'] ?? '');
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
            if (!str_contains($haystack, $query)) {
                continue;
            }
        }
        $matched[] = $post;
    }

    return $matched;
}

function request_slug(): string
{
    $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        return '';
    }

    return $slug;
}

function current_path(): string
{
    $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = '/' . ltrim($uri, '/');
    if ($uri === '/index.php') {
        $uri = '/';
    }

    $trimmed = rtrim($uri, '/');

    return $trimmed === '' ? '/' : $trimmed;
}

function nav_is_current(string $url, string $current): bool
{
    if ($url === '' || str_contains($url, '#')) {
        return false;
    }
    $path = (string) parse_url($url, PHP_URL_PATH);
    if ($path === '') {
        return false;
    }
    $path = rtrim($path, '/') ?: '/';

    return $path === $current;
}

function all_blocks(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $pdo = db_pdo();
    if (!$pdo instanceof PDO) {
        $rows = load_seed()['blocks'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }
        usort(
            $rows,
            static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0))
        );
    } else {
        $rows = $pdo->query(
            'SELECT id, block_key, block_type, title, sort_order, content, updated_at
             FROM blocks
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }

    $cache = [];
    foreach ($rows as $row) {
        if (!is_array($row) || (isset($row['is_active']) && (int) $row['is_active'] !== 1)) {
            continue;
        }
        $cache[] = present_block($row);
    }

    return $cache;
}

function present_block(array $row): array
{
    $content = $row['content'] ?? [];
    if (is_string($content)) {
        $decoded = decode_value($content);
        $content = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($content)) {
        $content = [];
    }

    $key = (string) ($row['block_key'] ?? '');
    $type = (string) ($row['block_type'] ?? '');
    $content['_key'] = $key;
    $content['_type'] = $type;
    if (trim((string) ($content['title'] ?? '')) === '') {
        $content['title'] = (string) ($row['title'] ?? '');
    }
    if ($type === 'post' && trim((string) ($content['url'] ?? '')) === '') {
        $content['url'] = '/yazi/' . $key;
    }
    if ($type === 'page' && trim((string) ($content['url'] ?? '')) === '') {
        $content['url'] = '/' . $key;
    }
    if ($type === 'post') {
        if (trim((string) ($content['author'] ?? '')) === '') {
            $content['author'] = 'Tuana Arıcılık';
        }
        if (trim((string) ($content['comments'] ?? '')) === '') {
            $content['comments'] = 'Yorum yok';
        }
        if (isset($row['updated_at']) && is_string($row['updated_at'])) {
            $content['_updated'] = $row['updated_at'];
        }
    }
    if ($type === 'page' && isset($row['updated_at']) && is_string($row['updated_at'])) {
        $content['_updated'] = $row['updated_at'];
    }
    if ($type === 'product') {
        $content['url'] = '/urun/' . $key;
        if (isset($row['updated_at']) && is_string($row['updated_at'])) {
            $content['_updated'] = $row['updated_at'];
        }
    }
    if (isset($row['id'])) {
        $content['_id'] = (int) $row['id'];
    }

    return $content;
}

function post_has_body(array $post): bool
{
    $html = (string) ($post['body_html'] ?? '');
    if (preg_match('/<img\b/i', $html) === 1) {
        return true;
    }
    if (trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '') {
        return true;
    }
    $body = $post['body'] ?? null;
    if (!is_array($body)) {
        return false;
    }
    foreach ($body as $paragraph) {
        if (trim((string) $paragraph) !== '') {
            return true;
        }
    }

    return false;
}

function post_lead(array $post): string
{
    foreach ($post['body'] ?? [] as $paragraph) {
        $text = trim((string) $paragraph);
        if ($text !== '') {
            return $text;
        }
    }
    $html = preg_replace('/<br\s*\/?>/i', "\n", (string) ($post['body_html'] ?? '')) ?? '';
    $html = preg_replace('/<\/(p|div|h[1-6]|li|blockquote|figcaption)>/i', "\n", $html) ?? $html;
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $line = trim(strtok($text, "\n") ?: '');

    return $line;
}

function post_asset_path(string $src): string
{
    $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($src === '' || preg_match('/^(data|javascript|blob):/i', $src) === 1) {
        return '';
    }
    if (preg_match('#^https?://#i', $src) === 1) {
        $parts = parse_url($src);
        if (!is_array($parts)) {
            return '';
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $siteHost = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $configured = parse_url((string) setting('site_url', ''));
        $configHost = is_array($configured) ? strtolower((string) ($configured['host'] ?? '')) : '';
        $local = $host === 'localhost' || $host === '127.0.0.1' || ($siteHost !== '' && $host === $siteHost) || ($configHost !== '' && $host === $configHost);
        if (!$local) {
            return strtolower((string) ($parts['scheme'] ?? '')) === 'https' ? $src : '';
        }
        $src = (string) ($parts['path'] ?? '');
    }
    $base = root_path();
    if ($base !== '' && ($src === $base || str_starts_with($src, $base . '/'))) {
        $src = substr($src, strlen($base));
    }
    $src = ltrim(str_replace('\\', '/', $src), '/');
    if ($src === '' || str_contains($src, '..') || preg_match('#^assets/(images|uploads)/[A-Za-z0-9_./-]+$#', $src) !== 1) {
        return '';
    }

    return $src;
}

function post_body_html(string $html): string
{
    $clean = HtmlSanitizer::clean($html);
    if ($clean === '') {
        return '';
    }
    $rendered = preg_replace_callback(
        '/\ssrc="([^"]*)"/',
        static function (array $match): string {
            $path = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (str_starts_with($path, 'https://')) {
                return ' src="' . e($path) . '"';
            }
            if (!str_starts_with($path, 'assets/')) {
                return '';
            }

            return ' src="' . e(root_path() . '/' . $path) . '"';
        },
        $clean
    );

    return is_string($rendered) ? $rendered : '';
}

function post_index_enabled(array $post): bool
{
    return !array_key_exists('index', $post) || !empty($post['index']);
}

function post_alias_key(string $slug): string
{
    if ($slug === '') {
        return '';
    }
    $pdo = db_pdo();
    if (!$pdo instanceof PDO) {
        return '';
    }
    $stmt = $pdo->query("SELECT block_key, content FROM blocks WHERE block_type = 'post' AND is_active = 1");
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $content = decode_value((string) ($row['content'] ?? ''));
        $aliases = is_array($content) ? ($content['aliases'] ?? null) : null;
        if (!is_array($aliases)) {
            continue;
        }
        foreach ($aliases as $alias) {
            if ((string) $alias === $slug) {
                return (string) $row['block_key'];
            }
        }
    }

    return '';
}

function page_alias_key(string $slug): string
{
    if ($slug === '') {
        return '';
    }
    $pdo = db_pdo();
    if (!$pdo instanceof PDO) {
        return '';
    }
    $stmt = $pdo->query("SELECT block_key, content FROM blocks WHERE block_type = 'page' AND is_active = 1");
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $content = decode_value((string) ($row['content'] ?? ''));
        $aliases = is_array($content) ? ($content['aliases'] ?? null) : null;
        if (!is_array($aliases)) {
            continue;
        }
        foreach ($aliases as $alias) {
            if ((string) $alias === $slug) {
                return (string) $row['block_key'];
            }
        }
    }

    return '';
}

/** @return array<string, mixed>|null */
function page_record(string $slug): ?array
{
    if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
        return null;
    }
    $pdo = db_pdo();
    if (!$pdo instanceof PDO) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT block_key, title, is_active, content, updated_at FROM blocks WHERE block_type = 'page' AND block_key = ? LIMIT 1"
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $content = decode_value((string) ($row['content'] ?? ''));
    $content = is_array($content) ? $content : [];
    $content['title'] = (string) $row['title'];
    $content['kind'] = (string) ($content['kind'] ?? 'classic');
    $content['_key'] = (string) $row['block_key'];
    $content['_active'] = (int) $row['is_active'] === 1;
    $content['_updated'] = (string) ($row['updated_at'] ?? '');

    return $content;
}

/** @return array{stored:string,input:string,date:string,time:string,iso:string}|null */
function post_moment(array $post): ?array
{
    $zone = new DateTimeZone('Europe/Istanbul');
    $stored = trim((string) ($post['published_at'] ?? ''));
    $moment = null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/', $stored, $match) === 1) {
        $moment = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $match[1] . '-' . $match[2] . '-' . $match[3] . ' ' . $match[4] . ':' . $match[5],
            $zone
        );
    }
    if (!$moment instanceof DateTimeImmutable) {
        $date = trim((string) ($post['date'] ?? ''));
        $time = trim((string) ($post['time'] ?? ''));
        $clock = ['', '00', '00'];
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $parsedClock) === 1) {
            $clock = $parsedClock;
        }
        $folded = strtr($date, ['İ' => 'i', 'I' => 'ı']);
        $folded = function_exists('mb_strtolower') ? mb_strtolower($folded, 'UTF-8') : strtolower($folded);
        $months = [
            'ocak' => 1, 'şubat' => 2, 'subat' => 2, 'mart' => 3, 'nisan' => 4,
            'mayıs' => 5, 'mayis' => 5, 'haziran' => 6, 'temmuz' => 7,
            'ağustos' => 8, 'agustos' => 8, 'eylül' => 9, 'eylul' => 9,
            'ekim' => 10, 'kasım' => 11, 'kasim' => 11, 'aralık' => 12, 'aralik' => 12,
        ];
        if (preg_match('/^(\d{1,2})\s+([a-zçğıöşü]+)\s+(\d{4})$/u', $folded, $parts) === 1 && isset($months[$parts[2]])) {
            $moment = DateTimeImmutable::createFromFormat(
                '!Y-n-j H:i',
                $parts[3] . '-' . $months[$parts[2]] . '-' . $parts[1] . ' ' . $clock[1] . ':' . $clock[2],
                $zone
            );
        }
    }
    if (!$moment instanceof DateTimeImmutable) {
        return null;
    }

    return format_post_moment($moment);
}

/** @return array{stored:string,input:string,date:string,time:string,iso:string} */
function format_post_moment(DateTimeImmutable $moment): array
{
    $names = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];

    return [
        'stored' => $moment->format('Y-m-d H:i:s'),
        'input' => $moment->format('Y-m-d\TH:i'),
        'date' => $moment->format('j') . ' ' . $names[(int) $moment->format('n')] . ' ' . $moment->format('Y'),
        'time' => $moment->format('H:i'),
        'iso' => $moment->format('Y-m-d\TH:i:sP'),
    ];
}

function block_by_key(string $key): ?array
{
    foreach (all_blocks() as $block) {
        if (($block['_key'] ?? '') === $key) {
            return $block;
        }
    }

    return null;
}

function blocks_by_keys(array $keys, string $type = ''): array
{
    $items = [];
    foreach ($keys as $key) {
        $block = block_by_key((string) $key);
        if ($block === null) {
            continue;
        }
        if ($type !== '' && ($block['_type'] ?? '') !== $type) {
            continue;
        }
        $items[] = $block;
    }

    return $items;
}

function blocks_of_type(string $type): array
{
    $items = [];
    foreach (all_blocks() as $block) {
        if (($block['_type'] ?? '') === $type) {
            $items[] = $block;
        }
    }

    return $items;
}

function section_posts(array $data, bool $archive = false): array
{
    $items = blocks_of_type('post');
    $category = trim((string) ($data['category'] ?? ''));
    if (!$archive && $category !== '') {
        $items = array_values(array_filter(
            $items,
            static fn (array $post): bool => (string) ($post['category_key'] ?? '') === $category
        ));
    }
    $oldestFirst = (string) ($data['order'] ?? 'new') === 'old';
    usort($items, static function (array $left, array $right) use ($oldestFirst): int {
        $compare = post_sort_stamp($left) <=> post_sort_stamp($right);

        return $oldestFirst ? $compare : -$compare;
    });
    if (!$archive) {
        $limit = max(0, (int) ($data['limit'] ?? 0));
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }
    }

    return $items;
}

function section_products(array $data): array
{
    $items = blocks_of_type('product');
    $category = trim((string) ($data['category'] ?? ''));
    if ($category !== '') {
        $items = array_values(array_filter(
            $items,
            static fn (array $product): bool => (string) ($product['category_key'] ?? '') === $category
        ));
    }
    $oldestFirst = (string) ($data['order'] ?? 'new') === 'old';
    usort($items, static function (array $left, array $right) use ($oldestFirst): int {
        $compare = ((int) ($left['_id'] ?? 0)) <=> ((int) ($right['_id'] ?? 0));
        if ($compare === 0) {
            $compare = strcmp((string) ($left['_key'] ?? ''), (string) ($right['_key'] ?? ''));
        }

        return $oldestFirst ? $compare : -$compare;
    });
    $limit = max(0, (int) ($data['limit'] ?? 0));
    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }

    return $items;
}

function post_sort_stamp(array $post): string
{
    $moment = post_moment($post);
    if (is_array($moment)) {
        return (string) $moment['iso'];
    }

    return (string) ($post['_updated'] ?? '');
}

function section_items(array $data, string $type): array
{
    $keys = $data['block_keys'] ?? null;
    if (is_array($keys) && $keys !== []) {
        return blocks_by_keys($keys, $type);
    }

    $source = (string) ($data['source'] ?? '');
    if ($source === 'posts' || $source === 'cards') {
        $items = blocks_of_type($source === 'posts' ? 'post' : 'card');
        $limit = max(0, (int) ($data['limit'] ?? 0));

        return $limit > 0 ? array_slice($items, 0, $limit) : $items;
    }

    $items = [];
    foreach ($data['items'] ?? [] as $item) {
        if (is_array($item)) {
            $items[] = $item;
        }
    }

    return $items;
}

function section_is_placed(array $section): bool
{
    $content = $section['content'] ?? [];

    return is_array($content) && (string) ($content['placed_in'] ?? '') !== '';
}

function render_page(array $sections): void
{
    foreach ($sections as $section) {
        if (!is_array($section) || section_is_placed($section)) {
            continue;
        }
        if (($section['section_type'] ?? '') === 'pagehead') {
            render_section($section);
        }
    }

    echo '<div class="page-canvas">';
    foreach ($sections as $section) {
        if (!is_array($section) || section_is_placed($section)) {
            continue;
        }
        if (($section['section_type'] ?? '') === 'pagehead') {
            continue;
        }
        render_section($section);
    }
    echo '</div>';
}

function render_section(array $section): void
{
    $type = (string) ($section['section_type'] ?? '');
    $partial = match ($type) {
        'hero' => 'hero.php',
        'pagehead' => 'pagehead.php',
        'features' => 'features.php',
        'about' => 'about.php',
        'banner' => 'banner.php',
        'services' => 'services.php',
        'products' => 'products.php',
        'why' => 'why.php',
        'benefits' => 'benefits.php',
        'promo' => 'promo.php',
        'testimonials' => 'testimonials.php',
        'blog' => 'blog.php',
        'grid' => 'grid.php',
        default => '',
    };

    if ($partial === '') {
        return;
    }

    $data = $section['content'] ?? [];
    if (is_string($data)) {
        $decoded = decode_value($data);
        $data = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($data)) {
        $data = [];
    }

    $sectionKey = (string) ($section['section_key'] ?? $type);
    include dirname(__DIR__) . '/partials/sections/' . $partial;
}

function title_spans(array $data): string
{
    $lines = $data['title_lines'] ?? null;
    if (!is_array($lines) || $lines === []) {
        $lines = [(string) ($data['title'] ?? '')];
    }

    $html = '';
    foreach ($lines as $line) {
        $html .= '<span>' . e((string) $line) . '</span>';
    }

    return $html;
}

function render_link(?array $link, string $class): void
{
    if (!is_array($link)) {
        return;
    }

    $label = trim((string) ($link['label'] ?? ''));
    if ($label === '') {
        return;
    }

    $modal = trim((string) ($link['modal'] ?? ''));
    echo '<a class="' . e($class) . '" href="' . url((string) ($link['url'] ?? '#')) . '"';
    if ($modal !== '') {
        echo ' data-modal="' . e($modal) . '"';
    }
    echo '>';
    if (($link['icon'] ?? '') === 'play') {
        echo '<span class="play" aria-hidden="true">' . icon('play') . '</span>';
    }
    echo e($label) . '</a>';
}

function hero_normalize(array $data): array
{
    $rawSlider = is_array($data['slider'] ?? null) ? $data['slider'] : [];
    $effect = (string) ($rawSlider['effect'] ?? 'fade');
    if (!in_array($effect, ['fade', 'slide', 'zoom', 'none'], true)) {
        $effect = 'fade';
    }
    $slides = $data['slides'] ?? null;
    if (!is_array($slides) || $slides === []) {
        $media = is_array($data['media'] ?? null) ? $data['media'] : [];
        if (!isset($media['image']) && isset($data['image'])) {
            $media['image'] = $data['image'];
        }
        $slides = [[
            'active' => true,
            'title_lines' => $data['title_lines'] ?? [],
            'text' => $data['text'] ?? '',
            'image_alt' => $data['image_alt'] ?? '',
            'image' => $data['image'] ?? '',
            'primary_button' => $data['primary_button'] ?? null,
            'secondary_button' => $data['secondary_button'] ?? null,
            'media' => $media,
            'type' => [],
        ]];
    }

    return [
        'slider' => [
            'effect' => $effect,
            'interval' => max(3, min(12, (int) ($rawSlider['interval'] ?? 6))),
            'autoplay' => ($rawSlider['autoplay'] ?? true) === true
                || ($rawSlider['autoplay'] ?? true) === 1
                || ($rawSlider['autoplay'] ?? null) === '1',
        ],
        'slides' => $slides,
    ];
}

function hero_type_attr(array $type): string
{
    $fonts = [
        'outfit' => '"Outfit", sans-serif',
        'manrope' => '"Manrope", sans-serif',
        'vibes' => '"Great Vibes", cursive',
        'georgia' => 'Georgia, "Times New Roman", serif',
    ];
    $titleSizes = [
        'small' => 'clamp(34px, 4vw, 52px)',
        'large' => 'clamp(56px, 6.2vw, 84px)',
    ];
    $textSizes = [
        'small' => '15px',
        'large' => '18px',
    ];
    $vars = [];
    $titleFont = (string) ($type['title_font'] ?? '');
    $textFont = (string) ($type['text_font'] ?? '');
    if (isset($fonts[$titleFont])) {
        $vars[] = '--hero-title-font:' . $fonts[$titleFont];
    }
    if (isset($fonts[$textFont])) {
        $vars[] = '--hero-text-font:' . $fonts[$textFont];
    }
    $titleColor = (string) ($type['title_color'] ?? '');
    $textColor = (string) ($type['text_color'] ?? '');
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $titleColor) === 1) {
        $vars[] = '--hero-title-color:' . $titleColor;
    }
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $textColor) === 1) {
        $vars[] = '--hero-text-color:' . $textColor;
    }
    $titleWeight = (string) ($type['title_weight'] ?? '');
    $textWeight = (string) ($type['text_weight'] ?? '');
    if (in_array($titleWeight, ['400', '500', '600', '700', '800'], true)) {
        $vars[] = '--hero-title-weight:' . $titleWeight;
    }
    if (in_array($textWeight, ['400', '500', '600', '700', '800'], true)) {
        $vars[] = '--hero-text-weight:' . $textWeight;
    }
    if (($type['title_style'] ?? '') === 'italic') {
        $vars[] = '--hero-title-style:italic';
    }
    if (($type['text_style'] ?? '') === 'italic') {
        $vars[] = '--hero-text-style:italic';
    }
    if (($type['title_decoration'] ?? '') === 'underline') {
        $vars[] = '--hero-title-decoration:underline';
    }
    if (($type['text_decoration'] ?? '') === 'underline') {
        $vars[] = '--hero-text-decoration:underline';
    }
    if (($type['title_transform'] ?? '') === 'uppercase') {
        $vars[] = '--hero-title-transform:uppercase';
    }
    if (($type['text_transform'] ?? '') === 'uppercase') {
        $vars[] = '--hero-text-transform:uppercase';
    }
    $titleSize = (string) ($type['title_size'] ?? '');
    $textSize = (string) ($type['text_size'] ?? '');
    if (isset($titleSizes[$titleSize])) {
        $vars[] = '--hero-title-size:' . $titleSizes[$titleSize];
    }
    if (isset($textSizes[$textSize])) {
        $vars[] = '--hero-text-size:' . $textSizes[$textSize];
    }
    return implode(';', $vars);
}

function hero_picture(array $data, string $sizes): string
{
    $media = is_array($data['media'] ?? null) ? $data['media'] : [];
    $path = trim((string) ($media['image'] ?? $data['image'] ?? ''));
    if ($path === '') {
        return '';
    }

    $sources = [];
    if (is_array($media['sources'] ?? null)) {
        foreach ($media['sources'] as $source) {
            if (!is_array($source)) {
                continue;
            }
            $sourcePath = trim((string) ($source['path'] ?? ''));
            $width = (int) ($source['w'] ?? 0);
            if ($sourcePath !== '' && $width > 0) {
                $sources[] = asset($sourcePath) . ' ' . $width . 'w';
            }
        }
    }

    $focalX = max(0, min(100, (int) ($media['focal_x'] ?? 50)));
    $focalY = max(0, min(100, (int) ($media['focal_y'] ?? 45)));
    $width = (int) ($media['width'] ?? 0);
    $height = (int) ($media['height'] ?? 0);
    $html = '<img src="' . asset($path) . '" alt="' . e((string) ($data['image_alt'] ?? '')) . '"';
    if ($sources !== []) {
        $html .= ' srcset="' . implode(', ', $sources) . '" sizes="' . e($sizes) . '"';
    }
    if ($width > 0 && $height > 0) {
        $html .= ' width="' . $width . '" height="' . $height . '"';
    }
    $html .= ' style="object-position:' . $focalX . '% ' . $focalY . '%"';
    $html .= ' loading="eager" decoding="async" fetchpriority="high">';

    return $html;
}

function media_tag(string $path, string $alt, string $class = '', bool $lazy = true, string $attrs = ''): string
{
    if ($path === '') {
        return '';
    }

    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    $loading = $lazy ? ' lazy' : ' eager';

    return '<img' . $classAttr . $attrs
        . ' src="' . asset($path) . '" alt="' . e($alt) . '"'
        . ' loading="' . trim($loading) . '" decoding="async">';
}

function motion_styles(): array
{
    return [
        'none' => 'Yok',
        'fade' => 'Belirme',
        'rise' => 'Aşağıdan yukarı',
        'drop' => 'Yukarıdan aşağı',
        'from-left' => 'Soldan',
        'from-right' => 'Sağdan',
        'zoom' => 'Yakınlaşma',
    ];
}

function motion_config(array $data): array
{
    $motion = $data['motion'] ?? [];
    if (!is_array($motion)) {
        $motion = [];
    }

    $allowed = array_keys(motion_styles());
    $slots = [];
    $rawSlots = $motion['slots'] ?? [];
    if (is_array($rawSlots)) {
        foreach ($rawSlots as $name => $style) {
            $style = (string) $style;
            if (!in_array($style, $allowed, true) || $style === 'none') {
                continue;
            }
            $slots[(string) $name] = $style;
        }
    }

    $once = array_key_exists('once', $motion) ? (bool) $motion['once'] : true;

    return [
        'stagger' => max(0, min(600, (int) ($motion['stagger'] ?? 120))),
        'distance' => max(12, min(140, (int) ($motion['distance'] ?? 48))),
        'duration' => max(240, min(1800, (int) ($motion['duration'] ?? 780))),
        'once' => $once,
        'slots' => $slots,
    ];
}

function motion_vars(array $motion): string
{
    if ($motion['slots'] === []) {
        return '';
    }

    return ' style="--anim-distance:' . (int) $motion['distance']
        . 'px;--anim-duration:' . (int) $motion['duration']
        . 'ms;--anim-step:' . (int) $motion['stagger'] . 'ms"';
}

function anim_bind(array $motion, string $slot, int $index = 0, string $extraStyle = ''): string
{
    $styles = $extraStyle !== '' ? [rtrim($extraStyle, ';')] : [];
    $styleName = $motion['slots'][$slot] ?? '';
    if ($styleName === '') {
        return $styles === [] ? '' : ' style="' . implode(';', $styles) . '"';
    }

    $styles[] = '--anim-index:' . max(0, $index);
    $once = !empty($motion['once']) ? '1' : '0';

    return ' data-anim="' . e($styleName) . '" data-anim-once="' . $once . '" style="' . implode(';', $styles) . '"';
}

function icon(string $name): string
{
    $icons = [
        'play' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7.5v9l8-4.5-8-4.5z" fill="currentColor"/></svg>',
        'dipper' => '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8c0 6 6 8 6 14v4"/><path d="M16 30h16M18 34h12M20 38h8"/><path d="M24 8v4"/><circle cx="24" cy="6" r="1.4" fill="currentColor" stroke="none"/></svg>',
        'hive' => '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 30h28M8 36h32M14 24h20M18 18h12"/><path d="M24 8v6M20 10l4 4 4-4"/><path d="M16 40h16"/></svg>',
        'jar' => '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 14h16v4c4 2 6 6 6 12 0 8-4 12-14 12s-14-4-14-12c0-6 2-10 6-12v-4z"/><path d="M18 14V10h12v4"/><path d="M17 28h14"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 12.5l4 4 8-9" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H8v3h2v7h3v-7h2.6l.4-3H13v-2c0-.6.4-1 1-1z"/></svg>',
        'twitter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17.6 4h2.4l-5.3 6.1L21 20h-4.8l-3.8-5-4.3 5H5.7l5.7-6.5L4 4h4.9l3.4 4.6L17.6 4zm-.8 14.4h1.3L7.3 5.5H5.9l10.9 12.9z"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M22 12.2s0-3.2-.4-4.6c-.2-.9-.9-1.6-1.8-1.8C18.2 5.4 12 5.4 12 5.4s-6.2 0-7.8.4c-.9.2-1.6.9-1.8 1.8C2 9 2 12.2 2 12.2s0 3.2.4 4.6c.2.9.9 1.6 1.8 1.8 1.6.4 7.8.4 7.8.4s6.2 0 7.8-.4c.9-.2 1.6-.9 1.8-1.8.4-1.4.4-4.6.4-4.6zM10 15.5v-6.6l5.2 3.3-5.2 3.3z"/></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M8 4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8a4 4 0 0 1 4-4zm8 1.8H8A2.2 2.2 0 0 0 5.8 8v8A2.2 2.2 0 0 0 8 18.2h8A2.2 2.2 0 0 0 18.2 16V8A2.2 2.2 0 0 0 16 5.8zM12 8.2A3.8 3.8 0 1 1 8.2 12 3.8 3.8 0 0 1 12 8.2zm0 1.6A2.2 2.2 0 1 0 14.2 12 2.2 2.2 0 0 0 12 9.8zM17.2 7.1a.9.9 0 1 1-.9.9.9.9 0 0 1 .9-.9z"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.1 3.2A8.7 8.7 0 0 0 4.6 16.3L3.4 20.6l4.4-1.2A8.7 8.7 0 1 0 12.1 3.2zm4.8 12.3c-.2.6-1.2 1.1-1.6 1.1-.4.1-.9.2-2.9-.6-2.4-1-4-3.4-4.1-3.6-.1-.2-1-1.3-1-2.5s.6-1.8.9-2c.2-.2.5-.3.7-.3h.5c.2 0 .4 0 .5.4.2.6.7 2 .7 2.1.1.1 0 .3-.1.5l-.4.5c-.1.1-.3.3-.1.6.2.3.7 1.2 1.5 1.9 1 .9 1.9 1.2 2.2 1.3.3.1.4.1.6-.1l.7-.8c.2-.2.3-.2.6-.1.2.1 1.6.8 1.9.9.3.2.4.2.5.3.1.2 0 .7-.2 1.3z"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14.2 3.2c.4 2.4 1.8 4.1 4.1 4.5v2.4c-1.4 0-2.7-.4-3.9-1.2v6.4a5.7 5.7 0 1 1-5.7-5.7c.3 0 .6 0 .9.1v2.6a3.2 3.2 0 1 0 2.2 3V3.2h2.4z"/></svg>',
        'pin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/></svg>',
        'mail' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="6" width="17" height="12" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4.5 7.5 12 13l7.5-5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 3h3l1.5 4-2 1.5a12 12 0 0 0 6 6L17 13l4 1.5V18a2 2 0 0 1-2.2 2A16 16 0 0 1 4 6.2 2 2 0 0 1 6 4h1z"/></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 3.5v4M16 3.5v4M4 10h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v5l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'comment' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 16.5 4 20l4.2-1.4A8 8 0 1 0 6 16.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 16l4 4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        'linkedin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.5 9H4V20h2.5V9zM5.2 4A1.6 1.6 0 1 0 5.2 7.2 1.6 1.6 0 0 0 5.2 4zM20 20h-2.5v-5.6c0-1.6-.6-2.6-2-2.6a2.1 2.1 0 0 0-2 1.4c-.1.3-.1.7-.1 1.1V20H11V9h2.4v1.5A3.2 3.2 0 0 1 16.4 9c2.4 0 3.6 1.6 3.6 4.6V20z"/></svg>',
        'pinterest' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3.2A8.8 8.8 0 0 0 8.3 20c-.1-.7-.2-1.8 0-2.6l1.6-6.7s-.4-.8-.4-2c0-1.8 1.1-3.2 2.4-3.2 1.1 0 1.7.8 1.7 1.9 0 1.1-.7 2.8-1.1 4.4-.3 1.3.6 2.3 1.9 2.3 2.3 0 3.8-2.9 3.8-6.4 0-2.6-1.8-4.6-5-4.6a5.3 5.3 0 0 0-5.5 5.3c0 1 .4 2 .9 2.6a.3.3 0 0 1 .1.3l-.3 1.3c0 .2-.2.2-.4.1-1.4-.6-2-2.2-2-4 0-3.3 2.8-7.2 8.3-7.2 4.4 0 7.3 3.2 7.3 6.6 0 4.5-2.5 7.9-6.2 7.9-1.2 0-2.4-.7-2.8-1.4l-.8 2.9a9 9 0 0 0 2.6.4 8.8 8.8 0 0 0 0-17.6z"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    ];

    return $icons[$name] ?? '';
}
