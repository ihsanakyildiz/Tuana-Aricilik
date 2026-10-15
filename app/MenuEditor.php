<?php

declare(strict_types=1);

final class MenuEditor
{
    public const KINDS = ['link', 'page', 'post', 'product', 'product_category', 'category'];

    /** @var array{header:string,list:list<array<string,mixed>>}|null */
    private static ?array $cache = null;

    /** @var array{index:array<string,array<string,array<string,mixed>>>,groups:list<array<string,mixed>>}|null */
    private static ?array $sources = null;

    /** @return list<array<string,mixed>> */
    public static function place(string $location): array
    {
        if ($location !== 'header') {
            return [];
        }
        $data = self::store();
        $menu = self::menuByKey($data, (string) $data['header']);
        if ($menu === null) {
            return [];
        }
        $index = self::sources()['index'];
        $flat = [];
        $hideDeeperThan = null;
        foreach (self::normalizeDepths($menu['items']) as $item) {
            $depth = (int) $item['depth'];
            if ($hideDeeperThan !== null && $depth > $hideDeeperThan) {
                continue;
            }
            $hideDeeperThan = null;
            $resolved = self::resolve($item, $index);
            if ($resolved === null) {
                $hideDeeperThan = $depth;
                continue;
            }
            $resolved['depth'] = $depth;
            $flat[] = $resolved;
        }

        return self::nest($flat);
    }

    /** @return list<array{key:string,name:string,header:bool,count:int}> */
    public static function menus(): array
    {
        $data = self::store();
        $rows = [];
        foreach ($data['list'] as $menu) {
            $rows[] = [
                'key' => (string) $menu['key'],
                'name' => (string) $menu['name'],
                'header' => $data['header'] === $menu['key'],
                'count' => count($menu['items']),
            ];
        }

        return $rows;
    }

    /** @return array<string,mixed>|null */
    public static function find(string $key): ?array
    {
        $data = self::store();
        $menu = self::menuByKey($data, $key);
        if ($menu === null) {
            return null;
        }

        return self::present($menu, $data['header'] === $menu['key']);
    }

    /** @return list<array{id:string,label:string,items:list<array<string,mixed>>}> */
    public static function catalog(): array
    {
        return self::sources()['groups'];
    }

    /**
     * @return array{errors:list<string>,key:string}
     */
    public static function create(string $name): array
    {
        $data = self::store();
        if (count($data['list']) >= 12) {
            return ['errors' => ['En fazla 12 menü oluşturulabilir.'], 'key' => ''];
        }
        $label = self::clip($name, 60);
        if ($label === '') {
            return ['errors' => ['Menü adı gerekli.'], 'key' => ''];
        }
        $key = self::uniqueKey($label, $data['list']);
        $data['list'][] = ['key' => $key, 'name' => $label, 'items' => []];
        if ($data['header'] === '') {
            $data['header'] = $key;
        }
        self::write($data);

        return ['errors' => [], 'key' => $key];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{errors:list<string>,key:string,menu:array<string,mixed>|null}
     */
    public static function save(array $post): array
    {
        $data = self::store();
        $key = is_string($post['menu_key'] ?? null) ? $post['menu_key'] : '';
        $index = self::indexOf($data, $key);
        if ($index === null) {
            return ['errors' => ['Menü bulunamadı.'], 'key' => '', 'menu' => null];
        }
        $name = self::clip((string) ($post['menu_name'] ?? ''), 60);
        $errors = [];
        if ($name === '') {
            $errors[] = 'Menü adı gerekli.';
            $name = (string) $data['list'][$index]['name'];
        }
        $parsed = self::parseItems($post);
        $errors = array_merge($errors, $parsed['errors']);
        if (count($errors) > 8) {
            $errors = array_slice($errors, 0, 8);
            $errors[] = 'Başka öğelerde de düzeltme gerekiyor.';
        }
        $menu = ['key' => $key, 'name' => $name, 'items' => $parsed['items']];
        $header = $data['header'] === $key;
        if ($errors !== []) {
            return ['errors' => $errors, 'key' => $key, 'menu' => self::present($menu, isset($post['use_header']))];
        }
        $data['list'][$index] = $menu;
        if (isset($post['use_header'])) {
            $data['header'] = $key;
            $header = true;
        } elseif ($data['header'] === $key) {
            $data['header'] = '';
            $header = false;
        }
        self::write($data);

        return ['errors' => [], 'key' => $key, 'menu' => self::present($menu, $header)];
    }

    /** @return list<string> */
    public static function remove(string $key): array
    {
        $data = self::store();
        $index = self::indexOf($data, $key);
        if ($index === null) {
            return ['Menü bulunamadı.'];
        }
        array_splice($data['list'], $index, 1);
        if ($data['header'] === $key) {
            $data['header'] = '';
        }
        self::write($data);

        return [];
    }

    /**
     * @param array{header:string,list:list<array<string,mixed>>} $data
     * @return array<string,mixed>|null
     */
    private static function menuByKey(array $data, string $key): ?array
    {
        foreach ($data['list'] as $menu) {
            if ($menu['key'] === $key) {
                return $menu;
            }
        }

        return null;
    }

    /**
     * @param array{header:string,list:list<array<string,mixed>>} $data
     */
    private static function indexOf(array $data, string $key): ?int
    {
        foreach ($data['list'] as $index => $menu) {
            if ($menu['key'] === $key) {
                return $index;
            }
        }

        return null;
    }

    /** @return array{header:string,list:list<array<string,mixed>>} */
    private static function store(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $stored = setting('menus', null);
            if (is_array($stored) && isset($stored['list']) && is_array($stored['list'])) {
                self::$cache = self::normalizeStore($stored);

                return self::$cache;
            }
            $built = self::fromNavigation(setting('navigation', []));
            if (db_pdo() instanceof PDO) {
                save_setting('menus', $built);
            }
            self::$cache = $built;

            return self::$cache;
        } catch (Throwable) {
            self::$cache = self::fromNavigation([]);

            return self::$cache;
        }
    }

