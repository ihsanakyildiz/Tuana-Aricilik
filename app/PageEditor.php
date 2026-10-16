<?php

declare(strict_types=1);

require_once __DIR__ . '/ImageSet.php';
require_once __DIR__ . '/SectionEditor.php';

final class PageEditor
{
    /** @return list<array{key:string,title:string,url:string,active:bool,kind:string}> */
    public static function designed(): array
    {
        $stmt = self::pdo()->query(
            "SELECT page_slug, section_type, title, is_active, content
             FROM sections
             WHERE page_slug <> 'home'
             ORDER BY page_slug ASC, sort_order ASC, id ASC"
        );
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = (string) $row['page_slug'];
            if (!isset($pages[$slug])) {
                $pages[$slug] = [
                    'key' => $slug,
                    'title' => $slug,
                    'url' => $slug === 'blog' ? '/blog' : '/' . $slug,
                    'active' => false,
                    'kind' => 'designed',
                ];
            }
            if ((int) $row['is_active'] === 1) {
                $pages[$slug]['active'] = true;
            }
            if ((string) $row['section_type'] !== 'pagehead') {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            $title = is_array($content) ? trim((string) ($content['title'] ?? '')) : '';
            if ($title === '') {
                $title = trim((string) $row['title']);
            }
            if ($title !== '') {
                $pages[$slug]['title'] = $title;
            }
        }

        return array_values($pages);
    }

