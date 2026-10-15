<?php

declare(strict_types=1);

require_once __DIR__ . '/ImageSet.php';
require_once __DIR__ . '/SectionCatalog.php';

final class SectionEditor
{
    private static string $page = 'home';

    public static function usePage(string $page): void
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $page) === 1) {
            self::$page = $page;
        }
    }

    private static function page(): string
    {
        return self::$page;
    }

    /** @var array<string, array{label:string,spans:list<int>}> */
    public const GRID_PRESETS = [
        '1' => ['label' => '1 kolon', 'spans' => [12]],
        '2' => ['label' => '2 eşit', 'spans' => [6, 6]],
        '3' => ['label' => '3 eşit', 'spans' => [4, 4, 4]],
        '4' => ['label' => '4 eşit', 'spans' => [3, 3, 3, 3]],
        '8-4' => ['label' => '8 + 4', 'spans' => [8, 4]],
        '4-8' => ['label' => '4 + 8', 'spans' => [4, 8]],
        '3-9' => ['label' => '3 + 9', 'spans' => [3, 9]],
        '9-3' => ['label' => '9 + 3', 'spans' => [9, 3]],
        '5-7' => ['label' => '5 + 7', 'spans' => [5, 7]],
        '7-5' => ['label' => '7 + 5', 'spans' => [7, 5]],
    ];

    /** @var array<string, string> */
    public const GRID_GUTTERS = [
        'g-0' => 'g-0 · yok',
        'g-2' => 'g-2 · dar',
        'g-3' => 'g-3 · normal',
        'g-4' => 'g-4 · geniş',
        'g-5' => 'g-5 · bol',
    ];

    /** @var array<string, string> */
    public const GRID_ALIGNS = [
        'stretch' => 'Eşit yükseklik',
        'start' => 'Üste hizala',
        'center' => 'Ortaya hizala',
        'end' => 'Alta hizala',
    ];

    /** @var array<string, string> */
    public const GRID_CONTAINERS = [
        'open' => 'Sayfa genişliği',
        'full' => 'Tam genişlik',
    ];

    /** @var array<string, string> */
    public const ICONS = [
        'dipper' => 'Bal kaşığı',
        'hive' => 'Kovan',
        'jar' => 'Kavanoz',
    ];

    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, section_key, section_type, title, sort_order, is_active, content
             FROM sections
             WHERE page_slug = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([self::page()]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = decode_value((string) $row['content']);
            $rows[] = [
                'id' => (int) $row['id'],
                'key' => (string) $row['section_key'],
                'type' => (string) $row['section_type'],
                'title' => (string) $row['title'],
                'sort_order' => (int) $row['sort_order'],
                'active' => (int) $row['is_active'] === 1,
                'content' => is_array($content) ? $content : [],
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function open(string $key): ?array
    {
        if (!self::validKey($key)) {
            return null;
        }
        foreach (self::rows() as $row) {
            if ($row['key'] === $key) {
                $row['cards'] = $row['type'] === 'features' ? self::cardsFor($row) : [];

                return $row;
            }
        }

        return null;
    }

    public static function featuresKey(): string
    {
        foreach (self::rows() as $row) {
            if ($row['type'] === 'features') {
                return (string) $row['key'];
            }
        }

        return '';
    }

    /** @return array{key:string,errors:list<string>} */
    public static function create(string $type, string $heading = ''): array
    {
        $known = SectionCatalog::has($type) || isset(SectionCatalog::EXTRA[$type]);
        if (!$known) {
            return ['key' => '', 'errors' => ['Bu tasarım seçilemez.']];
        }
        $pdo = self::pdo();
        $content = self::sourceContent($type);
        $design = SectionCatalog::DESIGNS[$type] ?? [
            'label' => SectionCatalog::label($type),
            'anchor' => $type === 'pagehead' ? 'ust' : ($type === 'hero' ? 'karsilama' : $type),
        ];
        $count = self::typeCount($type);
        $title = $count === 0 ? $design['label'] : $design['label'] . ' ' . ($count + 1);
        if ($type === 'pagehead' && trim($heading) !== '') {
            $title = self::clip($heading, 80);
            $content['title'] = $title;
            if (isset($content['crumbs'][1]) && is_array($content['crumbs'][1])) {
                $content['crumbs'][1]['label'] = $title;
            }
        }
        $suffix = bin2hex(random_bytes(2));
        $key = self::uniqueKey($type . '-' . $suffix);
        $anchor = $count === 0 ? $design['anchor'] : $design['anchor'] . '-' . $suffix;
        $content['anchor'] = $anchor;

        $pdo->beginTransaction();
        try {
            if ($type === 'features') {
                $content['block_keys'] = self::cloneCards(self::keyList($content['block_keys'] ?? null));
            }
            $order = self::nextOrder();
            $stmt = $pdo->prepare(
                'INSERT INTO sections (page_slug, section_key, section_type, title, sort_order, is_active, content)
                 VALUES (?, ?, ?, ?, ?, 1, ?)'
            );
            $stmt->execute([self::page(), $key, $type, $title, $order, encode_value($content)]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['key' => '', 'errors' => ['Bölüm eklenemedi.']];
        }

        return ['key' => $key, 'errors' => []];
    }

    /** @return list<string> */
    public static function remove(int $id): array
    {
        $row = self::byId($id);
        if ($row === null) {
            return ['Bölüm bulunamadı.'];
        }
        if ($row['type'] === 'hero' && self::page() === 'home' && $row['key'] === 'hero') {
            return ['Anasayfadaki ilk karşılama silinemez. Yerini değiştirip gizleyebilirsin.'];
        }
        if ($row['type'] === 'grid') {
            self::releaseGridChildren($row);
        } else {
            self::stripKeyFromGrids((string) $row['key']);
        }
        $pdo = self::pdo();
        $pdo->prepare('DELETE FROM sections WHERE id = ? AND page_slug = ?')->execute([$id, self::page()]);
        if ($row['type'] === 'features') {
            foreach (self::keyList($row['content']['block_keys'] ?? null) as $cardKey) {
                self::dropCard($cardKey, self::page(), $row['key']);
            }
        }
        if ($row['type'] === 'about' || $row['type'] === 'banner' || $row['type'] === 'why' || $row['type'] === 'benefits' || $row['type'] === 'promo') {
            self::releasePaths(self::aboutPaths($row['content']), $row['id']);
        }
        if ($row['type'] === 'services') {
            self::releasePaths(self::servicePaths($row['content']), $row['id']);
        }
        if ($row['type'] === 'products') {
            self::releasePaths(self::pledgePaths($row['content']), $row['id']);
        }

        return [];
    }

    public static function setActive(int $id, bool $active): void
    {
        if (self::byId($id) === null) {
            return;
        }
        self::pdo()->prepare('UPDATE sections SET is_active = ? WHERE id = ? AND page_slug = ?')
            ->execute([$active ? 1 : 0, $id, self::page()]);
    }

    public static function move(int $id, int $direction): void
    {
        if ($direction !== -1 && $direction !== 1) {
            return;
        }
        $rows = self::rows();
        $visible = [];
        $placed = [];
        foreach ($rows as $row) {
            if (self::isPlaced($row)) {
                $placed[] = $row;
            } else {
                $visible[] = $row;
            }
        }
        $index = null;
        foreach ($visible as $i => $row) {
            if ($row['id'] === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null || !isset($visible[$index + $direction])) {
            return;
        }
        $swap = $visible[$index];
        $visible[$index] = $visible[$index + $direction];
        $visible[$index + $direction] = $swap;
        $stmt = self::pdo()->prepare('UPDATE sections SET sort_order = ? WHERE id = ? AND page_slug = ?');
        $order = 10;
        foreach (array_merge($visible, $placed) as $row) {
            $stmt->execute([$order, $row['id'], self::page()]);
            $order += 10;
        }
    }

    /** @param array<string, mixed> $post @return list<string> */
    public static function saveGridHead(string $key, array $post): array
    {
        $row = self::open($key);
        if ($row === null || $row['type'] !== 'grid') {
            return ['Grid satırı bulunamadı.'];
        }
        $title = self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80);
        if ($title === '') {
            return ['Admin etiketi boş olamaz.'];
        }
        $anchor = self::anchor(is_string($post['anchor'] ?? null) ? $post['anchor'] : '', (string) ($row['content']['anchor'] ?? ''));
        $content = $row['content'];
        $content['anchor'] = $anchor;
        $content['eyebrow'] = self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 80);
        $content['title'] = self::clip(is_string($post['heading'] ?? null) ? $post['heading'] : '', 140);
        $content['subtitle'] = self::clip(is_string($post['subtitle'] ?? null) ? $post['subtitle'] : '', 220);
        self::write((int) $row['id'], $title, (bool) $row['active'], $content);

        return [];
    }

    /** @param array<string, mixed> $post @return list<string> */
    public static function saveGridLayout(string $key, array $post): array
    {
        $row = self::open($key);
        if ($row === null || $row['type'] !== 'grid') {
            return ['Grid satırı bulunamadı.'];
        }
        $gutter = is_string($post['gutter'] ?? null) ? $post['gutter'] : 'g-4';
        $align = is_string($post['align'] ?? null) ? $post['align'] : 'stretch';
        $container = is_string($post['container'] ?? null) ? $post['container'] : 'open';
        if (!isset(self::GRID_GUTTERS[$gutter]) || !isset(self::GRID_ALIGNS[$align]) || !isset(self::GRID_CONTAINERS[$container])) {
            return ['Yerleşim seçimi geçersiz.'];
        }
        $content = $row['content'];
        $content['gutter'] = $gutter;
        $content['align'] = $align;
        $content['container'] = $container;
        $content['columns'] = self::gridColumns($content);
        self::write((int) $row['id'], (string) $row['title'], (bool) $row['active'], $content);

        return [];
    }

    /** @return list<string> */
    public static function applyGridPreset(string $key, string $preset): array
    {
        $row = self::open($key);
        if ($row === null || $row['type'] !== 'grid' || !isset(self::GRID_PRESETS[$preset])) {
            return ['Yerleşim seçilemedi.'];
        }
        $spans = self::GRID_PRESETS[$preset]['spans'];
        $columns = self::gridColumns($row['content']);
        $buckets = [];
        foreach ($spans as $ignored) {
            $buckets[] = [];
        }
        unset($ignored);
        foreach ($columns as $index => $column) {
            $target = min($index, count($spans) - 1);
            foreach ($column['keys'] as $childKey) {
                $buckets[$target][] = $childKey;
            }
        }
        $next = [];
        foreach ($spans as $index => $span) {
            $next[] = ['span' => $span, 'keys' => $buckets[$index]];
        }
        $content = $row['content'];
        $content['columns'] = $next;
        self::write((int) $row['id'], (string) $row['title'], (bool) $row['active'], $content);

        return [];
    }

    /** @return list<string> */
    public static function adjustGridSpan(string $key, int $index, int $delta): array
    {
        if ($delta !== -1 && $delta !== 1) {
            return ['Kolon genişliği değiştirilemedi.'];
        }
        $row = self::open($key);
        if ($row === null || $row['type'] !== 'grid') {
            return ['Grid satırı bulunamadı.'];
        }
        $columns = self::gridColumns($row['content']);
        if (!isset($columns[$index])) {
            return ['Kolon bulunamadı.'];
        }
        $spans = [];
        foreach ($columns as $column) {
            $spans[] = $column['span'];
        }
        if ($delta === 1) {
            $donor = self::gridDonor($spans, $index);
            if ($donor === null) {
                return ['Bu kolon daha geniş olamaz.'];
            }
            $columns[$index]['span']++;
            $columns[$donor]['span']--;
        } elseif ($columns[$index]['span'] <= 1) {
            return ['Kolon 1 birimden dar olamaz.'];
        } else {
            $receiver = isset($columns[$index + 1]) ? $index + 1 : $index - 1;
            if (!isset($columns[$receiver])) {
                return ['Kolon genişliği değiştirilemedi.'];
            }
            $columns[$index]['span']--;
            $columns[$receiver]['span']++;
        }

        return self::writeGridColumns($key, $columns);
    }

    /** @return list<string> */
    public static function addGridColumn(string $key): array
    {
        $row = self::open($key);
        if ($row === null || $row['type'] !== 'grid') {
            return ['Grid satırı bulunamadı.'];
        }
        $columns = self::gridColumns($row['content']);
        if (count($columns) >= 12) {
            return ['Bir satırda en fazla 12 kolon olur.'];
        }
        $donor = null;
        for ($index = count($columns) - 1; $index >= 0; $index--) {
            if ($columns[$index]['span'] > 1) {
                $donor = $index;
                break;
            }
        }
        if ($donor === null) {
            return ['Yeni kolon için boş birim kalmadı.'];
        }
        $columns[$donor]['span']--;
        array_splice($columns, $donor + 1, 0, [['span' => 1, 'keys' => []]]);

        return self::writeGridColumns($key, $columns);
    }

    /** @return list<string> */
    public static function removeGridColumn(string $key, int $index): array
    {
        $row = self::open($key);
        if ($row === null || $row['type'] !== 'grid') {
            return ['Grid satırı bulunamadı.'];
        }
        $columns = self::gridColumns($row['content']);
        if (count($columns) < 2 || !isset($columns[$index])) {
            return ['Bu kolon kaldırılamaz.'];
        }
        if ($index > 0) {
            $columns[$index - 1]['span'] += $columns[$index]['span'];
            foreach ($columns[$index]['keys'] as $childKey) {
                $columns[$index - 1]['keys'][] = $childKey;
            }
            array_splice($columns, $index, 1);
        } else {
            $columns[1]['span'] += $columns[0]['span'];
            $columns[1]['keys'] = array_merge($columns[0]['keys'], $columns[1]['keys']);
            array_splice($columns, 0, 1);
        }

        return self::writeGridColumns($key, $columns);
    }

    /** @return array{key:string,errors:list<string>} */
    public static function gridAdd(string $gridKey, int $column, string $type): array
    {
        if ($type === 'grid' || (!SectionCatalog::has($type) && !isset(SectionCatalog::EXTRA[$type]))) {
            return ['key' => '', 'errors' => ['Bu tasarım seçilemez.']];
        }
        $grid = self::open($gridKey);
        if ($grid === null || $grid['type'] !== 'grid') {
            return ['key' => '', 'errors' => ['Grid satırı bulunamadı.']];
        }
        $columns = self::gridColumns($grid['content']);
        if (!isset($columns[$column])) {
            return ['key' => '', 'errors' => ['Kolon bulunamadı.']];
        }
        $created = self::create($type);
        if ($created['errors'] !== []) {
            return $created;
        }
        $grid = self::open($gridKey);
        if ($grid === null) {
            return ['key' => '', 'errors' => ['Grid satırı bulunamadı.']];
        }
        $columns = self::gridColumns($grid['content']);
        if (!isset($columns[$column])) {
            $column = count($columns) - 1;
        }
        $columns[$column]['keys'][] = $created['key'];
        $content = $grid['content'];
        $content['columns'] = $columns;
        self::write((int) $grid['id'], (string) $grid['title'], (bool) $grid['active'], $content);
        $child = self::open($created['key']);
        if ($child !== null) {
            $childContent = $child['content'];
            $childContent['placed_in'] = $gridKey;
            self::write((int) $child['id'], (string) $child['title'], (bool) $child['active'], $childContent);
        }

        return ['key' => $created['key'], 'errors' => []];
    }

    /** @return list<string> */
    public static function gridArrange(string $gridKey, string $payload): array
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return ['Sıra okunamadı.'];
        }
        $grid = self::open($gridKey);
        if ($grid === null || $grid['type'] !== 'grid') {
            return ['Grid satırı bulunamadı.'];
        }
        $columns = self::gridColumns($grid['content']);
        if (count($decoded) !== count($columns)) {
            return ['Kolon sayısı uyuşmuyor.'];
        }
        $known = [];
        foreach ($columns as $column) {
            foreach ($column['keys'] as $childKey) {
                $known[$childKey] = true;
            }
        }
        $seen = [];
        $next = [];
        foreach ($decoded as $index => $keys) {
            if (!is_array($keys)) {
                return ['Sıra okunamadı.'];
            }
            $clean = [];
            foreach ($keys as $childKey) {
                if (!is_string($childKey) || !isset($known[$childKey]) || isset($seen[$childKey])) {
                    continue;
                }
                $seen[$childKey] = true;
                $clean[] = $childKey;
            }
            $next[] = ['span' => $columns[$index]['span'], 'keys' => $clean];
        }
        foreach (array_keys($known) as $childKey) {
            if (!isset($seen[$childKey])) {
                self::clearPlaced($childKey, $gridKey);
            }
        }
        $content = $grid['content'];
        $content['columns'] = $next;
        self::write((int) $grid['id'], (string) $grid['title'], (bool) $grid['active'], $content);

        return [];
    }

    /** @return list<string> */
    public static function gridShift(string $gridKey, string $childKey, int $direction): array
    {
        if ($direction !== -1 && $direction !== 1 || !self::validKey($childKey)) {
            return [];
        }
        $grid = self::open($gridKey);
        if ($grid === null || $grid['type'] !== 'grid') {
            return ['Grid satırı bulunamadı.'];
        }
        $columns = self::gridColumns($grid['content']);
        foreach ($columns as $index => $column) {
            $position = array_search($childKey, $column['keys'], true);
            if ($position === false || !isset($column['keys'][$position + $direction])) {
                continue;
            }
            $swap = $column['keys'][$position];
            $columns[$index]['keys'][$position] = $column['keys'][$position + $direction];
            $columns[$index]['keys'][$position + $direction] = $swap;
            $content = $grid['content'];
            $content['columns'] = $columns;
            self::write((int) $grid['id'], (string) $grid['title'], (bool) $grid['active'], $content);

            return [];
        }

        return [];
    }

    /**
     * @return array{gutter:string,align:string,container:string,columns:list<array{span:int,keys:list<string>}>}|null
     */
    public static function gridState(string $key): ?array
    {
        $row = self::open($key);
        if ($row === null || $row['type'] !== 'grid') {
            return null;
        }
        $content = $row['content'];
        $gutter = (string) ($content['gutter'] ?? 'g-4');
        $align = (string) ($content['align'] ?? 'stretch');
        $container = (string) ($content['container'] ?? 'open');

        return [
            'gutter' => isset(self::GRID_GUTTERS[$gutter]) ? $gutter : 'g-4',
            'align' => isset(self::GRID_ALIGNS[$align]) ? $align : 'stretch',
            'container' => isset(self::GRID_CONTAINERS[$container]) ? $container : 'open',
            'columns' => self::gridColumns($content),
        ];
    }

    /** @return list<string> */
    public static function gridDrop(string $gridKey, string $childKey): array
    {
        $child = self::open($childKey);
        if ($child === null) {
            return ['Bölüm bulunamadı.'];
        }
        $errors = self::remove((int) $child['id']);
        if ($errors === []) {
            return [];
        }
        self::detachKey($gridKey, $childKey);

        return [];
    }

    /** @param array<string, mixed> $post @return list<string> */
    /**
     * @param array<string, mixed> $post
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function saveBlog(string $key, array $post): array
    {
        $row = self::open($key);
        $values = self::blogValues($post, is_array($row) ? $row : null);
        if ($row === null || $row['type'] !== 'blog') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['button_error']) {
            $errors[] = 'Düğme adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
        }
        if ($values['category'] !== '' && !isset(post_categories()[$values['category']])) {
            $errors[] = 'Seçilen kategori bulunamadı.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $content = $row['content'];
        $content['anchor'] = $values['anchor'];
        $content['source'] = 'posts';
        $content['eyebrow'] = $values['eyebrow'];
        $content['title'] = $values['heading'];
        $content['category'] = $values['category'];
        $content['order'] = $values['order'];
        $content['columns'] = $values['columns'];
        $content['limit'] = $values['limit'];
        $content['slider'] = $values['slider'];
        if ($values['button'] === null) {
            unset($content['button']);
        } else {
            $content['button'] = $values['button'];
        }
        self::write((int) $row['id'], $values['title'], $values['active'], $content);

        return ['errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function saveProducts(string $key, array $post, array $files = []): array
    {
        $row = self::open($key);
        $values = self::productsValues($post, is_array($row) ? $row : null);
        if ($row === null || $row['type'] !== 'products') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['button_error']) {
            $errors[] = 'Düğme adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
        }
        if ($values['pledge_button_error']) {
            $errors[] = 'Söz düğmesinin adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
        }
        if ($values['pledge_enabled'] && $values['pledge_lines'] === []) {
            $errors[] = 'Söz başlığı için en az bir satır yaz.';
        }
        if ($values['category'] !== '' && !isset(product_categories()[$values['category']])) {
            $errors[] = 'Seçilen kategori bulunamadı.';
        }
        $imageFile = self::inspectRaster($files['pledge_image'] ?? null);
        if ($imageFile === false) {
            $errors[] = 'Söz görseli PNG, JPEG, WebP veya GIF olmalı ve 8 MB altında kalmalı.';
        }
        if (is_array($imageFile) && !ImageSet::ready()) {
            $errors[] = 'WebP dönüştürme şu an kullanılamıyor.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $content = $row['content'];
        $previous = self::pledgePaths($content);
        $fresh = [];
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('pledge'), 'pledge-' . bin2hex(random_bytes(4)));
                $fresh = array_column($stored['sources'], 'path');
                $content['pledge_image'] = $stored['image'];
                $content['pledge_image_sources'] = $stored['sources'];
            } elseif (!empty($values['pledge_remove_image'])) {
                $content['pledge_image'] = 'assets/images/pledge.jpg';
                $content['pledge_image_sources'] = [];
            }
            $content['anchor'] = $values['anchor'];
            $content['source'] = 'products';
            $content['eyebrow'] = $values['eyebrow'];
            $content['title'] = $values['heading'];
            $content['title_lines'] = $values['heading'] === '' ? [] : [$values['heading']];
            $content['text'] = $values['text'];
            $content['category'] = $values['category'];
            $content['order'] = $values['order'];
            $content['columns'] = $values['columns'];
            $content['limit'] = $values['limit'];
            $content['slider'] = $values['slider'];
            $content['pledge_enabled'] = $values['pledge_enabled'];
            $content['pledge_side'] = $values['pledge_side'];
            $content['pledge_title'] = $values['pledge_lines'];
            $content['pledge_text'] = $values['pledge_text'];
            $content['pledge_alt'] = $values['pledge_alt'];
            if ($values['button'] === null) {
                unset($content['button']);
            } else {
                $content['button'] = $values['button'];
            }
            if ($values['pledge_button'] === null) {
                unset($content['pledge_button']);
            } else {
                $content['pledge_button'] = $values['pledge_button'];
            }
            self::write((int) $row['id'], $values['title'], $values['active'], $content);
        } catch (Throwable $exception) {
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Ürün vitrini kaydedilemedi.'], 'values' => $values];
        }
        if ($fresh !== [] || !empty($values['pledge_remove_image'])) {
            self::releasePaths($previous, (int) $row['id']);
        }

        return ['errors' => [], 'values' => $values];
    }

    public static function saveMeta(string $key, array $post): array
    {
        $row = self::open($key);
        if ($row === null || $row['type'] === 'features') {
            return ['Bölüm bulunamadı.'];
        }
        $title = self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80);
        if ($title === '') {
            return ['Bölüm adı boş olamaz.'];
        }
        $anchor = self::anchor(is_string($post['anchor'] ?? null) ? $post['anchor'] : '', (string) ($row['content']['anchor'] ?? $key));
        if ($anchor === '') {
            return ['Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.'];
        }
        $content = $row['content'];
        $content['anchor'] = $anchor;
        self::write($row['id'], $title, isset($post['active']), $content);

        return [];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function saveAbout(string $key, array $post, array $files): array
    {
        $row = self::open($key);
        $values = self::aboutValues($post, is_array($row) ? $row : null);
        if ($row === null || $row['type'] !== 'about') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['title_lines'] === []) {
            $errors[] = 'Başlık için en az bir satır yaz.';
        }
        if ($values['button_error']) {
            $errors[] = 'Düğme adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
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

        $content = $row['content'];
        $previous = self::aboutPaths($content);
        $fresh = [];
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('about'), 'about-' . bin2hex(random_bytes(4)));
                $fresh = array_column($stored['sources'], 'path');
                $content['image'] = $stored['image'];
                $content['image_sources'] = $stored['sources'];
            } elseif (!empty($values['remove_image'])) {
                $content['image'] = 'assets/images/pledge.jpg';
                $content['image_sources'] = [];
            }
            $content['anchor'] = $values['anchor'];
            $content['badge_value'] = $values['badge_value'];
            $content['badge_label'] = $values['badge_label'];
            $content['eyebrow'] = $values['eyebrow'];
            $content['title_lines'] = $values['title_lines'];
            $content['text'] = $values['text'];
            $content['text_extra'] = $values['text_extra'];
            $content['image_alt'] = $values['image_alt'];
            $content['button'] = $values['button'];
            self::write($row['id'], $values['title'], $values['active'], $content);
        } catch (Throwable $exception) {
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Hakkımızda kaydedilemedi.'], 'values' => $values];
        }
        if ($fresh !== [] || !empty($values['remove_image'])) {
            ImageSet::deletePaths($previous);
        }

        return ['errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function saveBanner(string $key, array $post, array $files): array
    {
        $row = self::open($key);
        $values = self::bannerValues($post, is_array($row) ? $row : null);
        if ($row === null || $row['type'] !== 'banner') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['title_lines'] === []) {
            $errors[] = 'Başlık için en az bir satır yaz.';
        }
        if ($values['button_error']) {
            $errors[] = 'Düğme adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
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

        $content = $row['content'];
        $previous = self::aboutPaths($content);
        $fresh = [];
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('banner'), 'banner-' . bin2hex(random_bytes(4)));
                $fresh = array_column($stored['sources'], 'path');
                $content['image'] = $stored['image'];
                $content['image_sources'] = $stored['sources'];
            } elseif (!empty($values['remove_image'])) {
                $content['image'] = 'assets/images/blog-1.jpg';
                $content['image_sources'] = [];
            }
            $content['anchor'] = $values['anchor'];
            $content['eyebrow'] = $values['eyebrow'];
            $content['title_lines'] = $values['title_lines'];
            $content['text'] = $values['text'];
            $content['image_alt'] = $values['image_alt'];
            $content['button'] = $values['button'];
            self::write($row['id'], $values['title'], $values['active'], $content);
        } catch (Throwable $exception) {
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Vurgu bandı kaydedilemedi.'], 'values' => $values];
        }
        if ($fresh !== [] || !empty($values['remove_image'])) {
            ImageSet::deletePaths($previous);
        }

        return ['errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function savePromo(string $key, array $post, array $files): array
    {
        $row = self::open($key);
        $values = self::promoValues($post, is_array($row) ? $row : null);
        if ($row === null || $row['type'] !== 'promo') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['title_lines'] === []) {
            $errors[] = 'Başlık için en az bir satır yaz.';
        }
        if ($values['button_error']) {
            $errors[] = 'Düğme adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
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

        $content = $row['content'];
        $previous = self::aboutPaths($content);
        $fresh = [];
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('promo'), 'kampanya-' . bin2hex(random_bytes(4)));
                $fresh = array_column($stored['sources'], 'path');
                $content['image'] = $stored['image'];
                $content['image_sources'] = $stored['sources'];
            } elseif (!empty($values['remove_image'])) {
                $content['image'] = 'assets/images/promo.jpg';
                $content['image_sources'] = [];
            }
            $content['anchor'] = $values['anchor'];
            $content['eyebrow'] = $values['eyebrow'];
            $content['title_lines'] = $values['title_lines'];
            $content['text'] = $values['text'];
            $content['image_alt'] = $values['image_alt'];
            $content['button'] = $values['button'];
            self::write($row['id'], $values['title'], $values['active'], $content);
        } catch (Throwable $exception) {
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Promosyon kaydedilemedi.'], 'values' => $values];
        }
        if ($fresh !== [] || !empty($values['remove_image'])) {
            self::releasePaths($previous, $row['id']);
        }

        return ['errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function saveServices(string $key, array $post, array $files): array
    {
        $row = self::open($key);
        $previousItems = is_array($row) && is_array($row['content']['items'] ?? null) ? $row['content']['items'] : [];
        $values = self::serviceValues($post, is_array($row) ? $row : null, $previousItems);
        if ($row === null || $row['type'] !== 'services') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['title_lines'] === []) {
            $errors[] = 'Başlık için en az bir satır yaz.';
        }
        if ($values['items'] === []) {
            $errors[] = 'En az bir hizmet kartı kalmalı.';
        }
        if (count($values['items']) > 24) {
            $errors[] = 'Bir hizmetler bölümünde en fazla 24 kart olabilir.';
        }
        foreach ($values['items'] as $item) {
            if ($item['title'] === '') {
                $errors[] = 'Her hizmet kartının bir adı olmalı.';
                break;
            }
            if ($item['button_error']) {
                $errors[] = 'Düğme adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
                break;
            }
        }
        $uploads = [];
        foreach ($values['items'] as $index => $item) {
            $upload = self::inspectRaster(self::fileAt($files, 'item_image', $index));
            if ($upload === false) {
                $errors[] = 'Hizmet görseli PNG, JPEG, WebP veya GIF olmalı ve 8 MB altında kalmalı.';
                break;
            }
            $uploads[$index] = $upload;
        }
        if ($uploads !== [] && in_array(true, array_map(static fn (mixed $file): bool => is_array($file), $uploads), true) && !ImageSet::ready()) {
            $errors[] = 'WebP dönüştürme şu an kullanılamıyor.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $known = [];
        foreach ($previousItems as $item) {
            if (is_array($item) && is_string($item['id'] ?? null) && $item['id'] !== '') {
                $known[$item['id']] = $item;
            }
        }
        $content = $row['content'];
        $before = self::servicePaths($content);
        $fresh = [];
        $storedItems = [];
        try {
            foreach ($values['items'] as $index => $item) {
                $prior = $known[$item['id']] ?? null;
                $image = is_array($prior) ? (string) ($prior['image'] ?? '') : '';
                $sources = is_array($prior) && is_array($prior['image_sources'] ?? null) ? $prior['image_sources'] : [];
                if ($image === '' && $item['image_keep'] !== '' && self::knownServiceImage($previousItems, $item['image_keep'])) {
                    $image = $item['image_keep'];
                    foreach ($previousItems as $earlier) {
                        if (is_array($earlier) && (string) ($earlier['image'] ?? '') === $image && is_array($earlier['image_sources'] ?? null)) {
                            $sources = $earlier['image_sources'];
                            break;
                        }
                    }
                }
                if ($image === '') {
                    $image = 'assets/images/service-keeping.jpg';
                }
                $upload = $uploads[$index] ?? null;
                if (is_array($upload)) {
                    $stored = ImageSet::storeRaster($upload['tmp'], $upload['mime'], self::uploadFolder('services'), 'hizmet-' . bin2hex(random_bytes(4)));
                    foreach ($stored['sources'] as $source) {
                        if (is_array($source)) {
                            $fresh[] = (string) ($source['path'] ?? '');
                        }
                    }
                    $image = $stored['image'];
                    $sources = $stored['sources'];
                } elseif (!empty($item['clear']) && self::isManaged($image)) {
                    $image = 'assets/images/service-keeping.jpg';
                    $sources = [];
                }
                $storedItems[] = [
                    'id' => $item['id'] !== '' ? $item['id'] : bin2hex(random_bytes(4)),
                    'active' => $item['active'],
                    'title' => $item['title'],
                    'text' => $item['text'],
                    'image' => $image,
                    'image_alt' => $item['image_alt'],
                    'image_sources' => $sources,
                    'button' => $item['button'],
                ];
            }
            $content['anchor'] = $values['anchor'];
            $content['eyebrow'] = $values['eyebrow'];
            $content['title_lines'] = $values['title_lines'];
            $content['text'] = $values['text'];
            $content['columns'] = $values['columns'];
            $content['slider'] = $values['slider'];
            $content['items'] = $storedItems;
            self::write($row['id'], $values['title'], $values['active'], $content);
        } catch (Throwable $exception) {
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Hizmetler kaydedilemedi.'], 'values' => $values];
        }
        $after = self::servicePaths($content);
        self::releasePaths(array_values(array_diff($before, $after)), $row['id']);

        return ['errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function saveWhy(string $key, array $post, array $files): array
    {
        $row = self::open($key);
        $values = self::whyValues($post, is_array($row) ? $row : null);
        if ($row === null || $row['type'] !== 'why') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['title_lines'] === []) {
            $errors[] = 'Başlık için en az bir satır yaz.';
        }
        if ($values['items'] === []) {
            $errors[] = 'En az bir madde kalmalı.';
        }
        foreach ($values['items'] as $item) {
            if ($item['title'] === '') {
                $errors[] = 'Her maddenin bir adı olmalı.';
                break;
            }
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

        $content = $row['content'];
        $previous = self::aboutPaths($content);
        $fresh = [];
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('why'), 'neden-' . bin2hex(random_bytes(4)));
                $fresh = array_column($stored['sources'], 'path');
                $content['image'] = $stored['image'];
                $content['image_sources'] = $stored['sources'];
            } elseif (!empty($values['remove_image'])) {
                $content['image'] = 'assets/images/why.jpg';
                $content['image_sources'] = [];
            }
            $content['anchor'] = $values['anchor'];
            $content['eyebrow'] = $values['eyebrow'];
            $content['title_lines'] = $values['title_lines'];
            $content['text'] = $values['text'];
            $content['image_alt'] = $values['image_alt'];
            $content['align'] = $values['align'];
            $content['items'] = $values['stored_items'];
            self::write($row['id'], $values['title'], $values['active'], $content);
        } catch (Throwable $exception) {
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Neden biz kaydedilemedi.'], 'values' => $values];
        }
        if ($fresh !== [] || !empty($values['remove_image'])) {
            self::releasePaths($previous, $row['id']);
        }

        return ['errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function saveTestimonials(string $key, array $post): array
    {
        $row = self::open($key);
        $values = self::testimonialValues($post, is_array($row) ? $row : null);
        if ($row === null || $row['type'] !== 'testimonials') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['title_lines'] === []) {
            $errors[] = 'Başlık için en az bir satır yaz.';
        }
        if ($values['items'] === []) {
            $errors[] = 'En az bir yorum kalmalı.';
        }
        foreach ($values['items'] as $item) {
            if ($item['quote'] === '') {
                $errors[] = 'Her yorumun bir metni olmalı.';
                break;
            }
            if ($item['name'] === '') {
                $errors[] = 'Her yorumun bir adı olmalı.';
                break;
            }
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'values' => $values];
        }

        $content = $row['content'];
        $content['anchor'] = $values['anchor'];
        $content['eyebrow'] = $values['eyebrow'];
        $content['title_lines'] = $values['title_lines'];
        $content['text'] = $values['text'];
        $content['items'] = $values['stored_items'];
        self::write($row['id'], $values['title'], $values['active'], $content);

        return ['errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{errors:list<string>,values:array<string,mixed>}
     */
    public static function saveBenefits(string $key, array $post, array $files): array
    {
        $row = self::open($key);
        $values = self::benefitsValues($post, is_array($row) ? $row : null);
        if ($row === null || $row['type'] !== 'benefits') {
            return ['errors' => ['Bölüm bulunamadı.'], 'values' => $values];
        }
        $errors = [];
        if ($values['title'] === '') {
            $errors[] = 'Bölüm adı boş olamaz.';
        }
        if ($values['anchor'] === '') {
            $errors[] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($values['title_lines'] === []) {
            $errors[] = 'Başlık için en az bir satır yaz.';
        }
        if ($values['bars'] === []) {
            $errors[] = 'En az bir çubuk kalmalı.';
        }
        foreach ($values['bars'] as $bar) {
            if ($bar['label'] === '') {
                $errors[] = 'Her çubuğun bir adı olmalı.';
                break;
            }
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

        $content = $row['content'];
        $previous = self::aboutPaths($content);
        $fresh = [];
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::uploadFolder('benefits'), 'profil-' . bin2hex(random_bytes(4)));
                $fresh = array_column($stored['sources'], 'path');
                $content['image'] = $stored['image'];
                $content['image_sources'] = $stored['sources'];
            } elseif (!empty($values['remove_image'])) {
                $content['image'] = 'assets/images/about.jpg';
                $content['image_sources'] = [];
            }
            $content['anchor'] = $values['anchor'];
            $content['eyebrow'] = $values['eyebrow'];
            $content['title_lines'] = $values['title_lines'];
            $content['text'] = $values['text'];
            $content['image_alt'] = $values['image_alt'];
            $content['bars'] = $values['bars'];
            self::write($row['id'], $values['title'], $values['active'], $content);
        } catch (Throwable $exception) {
            ImageSet::deletePaths($fresh);

            return ['errors' => ['Balın profili kaydedilemedi.'], 'values' => $values];
        }
        if ($fresh !== [] || !empty($values['remove_image'])) {
            self::releasePaths($previous, $row['id']);
        }

        return ['errors' => [], 'values' => $values];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{errors:list<string>,cards:list<array<string,mixed>>,title:string,anchor:string,active:bool}
     */
    public static function saveFeatures(string $key, array $post): array
    {
        $row = self::open($key);
        $blank = [
            'errors' => ['Bölüm bulunamadı.'],
            'cards' => [],
            'title' => '',
            'anchor' => '',
            'active' => true,
        ];
        if ($row === null || $row['type'] !== 'features') {
            return $blank;
        }
        $title = self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80);
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $anchor = self::anchor($anchorInput, (string) ($row['content']['anchor'] ?? $key));
        $active = isset($post['active']);
        $posted = self::postedCards($post);
        $result = [
            'errors' => [],
            'cards' => $posted,
            'title' => $title,
            'anchor' => $anchorInput,
            'active' => $active,
        ];
        if ($title === '') {
            $result['errors'][] = 'Bölüm adı boş olamaz.';
        }
        if ($anchor === '') {
            $result['errors'][] = 'Bağlantı adresi yalnızca küçük harf, rakam ve tire olabilir.';
        }
        if ($posted === [] || count($posted) > 6) {
            $result['errors'][] = 'Bir bölümde 1 ile 6 kart olabilir.';
        }
        $owned = self::keyList($row['content']['block_keys'] ?? null);
        foreach ($posted as $index => $card) {
            $number = $index + 1;
            if ($card['title'] === '') {
                $result['errors'][] = $number . '. kartın başlığı boş olamaz.';
            }
            if ($card['button_error']) {
                $result['errors'][] = $number . '. kartın düğme adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
            }
            if ($card['key'] !== '' && !in_array($card['key'], $owned, true)) {
                $result['errors'][] = 'Kart listesi bu bölüme ait değil.';
            }
        }
        if ($result['errors'] !== []) {
            return $result;
        }

        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $next = [];
            foreach ($posted as $card) {
                $blockKey = $card['key'] !== '' ? $card['key'] : self::newBlockKey();
                self::upsertCard($blockKey, $card);
                $next[] = $blockKey;
            }
            foreach ($owned as $old) {
                if (!in_array($old, $next, true)) {
                    self::dropCard($old, self::page(), $key);
                }
            }
            $content = $row['content'];
            $content['anchor'] = $anchor;
            $content['block_keys'] = $next;
            self::write($row['id'], $title, $active, $content);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $result['errors'][] = 'Kartlar kaydedilemedi.';

            return $result;
        }

        return [
            'errors' => [],
            'cards' => [],
            'title' => $title,
            'anchor' => $anchor,
            'active' => $active,
        ];
    }

    /** @param array<string, mixed> $post @return list<array<string, mixed>> */
    public static function postedCards(array $post): array
    {
        $keys = is_array($post['card_key'] ?? null) ? array_values($post['card_key']) : [];
        $icons = is_array($post['card_icon'] ?? null) ? array_values($post['card_icon']) : [];
        $titles = is_array($post['card_title'] ?? null) ? array_values($post['card_title']) : [];
        $texts = is_array($post['card_text'] ?? null) ? array_values($post['card_text']) : [];
        $labels = is_array($post['card_button_label'] ?? null) ? array_values($post['card_button_label']) : [];
        $urls = is_array($post['card_button_url'] ?? null) ? array_values($post['card_button_url']) : [];
        $cards = [];
        foreach ($keys as $index => $rawKey) {
            $icon = is_string($icons[$index] ?? null) ? $icons[$index] : 'jar';
            if (!isset(self::ICONS[$icon])) {
                $icon = 'jar';
            }
            $label = self::clip(is_string($labels[$index] ?? null) ? $labels[$index] : '', 32);
            $url = trim(is_string($urls[$index] ?? null) ? $urls[$index] : '');
            $buttonError = false;
            $button = null;
            if ($label !== '' || $url !== '') {
                if ($label === '' || $url === '' || !self::allowedUrl($url)) {
                    $buttonError = true;
                } else {
                    $button = ['label' => $label, 'url' => $url];
                }
            }
            $key = is_string($rawKey) ? $rawKey : '';
            if ($key !== '' && !self::validKey($key)) {
                $key = '';
            }
            $cards[] = [
                'key' => $key,
                'icon' => $icon,
                'title' => self::clip(is_string($titles[$index] ?? null) ? $titles[$index] : '', 80),
                'text' => self::clip(is_string($texts[$index] ?? null) ? $texts[$index] : '', 220),
                'button_label' => $label,
                'button_url' => $url,
                'button' => $button,
                'button_error' => $buttonError,
                'shared' => [],
            ];
        }

        return $cards;
    }

    /** @param array<string, mixed> $row @return list<array<string, mixed>> */
    private static function cardsFor(array $row): array
    {
        $keys = self::keyList($row['content']['block_keys'] ?? null);
        if ($keys === []) {
            return [];
        }
        $pdo = self::pdo();
        $marks = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare(
            "SELECT block_key, title, content FROM blocks WHERE block_key IN ($marks) AND block_type = 'card'"
        );
        $stmt->execute($keys);
        $found = [];
        foreach ($stmt->fetchAll() as $block) {
            if (!is_array($block)) {
                continue;
            }
            $content = decode_value((string) $block['content']);
            if (!is_array($content)) {
                $content = [];
            }
            $button = is_array($content['button'] ?? null) ? $content['button'] : [];
            $icon = (string) ($content['icon'] ?? 'jar');
            if (!isset(self::ICONS[$icon])) {
                $icon = 'jar';
            }
            $blockKey = (string) $block['block_key'];
            $found[$blockKey] = [
                'key' => $blockKey,
                'icon' => $icon,
                'title' => (string) ($content['title'] ?? $block['title'] ?? ''),
                'text' => (string) ($content['text'] ?? ''),
                'button_label' => (string) ($button['label'] ?? ''),
                'button_url' => (string) ($button['url'] ?? ''),
                'button' => null,
                'button_error' => false,
                'shared' => self::cardUsers($blockKey, self::page(), (string) $row['key']),
            ];
        }
        $cards = [];
        foreach ($keys as $key) {
            if (isset($found[$key])) {
                $cards[] = $found[$key];
            }
        }

        return $cards;
    }

    /** @return list<string> */
    private static function cardUsers(string $cardKey, string $exceptPage, string $exceptKey): array
    {
        $names = [];
        $stmt = self::pdo()->query('SELECT page_slug, section_key, title, content FROM sections');
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) $row['page_slug'] === $exceptPage && (string) $row['section_key'] === $exceptKey) {
                continue;
            }
            $content = decode_value((string) $row['content']);
            $keys = self::keyList(is_array($content) ? ($content['block_keys'] ?? null) : null);
            if (in_array($cardKey, $keys, true)) {
                $page = (string) $row['page_slug'];
                $pages = ['home' => 'Anasayfa', 'hakkimizda' => 'Hakkımızda', 'blog' => 'Blog'];
                $names[] = ($pages[$page] ?? $page) . ' · ' . (string) $row['title'];
            }
        }

        return $names;
    }

    /** @param list<string> $keys @return list<string> */
    private static function cloneCards(array $keys): array
    {
        $created = [];
        foreach (self::cardsFor(['key' => '', 'content' => ['block_keys' => $keys]]) as $card) {
            $blockKey = self::newBlockKey();
            $card['button'] = $card['button_label'] !== '' && $card['button_url'] !== ''
                ? ['label' => $card['button_label'], 'url' => $card['button_url']]
                : null;
            self::upsertCard($blockKey, $card);
            $created[] = $blockKey;
        }
        if ($created !== []) {
            return $created;
        }
        $blockKey = self::newBlockKey();
        self::upsertCard($blockKey, [
            'icon' => 'jar',
            'title' => 'Yeni kart',
            'text' => 'Kısa bir açıklama yaz.',
            'button' => ['label' => 'Daha Fazla', 'url' => '#iletisim'],
        ]);

        return [$blockKey];
    }

    /** @param array<string, mixed> $card */
    private static function upsertCard(string $key, array $card): void
    {
        $button = is_array($card['button'] ?? null) ? $card['button'] : null;
        $content = [
            'icon' => (string) $card['icon'],
            'title' => (string) $card['title'],
            'text' => (string) $card['text'],
            'button' => $button,
        ];
        $pdo = self::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO blocks (block_key, block_type, title, sort_order, is_active, content)
             VALUES (?, \'card\', ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), is_active = 1'
        );
        $stmt->execute([$key, (string) $card['title'], self::nextBlockOrder(), encode_value($content)]);
    }

    private static function dropCard(string $cardKey, string $exceptPage, string $exceptKey): void
    {
        if (self::cardUsers($cardKey, $exceptPage, $exceptKey) !== []) {
            return;
        }
        self::pdo()->prepare('DELETE FROM blocks WHERE block_key = ? AND block_type = \'card\'')->execute([$cardKey]);
    }

    /** @param array<string, mixed> $row */
    private static function isPlaced(array $row): bool
    {
        $parent = $row['content']['placed_in'] ?? '';

        return is_string($parent) && $parent !== '';
    }

    /**
     * @param list<array{span:int,keys:list<string>}> $columns
     * @return list<string>
     */
    private static function writeGridColumns(string $key, array $columns): array
    {
        $row = self::open($key);
        if ($row === null || $row['type'] !== 'grid') {
            return ['Grid satırı bulunamadı.'];
        }
        $sum = 0;
        foreach ($columns as $column) {
            $span = (int) ($column['span'] ?? 0);
            if ($span < 1 || $span > 12) {
                return ['Kolon genişliği 1 ile 12 arasında olmalı.'];
            }
            $sum += $span;
        }
        if ($columns === [] || count($columns) > 12 || $sum !== 12) {
            return ['Kolonların toplamı 12 olmalı.'];
        }
        $content = $row['content'];
        $content['columns'] = $columns;
        self::write((int) $row['id'], (string) $row['title'], (bool) $row['active'], $content);

        return [];
    }

    /** @param list<int> $spans */
    private static function gridDonor(array $spans, int $index): ?int
    {
        $count = count($spans);
        for ($step = 1; $step < $count; $step++) {
            $candidate = ($index + $step) % $count;
            if (($spans[$candidate] ?? 0) > 1) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $content
     * @return list<array{span:int,keys:list<string>}>
     */
    private static function gridColumns(array $content): array
    {
        $columns = [];
        foreach ($content['columns'] ?? [] as $column) {
            if (!is_array($column)) {
                continue;
            }
            $span = (int) ($column['span'] ?? 12);
            if ($span < 1 || $span > 12) {
                $span = 12;
            }
            $keys = [];
            foreach ($column['keys'] ?? [] as $key) {
                if (is_string($key) && self::validKey($key)) {
                    $keys[] = $key;
                }
            }
            $columns[] = ['span' => $span, 'keys' => $keys];
        }
        if ($columns === []) {
            return [
                ['span' => 6, 'keys' => []],
                ['span' => 6, 'keys' => []],
            ];
        }

        return $columns;
    }

    /** @param array<string, mixed> $row */
    private static function releaseGridChildren(array $row): void
    {
        foreach (self::gridColumns($row['content']) as $column) {
            foreach ($column['keys'] as $childKey) {
                self::clearPlaced($childKey, (string) $row['key']);
            }
        }
    }

    private static function stripKeyFromGrids(string $childKey): void
    {
        foreach (self::rows() as $row) {
            if ($row['type'] !== 'grid') {
                continue;
            }
            $columns = self::gridColumns($row['content']);
            $changed = false;
            foreach ($columns as $index => $column) {
                $keys = array_values(array_filter(
                    $column['keys'],
                    static fn (string $key): bool => $key !== $childKey
                ));
                if ($keys !== $column['keys']) {
                    $columns[$index]['keys'] = $keys;
                    $changed = true;
                }
            }
            if (!$changed) {
                continue;
            }
            $content = $row['content'];
            $content['columns'] = $columns;
            self::write((int) $row['id'], (string) $row['title'], (bool) $row['active'], $content);
        }
    }

    private static function clearPlaced(string $childKey, string $gridKey): void
    {
        $child = self::open($childKey);
        if ($child === null) {
            return;
        }
        $parent = $child['content']['placed_in'] ?? '';
        if ($parent !== $gridKey) {
            return;
        }
        $content = $child['content'];
        unset($content['placed_in']);
        self::write((int) $child['id'], (string) $child['title'], (bool) $child['active'], $content);
    }

    private static function detachKey(string $gridKey, string $childKey): void
    {
        $grid = self::open($gridKey);
        if ($grid !== null && $grid['type'] === 'grid') {
            $columns = self::gridColumns($grid['content']);
            foreach ($columns as $index => $column) {
                $columns[$index]['keys'] = array_values(array_filter(
                    $column['keys'],
                    static fn (string $key): bool => $key !== $childKey
                ));
            }
            $content = $grid['content'];
            $content['columns'] = $columns;
            self::write((int) $grid['id'], (string) $grid['title'], (bool) $grid['active'], $content);
        }
        self::clearPlaced($childKey, $gridKey);
    }

    /** @return array<string, mixed> */
    private static function sourceContent(string $type): array
    {
        foreach (self::rows() as $row) {
            if ($row['type'] === $type && $row['content'] !== []) {
                return $row['content'];
            }
        }
        if ($type === 'pagehead' || $type === 'grid') {
            return self::blankContent($type);
        }
        $borrowed = self::borrowContent($type);
        if ($borrowed !== []) {
            return $borrowed;
        }
        $seed = SectionCatalog::seedContent($type);
        if ($seed !== []) {
            return $seed;
        }

        return self::blankContent($type);
    }

    /** @return array<string, mixed> */
    private static function borrowContent(string $type): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT content FROM sections WHERE section_type = ? AND page_slug = ? ORDER BY sort_order ASC, id ASC LIMIT 1'
        );
        $stmt->execute([$type, 'home']);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            $stmt = self::pdo()->prepare(
                'SELECT content FROM sections WHERE section_type = ? ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute([$type]);
            $row = $stmt->fetch();
        }
        if (!is_array($row)) {
            return [];
        }
        $content = decode_value((string) ($row['content'] ?? ''));

        return is_array($content) ? $content : [];
    }

    /** @return array<string, mixed> */
    private static function blankContent(string $type): array
    {
        if ($type === 'grid') {
            return [
                'anchor' => '',
                'eyebrow' => '',
                'title' => '',
                'subtitle' => '',
                'gutter' => 'g-4',
                'align' => 'stretch',
                'container' => 'open',
                'columns' => [
                    ['span' => 6, 'keys' => []],
                    ['span' => 6, 'keys' => []],
                ],
            ];
        }
        if ($type === 'pagehead') {
            return [
                'anchor' => 'ust',
                'title' => 'Sayfa',
                'description' => '',
                'image' => 'assets/images/about.jpg',
                'image_alt' => '',
                'crumbs' => [
                    ['label' => 'Ana Sayfa', 'url' => '/'],
                    ['label' => 'Sayfa', 'url' => ''],
                ],
            ];
        }
        if ($type === 'hero') {
            return [
                'anchor' => 'karsilama',
                'slider' => ['effect' => 'fade', 'interval' => 6, 'autoplay' => true],
                'slides' => [],
            ];
        }
        if ($type === 'blog') {
            return [
                'anchor' => 'blog',
                'source' => 'posts',
                'category' => '',
                'order' => 'new',
                'columns' => 2,
                'limit' => 4,
                'slider' => [
                    'enabled' => false,
                    'effect' => 'slide',
                    'step' => '1',
                    'autoplay' => true,
                    'interval' => 5,
                    'loop' => true,
                    'arrows' => true,
                    'dots' => true,
                    'pause' => true,
                ],
            ];
        }
        $anchor = SectionCatalog::DESIGNS[$type]['anchor'] ?? $type;

        return ['anchor' => $anchor];
    }

    private static function typeCount(string $type): int
    {
        $count = 0;
        foreach (self::rows() as $row) {
            if ($row['type'] === $type) {
                $count++;
            }
        }

        return $count;
    }

    private static function nextOrder(): int
    {
        $max = 0;
        foreach (self::rows() as $row) {
            $max = max($max, (int) $row['sort_order']);
        }

        return $max + 10;
    }

    private static function nextBlockOrder(): int
    {
        $value = self::pdo()->query('SELECT COALESCE(MAX(sort_order), 0) FROM blocks')->fetchColumn();

        return (int) $value + 10;
    }

    /** @param array<string, mixed> $content */
    private static function write(int $id, string $title, bool $active, array $content): void
    {
        self::pdo()->prepare(
            'UPDATE sections SET title = ?, is_active = ?, content = ? WHERE id = ? AND page_slug = ?'
        )->execute([$title, $active ? 1 : 0, encode_value($content), $id, self::page()]);
    }

    /** @return array<string, mixed>|null */
    private static function byId(int $id): ?array
    {
        foreach (self::rows() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function keyList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $keys = [];
        foreach ($value as $key) {
            if (is_string($key) && self::validKey($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private static function newBlockKey(): string
    {
        do {
            $key = 'kart-' . bin2hex(random_bytes(4));
        } while (self::blockExists($key));

        return $key;
    }

    private static function uniqueKey(string $key): string
    {
        $candidate = $key;
        $guard = 0;
        while (self::open($candidate) !== null && $guard < 5) {
            $candidate = $key . bin2hex(random_bytes(1));
            $guard++;
        }

        return $candidate;
    }

    private static function blockExists(string $key): bool
    {
        $stmt = self::pdo()->prepare('SELECT id FROM blocks WHERE block_key = ? LIMIT 1');
        $stmt->execute([$key]);

        return $stmt->fetchColumn() !== false;
    }

    private static function anchor(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return self::validKey($fallback) ? $fallback : '';
        }

        return self::validKey($value) ? $value : '';
    }

    private static function allowedUrl(string $url): bool
    {
        if (preg_match('/^\s*javascript:/i', $url) === 1) {
            return false;
        }

        return str_starts_with($url, '/')
            || str_starts_with($url, '#')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
            || preg_match('#^https?://#i', $url) === 1;
    }

    private static function validKey(string $key): bool
    {
        return strlen($key) <= 64 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) === 1;
    }

    private static function clip(string $value, int $limit): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }

        return substr($value, 0, $limit);
    }

    private static function initials(string $name, string $given): string
    {
        $given = self::clip($given, 4);
        if ($given !== '') {
            return function_exists('mb_strtoupper') ? mb_strtoupper($given, 'UTF-8') : strtoupper($given);
        }
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            if (!is_string($part) || $part === '') {
                continue;
            }
            $letters .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
        }
        if ($letters === '') {
            return 'TY';
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($letters, 'UTF-8') : strtoupper($letters);
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private static function aboutValues(array $post, ?array $row): array
    {
        $content = is_array($row) ? $row['content'] : [];
        $lines = [];
        $lineFields = ['', '', ''];
        $postedLines = $post['title_lines'] ?? [];
        if (is_array($postedLines)) {
            foreach (array_slice(array_values($postedLines), 0, 3) as $index => $line) {
                $clipped = self::clip(is_string($line) ? $line : '', 60);
                $lineFields[$index] = $clipped;
                if ($clipped !== '') {
                    $lines[] = $clipped;
                }
            }
        }
        $label = self::clip(is_string($post['button_label'] ?? null) ? $post['button_label'] : '', 32);
        $url = trim(is_string($post['button_url'] ?? null) ? $post['button_url'] : '');
        $buttonError = false;
        $button = null;
        if ($label !== '' || $url !== '') {
            if ($label === '' || $url === '' || !self::allowedUrl($url)) {
                $buttonError = true;
            } else {
                $button = ['label' => $label, 'url' => $url];
            }
        }
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $image = (string) ($content['image'] ?? '');

        return [
            'title' => self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80),
            'anchor' => self::anchor($anchorInput, (string) ($content['anchor'] ?? 'hakkimizda')),
            'anchor_input' => $anchorInput,
            'active' => isset($post['active']),
            'badge_value' => self::clip(is_string($post['badge_value'] ?? null) ? $post['badge_value'] : '', 8),
            'badge_label' => self::clip(is_string($post['badge_label'] ?? null) ? $post['badge_label'] : '', 32),
            'eyebrow' => self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 40),
            'title_lines' => $lines,
            'line_fields' => $lineFields,
            'text' => self::clip(is_string($post['text'] ?? null) ? $post['text'] : '', 420),
            'text_extra' => self::clip(is_string($post['text_extra'] ?? null) ? $post['text_extra'] : '', 420),
            'image' => $image,
            'image_alt' => self::clip(is_string($post['image_alt'] ?? null) ? $post['image_alt'] : '', 140),
            'button_label' => $label,
            'button_url' => $url,
            'button' => $button,
            'button_error' => $buttonError,
            'remove_image' => isset($post['remove_image']) && self::isManaged($image),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private static function bannerValues(array $post, ?array $row): array
    {
        $content = is_array($row) ? $row['content'] : [];
        $lines = [];
        $lineFields = ['', '', ''];
        $postedLines = $post['title_lines'] ?? [];
        if (is_array($postedLines)) {
            foreach (array_slice(array_values($postedLines), 0, 3) as $index => $line) {
                $clipped = self::clip(is_string($line) ? $line : '', 70);
                $lineFields[$index] = $clipped;
                if ($clipped !== '') {
                    $lines[] = $clipped;
                }
            }
        }
        $label = self::clip(is_string($post['button_label'] ?? null) ? $post['button_label'] : '', 32);
        $url = trim(is_string($post['button_url'] ?? null) ? $post['button_url'] : '');
        $buttonError = false;
        $button = null;
        if ($label !== '' || $url !== '') {
            if ($label === '' || $url === '' || !self::allowedUrl($url)) {
                $buttonError = true;
            } else {
                $button = ['label' => $label, 'url' => $url];
            }
        }
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $image = (string) ($content['image'] ?? '');

        return [
            'title' => self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80),
            'anchor' => self::anchor($anchorInput, (string) ($content['anchor'] ?? 'dogal-bal')),
            'anchor_input' => $anchorInput,
            'active' => isset($post['active']),
            'eyebrow' => self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 40),
            'title_lines' => $lines,
            'line_fields' => $lineFields,
            'text' => self::clip(is_string($post['text'] ?? null) ? $post['text'] : '', 420),
            'image' => $image,
            'image_alt' => self::clip(is_string($post['image_alt'] ?? null) ? $post['image_alt'] : '', 140),
            'button_label' => $label,
            'button_url' => $url,
            'button' => $button,
            'button_error' => $buttonError,
            'remove_image' => isset($post['remove_image']) && self::isManaged($image),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private static function promoValues(array $post, ?array $row): array
    {
        $values = self::bannerValues($post, $row);
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $content = is_array($row) ? $row['content'] : [];
        $values['anchor'] = self::anchor($anchorInput, (string) ($content['anchor'] ?? 'promosyon'));

        return $values;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private static function testimonialValues(array $post, ?array $row): array
    {
        $content = is_array($row) ? $row['content'] : [];
        $lines = [];
        $lineFields = ['', '', ''];
        $postedLines = $post['title_lines'] ?? [];
        if (is_array($postedLines)) {
            foreach (array_slice(array_values($postedLines), 0, 3) as $index => $line) {
                $clipped = self::clip(is_string($line) ? $line : '', 70);
                $lineFields[$index] = $clipped;
                if ($clipped !== '') {
                    $lines[] = $clipped;
                }
            }
        }
        $quotes = is_array($post['item_quote'] ?? null) ? array_values($post['item_quote']) : [];
        $names = is_array($post['item_name'] ?? null) ? array_values($post['item_name']) : [];
        $roles = is_array($post['item_role'] ?? null) ? array_values($post['item_role']) : [];
        $marks = is_array($post['item_initials'] ?? null) ? array_values($post['item_initials']) : [];
        $tones = is_array($post['item_tone'] ?? null) ? array_values($post['item_tone']) : [];
        $actives = is_array($post['item_active'] ?? null) ? array_values($post['item_active']) : [];
        $items = [];
        $stored = [];
        foreach (array_slice($quotes, 0, 12) as $index => $quote) {
            $text = self::clip(is_string($quote) ? $quote : '', 320);
            $name = self::clip(is_string($names[$index] ?? null) ? (string) $names[$index] : '', 48);
            $role = self::clip(is_string($roles[$index] ?? null) ? (string) $roles[$index] : '', 40);
            if ($text === '' && $name === '' && $role === '') {
                continue;
            }
            $tone = (int) ($tones[$index] ?? 1);
            if ($tone < 1 || $tone > 4) {
                $tone = 1;
            }
            $active = (string) ($actives[$index] ?? '0') === '1';
            $rowItem = [
                'quote' => $text,
                'name' => $name,
                'role' => $role,
                'initials' => self::clip(is_string($marks[$index] ?? null) ? (string) $marks[$index] : '', 4),
                'tone' => $tone,
                'active' => $active,
            ];
            $items[] = $rowItem;
            $stored[] = [
                'quote' => $text,
                'name' => $name,
                'role' => $role,
                'initials' => self::initials($name, $rowItem['initials']),
                'tone' => $tone,
                'active' => $active,
            ];
        }
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';

        return [
            'title' => self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80),
            'anchor' => self::anchor($anchorInput, (string) ($content['anchor'] ?? 'yorumlar')),
            'anchor_input' => $anchorInput,
            'active' => isset($post['active']),
            'eyebrow' => self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 40),
            'title_lines' => $lines,
            'line_fields' => $lineFields,
            'text' => self::clip(is_string($post['text'] ?? null) ? $post['text'] : '', 280),
            'items' => $items,
            'stored_items' => $stored,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private static function blogValues(array $post, ?array $row): array
    {
        $content = is_array($row) ? $row['content'] : [];
        $columns = (int) ($post['columns'] ?? ($content['columns'] ?? 2));
        if (!in_array($columns, [2, 3, 4, 5, 6], true)) {
            $columns = 2;
        }
        $limit = (int) ($post['limit'] ?? ($content['limit'] ?? 0));
        $limit = max(0, min(24, $limit));
        $order = ($post['order'] ?? ($content['order'] ?? 'new')) === 'old' ? 'old' : 'new';
        $category = trim(is_string($post['category'] ?? null) ? $post['category'] : (string) ($content['category'] ?? ''));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category)) {
            $category = '';
        }
        $effect = is_string($post['slider_effect'] ?? null) ? $post['slider_effect'] : 'slide';
        if (!in_array($effect, ['slide', 'fade', 'none'], true)) {
            $effect = 'slide';
        }
        $step = ($post['slider_step'] ?? '1') === 'group' ? 'group' : '1';
        $interval = max(3, min(12, (int) ($post['slider_interval'] ?? 5)));
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $label = self::clip(is_string($post['button_label'] ?? null) ? $post['button_label'] : '', 40);
        $url = trim(is_string($post['button_url'] ?? null) ? $post['button_url'] : '');
        $buttonError = false;
        $button = null;
        if ($label !== '' || $url !== '') {
            if ($label === '' || $url === '' || !self::allowedUrl($url)) {
                $buttonError = true;
            } else {
                $button = ['label' => $label, 'url' => $url];
            }
        }

        return [
            'title' => self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80),
            'anchor' => self::anchor($anchorInput, (string) ($content['anchor'] ?? 'blog')),
            'anchor_input' => $anchorInput,
            'active' => isset($post['active']),
            'eyebrow' => self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 40),
            'heading' => self::clip(is_string($post['heading'] ?? null) ? $post['heading'] : '', 120),
            'button' => $button,
            'button_label' => $label,
            'button_url' => $url,
            'button_error' => $buttonError,
            'category' => $category,
            'order' => $order,
            'columns' => $columns,
            'limit' => $limit,
            'slider' => [
                'enabled' => isset($post['slider_enabled']),
                'effect' => $effect,
                'step' => $step,
                'autoplay' => isset($post['slider_autoplay']),
                'interval' => $interval,
                'loop' => isset($post['slider_loop']),
                'arrows' => isset($post['slider_arrows']),
                'dots' => isset($post['slider_dots']),
                'pause' => isset($post['slider_pause']),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private static function productsValues(array $post, ?array $row): array
    {
        $content = is_array($row) ? $row['content'] : [];
        $columns = (int) ($post['columns'] ?? ($content['columns'] ?? 3));
        if (!in_array($columns, [2, 3, 4, 5, 6], true)) {
            $columns = 3;
        }
        $limit = (int) ($post['limit'] ?? ($content['limit'] ?? 0));
        $limit = max(0, min(24, $limit));
        $order = ($post['order'] ?? ($content['order'] ?? 'new')) === 'old' ? 'old' : 'new';
        $category = trim(is_string($post['category'] ?? null) ? $post['category'] : (string) ($content['category'] ?? ''));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $category)) {
            $category = '';
        }
        $effect = is_string($post['slider_effect'] ?? null) ? $post['slider_effect'] : 'slide';
        if (!in_array($effect, ['slide', 'fade', 'none'], true)) {
            $effect = 'slide';
        }
        $step = ($post['slider_step'] ?? '1') === 'group' ? 'group' : '1';
        $interval = max(3, min(12, (int) ($post['slider_interval'] ?? 5)));
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $label = self::clip(is_string($post['button_label'] ?? null) ? $post['button_label'] : '', 40);
        $url = trim(is_string($post['button_url'] ?? null) ? $post['button_url'] : '');
        $buttonError = false;
        $button = null;
        if ($label !== '' || $url !== '') {
            if ($label === '' || $url === '' || !self::allowedUrl($url)) {
                $buttonError = true;
            } else {
                $button = ['label' => $label, 'url' => $url];
            }
        }
        $pledgeLabel = self::clip(is_string($post['pledge_button_label'] ?? null) ? $post['pledge_button_label'] : '', 40);
        $pledgeUrl = trim(is_string($post['pledge_button_url'] ?? null) ? $post['pledge_button_url'] : '');
        $pledgeButtonError = false;
        $pledgeButton = null;
        if ($pledgeLabel !== '' || $pledgeUrl !== '') {
            if ($pledgeLabel === '' || $pledgeUrl === '' || !self::allowedUrl($pledgeUrl)) {
                $pledgeButtonError = true;
            } else {
                $pledgeButton = ['label' => $pledgeLabel, 'url' => $pledgeUrl];
            }
        }
        $pledgeLines = [];
        $pledgeFields = ['', '', ''];
        $postedPledge = $post['pledge_title'] ?? [];
        if (is_array($postedPledge)) {
            foreach (array_slice(array_values($postedPledge), 0, 3) as $index => $line) {
                $clipped = self::clip(is_string($line) ? $line : '', 40);
                $pledgeFields[$index] = $clipped;
                if ($clipped !== '') {
                    $pledgeLines[] = $clipped;
                }
            }
        }
        $pledgeSide = ($post['pledge_side'] ?? 'left') === 'right' ? 'right' : 'left';

        return [
            'title' => self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80),
            'anchor' => self::anchor($anchorInput, (string) ($content['anchor'] ?? 'urunler')),
            'anchor_input' => $anchorInput,
            'active' => isset($post['active']),
            'eyebrow' => self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 40),
            'heading' => self::clip(is_string($post['heading'] ?? null) ? $post['heading'] : '', 120),
            'text' => self::clip(is_string($post['text'] ?? null) ? $post['text'] : '', 280),
            'button' => $button,
            'button_label' => $label,
            'button_url' => $url,
            'button_error' => $buttonError,
            'pledge_enabled' => isset($post['pledge_enabled']),
            'pledge_side' => $pledgeSide,
            'pledge_lines' => $pledgeLines,
            'pledge_line_fields' => $pledgeFields,
            'pledge_text' => self::clip(is_string($post['pledge_text'] ?? null) ? $post['pledge_text'] : '', 280),
            'pledge_alt' => self::clip(is_string($post['pledge_alt'] ?? null) ? $post['pledge_alt'] : '', 140),
            'pledge_button' => $pledgeButton,
            'pledge_button_label' => $pledgeLabel,
            'pledge_button_url' => $pledgeUrl,
            'pledge_button_error' => $pledgeButtonError,
            'pledge_remove_image' => isset($post['remove_pledge_image']),
            'category' => $category,
            'order' => $order,
            'columns' => $columns,
            'limit' => $limit,
            'slider' => [
                'enabled' => isset($post['slider_enabled']),
                'effect' => $effect,
                'step' => $step,
                'autoplay' => isset($post['slider_autoplay']),
                'interval' => $interval,
                'loop' => isset($post['slider_loop']),
                'arrows' => isset($post['slider_arrows']),
                'dots' => isset($post['slider_dots']),
                'pause' => isset($post['slider_pause']),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @param list<mixed> $previousItems
     * @return array<string, mixed>
     */
    private static function serviceValues(array $post, ?array $row, array $previousItems): array
    {
        $content = is_array($row) ? $row['content'] : [];
        $lines = [];
        $lineFields = ['', '', ''];
        $postedLines = $post['title_lines'] ?? [];
        if (is_array($postedLines)) {
            foreach (array_slice(array_values($postedLines), 0, 3) as $index => $line) {
                $clipped = self::clip(is_string($line) ? $line : '', 70);
                $lineFields[$index] = $clipped;
                if ($clipped !== '') {
                    $lines[] = $clipped;
                }
            }
        }
        $columns = (int) ($post['columns'] ?? ($content['columns'] ?? 4));
        if (!in_array($columns, [2, 3, 4, 5, 6], true)) {
            $columns = 4;
        }
        $effect = is_string($post['slider_effect'] ?? null) ? $post['slider_effect'] : 'slide';
        if (!in_array($effect, ['slide', 'fade', 'none'], true)) {
            $effect = 'slide';
        }
        $step = ($post['slider_step'] ?? '1') === 'group' ? 'group' : '1';
        $interval = (int) ($post['slider_interval'] ?? 5);
        $interval = max(3, min(12, $interval));
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $known = [];
        foreach ($previousItems as $item) {
            if (is_array($item) && is_string($item['id'] ?? null)) {
                $known[$item['id']] = $item;
            }
        }
        $titles = is_array($post['item_title'] ?? null) ? array_values($post['item_title']) : [];
        $ids = is_array($post['item_id'] ?? null) ? array_values($post['item_id']) : [];
        $texts = is_array($post['item_text'] ?? null) ? array_values($post['item_text']) : [];
        $alts = is_array($post['item_alt'] ?? null) ? array_values($post['item_alt']) : [];
        $keeps = is_array($post['item_image_keep'] ?? null) ? array_values($post['item_image_keep']) : [];
        $labels = is_array($post['item_button'] ?? null) ? array_values($post['item_button']) : [];
        $urls = is_array($post['item_url'] ?? null) ? array_values($post['item_url']) : [];
        $actives = is_array($post['item_active'] ?? null) ? array_values($post['item_active']) : [];
        $clears = is_array($post['item_clear'] ?? null) ? array_values($post['item_clear']) : [];
        $items = [];
        foreach (array_slice($titles, 0, 24) as $index => $title) {
            $id = self::clip(is_string($ids[$index] ?? null) ? $ids[$index] : '', 16);
            if ($id !== '' && !preg_match('/^[a-f0-9]+$/', $id)) {
                $id = '';
            }
            $prior = $known[$id] ?? null;
            $label = self::clip(is_string($labels[$index] ?? null) ? $labels[$index] : '', 32);
            $url = trim(is_string($urls[$index] ?? null) ? $urls[$index] : '');
            $buttonError = false;
            $button = null;
            if ($label !== '' || $url !== '') {
                if ($label === '' || $url === '' || !self::allowedUrl($url)) {
                    $buttonError = true;
                } else {
                    $button = ['label' => $label, 'url' => $url];
                }
            }
            $image = is_array($prior) ? (string) ($prior['image'] ?? '') : '';
            $keep = self::clip(is_string($keeps[$index] ?? null) ? $keeps[$index] : '', 180);
            if ($image === '' && $keep !== '' && self::knownServiceImage($previousItems, $keep)) {
                $image = $keep;
            }
            $items[] = [
                'id' => $id,
                'active' => (string) ($actives[$index] ?? '0') === '1',
                'title' => self::clip(is_string($title) ? $title : '', 48),
                'text' => self::clip(is_string($texts[$index] ?? null) ? $texts[$index] : '', 280),
                'image' => $image,
                'image_keep' => $keep,
                'image_alt' => self::clip(is_string($alts[$index] ?? null) ? $alts[$index] : '', 140),
                'button' => $button,
                'button_label' => $label,
                'button_url' => $url,
                'button_error' => $buttonError,
                'clear' => (string) ($clears[$index] ?? '0') === '1' && self::isManaged($image),
            ];
        }

        return [
            'title' => self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80),
            'anchor' => self::anchor($anchorInput, (string) ($content['anchor'] ?? 'hizmetler')),
            'anchor_input' => $anchorInput,
            'active' => isset($post['active']),
            'eyebrow' => self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 40),
            'title_lines' => $lines,
            'line_fields' => $lineFields,
            'text' => self::clip(is_string($post['text'] ?? null) ? $post['text'] : '', 420),
            'columns' => $columns,
            'slider' => [
                'enabled' => isset($post['slider_enabled']),
                'effect' => $effect,
                'step' => $step,
                'autoplay' => isset($post['slider_autoplay']),
                'interval' => $interval,
                'loop' => isset($post['slider_loop']),
                'arrows' => isset($post['slider_arrows']),
                'dots' => isset($post['slider_dots']),
                'pause' => isset($post['slider_pause']),
            ],
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private static function whyValues(array $post, ?array $row): array
    {
        $content = is_array($row) ? $row['content'] : [];
        $lines = [];
        $lineFields = ['', '', ''];
        $postedLines = $post['title_lines'] ?? [];
        if (is_array($postedLines)) {
            foreach (array_slice(array_values($postedLines), 0, 3) as $index => $line) {
                $clipped = self::clip(is_string($line) ? $line : '', 70);
                $lineFields[$index] = $clipped;
                if ($clipped !== '') {
                    $lines[] = $clipped;
                }
            }
        }
        $align = ($post['align'] ?? 'right') === 'left' ? 'left' : 'right';
        $titles = is_array($post['item_title'] ?? null) ? array_values($post['item_title']) : [];
        $texts = is_array($post['item_text'] ?? null) ? array_values($post['item_text']) : [];
        $actives = is_array($post['item_active'] ?? null) ? array_values($post['item_active']) : [];
        $items = [];
        $stored = [];
        foreach (array_slice($titles, 0, 8) as $index => $title) {
            $clippedTitle = self::clip(is_string($title) ? $title : '', 48);
            $clippedText = self::clip(is_string($texts[$index] ?? null) ? $texts[$index] : '', 220);
            if ($clippedTitle === '' && $clippedText === '') {
                continue;
            }
            $active = (string) ($actives[$index] ?? '0') === '1';
            $items[] = [
                'title' => $clippedTitle,
                'text' => $clippedText,
                'active' => $active,
            ];
            $stored[] = $items[array_key_last($items)];
        }
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $image = (string) ($content['image'] ?? '');

        return [
            'title' => self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80),
            'anchor' => self::anchor($anchorInput, (string) ($content['anchor'] ?? 'neden-biz')),
            'anchor_input' => $anchorInput,
            'active' => isset($post['active']),
            'eyebrow' => self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 40),
            'title_lines' => $lines,
            'line_fields' => $lineFields,
            'text' => self::clip(is_string($post['text'] ?? null) ? $post['text'] : '', 420),
            'image' => $image,
            'image_alt' => self::clip(is_string($post['image_alt'] ?? null) ? $post['image_alt'] : '', 140),
            'align' => $align,
            'items' => $items,
            'stored_items' => $stored,
            'remove_image' => isset($post['remove_image']) && self::isManaged($image),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>
     */
    private static function benefitsValues(array $post, ?array $row): array
    {
        $content = is_array($row) ? $row['content'] : [];
        $lines = [];
        $lineFields = ['', '', ''];
        $postedLines = $post['title_lines'] ?? [];
        if (is_array($postedLines)) {
            foreach (array_slice(array_values($postedLines), 0, 3) as $index => $line) {
                $clipped = self::clip(is_string($line) ? $line : '', 70);
                $lineFields[$index] = $clipped;
                if ($clipped !== '') {
                    $lines[] = $clipped;
                }
            }
        }
        $labels = is_array($post['bar_label'] ?? null) ? array_values($post['bar_label']) : [];
        $levels = is_array($post['bar_value'] ?? null) ? array_values($post['bar_value']) : [];
        $actives = is_array($post['bar_active'] ?? null) ? array_values($post['bar_active']) : [];
        $bars = [];
        foreach (array_slice($labels, 0, 8) as $index => $label) {
            $clipped = self::clip(is_string($label) ? $label : '', 40);
            $raw = $levels[$index] ?? 0;
            $value = is_numeric($raw) ? (int) $raw : 0;
            $value = max(0, min(100, $value));
            if ($clipped === '' && $value === 0) {
                continue;
            }
            $bars[] = [
                'label' => $clipped,
                'value' => $value,
                'active' => (string) ($actives[$index] ?? '0') === '1',
            ];
        }
        $anchorInput = is_string($post['anchor'] ?? null) ? $post['anchor'] : '';
        $image = (string) ($content['image'] ?? '');

        return [
            'title' => self::clip(is_string($post['title'] ?? null) ? $post['title'] : '', 80),
            'anchor' => self::anchor($anchorInput, (string) ($content['anchor'] ?? 'faydalar')),
            'anchor_input' => $anchorInput,
            'active' => isset($post['active']),
            'eyebrow' => self::clip(is_string($post['eyebrow'] ?? null) ? $post['eyebrow'] : '', 40),
            'title_lines' => $lines,
            'line_fields' => $lineFields,
            'text' => self::clip(is_string($post['text'] ?? null) ? $post['text'] : '', 420),
            'image' => $image,
            'image_alt' => self::clip(is_string($post['image_alt'] ?? null) ? $post['image_alt'] : '', 140),
            'bars' => $bars,
            'remove_image' => isset($post['remove_image']) && self::isManaged($image),
        ];
    }

    /** @param array<string, mixed> $files @return array<string, mixed>|null */
    private static function fileAt(array $files, string $key, int $index): ?array
    {
        $bag = $files[$key] ?? null;
        if (!is_array($bag) || !is_array($bag['error'] ?? null) || !array_key_exists($index, $bag['error'])) {
            return null;
        }

        return [
            'name' => is_array($bag['name'] ?? null) ? (string) ($bag['name'][$index] ?? '') : '',
            'type' => is_array($bag['type'] ?? null) ? (string) ($bag['type'][$index] ?? '') : '',
            'tmp_name' => is_array($bag['tmp_name'] ?? null) ? (string) ($bag['tmp_name'][$index] ?? '') : '',
            'error' => (int) $bag['error'][$index],
            'size' => is_array($bag['size'] ?? null) ? (int) ($bag['size'][$index] ?? 0) : 0,
        ];
    }

    /** @param list<mixed> $items */
    private static function knownServiceImage(array $items, string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return false;
        }
        foreach ($items as $item) {
            if (is_array($item) && ltrim(str_replace('\\', '/', (string) ($item['image'] ?? '')), '/') === $path) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $content @return list<string> */
    private static function servicePaths(array $content): array
    {
        $paths = [];
        $items = $content['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $image = (string) ($item['image'] ?? '');
            if (self::isManaged($image)) {
                $paths[] = $image;
            }
            $sources = $item['image_sources'] ?? [];
            if (!is_array($sources)) {
                continue;
            }
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
        $used = [];
        $stmt = self::pdo()->prepare('SELECT content FROM sections WHERE id <> ?');
        $stmt->execute([$exceptId]);
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $decoded = decode_value((string) ($row['content'] ?? ''));
            if (is_array($decoded)) {
                $used = array_merge($used, self::servicePaths($decoded), self::aboutPaths($decoded), self::pledgePaths($decoded));
            }
        }
        ImageSet::deletePaths(array_values(array_diff($paths, $used)));
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

    /** @param array<string, mixed> $content @return list<string> */
    private static function aboutPaths(array $content): array
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

    /** @param array<string, mixed> $content @return list<string> */
    private static function pledgePaths(array $content): array
    {
        $paths = [];
        $image = (string) ($content['pledge_image'] ?? '');
        if (self::isManaged($image)) {
            $paths[] = $image;
        }
        $sources = $content['pledge_image_sources'] ?? [];
        if (is_array($sources)) {
            foreach ($sources as $source) {
                if (is_array($source) && self::isManaged((string) ($source['path'] ?? ''))) {
                    $paths[] = (string) $source['path'];
                }
            }
        }

        return array_values(array_unique($paths));
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
            throw new RuntimeException('Veritabanına ulaşılamadı.');
        }

        return $pdo;
    }
}