    /** @param array{header:string,list:list<array<string,mixed>>} $data */
    private static function write(array $data): void
    {
        save_setting('menus', $data);
        self::$cache = $data;
    }

    /** @return array{header:string,list:list<array<string,mixed>>} */
    private static function fromNavigation(mixed $navigation): array
    {
        $items = [];
        if (is_array($navigation)) {
            foreach ($navigation as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $top = self::readItem([
                    'id' => '',
                    'label' => (string) ($item['label'] ?? ''),
                    'kind' => 'link',
                    'ref' => '',
                    'url' => (string) ($item['url'] ?? '/'),
                    'blank' => false,
                    'depth' => 0,
                    'mega' => false,
                ]);
                if ($top !== null) {
                    $items[] = $top;
                }
                $children = is_array($item['children'] ?? null) ? $item['children'] : [];
                foreach ($children as $child) {
                    if (!is_array($child)) {
                        continue;
                    }
                    $nested = self::readItem([
                        'id' => '',
                        'label' => (string) ($child['label'] ?? ''),
                        'kind' => 'link',
                        'ref' => '',
                        'url' => (string) ($child['url'] ?? '/'),
                        'blank' => false,
                        'depth' => 1,
                        'mega' => false,
                    ]);
                    if ($nested !== null) {
                        $items[] = $nested;
                    }
                }
            }
        }

        return [
            'header' => 'ana-menu',
            'list' => [
                [
                    'key' => 'ana-menu',
                    'name' => 'Ana menü',
                    'items' => self::normalizeDepths($items),
                ],
            ],
        ];
    }

    /** @return array{header:string,list:list<array<string,mixed>>} */
    private static function normalizeStore(array $stored): array
    {
        $list = [];
        foreach ($stored['list'] as $menu) {
            if (!is_array($menu)) {
                continue;
            }
            $key = (string) ($menu['key'] ?? '');
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1) {
                continue;
            }
            $items = [];
            if (is_array($menu['items'] ?? null)) {
                foreach ($menu['items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $clean = self::readItem($item);
                    if ($clean !== null) {
                        $items[] = $clean;
                    }
                }
            }
            $name = self::clip((string) ($menu['name'] ?? ''), 60);
            $list[] = [
                'key' => $key,
                'name' => $name !== '' ? $name : 'Menü',
                'items' => self::normalizeDepths($items),
            ];
        }
        $header = (string) ($stored['header'] ?? '');
        $keys = [];
        foreach ($list as $menu) {
            $keys[] = $menu['key'];
        }
        if (!in_array($header, $keys, true)) {
            $header = '';
        }

        return ['header' => $header, 'list' => $list];
    }