    /** @return list<array{id:int,key:string,title:string,active:bool,index:bool,image:string,kind:string}> */
    public static function all(): array
    {
        $stmt = self::pdo()->query(
            "SELECT id, block_key, title, is_active, content
             FROM blocks
             WHERE block_type = 'page'
             ORDER BY sort_order ASC, id ASC"
        );
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            $content = is_array($content) ? $content : [];
            $rows[] = [
                'id' => (int) $row['id'],
                'key' => (string) $row['block_key'],
                'title' => (string) $row['title'],
                'active' => (int) $row['is_active'] === 1,
                'index' => post_index_enabled($content),
                'image' => (string) ($content['image'] ?? ''),
                'kind' => (string) ($content['kind'] ?? 'classic'),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        $stmt = self::pdo()->prepare(
            "SELECT id, block_key, title, is_active, content
             FROM blocks
             WHERE block_type = 'page' AND block_key = ?
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
            'image' => (string) ($content['image'] ?? ''),
            'image_alt' => (string) ($content['image_alt'] ?? ''),
            'excerpt' => (string) ($content['excerpt'] ?? ''),
            'body_html' => (string) ($content['body_html'] ?? ''),
            'seo_title' => (string) ($content['seo_title'] ?? ''),
            'seo_description' => (string) ($content['seo_description'] ?? ''),
            'index' => post_index_enabled($content),
            'kind' => (string) ($content['kind'] ?? 'classic'),
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
        $original = is_string($post['page_key'] ?? null) ? $post['page_key'] : '';
        $current = $original !== '' ? self::find($original) : null;
        $title = self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 140);
        $slugInput = is_string($post['slug'] ?? null) ? $post['slug'] : '';
        $slug = self::slug($slugInput !== '' ? $slugInput : $title);
        $status = ($post['status'] ?? '') === 'publish' ? 'publish' : 'draft';
        $values = [
            'title' => $title,
            'slug' => $slugInput !== '' ? $slugInput : $slug,
            'status' => $status,
            'excerpt' => self::clip(is_string($post['excerpt'] ?? null) ? $post['excerpt'] : '', 300),
            'body' => is_string($post['body'] ?? null) ? $post['body'] : '',
            'image_alt' => self::clip(is_string($post['image_alt'] ?? null) ? $post['image_alt'] : '', 140),
            'seo_title' => self::clip(is_string($post['seo_title'] ?? null) ? $post['seo_title'] : '', 70),
            'seo_description' => self::clip(is_string($post['seo_description'] ?? null) ? $post['seo_description'] : '', 160),
            'index' => isset($post['index']),
            'key' => $original,
        ];
        if ($original !== '' && $current === null) {
            return ['errors' => ['Sayfa bulunamadı.'], 'values' => $values, 'key' => $original];
        }
        if ($current !== null && ($current['kind'] ?? '') === 'advanced') {
            return ['errors' => ['Gelişmiş sayfa kendi düzenleyicisinden kaydedilir.'], 'values' => $values, 'key' => $original];
        }

        $errors = [];
        if ($title === '') {
            $errors[] = 'Başlık boş olamaz.';
        }
        if ($slug === '') {
            $errors[] = 'Adres yalnızca küçük harf, rakam ve tire olabilir.';
        } elseif (self::reserved($slug)) {
            $errors[] = 'Bu adres site sayfalarına ayrılmış.';
        } elseif (self::keyTaken($slug, $current === null ? 0 : (int) $current['id'])) {
            $errors[] = 'Bu adres başka bir kayıtta kullanılıyor.';
        }
        $prepared = self::prepareBody((string) $values['body'], $errors);
        $imageFile = self::inspectRaster($files['image'] ?? null);
        if ($imageFile === false) {
            $errors[] = 'Görsel PNG, JPEG, WebP veya GIF olmalı ve 8 MB altında kalmalı.';
        }
        if (is_array($imageFile) && !ImageSet::ready()) {
            $errors[] = 'WebP dönüştürme şu an kullanılamıyor.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values, 'key' => $original];
        }

        $content = is_array($current['content'] ?? null) ? $current['content'] : [];
        $previous = self::imagePaths($content);
        $fresh = [];
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('pages'), 'kapak-' . bin2hex(random_bytes(4)));
                foreach ($stored['sources'] as $source) {
                    $fresh[] = (string) $source['path'];
                }
                $content['image'] = $stored['image'];
                $content['image_sources'] = $stored['sources'];
            } elseif (!empty($post['remove_image']) && self::isManaged((string) ($content['image'] ?? ''))) {
                $content['image'] = '';
                $content['image_sources'] = [];
            }
            $content['kind'] = 'classic';
            $content['title'] = $title;
            $content['excerpt'] = $values['excerpt'];
            $content['body'] = $prepared['paragraphs'];
            $content['body_html'] = $prepared['html'];
            $content['image_alt'] = $values['image_alt'];
            $content['seo_title'] = $values['seo_title'];
            $content['seo_description'] = $values['seo_description'];
            $content['index'] = $values['index'];
            $content['url'] = '/' . $slug;
            $content['aliases'] = self::aliases($content, $current === null ? '' : (string) $current['key'], $slug);
            unset($content['_key'], $content['_type'], $content['_updated']);
            if ($current === null) {
                $insert = $pdo->prepare(
                    'INSERT INTO blocks (block_key, block_type, title, sort_order, is_active, content) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$slug, 'page', $title, self::nextOrder(), $status === 'publish' ? 1 : 0, encode_value($content)]);
                $savedId = (int) $pdo->lastInsertId();
            } else {
                $savedId = (int) $current['id'];
                $update = $pdo->prepare(
                    'UPDATE blocks SET block_key = ?, title = ?, is_active = ?, content = ? WHERE id = ? AND block_type = ?'
                );
                $update->execute([$slug, $title, $status === 'publish' ? 1 : 0, encode_value($content), $savedId, 'page']);
            }
            self::claimSlug($pdo, $slug, $savedId);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Sayfa kaydedilemedi.'], 'values' => $values, 'key' => $original];
        }
        self::releasePaths(array_values(array_diff($previous, self::imagePaths($content))), $savedId);
        self::sweepUploads();

