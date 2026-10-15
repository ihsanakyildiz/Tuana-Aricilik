<?php

declare(strict_types=1);

require_once __DIR__ . '/ImageSet.php';
require_once __DIR__ . '/HtmlSanitizer.php';
require_once __DIR__ . '/ProductCategoryEditor.php';

final class ProductEditor
{
    private const TYPE = 'product';

    private const GALLERY_LIMIT = 8;

    /** @var list<string> */
    private const RESERVED = [
        'home', 'blog', 'admin', 'yazi', 'sayfa', 'sitemap', 'robots', 'assets',
        'urun', 'urunler', 'magaza', 'sepet', 'siparis', 'siparisler', 'kategori', 'kategoriler',
        'uye-giris', 'uye-ol', 'hesabim', 'uye-cikis', 'uyeler',
        'tesekkur', 'siparislerim',
    ];

    /** @var array<string, string> */
    private const UNITS = [
        'adet' => 'Adet',
        'kg' => 'Kilogram',
        'g' => 'Gram',
        'litre' => 'Litre',
        'kavanoz' => 'Kavanoz',
        'kutu' => 'Kutu',
        'paket' => 'Paket',
    ];

    /** @return array<string, string> */
    public static function units(): array
    {
        return self::UNITS;
    }

    public static function normalizeMoney(string $value): ?string
    {
        return self::money($value);
    }

    public static function moneyCents(string $amount): int
    {
        if (preg_match('/^\d+\.\d{2}$/', $amount) !== 1) {
            return 0;
        }

        return self::cents($amount);
    }

    public static function moneyLabel(string $amount): string
    {
        if (preg_match('/^\d+\.\d{2}$/', $amount) !== 1) {
            return '';
        }
        [$whole, $fraction] = explode('.', $amount);

        return number_format((int) $whole, 0, ',', '.') . ',' . $fraction . ' TL';
    }