    /** @return array<string,mixed>|null */
    private static function readItem(array $item): ?array
    {
        $kind = (string) ($item['kind'] ?? '');
        if (!in_array($kind, self::KINDS, true)) {
            return null;
        }
        $depth = (int) ($item['depth'] ?? 0);
        if ($depth < 0 || $depth > 2) {
            $depth = 0;
        }
        $label = self::clip((string) ($item['label'] ?? ''), 80);
        if ($label === '') {
            return null;
        }

        return [
            'id' => self::validId((string) ($item['id'] ?? '')),
            'label' => $label,
            'kind' => $kind,
            'ref' => self::validRef((string) ($item['ref'] ?? '')),
            'url' => self::clip((string) ($item['url'] ?? ''), 180),
            'blank' => !empty($item['blank']),
            'depth' => $depth,
            'mega' => $depth === 0 && !empty($item['mega']),
        ];
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private static function normalizeDepths(array $items): array
    {
        $previous = -1;
        foreach ($items as $index => $item) {
            $depth = (int) $item['depth'];
            if ($previous < 0 || $depth < 0) {
                $depth = 0;
            } elseif ($depth > $previous + 1) {
                $depth = $previous + 1;
            }
            if ($depth > 2) {
                $depth = 2;
            }
            $items[$index]['depth'] = $depth;
            if ($depth !== 0) {
                $items[$index]['mega'] = false;
            }
            $previous = $depth;
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $post
     * @return array{errors:list<string>,items:list<array<string,mixed>>}
     */
    private static function parseItems(array $post): array
    {
        $labels = self::postedList($post, 'item_label');
        $kinds = self::postedList($post, 'item_kind');
        $refs = self::postedList($post, 'item_ref');
        $urls = self::postedList($post, 'item_url');
        $depths = self::postedList($post, 'item_depth');
        $megas = self::postedList($post, 'item_mega');
        $blanks = self::postedList($post, 'item_blank');
        $ids = self::postedList($post, 'item_id');
        $count = count($labels);
        $errors = [];
        if ($count > 80) {
            $errors[] = 'Bir menüde en fazla 80 öğe olabilir.';
            $count = 80;
        }
        $index = self::sources()['index'];
        $items = [];
        $used = [];
        for ($i = 0; $i < $count; $i++) {
            $label = self::clip($labels[$i] ?? '', 80);
            $kind = (string) ($kinds[$i] ?? 'link');
            if (!in_array($kind, self::KINDS, true)) {
                $kind = 'link';
            }
            if ($label === '') {
                $errors[] = 'Her menü öğesinin bir adı olmalı.';
            }
            $ref = self::validRef((string) ($refs[$i] ?? ''));
            $url = self::clip((string) ($urls[$i] ?? ''), 180);
            if ($kind === 'link') {
                if (!self::linkOk($url)) {
                    $errors[] = ($label !== '' ? $label : 'Bağlantı') . ' için adres geçersiz.';
                }
            } else {
                $source = $index[$kind][$ref] ?? null;
                if ($ref === '') {
                    $errors[] = ($label !== '' ? $label : 'Öğe') . ' için seçilen içerik bulunamadı.';
                } elseif (is_array($source)) {
                    $url = (string) $source['url'];
                }
            }
            $depth = (int) ($depths[$i] ?? 0);
            $items[] = [
                'id' => self::validId((string) ($ids[$i] ?? ''), $used),
                'label' => $label,
                'kind' => $kind,
                'ref' => $ref,
                'url' => $url,
                'blank' => ($blanks[$i] ?? '0') === '1',
                'depth' => $depth,
                'mega' => ($megas[$i] ?? '0') === '1',
            ];
        }

        return ['errors' => $errors, 'items' => self::normalizeDepths($items)];
    }

    /** @param array<string,bool> $used */
    private static function validId(string $id, array &$used = []): string
    {
        $id = strtolower($id);
        if (preg_match('/^[a-f0-9]{8}$/', $id) !== 1 || isset($used[$id])) {
            do {
                $id = bin2hex(random_bytes(4));
            } while (isset($used[$id]));
        }
        $used[$id] = true;

        return $id;
    }

    private static function validRef(string $ref): string
    {
        $ref = strtolower(trim($ref));
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $ref) !== 1) {
            return '';
        }

        return $ref;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,array<string,array<string,mixed>>> $index
     * @return array<string,mixed>|null
     */
    private static function resolve(array $item, array $index): ?array
    {
        $kind = (string) $item['kind'];
        $label = trim((string) $item['label']);
        $url = trim((string) $item['url']);
        if ($kind !== 'link') {
            $source = $index[$kind][(string) $item['ref']] ?? null;
            if (!is_array($source) || empty($source['active'])) {
                return null;
            }
            $url = (string) $source['url'];
        }
        if ($label === '' || !self::linkOk($url)) {
            return null;
        }

        return [
            'label' => $label,
            'url' => $url,
            'blank' => !empty($item['blank']),
            'mega' => !empty($item['mega']),
        ];
    }

    /**
     * @param list<array<string,mixed>> $flat
     * @return list<array<string,mixed>>
     */
    private static function nest(array $flat): array
    {
        $root = [];
        $ancestry = [];
        foreach ($flat as $item) {
            $depth = (int) ($item['depth'] ?? 0);
            unset($item['depth']);
            $item['children'] = [];
            if ($depth <= 0 || $ancestry === []) {
                $root[] = $item;
                $ancestry = [count($root) - 1];
                continue;
            }
            if ($depth > count($ancestry)) {
                $depth = count($ancestry);
            }
            $parentPath = array_slice($ancestry, 0, $depth);
            $ancestry = $parentPath;
            $ancestry[] = self::pushAt($root, $parentPath, $item);
        }

        return $root;
    }

    /**
     * @param list<array<string,mixed>> $root
     * @param list<int> $path
     */
    private static function pushAt(array &$root, array $path, array $item): int
    {
        $node = &$root;
        foreach ($path as $index) {
            $node = &$node[$index]['children'];
        }
        $node[] = $item;
        $index = (int) array_key_last($node);
        unset($node);

        return $index;
    }

    /**
     * @param array<string,mixed> $menu
     * @return array<string,mixed>
     */
    private static function present(array $menu, bool $header): array
    {
        $index = self::sources()['index'];
        $items = [];
        foreach ($menu['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = self::presentItem($item, $index);
        }

        return [
            'key' => (string) $menu['key'],
            'name' => (string) $menu['name'],
            'header' => $header,
            'items' => $items,
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,array<string,array<string,mixed>>> $index
     * @return array<string,mixed>
     */
    private static function presentItem(array $item, array $index): array
    {
        $kind = (string) $item['kind'];
        $shown = (string) $item['url'];
        $status = '';
        if ($kind !== 'link') {
            $source = $index[$kind][(string) $item['ref']] ?? null;
            if (!is_array($source)) {
                $status = 'Kaynak silinmiş. Bu öğe sitede görünmez.';
            } elseif (empty($source['active'])) {
                $status = 'Taslak. Yayına alınca sitede görünür.';
                $shown = (string) $source['url'];
            } else {
                $shown = (string) $source['url'];
            }
        }

        return [
            'id' => (string) $item['id'],
            'label' => (string) $item['label'],
            'kind' => $kind,
            'ref' => (string) $item['ref'],
            'url' => $shown,
            'blank' => !empty($item['blank']),
            'depth' => (int) $item['depth'],
            'mega' => !empty($item['mega']),
            'kind_label' => self::kindLabel($kind),
            'status' => $status,
            'url_locked' => $kind !== 'link',
        ];
    }

    /** @return array{index:array<string,array<string,array<string,mixed>>>,groups:list<array<string,mixed>>} */
    private static function sources(): array
    {
        if (self::$sources !== null) {
            return self::$sources;
        }
        $index = [
            'page' => [],
            'post' => [],
            'product' => [],
            'product_category' => [],
            'category' => [],
        ];
        $buckets = $index;
        $pdo = db_pdo();
        if ($pdo instanceof PDO) {
            try {
                self::loadDesigned($pdo, $index, $buckets);
                self::loadBlocks($pdo, $index, $buckets);
            } catch (Throwable) {
                $index = [
                    'page' => [],
                    'post' => [],
                    'product' => [],
                    'product_category' => [],
                    'category' => [],
                ];
                $buckets = $index;
            }
        }
        $groups = [];
        $labels = [
            'page' => ['sayfa', 'Sayfalar'],
            'post' => ['yazi', 'Yazılar'],
            'product' => ['urun', 'Ürünler'],
            'product_category' => ['urun-kategori', 'Ürün kategorileri'],
            'category' => ['yazi-kategori', 'Yazı kategorileri'],
        ];
        foreach ($labels as $kind => $meta) {
            $items = array_values($buckets[$kind]);
            usort($items, static fn (array $a, array $b): int => strcasecmp((string) $a['title'], (string) $b['title']));
            if ($kind === 'page') {
                $items = array_merge(self::shortcuts(), $items);
            }
            $groups[] = ['id' => $meta[0], 'label' => $meta[1], 'items' => $items];
        }
        self::$sources = ['index' => $index, 'groups' => $groups];

        return self::$sources;
    }

    /**
     * @param array<string,array<string,array<string,mixed>>> $index
     * @param array<string,array<string,array<string,mixed>>> $buckets
     */
    private static function loadBlocks(PDO $pdo, array &$index, array &$buckets): void
    {
        $rows = $pdo->query(
            "SELECT block_key, block_type, title, is_active
             FROM blocks
             WHERE block_type IN ('page', 'post', 'product', 'product_category', 'category')
             ORDER BY title ASC, id ASC"
        )->fetchAll();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = (string) $row['block_type'];
            $key = (string) $row['block_key'];
            if (!isset($index[$type]) || self::validRef($key) === '') {
                continue;
            }
            if (isset($index[$type][$key])) {
                continue;
            }
            $entry = [
                'kind' => $type,
                'ref' => $key,
                'title' => self::clip((string) $row['title'], 80),
                'url' => self::contentUrl($type, $key),
                'active' => (int) $row['is_active'] === 1,
                'note' => (int) $row['is_active'] === 1 ? '' : 'Taslak',
            ];
            if ($entry['title'] === '') {
                $entry['title'] = $key;
            }
            $index[$type][$key] = $entry;
            $buckets[$type][$key] = $entry;
        }
    }

    /**
     * @param array<string,array<string,array<string,mixed>>> $index
     * @param array<string,array<string,array<string,mixed>>> $buckets
     */
    private static function loadDesigned(PDO $pdo, array &$index, array &$buckets): void
    {
        $rows = $pdo->query(
            "SELECT page_slug, section_type, title, is_active, content
             FROM sections
             WHERE page_slug <> 'home'
             ORDER BY page_slug ASC, sort_order ASC, id ASC"
        )->fetchAll();
        $pages = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = (string) $row['page_slug'];
            if (self::validRef($slug) === '') {
                continue;
            }
            if (!isset($pages[$slug])) {
                $pages[$slug] = [
                    'kind' => 'page',
                    'ref' => $slug,
                    'title' => $slug,
                    'url' => $slug === 'blog' ? '/blog' : '/' . $slug,
                    'active' => false,
                    'note' => '',
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
                $pages[$slug]['title'] = self::clip($title, 80);
            }
        }
        foreach ($pages as $slug => $page) {
            if (empty($page['active'])) {
                $page['note'] = 'Taslak';
            }
            $index['page'][$slug] = $page;
            $buckets['page'][$slug] = $page;
        }
    }

    /** @return list<array<string,mixed>> */
    private static function shortcuts(): array
    {
        return [
            ['kind' => 'link', 'ref' => '', 'title' => 'Ana sayfa', 'url' => '/', 'active' => true, 'note' => ''],
            ['kind' => 'link', 'ref' => '', 'title' => 'Ürünler', 'url' => '/urunler', 'active' => true, 'note' => ''],
            ['kind' => 'link', 'ref' => '', 'title' => 'Hizmetler', 'url' => '/#hizmetler', 'active' => true, 'note' => ''],
            ['kind' => 'link', 'ref' => '', 'title' => 'İletişim', 'url' => '#iletisim', 'active' => true, 'note' => ''],
        ];
    }

    private static function contentUrl(string $type, string $key): string
    {
        return match ($type) {
            'page' => '/' . $key,
            'post' => '/yazi/' . $key,
            'product' => '/urun/' . $key,
            'product_category' => '/urunler/kategori/' . $key,
            'category' => '/blog/kategori/' . $key,
            default => '/',
        };
    }

    /** @param list<array<string,mixed>> $menus */
    private static function uniqueKey(string $name, array $menus): string
    {
        $taken = [];
        foreach ($menus as $menu) {
            $taken[(string) $menu['key']] = true;
        }
        $base = self::slug($name);
        $key = $base;
        $n = 2;
        while (isset($taken[$key])) {
            $key = $base . '-' . $n;
            $n++;
        }

        return $key;
    }

    private static function slug(string $name): string
    {
        $map = [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'I' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u',
        ];
        $value = strtolower(strtr($name, $map));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        if ($value === '') {
            $value = 'menu';
        }
        if (strlen($value) > 36) {
            $value = trim(substr($value, 0, 36), '-');
        }

        return $value !== '' ? $value : 'menu';
    }

    private static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'link' => 'Bağlantı',
            'page' => 'Sayfa',
            'post' => 'Yazı',
            'product' => 'Ürün',
            'product_category' => 'Ürün kategorisi',
            'category' => 'Yazı kategorisi',
            default => 'Bağlantı',
        };
    }

    private static function linkOk(string $url): bool
    {
        if ($url === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $url) === 1 || str_contains($url, "\n")) {
            return false;
        }
        if (str_starts_with($url, '//')) {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1;
    }

    /** @return list<string> */
    private static function postedList(array $post, string $key): array
    {
        $value = $post[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            $list[] = is_string($item) ? $item : '';
        }

        return $list;
    }

    private static function clip(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }
}