        return ['errors' => [], 'values' => $values, 'key' => $slug];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{errors:list<string>,values:array<string,mixed>,key:string}
     */
    public static function saveAdvanced(array $post): array
    {
        $original = is_string($post['page_key'] ?? null) ? $post['page_key'] : '';
        $current = $original !== '' ? self::find($original) : null;
        $title = self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 140);
        $slugInput = is_string($post['slug'] ?? null) ? $post['slug'] : '';
        $slug = self::slug($slugInput !== '' ? $slugInput : $title);
        $status = ($post['status'] ?? '') === 'publish' ? 'publish' : 'draft';
        $values = [
            'title' => $title,
            'slug' => $slugInput !== '' ? $slugInput : $slug,
            'status' => $status,
            'excerpt' => self::clip(is_string($post['excerpt'] ?? null) ? $post['excerpt'] : '', 300),
            'seo_title' => self::clip(is_string($post['seo_title'] ?? null) ? $post['seo_title'] : '', 70),
            'seo_description' => self::clip(is_string($post['seo_description'] ?? null) ? $post['seo_description'] : '', 160),
            'index' => isset($post['index']),
            'key' => $original,
        ];
        if ($original !== '' && $current === null) {
            return ['errors' => ['Sayfa bulunamadı.'], 'values' => $values, 'key' => $original];
        }
        if ($current !== null && ($current['kind'] ?? '') !== 'advanced') {
            return ['errors' => ['Bu kayıt klasik sayfa.'], 'values' => $values, 'key' => $original];
        }

        $errors = [];
        if ($title === '') {
            $errors[] = 'Başlık boş olamaz.';
        }
        $ownsSlug = $current !== null && (string) $current['key'] === $slug;
        if ($slug === '') {
            $errors[] = 'Adres yalnızca küçük harf, rakam ve tire olabilir.';
        } elseif (!$ownsSlug && self::reserved($slug)) {
            $errors[] = 'Bu adres site sayfalarına ayrılmış.';
        } elseif (self::keyTaken($slug, $current === null ? 0 : (int) $current['id'])) {
            $errors[] = 'Bu adres başka bir kayıtta kullanılıyor.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values, 'key' => $original];
        }

        $content = is_array($current['content'] ?? null) ? $current['content'] : [];
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $content['kind'] = 'advanced';
            $content['title'] = $title;
            $content['excerpt'] = $values['excerpt'];
            $content['seo_title'] = $values['seo_title'];
            $content['seo_description'] = $values['seo_description'];
            $content['index'] = $values['index'];
            $content['url'] = '/' . $slug;
            $content['aliases'] = self::aliases($content, $current === null ? '' : (string) $current['key'], $slug);
            unset($content['body'], $content['body_html'], $content['_key'], $content['_type'], $content['_updated']);
            if ($current === null) {
                $insert = $pdo->prepare(
                    'INSERT INTO blocks (block_key, block_type, title, sort_order, is_active, content) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$slug, 'page', $title, self::nextOrder(), $status === 'publish' ? 1 : 0, encode_value($content)]);
                $savedId = (int) $pdo->lastInsertId();
            } else {
                $savedId = (int) $current['id'];
                if ((string) $current['key'] !== $slug) {
                    $pdo->prepare('UPDATE sections SET page_slug = ? WHERE page_slug = ?')->execute([$slug, $current['key']]);
                }
                $update = $pdo->prepare(
                    'UPDATE blocks SET block_key = ?, title = ?, is_active = ?, content = ? WHERE id = ? AND block_type = ?'
                );
                $update->execute([$slug, $title, $status === 'publish' ? 1 : 0, encode_value($content), $savedId, 'page']);
            }
            self::claimSlug($pdo, $slug, $savedId);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['errors' => ['Sayfa kaydedilemedi.'], 'values' => $values, 'key' => $original];
        }

        if ($current === null) {
            SectionEditor::usePage($slug);
            SectionEditor::create('pagehead', $title);
        }

