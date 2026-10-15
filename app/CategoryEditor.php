<?php

declare(strict_types=1);

require_once __DIR__ . '/ImageSet.php';

final class CategoryEditor
{
    /** @return list<array{id:int,key:string,title:string,active:bool,posts:int}> */
    public static function all(): array
    {
        $stmt = self::pdo()->query(
            "SELECT id, block_key, title, is_active, content
             FROM blocks
             WHERE block_type = 'category'
             ORDER BY sort_order ASC, id ASC"
        );
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
                'index' => !array_key_exists('index', $content) || !empty($content['index']),
                'posts' => self::usage($key),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        $stmt = self::pdo()->prepare(
            "SELECT id, block_key, title, is_active, content, updated_at
             FROM blocks
             WHERE block_type = 'category' AND block_key = ?
             LIMIT 1"
        );
        $stmt->execute([$key]);
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
            'updated' => (string) ($row['updated_at'] ?? ''),
            'image' => (string) ($content['image'] ?? ''),
            'image_alt' => (string) ($content['image_alt'] ?? ''),
            'seo_title' => (string) ($content['seo_title'] ?? ''),
            'seo_description' => (string) ($content['seo_description'] ?? ''),
            'index' => !array_key_exists('index', $content) || !empty($content['index']),
            'content' => $content,
        ];
    }

    /** @return array<string, mixed>|null */
    public static function open(string $key): ?array
    {
        return self::find($key);
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function save(array $post, array $files = []): array
    {
        $original = is_string($post['category_key'] ?? null) ? $post['category_key'] : '';
        $current = $original !== '' ? self::find($original) : null;
        $title = self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 48);
        $slugInput = is_string($post['slug'] ?? null) ? $post['slug'] : '';
        $slug = self::slug($slugInput !== '' ? $slugInput : $title);
        $active = isset($post['active']);
        $values = [
            'title' => $title,
            'slug' => $slugInput,
            'active' => $active,
            'key' => $original,
            'seo_title' => self::clip(is_string($post['seo_title'] ?? null) ? $post['seo_title'] : '', 70),
            'seo_description' => self::clip(is_string($post['seo_description'] ?? null) ? $post['seo_description'] : '', 160),
            'image_alt' => self::clip(is_string($post['image_alt'] ?? null) ? $post['image_alt'] : '', 140),
            'index' => isset($post['index']),
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
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('categories'), 'kategori-' . bin2hex(random_bytes(4)));
                $fresh = array_column($stored['sources'], 'path');
                $content['image'] = $stored['image'];
                $content['image_sources'] = $stored['sources'];
            } elseif (!empty($post['remove_image']) && self::isManaged((string) ($content['image'] ?? ''))) {
                $content['image'] = '';
                $content['image_sources'] = [];
            }
            $content['image_alt'] = $values['image_alt'];
            $content['seo_title'] = $values['seo_title'];
            $content['seo_description'] = $values['seo_description'];
            $content['index'] = $values['index'];
            if ($current === null) {
                $insert = $pdo->prepare(
                    'INSERT INTO blocks (block_key, block_type, title, sort_order, is_active, content) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$slug, 'category', $title, self::nextOrder(), $active ? 1 : 0, encode_value($content)]);
                $savedId = (int) $pdo->lastInsertId();
            } else {
                $savedId = (int) $current['id'];
                $update = $pdo->prepare(
                    'UPDATE blocks SET block_key = ?, title = ?, is_active = ?, content = ? WHERE id = ? AND block_type = ?'
                );
                $update->execute([$slug, $title, $active ? 1 : 0, encode_value($content), $savedId, 'category']);
                if ((string) $current['key'] !== $slug || (string) $current['title'] !== $title) {
                    self::retagPosts($pdo, (string) $current['key'], $slug, $title);
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
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
            return ['Bu kategoride ' . $used . ' yazı var. Silmeden önce o yazıların kategorisini değiştir.'];
        }
        $paths = self::imagePaths(is_array($row['content'] ?? null) ? $row['content'] : []);
        self::pdo()->prepare("DELETE FROM blocks WHERE id = ? AND block_type = 'category'")->execute([$row['id']]);
        self::releasePaths($paths, (int) $row['id']);

        return [];
    }

    public static function setActive(string $key, bool $active): void
    {
        $row = self::open($key);
        if ($row === null) {
            return;
        }
        self::pdo()->prepare("UPDATE blocks SET is_active = ? WHERE id = ? AND block_type = 'category'")
            ->execute([$active ? 1 : 0, $row['id']]);
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
        $stmt = self::pdo()->prepare("UPDATE blocks SET sort_order = ? WHERE id = ? AND block_type = 'category'");
        $order = 10;
        foreach ($rows as $row) {
            $stmt->execute([$order, $row['id']]);
            $order += 10;
        }
    }

    public static function usage(string $key): int
    {
        if ($key === '') {
            return 0;
        }
        $count = 0;
        $stmt = self::pdo()->query("SELECT content FROM blocks WHERE block_type = 'post'");
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

    private static function retagPosts(PDO $pdo, string $oldKey, string $newKey, string $label): void
    {
        $update = $pdo->prepare('UPDATE blocks SET content = ? WHERE id = ? AND block_type = ?');
        $stmt = $pdo->query("SELECT id, content FROM blocks WHERE block_type = 'post'");
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            if (!is_array($content) || (string) ($content['category_key'] ?? '') !== $oldKey) {
                continue;
            }
            $content['category_key'] = $newKey;
            $content['category'] = $label;
            $update->execute([encode_value($content), (int) $row['id'], 'post']);
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
        $value = self::pdo()->query("SELECT COALESCE(MAX(sort_order), 0) FROM blocks WHERE block_type = 'category'")->fetchColumn();

        return ((int) $value) + 10;
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
        $stmt = self::pdo()->prepare('SELECT id, content FROM blocks WHERE id <> ?');
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