    public static function moneyInput(string $amount): string
    {
        $label = self::moneyLabel($amount);
        if (!str_ends_with($label, ' TL')) {
            return '';
        }

        return substr($label, 0, -3);
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $titles = [];
        $stmt = self::pdo()->prepare('SELECT block_key, title FROM blocks WHERE block_type = ?');
        $stmt->execute(['product_category']);
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $titles[(string) $row['block_key']] = (string) $row['title'];
            }
        }
        $stmt = self::pdo()->prepare(
            'SELECT id, block_key, title, is_active, content, updated_at
             FROM blocks
             WHERE block_type = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([self::TYPE]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            $content = is_array($content) ? $content : [];
            $categoryKey = (string) ($content['category_key'] ?? '');
            $rows[] = [
                'id' => (int) $row['id'],
                'key' => (string) $row['block_key'],
                'title' => (string) $row['title'],
                'active' => (int) $row['is_active'] === 1,
                'image' => (string) ($content['image'] ?? ''),
                'category_key' => $categoryKey,
                'category' => (string) ($titles[$categoryKey] ?? ''),
                'price' => (string) ($content['price'] ?? ''),
                'discount_price' => (string) ($content['discount_price'] ?? ''),
                'sku' => (string) ($content['sku'] ?? ''),
                'unit' => (string) ($content['unit'] ?? ''),
                'amount' => (string) ($content['amount'] ?? ''),
                'stock_track' => !empty($content['stock_track']),
                'stock_qty' => (int) ($content['stock_qty'] ?? 0),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function open(string $key): ?array
    {
        if ($key === '') {
            return null;
        }
        $stmt = self::pdo()->prepare(
            'SELECT id, block_key, title, is_active, content
             FROM blocks
             WHERE block_type = ? AND block_key = ?
             LIMIT 1'
        );
        $stmt->execute([self::TYPE, $key]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $content = decode_value((string) ($row['content'] ?? ''));
        $content = is_array($content) ? $content : [];
        $gallery = [];
        foreach (is_array($content['gallery'] ?? null) ? $content['gallery'] : [] as $shot) {
            if (!is_array($shot)) {
                continue;
            }
            $id = (string) ($shot['id'] ?? '');
            $image = (string) ($shot['image'] ?? '');
            if ($id === '' || $image === '') {
                continue;
            }
            $gallery[] = [
                'id' => $id,
                'image' => $image,
                'alt' => (string) ($shot['alt'] ?? ''),
            ];
        }

        return [
            'id' => (int) $row['id'],
            'key' => (string) $row['block_key'],
            'title' => (string) $row['title'],
            'active' => (int) $row['is_active'] === 1,
            'excerpt' => (string) ($content['excerpt'] ?? ''),
            'body' => (string) ($content['body_html'] ?? ''),
            'category_key' => (string) ($content['category_key'] ?? ''),
            'sku' => (string) ($content['sku'] ?? ''),
            'price' => self::moneyInput((string) ($content['price'] ?? '')),
            'discount_price' => self::moneyInput((string) ($content['discount_price'] ?? '')),
            'unit' => (string) ($content['unit'] ?? 'adet'),
            'amount' => (string) ($content['amount'] ?? ''),
            'stock_track' => !empty($content['stock_track']),
            'stock_qty' => (int) ($content['stock_qty'] ?? 0),
            'image' => (string) ($content['image'] ?? ''),
            'gallery' => $gallery,
            'content' => $content,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>,key:string}
     */
    public static function save(array $post, array $files = []): array
    {
        $original = is_string($post['product_key'] ?? null) ? $post['product_key'] : '';
        $current = $original !== '' ? self::open($original) : null;
        $title = self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 140);
        $slugInput = is_string($post['slug'] ?? null) ? $post['slug'] : '';
        $slug = self::slug($slugInput !== '' ? $slugInput : $title);
        $categoryKey = is_string($post['category_key'] ?? null) ? $post['category_key'] : '';
        $unit = is_string($post['unit'] ?? null) ? $post['unit'] : 'adet';
        $status = ($post['status'] ?? '') === 'publish' ? 'publish' : 'draft';
        $values = [
            'title' => $title,
            'slug' => $slugInput,
            'status' => $status,
            'excerpt' => self::clip(is_string($post['excerpt'] ?? null) ? $post['excerpt'] : '', 300),
            'body' => is_string($post['body'] ?? null) ? $post['body'] : '',
            'category_key' => $categoryKey,
            'sku' => self::clip(is_string($post['sku'] ?? null) ? $post['sku'] : '', 32),
            'price' => self::clip(is_string($post['price'] ?? null) ? $post['price'] : '', 16),
            'discount_price' => self::clip(is_string($post['discount_price'] ?? null) ? $post['discount_price'] : '', 16),
            'unit' => $unit,
            'amount' => self::clip(is_string($post['amount'] ?? null) ? $post['amount'] : '', 40),
            'stock_track' => isset($post['stock_track']),
            'stock_qty' => self::clip(is_string($post['stock_qty'] ?? null) ? $post['stock_qty'] : '', 6),
            'key' => $original,
        ];
        if ($original !== '' && $current === null) {
            return ['errors' => ['Ürün bulunamadı.'], 'values' => $values, 'key' => $original];
        }

        $errors = [];
        if ($title === '') {
            $errors[] = 'Ürün adı boş olamaz.';
        }
        if ($slug === '') {
            $errors[] = 'Adres yalnızca küçük harf, rakam ve tire olabilir.';
        } elseif (in_array($slug, self::RESERVED, true)) {
            $errors[] = 'Bu adres mağaza sayfalarına ayrılmış.';
        } elseif (self::keyTaken($slug, $current === null ? 0 : (int) $current['id'])) {
            $errors[] = 'Bu adres başka bir kayıtta kullanılıyor.';
        }
        if (!isset(self::UNITS[$unit])) {
            $errors[] = 'Satış birimi seçilmeli.';
            $unit = 'adet';
            $values['unit'] = $unit;
        }
        $category = $categoryKey !== '' ? ProductCategoryEditor::open($categoryKey) : null;
        if ($category === null) {
            $errors[] = 'Bir ürün kategorisi seç.';
        }
        $price = self::money($values['price']);
        $discountRaw = trim($values['discount_price']);
        $discount = $discountRaw === '' ? '' : self::money($discountRaw);
        if ($price === null) {
            $errors[] = 'Satış fiyatı sıfırdan büyük bir tutar olmalı.';
        }
        if ($discountRaw !== '' && $discount === null) {
            $errors[] = 'İndirimli satış fiyatı sıfırdan büyük bir tutar olmalı.';
        } elseif ($price !== null && $discount !== null && $discount !== '' && self::cents($discount) >= self::cents($price)) {
            $errors[] = 'İndirimli fiyat, satış fiyatından küçük olmalı.';
        }
        $sku = trim($values['sku']);
        if ($sku !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/', $sku) !== 1) {
            $errors[] = 'Stok kodu harf, rakam, nokta, alt çizgi ve tire içerebilir.';
        } elseif ($sku !== '' && self::skuTaken($sku, $current === null ? 0 : (int) $current['id'])) {
            $errors[] = 'Bu stok kodu başka bir üründe kullanılıyor.';
        }
        $stockQty = 0;
        if ($values['stock_track']) {
            if (preg_match('/^\d{1,6}$/', trim($values['stock_qty'])) !== 1) {
                $errors[] = 'Stok adedi 0 ile 999999 arasında bir tam sayı olmalı.';
            } else {
                $stockQty = (int) $values['stock_qty'];
            }
        }
        $bodyRaw = $values['body'];
        if (strlen($bodyRaw) > 200000) {
            $errors[] = 'Ürün açıklaması çok uzun.';
            $body = '';
        } else {
            $body = HtmlSanitizer::clean($bodyRaw);
        }
        $kept = self::keptGallery($post, is_array($current['content'] ?? null) ? $current['content'] : [], $errors);
        $uploads = self::uploads($files['gallery_new'] ?? null, $errors);
        if (count($kept) + count($uploads) > self::GALLERY_LIMIT) {
            $errors[] = 'Bir üründe en fazla 8 görsel olabilir.';
        }
        if ($uploads !== [] && !ImageSet::ready()) {
            $errors[] = 'WebP dönüştürme şu an kullanılamıyor.';
        }
        if ($errors !== [] || $price === null) {
            return ['errors' => $errors, 'values' => $values, 'key' => $original];
        }

        $content = is_array($current['content'] ?? null) ? $current['content'] : [];
        $previous = self::imagePaths($content);
        $fresh = [];
        $savedId = $current === null ? 0 : (int) $current['id'];
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $gallery = $kept;
            foreach ($uploads as $upload) {
                $stored = ImageSet::storeRaster($upload['tmp'], $upload['mime'], self::uploadFolder('shop'), 'urun-galeri-' . bin2hex(random_bytes(4)));
                foreach ($stored['sources'] as $source) {
                    $fresh[] = (string) $source['path'];
                }
                $gallery[] = [
                    'id' => bin2hex(random_bytes(4)),
                    'image' => $stored['image'],
                    'sources' => $stored['sources'],
                    'alt' => '',
                ];
            }
            $cover = $gallery[0] ?? null;
            $inline = self::htmlPaths($body);
            $content['excerpt'] = $values['excerpt'];
            $content['body_html'] = $body;
            $content['category_key'] = $categoryKey;
            $content['sku'] = $sku;
            $content['price'] = $price;
            $content['discount_price'] = $discount ?? '';
            $content['unit'] = $unit;
            $content['amount'] = $values['amount'];
            $content['stock_track'] = $values['stock_track'];
            $content['stock_qty'] = $stockQty;
            $content['gallery'] = $gallery;
            $content['image'] = is_array($cover) ? (string) $cover['image'] : '';
            $content['image_alt'] = is_array($cover) ? (string) $cover['alt'] : '';
            $content['inline_sources'] = $inline;
            unset($content['_key'], $content['_type'], $content['_updated']);
            if ($current === null) {
                $insert = $pdo->prepare(
                    'INSERT INTO blocks (block_key, block_type, title, sort_order, is_active, content) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$slug, self::TYPE, $title, self::nextOrder(), $status === 'publish' ? 1 : 0, encode_value($content)]);
                $savedId = (int) $pdo->lastInsertId();
            } else {
                $update = $pdo->prepare(
                    'UPDATE blocks SET block_key = ?, title = ?, is_active = ?, content = ? WHERE id = ? AND block_type = ?'
                );
                $update->execute([$slug, $title, $status === 'publish' ? 1 : 0, encode_value($content), $savedId, self::TYPE]);
            }
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Ürün kaydedilemedi.'], 'values' => $values, 'key' => $original];
        }
        self::releasePaths(array_values(array_diff($previous, self::imagePaths($content))), $savedId);
        self::sweepUploads();

        return ['errors' => [], 'values' => $values, 'key' => $slug];
    }

    /** @return list<string> */
    public static function remove(string $key): array
    {
        $row = self::open($key);
        if ($row === null) {
            return ['Ürün bulunamadı.'];
        }
        $paths = self::imagePaths(is_array($row['content'] ?? null) ? $row['content'] : []);
        self::pdo()->prepare('DELETE FROM blocks WHERE id = ? AND block_type = ?')->execute([(int) $row['id'], self::TYPE]);
        self::releasePaths($paths, (int) $row['id']);
        self::sweepUploads();

        return [];
    }

    public static function setActive(string $key, bool $active): void
    {
        $row = self::open($key);
        if ($row === null) {
            return;
        }
        self::pdo()->prepare('UPDATE blocks SET is_active = ? WHERE id = ? AND block_type = ?')
            ->execute([$active ? 1 : 0, (int) $row['id'], self::TYPE]);
    }

    public static function move(string $key, int $direction): void
    {
        if ($direction !== -1 && $direction !== 1) {
            return;
        }
        $rows = self::all();
        $index = null;
        foreach ($rows as $i => $row) {
            if ($row['key'] === $key) {
                $index = $i;
                break;
            }
        }
        if ($index === null || !isset($rows[$index + $direction])) {
            return;
        }
        $swap = $rows[$index];
        $rows[$index] = $rows[$index + $direction];
        $rows[$index + $direction] = $swap;
        $stmt = self::pdo()->prepare('UPDATE blocks SET sort_order = ? WHERE id = ? AND block_type = ?');
        $order = 10;
        foreach ($rows as $row) {
            $stmt->execute([$order, $row['id'], self::TYPE]);
            $order += 10;
        }
    }

    /** @param array<string, mixed> $file @return array{path:string,url:string}|array{error:string} */
    public static function storeInlineImage(array $file): array
    {
        $image = self::inspectRaster($file);
        if ($image === null) {
            return ['error' => 'Görsel seçilmedi.'];
        }
        if ($image === false) {
            return ['error' => 'Görsel PNG, JPEG, WebP veya GIF olmalı ve 8 MB altında kalmalı.'];
        }
        if (!ImageSet::ready()) {
            return ['error' => 'WebP dönüştürme şu an kullanılamıyor.'];
        }
        try {
            $stored = ImageSet::storeRaster($image['tmp'], $image['mime'], self::uploadFolder('shop'), 'urun-govde-' . bin2hex(random_bytes(4)));
        } catch (Throwable) {
            return ['error' => 'Görsel kaydedilemedi.'];
        }
        $path = (string) $stored['image'];

        return ['path' => $path, 'url' => root_path() . '/' . ltrim($path, '/')];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $content
     * @param list<string> $errors
     * @return list<array<string, mixed>>
     */
    private static function keptGallery(array $post, array $content, array &$errors): array
    {
        $ids = $post['gallery_id'] ?? [];
        $alts = $post['gallery_alt'] ?? [];
        if (!is_array($ids)) {
            return [];
        }
        if (!is_array($alts)) {
            $alts = [];
        }
        $known = [];
        foreach (is_array($content['gallery'] ?? null) ? $content['gallery'] : [] as $shot) {
            if (is_array($shot) && (string) ($shot['id'] ?? '') !== '') {
                $known[(string) $shot['id']] = $shot;
            }
        }
        $gallery = [];
        $seen = [];
        foreach (array_values($ids) as $index => $id) {
            $id = is_string($id) ? $id : '';
            if ($id === '' || !isset($known[$id]) || isset($seen[$id])) {
                $errors[] = 'Galeri sırası geçersiz.';

                return [];
            }
            $seen[$id] = true;
            $shot = $known[$id];
            $alt = $alts[$index] ?? '';
            $shot['alt'] = self::clip(is_string($alt) ? $alt : '', 140);
            $gallery[] = $shot;
        }

        return $gallery;
    }

    /**
     * @param list<string> $errors
     * @return list<array{tmp:string,mime:string}>
     */
    private static function uploads(mixed $bag, array &$errors): array
    {
        if (!is_array($bag) || !is_array($bag['name'] ?? null)) {
            return [];
        }
        $uploads = [];
        $invalid = false;
        foreach ($bag['name'] as $index => $name) {
            $file = [
                'name' => $name,
                'type' => is_array($bag['type'] ?? null) ? ($bag['type'][$index] ?? '') : '',
                'tmp_name' => is_array($bag['tmp_name'] ?? null) ? ($bag['tmp_name'][$index] ?? '') : '',
                'error' => is_array($bag['error'] ?? null) ? ($bag['error'][$index] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
                'size' => is_array($bag['size'] ?? null) ? ($bag['size'][$index] ?? 0) : 0,
            ];
            $image = self::inspectRaster($file);
            if ($image === null) {
                continue;
            }
            if ($image === false) {
                $invalid = true;
                continue;
            }
            $uploads[] = $image;
        }
        if ($invalid) {
            $errors[] = 'Görsel PNG, JPEG, WebP veya GIF olmalı ve 8 MB altında kalmalı.';
        }

        return $uploads;
    }

    private static function money(string $value): ?string
    {
        $value = str_replace([' ', '₺', 'TL', 'tl'], '', trim($value));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{1,3}(?:\.\d{3})+),(\d{1,2})$/', $value, $match) === 1) {
            $whole = str_replace('.', '', $match[1]);
            $fraction = str_pad($match[2], 2, '0');
        } elseif (preg_match('/^(\d{1,7}),(\d{1,2})$/', $value, $match) === 1 || preg_match('/^(\d{1,7})\.(\d{1,2})$/', $value, $match) === 1) {
            $whole = $match[1];
            $fraction = str_pad($match[2], 2, '0');
        } elseif (preg_match('/^(\d{1,7})$/', $value, $match) === 1) {
            $whole = $match[1];
            $fraction = '00';
        } else {
            return null;
        }
        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }
        if ($whole === '0' && $fraction === '00') {
            return null;
        }
        if (strlen($whole) > 7) {
            return null;
        }

        return $whole . '.' . $fraction;
    }

    private static function cents(string $amount): int
    {
        [$whole, $fraction] = explode('.', $amount);

        return ((int) $whole) * 100 + (int) $fraction;
    }

    private static function skuTaken(string $sku, int $exceptId): bool
    {
        $stmt = self::pdo()->prepare('SELECT id, content FROM blocks WHERE block_type = ? AND id <> ?');
        $stmt->execute([self::TYPE, $exceptId]);
        $needle = strtolower($sku);
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            if (is_array($content) && strtolower((string) ($content['sku'] ?? '')) === $needle) {
                return true;
            }
        }

        return false;
    }

    private static function keyTaken(string $key, int $exceptId): bool
    {
        $stmt = self::pdo()->prepare('SELECT id FROM blocks WHERE block_key = ? AND id <> ? LIMIT 1');
        $stmt->execute([$key, $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    private static function nextOrder(): int
    {
        $stmt = self::pdo()->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM blocks WHERE block_type = ?');
        $stmt->execute([self::TYPE]);

        return ((int) $stmt->fetchColumn()) + 10;
    }

    private static function slug(string $value): string
    {
        $value = strtr($value, [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'I' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u',
        ]);
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        if (strlen($value) > 64) {
            $value = rtrim(substr($value, 0, 64), '-');
        }

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1 ? $value : '';
    }

    private static function clip(string $value, int $limit): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }

        return substr($value, 0, $limit);
    }

    /** @return list<string> */
    private static function htmlPaths(string $html): array
    {
        $paths = [];
        if (preg_match_all('/\ssrc="([^"]+)"/', $html, $matches) < 1) {
            return [];
        }
        foreach ($matches[1] as $src) {
            $path = post_asset_path(html_entity_decode((string) $src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (!self::isManaged($path)) {
                continue;
            }
            foreach (self::siblings($path) as $sibling) {
                $paths[] = $sibling;
            }
        }

        return array_values(array_unique($paths));
    }

    /** @return list<string> */
    private static function siblings(string $path): array
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');
        $pattern = '#^(' . preg_quote($upload, '#') . '/shop/urun-(?:galeri|govde)-[a-f0-9]+)-\d+\.webp$#';
        if (preg_match($pattern, $path, $match) !== 1) {
            return [$path];
        }
        $directory = dirname(__DIR__) . '/' . dirname($match[1]);
        $prefix = basename($match[1]);
        $paths = [];
        $files = glob($directory . '/' . $prefix . '-*.webp');
        if (is_array($files)) {
            foreach ($files as $file) {
                $name = basename($file);
                if (preg_match('/^' . preg_quote($prefix, '/') . '-\d+\.webp$/', $name) === 1) {
                    $paths[] = dirname($match[1]) . '/' . $name;
                }
            }
        }

        return $paths === [] ? [$path] : $paths;
    }

    /** @param array<string, mixed> $content @return list<string> */
    private static function imagePaths(array $content): array
    {
        $paths = [];
        foreach (is_array($content['gallery'] ?? null) ? $content['gallery'] : [] as $shot) {
            if (!is_array($shot)) {
                continue;
            }
            $image = (string) ($shot['image'] ?? '');
            if (self::isManaged($image)) {
                $paths[] = $image;
            }
            foreach (is_array($shot['sources'] ?? null) ? $shot['sources'] : [] as $source) {
                if (is_array($source) && self::isManaged((string) ($source['path'] ?? ''))) {
                    $paths[] = (string) $source['path'];
                }
            }
        }
        foreach (is_array($content['inline_sources'] ?? null) ? $content['inline_sources'] : [] as $path) {
            if (is_string($path) && self::isManaged($path)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /** @param list<string> $paths */
    private static function releasePaths(array $paths, int $exceptId): void
    {
        if ($paths === []) {
            return;
        }
        $stmt = self::pdo()->prepare('SELECT content FROM blocks WHERE id <> ?');
        $stmt->execute([$exceptId]);
        $held = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $raw = (string) ($row['content'] ?? '');
            foreach ($paths as $path) {
                if ($path !== '' && str_contains($raw, $path)) {
                    $held[] = $path;
                }
            }
        }
        ImageSet::deletePaths(array_values(array_diff($paths, $held)));
    }

    private static function sweepUploads(): void
    {
        ImageSet::deleteUnreferenced(self::uploadFolder('shop'), '/^urun-(?:galeri|govde)-[a-f0-9]+-\d+\.webp$/');
    }

    /** @return array{tmp:string,mime:string}|false|null */
    private static function inspectRaster(mixed $file): array|false|null
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return false;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || $size < 1 || $size > 8_000_000 || !is_uploaded_file($tmp)) {
            return false;
        }
        $mime = '';
        if (class_exists(finfo::class)) {
            $info = new finfo(FILEINFO_MIME_TYPE);
            $detected = $info->file($tmp);
            $mime = is_string($detected) ? $detected : '';
        }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return false;
        }

        return ['tmp' => $tmp, 'mime' => $mime];
    }

    private static function isManaged(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');

        return $path !== '' && $upload !== '' && str_starts_with($path, $upload . '/');
    }

    private static function uploadFolder(string $name): string
    {
        if (!preg_match('/^[a-z]+$/', $name)) {
            $name = 'media';
        }
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');
        if ($upload === '' || str_contains($upload, '..')) {
            $upload = 'assets/uploads';
        }
        $relative = $upload . '/' . $name;
        $full = dirname(__DIR__) . '/' . $relative;
        if (!is_dir($full) && !mkdir($full, 0755, true) && !is_dir($full)) {
            throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
        }

        return $relative;
    }

    /**
     * Stok düşer. Çağıran taraf aynı bağlantıda transaction açmış olmalı.
     *
     * @return array{title:string,unit_price:string,image:string,tracked:bool}|null
     */
    public static function holdStock(PDO $pdo, string $key, int $qty): ?array
    {
        if ($qty < 1 || $qty > 99 || !preg_match('/^[a-z0-9\-]+$/', $key)) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT id, title, is_active, content
             FROM blocks
             WHERE block_type = ? AND block_key = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([self::TYPE, $key]);
        $row = $stmt->fetch();
        if (!is_array($row) || (int) $row['is_active'] !== 1) {
            return null;
        }
        $content = decode_value((string) ($row['content'] ?? ''));
        $content = is_array($content) ? $content : [];
        $discount = (string) ($content['discount_price'] ?? '');
        $price = $discount !== '' ? $discount : (string) ($content['price'] ?? '');
        if (preg_match('/^\d+\.\d{2}$/', $price) !== 1 || $price === '0.00') {
            return null;
        }
        $tracked = !empty($content['stock_track']);
        if ($tracked) {
            $stock = (int) ($content['stock_qty'] ?? 0);
            if ($stock < $qty) {
                return null;
            }
            $content['stock_qty'] = $stock - $qty;
            $update = $pdo->prepare('UPDATE blocks SET content = ? WHERE id = ? AND block_type = ?');
            $update->execute([encode_value($content), (int) $row['id'], self::TYPE]);
        }

        return [
            'title' => (string) $row['title'],
            'unit_price' => $price,
            'image' => (string) ($content['image'] ?? ''),
            'tracked' => $tracked,
        ];
    }

    public static function releaseStock(PDO $pdo, string $key, int $qty): void
    {
        if ($qty < 1 || !preg_match('/^[a-z0-9\-]+$/', $key)) {
            return;
        }
        $stmt = $pdo->prepare(
            'SELECT id, content FROM blocks WHERE block_type = ? AND block_key = ? LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([self::TYPE, $key]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return;
        }
        $content = decode_value((string) ($row['content'] ?? ''));
        $content = is_array($content) ? $content : [];
        if (empty($content['stock_track'])) {
            return;
        }
        $stock = (int) ($content['stock_qty'] ?? 0) + $qty;
        if ($stock > 999999) {
            $stock = 999999;
        }
        $content['stock_qty'] = $stock;
        $update = $pdo->prepare('UPDATE blocks SET content = ? WHERE id = ? AND block_type = ?');
        $update->execute([encode_value($content), (int) $row['id'], self::TYPE]);
    }

    private static function pdo(): PDO
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Veritabanına bağlanılamadı.');
        }

        return $pdo;
    }
}