        return ['errors' => [], 'values' => $values, 'key' => $slug];
    }

    /** @return list<string> */
    public static function remove(string $key): array
    {
        $row = self::find($key);
        if ($row === null) {
            return ['Sayfa bulunamadı.'];
        }
        if (($row['kind'] ?? '') === 'advanced') {
            SectionEditor::usePage($key);
            foreach (array_reverse(SectionEditor::rows()) as $section) {
                SectionEditor::remove((int) $section['id']);
            }
        }
        $paths = self::imagePaths(is_array($row['content'] ?? null) ? $row['content'] : []);
        self::pdo()->prepare("DELETE FROM blocks WHERE id = ? AND block_type = 'page'")->execute([$row['id']]);
        self::releasePaths($paths, (int) $row['id']);
        self::sweepUploads();

        return [];
    }

    public static function setActive(string $key, bool $active): void
    {
        $row = self::find($key);
        if ($row === null) {
            return;
        }
        self::pdo()->prepare("UPDATE blocks SET is_active = ? WHERE id = ? AND block_type = 'page'")
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
        $stmt = self::pdo()->prepare("UPDATE blocks SET sort_order = ? WHERE id = ? AND block_type = 'page'");
        $order = 10;
        foreach ($rows as $row) {
            $stmt->execute([$order, $row['id']]);
            $order += 10;
        }
    }

    /** @param array<string, mixed> $record */
    public static function editorHtml(array $record): string
    {
        $html = trim((string) ($record['body_html'] ?? ''));
        if ($html === '') {
            return '';
        }

        return self::editorImageUrls($html);
    }

    /** @param array{error?:string,tmp?:string,mime?:string}|array<string, mixed> $file @return array{error?:string,url?:string,path?:string} */
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
            $stored = ImageSet::storeRaster($image['tmp'], $image['mime'], self::uploadFolder('pages'), 'sayfa-' . bin2hex(random_bytes(4)));
        } catch (Throwable) {
            return ['error' => 'Görsel kaydedilemedi.'];
        }
        $path = (string) $stored['image'];

        return ['path' => $path, 'url' => root_path() . '/' . ltrim($path, '/')];
    }

    /**
     * @param array<string, mixed> $content
     * @return list<string>
     */
    private static function aliases(array $content, string $oldKey, string $slug): array
    {
        $aliases = [];
        if ($oldKey !== '' && $oldKey !== $slug) {
            $aliases[] = $oldKey;
        }
        foreach ($content['aliases'] ?? [] as $alias) {
            $alias = self::slug((string) $alias);
            if ($alias !== '' && $alias !== $slug && !in_array($alias, $aliases, true)) {
                $aliases[] = $alias;
            }
        }

        return array_slice($aliases, 0, 8);
    }

    private static function claimSlug(PDO $pdo, string $slug, int $ownerId): void
    {
        $stmt = $pdo->query("SELECT id, content FROM blocks WHERE block_type = 'page'");
        $update = $pdo->prepare('UPDATE blocks SET content = ? WHERE id = ? AND block_type = ?');
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row) || (int) $row['id'] === $ownerId) {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            if (!is_array($content) || !is_array($content['aliases'] ?? null)) {
                continue;
            }
            $aliases = [];
            $changed = false;
            foreach ($content['aliases'] as $alias) {
                if ((string) $alias === $slug) {
                    $changed = true;
                    continue;
                }
                $aliases[] = (string) $alias;
            }
            if (!$changed) {
                continue;
            }
            $content['aliases'] = $aliases;
            $update->execute([encode_value($content), (int) $row['id'], 'page']);
        }
    }

    /** @param list<string> $errors @return array{html:string,paragraphs:list<string>} */
    private static function prepareBody(string $raw, array &$errors): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['html' => '', 'paragraphs' => []];
        }
        if (strlen($raw) > 80000) {
            $errors[] = 'Sayfa çok uzun.';

            return ['html' => '', 'paragraphs' => []];
        }
        if (preg_match('/<[a-z!\/][^>]*>/i', $raw) !== 1) {
            $paragraphs = [];
            $html = '';
            $chunks = preg_split("/\n{2,}/", str_replace(["\r\n", "\r"], "\n", $raw)) ?: [];
            foreach ($chunks as $chunk) {
                $text = trim(preg_replace('/[ \t]*\n[ \t]*/', ' ', $chunk) ?? '');
                if ($text === '') {
                    continue;
                }
                $text = self::clip($text, 1500);
                $paragraphs[] = $text;
                $html .= '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
                if (count($paragraphs) >= 80) {
                    break;
                }
            }

            return ['html' => $html, 'paragraphs' => $paragraphs];
        }
        $html = HtmlSanitizer::clean($raw);
        $visible = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($visible !== '' && $html === '') {
            $errors[] = 'İçerik kayda uygun değil.';
        }

        return ['html' => $html, 'paragraphs' => self::textBlocks($html)];
    }

    /** @return list<string> */
    private static function textBlocks(string $html): array
    {
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|h[1-6]|li|blockquote|tr|figcaption)>/i', "\n\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $chunks = preg_split("/\n{2,}/", str_replace("\r", '', $text)) ?: [];
        $blocks = [];
        foreach ($chunks as $chunk) {
            $line = trim(preg_replace('/\s+/u', ' ', $chunk) ?? '');
            if ($line === '') {
                continue;
            }
            $blocks[] = self::clip($line, 1500);
            if (count($blocks) >= 80) {
                break;
            }
        }

        return $blocks;
    }

    private static function editorImageUrls(string $html): string
    {
        $updated = preg_replace_callback(
            '/\ssrc="([^"]*)"/',
            static function (array $match): string {
                $path = post_asset_path(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($path === '' || str_starts_with($path, 'https://')) {
                    return $match[0];
                }

                return ' src="' . htmlspecialchars(root_path() . '/' . $path, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );

        return is_string($updated) ? $updated : $html;
    }

    private static function reserved(string $slug): bool
    {
        $fixed = ['home', 'blog', 'admin', 'yazi', 'sayfa', 'sitemap', 'robots', 'assets', 'app', 'config', 'data', 'sql', 'storage', 'onizleme', 'index', 'giris', 'kategori', 'uye-giris', 'uye-ol', 'hesabim', 'uye-cikis', 'uyeler', 'iletisim'];
        if (in_array($slug, $fixed, true)) {
            return true;
        }
        $stmt = self::pdo()->prepare('SELECT id FROM sections WHERE page_slug = ? LIMIT 1');
        $stmt->execute([$slug]);

        return $stmt->fetchColumn() !== false;
    }

    private static function keyTaken(string $key, int $exceptId): bool
    {
        $stmt = self::pdo()->prepare('SELECT id FROM blocks WHERE block_key = ? AND id <> ? LIMIT 1');
        $stmt->execute([$key, $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    private static function nextOrder(): int
    {
        $value = self::pdo()->query("SELECT COALESCE(MIN(sort_order), 10) FROM blocks WHERE block_type = 'page'")->fetchColumn();

        return ((int) $value) - 10;
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
            foreach (self::imageFamily($image) as $item) {
                $paths[] = $item;
            }
        }
        $sources = $content['image_sources'] ?? [];
        if (is_array($sources)) {
            foreach ($sources as $item) {
                if (is_array($item) && self::isManaged((string) ($item['path'] ?? ''))) {
                    $paths[] = (string) $item['path'];
                }
            }
        }
        if (preg_match_all('/\ssrc="([^"]+)"/', (string) ($content['body_html'] ?? ''), $matches) > 0) {
            foreach ($matches[1] as $src) {
                $path = post_asset_path(html_entity_decode((string) $src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (!self::isManaged($path)) {
                    continue;
                }
                foreach (self::imageFamily($path) as $item) {
                    $paths[] = $item;
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

    /** @return list<string> */
    private static function imageFamily(string $path): array
    {
        return ImageSet::family($path);
    }

    private static function sweepUploads(): void
    {
        ImageSet::deleteUnreferenced(self::uploadFolder('pages'), '/^(?:sayfa|kapak)-[a-f0-9]+-\d+\.webp$/');
    }

    private static function isManaged(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');

        return $path !== '' && $upload !== '' && str_starts_with($path, $upload . '/pages/');
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
