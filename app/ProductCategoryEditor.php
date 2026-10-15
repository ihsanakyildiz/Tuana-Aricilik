<?php

declare(strict_types=1);

require_once __DIR__ . '/ImageSet.php';

final class ProductCategoryEditor
{
    private const TYPE = 'product_category';

    /** @var list<string> */
    private const RESERVED = [
        'home', 'blog', 'admin', 'yazi', 'sayfa', 'sitemap', 'robots', 'assets',
        'urun', 'urunler', 'magaza', 'sepet', 'siparis', 'siparisler', 'kategori', 'kategoriler',
        'uye-giris', 'uye-ol', 'hesabim', 'uye-cikis', 'uyeler',
    ];

    /** @return list<array{id:int,key:string,title:string,active:bool,products:int,image:string,description:string,updated_at:string}> */
    public static function all(): array
    {
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
            $key = (string) $row['block_key'];
            $content = decode_value((string) ($row['content'] ?? ''));
            $content = is_array($content) ? $content : [];
            $rows[] = [
                'id' => (int) $row['id'],
                'key' => $key,
                'title' => (string) $row['title'],
                'active' => (int) $row['is_active'] === 1,
                'products' => self::usage($key),
                'image' => (string) ($content['image'] ?? ''),
                'description' => (string) ($content['description'] ?? ''),
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

        return [
            'id' => (int) $row['id'],
            'key' => (string) $row['block_key'],
            'title' => (string) $row['title'],
            'active' => (int) $row['is_active'] === 1,
            'description' => (string) ($content['description'] ?? ''),
            'image' => (string) ($content['image'] ?? ''),
            'image_alt' => (string) ($content['image_alt'] ?? ''),
            'content' => $content,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function save(array $post, array $files = []): array
    {
        $original = is_string($post['category_key'] ?? null) ? $post['category_key'] : '';
        $current = $original !== '' ? self::open($original) : null;
        $title = self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80);
        $slugInput = is_string($post['slug'] ?? null) ? $post['slug'] : '';
        $slug = self::slug($slugInput !== '' ? $slugInput : $title);
        $values = [
            'title' => $title,
            'slug' => $slugInput,
            'active' => isset($post['active']),
            'key' => $original,
            'description' => self::clip(is_string($post['description'] ?? null) ? $post['description'] : '', 300),
            'image_alt' => self::clip(is_string($post['image_alt'] ?? null) ? $post['image_alt'] : '', 140),
        ];
        if ($original !== '' && $current === null) {
            return ['errors' => ['Kategori bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($title === '') {
            $errors[] = 'Kategori adı boş olamaz.';
        }
        if ($slug === '') {
            $errors[] = 'Adres yalnızca küçük harf, rakam ve tire olabilir.';
        } elseif (in_array($slug, self::RESERVED, true)) {
            $errors[] = 'Bu adres mağaza sayfalarına ayrılmış.';
        } elseif (self::keyTaken($slug, $current === null ? 0 : (int) $current['id'])) {
            $errors[] = 'Bu adres başka bir kayıtta kullanılıyor.';
        }
        $imageFile = self::inspectRaster($files['image'] ?? null);
        if ($imageFile === false) {
            $errors[] = 'Görsel PNG, JPEG, WebP veya GIF olmalı ve 8 MB altında kalmalı.';
        }
        if (is_array($imageFile) && !ImageSet::ready()) {
            $errors[] = 'WebP dönüştürme şu an kullanılamıyor.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $content = is_array($current['content'] ?? null) ? $current['content'] : [];
        $previous = self::imagePaths($content);
        $fresh = [];
        $savedId = $current === null ? 0 : (int) $current['id'];
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('shop'), 'urun-kategori-' . bin2hex(random_bytes(4)));
                $fresh = array_column($stored['sources'], 'path');
                $content['image'] = $stored['image'];
                $content['image_sources'] = $stored['sources'];
            } elseif (!empty($post['remove_image']) && self::isManaged((string) ($content['image'] ?? ''))) {
                $content['image'] = '';
                $content['image_sources'] = [];
            }
            $content['description'] = $values['description'];
            $content['image_alt'] = $values['image_alt'];
            unset($content['_key'], $content['_type'], $content['_updated']);
            if ($current === null) {
                $insert = $pdo->prepare(
                    'INSERT INTO blocks (block_key, block_type, title, sort_order, is_active, content) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$slug, self::TYPE, $title, self::nextOrder(), $values['active'] ? 1 : 0, encode_value($content)]);
                $savedId = (int) $pdo->lastInsertId();
            } else {
                $update = $pdo->prepare(
                    'UPDATE blocks SET block_key = ?, title = ?, is_active = ?, content = ? WHERE id = ? AND block_type = ?'
                );
                $update->execute([$slug, $title, $values['active'] ? 1 : 0, encode_value($content), (int) $current['id'], self::TYPE]);
                if ((string) $current['key'] !== $slug) {
                    self::retagProducts($pdo, (string) $current['key'], $slug);
                }
            }
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Kategori kaydedilemedi.'], 'values' => $values];
        }
        if ($fresh !== [] || !empty($post['remove_image'])) {
            self::releasePaths($previous, $savedId);
        }

        return ['errors' => [], 'values' => $values];
    }

    /** @return list<string> */
    public static function remove(string $key): array
    {
        $row = self::open($key);
        if ($row === null) {
            return ['Kategori bulunamadı.'];
        }
        $used = self::usage($key);
        if ($used > 0) {
            return ['Bu kategoride ' . $used . ' ürün var. Silmeden önce o ürünlerin kategorisini değiştir.'];
        }
        $paths = self::imagePaths(is_array($row['content'] ?? null) ? $row['content'] : []);
        self::pdo()->prepare('DELETE FROM blocks WHERE id = ? AND block_type = ?')->execute([(int) $row['id'], self::TYPE]);
        self::releasePaths($paths, (int) $row['id']);

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

    public static function usage(string $key): int
    {
        if ($key === '') {
            return 0;
        }
        $count = 0;
        $stmt = self::pdo()->query("SELECT content FROM blocks WHERE block_type = 'product'");
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            if (is_array($content) && (string) ($content['category_key'] ?? '') === $key) {
                $count++;
            }
        }

        return $count;
    }

    private static function retagProducts(PDO $pdo, string $oldKey, string $newKey): void
    {
        if ($oldKey === '' || $oldKey === $newKey) {
            return;
        }
        $update = $pdo->prepare('UPDATE blocks SET content = ? WHERE id = ? AND block_type = ?');
        $stmt = $pdo->query("SELECT id, content FROM blocks WHERE block_type = 'product'");
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            if (!is_array($content) || (string) ($content['category_key'] ?? '') !== $oldKey) {
                continue;
            }
            $content['category_key'] = $newKey;
            $update->execute([encode_value($content), (int) $row['id'], 'product']);
        }
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

    /** @param array<string, mixed> $content @return list<string> */
    private static function imagePaths(array $content): array
    {
        $paths = [];
        $image = (string) ($content['image'] ?? '');
        if (self::isManaged($image)) {
            $paths[] = $image;
        }
        $sources = $content['image_sources'] ?? [];
        if (is_array($sources)) {
            foreach ($sources as $source) {
                if (is_array($source) && self::isManaged((string) ($source['path'] ?? ''))) {
                    $paths[] = (string) $source['path'];
                }
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

    private static function pdo(): PDO
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Veritabanına bağlanılamadı.');
        }

        return $pdo;
    }
}
