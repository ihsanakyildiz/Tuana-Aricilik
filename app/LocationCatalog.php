<?php

declare(strict_types=1);

final class LocationCatalog
{
    private const PAGE_SIZE = 40;

    /** @return array{il:int,ilce:int,mahalle:int,koy:int} */
    public static function totals(): array
    {
        $totals = ['il' => 0, 'ilce' => 0, 'mahalle' => 0, 'koy' => 0];
        $pdo = self::pdo();
        $rows = $pdo->query('SELECT kind, COUNT(*) AS total FROM locations GROUP BY kind')->fetchAll();
        foreach ($rows as $row) {
            $kind = (string) ($row['kind'] ?? '');
            if (isset($totals[$kind])) {
                $totals[$kind] = (int) $row['total'];
            }
        }

        return $totals;
    }

    /** @return array{id:int,parent_id:int,kind:string,name:string,postal_code:string,source_key:string,is_active:int}|null */
    public static function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = self::pdo()->prepare(
            'SELECT id, parent_id, kind, name, postal_code, source_key, is_active FROM locations WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? self::row($row) : null;
    }

    /** @return list<array{id:int,parent_id:int,kind:string,name:string,postal_code:string,source_key:string,is_active:int}> */
    public static function ancestors(int $id): array
    {
        $trail = [];
        $guard = 0;
        while ($id > 0 && $guard < 4) {
            $row = self::find($id);
            if ($row === null) {
                break;
            }
            array_unshift($trail, $row);
            $id = $row['parent_id'];
            $guard++;
        }

        return $trail;
    }

    /**
     * @return array{rows:list<array<string, mixed>>,total:int,page:int,pages:int}
     */
    public static function page(int $parentId, string $query, int $page): array
    {
        $parentId = max(0, $parentId);
        $query = self::clip($query, 80);
        $where = 'parent_id = ?';
        $params = [$parentId];
        if ($query !== '') {
            $where .= ' AND name LIKE ?';
            $params[] = '%' . self::like($query) . '%';
        }
        $pdo = self::pdo();
        $count = $pdo->prepare('SELECT COUNT(*) FROM locations WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);
        $stmt = $pdo->prepare(
            'SELECT id, parent_id, kind, name, postal_code, source_key, is_active,
                (SELECT COUNT(*) FROM locations AS child WHERE child.parent_id = locations.id) AS child_count
             FROM locations
             WHERE ' . $where . '
             ORDER BY name
             LIMIT ' . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE)
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = self::row($row);
            $item['child_count'] = (int) ($row['child_count'] ?? 0);
            $item['label'] = self::baseLabel($item['kind'], $item['name']);
            $rows[] = $item;
        }

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<array{id:int,kind:string,name:string,label:string,postal:string,active:bool}> */
    public static function choices(int $parentId, int $includeId = 0): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT id, kind, name, postal_code, is_active
             FROM locations
             WHERE parent_id = ? AND (is_active = 1 OR id = ?)
             ORDER BY name'
        );
        $stmt->execute([max(0, $parentId), max(0, $includeId)]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = self::row($row);
            }
        }

        return self::withLabels($rows);
    }

    /** @return list<array{id:int,label:string,postal:string}> */
    public static function publicChoices(int $parentId): array
    {
        if ($parentId > 0) {
            $parent = self::find($parentId);
            if ($parent === null || $parent['is_active'] !== 1) {
                return [];
            }
        }
        $choices = [];
        foreach (self::choices($parentId) as $choice) {
            if (!$choice['active']) {
                continue;
            }
            $choices[] = [
                'id' => $choice['id'],
                'label' => $choice['label'],
                'postal' => $choice['postal'],
            ];
        }

        return $choices;
    }

    public static function matchId(int $parentId, string $kind, string $name): int
    {
        $name = self::plainName($name);
        if ($name === '' || !self::known($kind)) {
            return 0;
        }
        $stmt = self::pdo()->prepare(
            'SELECT id FROM locations WHERE parent_id = ? AND kind = ? AND name = ? AND is_active = 1'
        );
        $stmt->execute([$parentId, $kind, $name]);
        $ids = array_map(static fn (mixed $id): int => (int) $id, $stmt->fetchAll(PDO::FETCH_COLUMN));

        return count($ids) === 1 ? $ids[0] : 0;
    }

    /**
     * @return array{city_id:string,city:string,district_id:string,district:string,place_id:string,place_kind:string,neighborhood:string}|null
     */
    public static function chain(int $cityId, int $districtId, int $placeId): ?array
    {
        $city = self::find($cityId);
        $district = self::find($districtId);
        $place = self::find($placeId);
        if (
            $city === null
            || $district === null
            || $place === null
            || $city['kind'] !== 'il'
            || $city['parent_id'] !== 0
            || $district['kind'] !== 'ilce'
            || $district['parent_id'] !== $city['id']
            || !in_array($place['kind'], ['mahalle', 'koy'], true)
            || $place['parent_id'] !== $district['id']
        ) {
            return null;
        }
        $label = self::baseLabel($place['kind'], $place['name']);
        foreach (self::choices($district['id'], $place['id']) as $choice) {
            if ($choice['id'] === $place['id']) {
                $label = $choice['label'];
                break;
            }
        }

        return [
            'city_id' => (string) $city['id'],
            'city' => $city['name'],
            'district_id' => (string) $district['id'],
            'district' => $district['name'],
            'place_id' => (string) $place['id'],
            'place_kind' => $place['kind'],
            'neighborhood' => $label,
        ];
    }

    /** @return list<string> */
    public static function childKinds(string $parentKind): array
    {
        return match ($parentKind) {
            '' => ['il'],
            'il' => ['ilce'],
            'ilce' => ['mahalle', 'koy'],
            'mahalle', 'koy' => [],
            default => [],
        };
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'il' => 'İl',
            'ilce' => 'İlçe',
            'mahalle' => 'Mahalle',
            'koy' => 'Köy',
            default => 'Kayıt',
        };
    }

    /** @return list<string> */
    public static function create(int $parentId, string $kind, string $name, string $postal): array
    {
        $parent = $parentId > 0 ? self::find($parentId) : null;
        if ($parentId > 0 && $parent === null) {
            return ['Üst kayıt bulunamadı.'];
        }
        $parentKind = $parent['kind'] ?? '';
        if (!in_array($kind, self::childKinds($parentKind), true)) {
            return ['Bu düzeyde bu kayıt türü eklenemez.'];
        }
        $name = self::cleanName($name);
        if ($name === null) {
            return ['Ad 2–80 karakter olmalı. Harf, rakam, boşluk, nokta ve tire kullanılabilir.'];
        }
        $postal = self::cleanPostal($postal, $kind);
        if ($postal === null) {
            return ['Posta kodu 5 rakam olmalı.'];
        }
        if (self::nameTaken($parentId, $kind, $name, 0)) {
            return ['Bu ad bu listenin içinde zaten var.'];
        }
        $stmt = self::pdo()->prepare(
            'INSERT INTO locations (parent_id, kind, name, postal_code, is_active) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$parentId, $kind, $name, $postal]);

        return [];
    }

    /** @return list<string> */
    public static function update(int $id, int $parentId, string $kind, string $name, string $postal): array
    {
        $current = self::find($id);
        if ($current === null) {
            return ['Kayıt bulunamadı.'];
        }
        if (!self::kindEditable($current['kind'], $kind)) {
            return ['Kaydın türü değiştirilemez.'];
        }
        $parent = $parentId > 0 ? self::find($parentId) : null;
        if ($parentId > 0 && $parent === null) {
            return ['Üst kayıt bulunamadı.'];
        }
        if ($parentId === $id || self::isDescendant($id, $parentId)) {
            return ['Kayıt kendi altına taşınamaz.'];
        }
        $parentKind = $parent['kind'] ?? '';
        if (!in_array($kind, self::childKinds($parentKind), true)) {
            return ['Seçilen üst kayıt bu türü taşıyamaz.'];
        }
        $name = self::cleanName($name);
        if ($name === null) {
            return ['Ad 2–80 karakter olmalı. Harf, rakam, boşluk, nokta ve tire kullanılabilir.'];
        }
        $postal = self::cleanPostal($postal, $kind);
        if ($postal === null) {
            return ['Posta kodu 5 rakam olmalı.'];
        }
        if (self::nameTaken($parentId, $kind, $name, $id)) {
            return ['Bu ad bu listenin içinde zaten var.'];
        }
        $stmt = self::pdo()->prepare(
            'UPDATE locations SET parent_id = ?, kind = ?, name = ?, postal_code = ? WHERE id = ?'
        );
        $stmt->execute([$parentId, $kind, $name, $postal, $id]);

        return [];
    }

    public static function setActive(int $id, bool $active): void
    {
        if (self::find($id) === null) {
            return;
        }
        $stmt = self::pdo()->prepare('UPDATE locations SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    /** @return list<string> */
    public static function remove(int $id): array
    {
        $current = self::find($id);
        if ($current === null) {
            return ['Kayıt bulunamadı.'];
        }
        $count = self::pdo()->prepare('SELECT COUNT(*) FROM locations WHERE parent_id = ?');
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            return ['Alt kayıtları olan bir yer silinemez. Önce içindeki kayıtları sil veya başka yere taşı.'];
        }
        $stmt = self::pdo()->prepare('DELETE FROM locations WHERE id = ?');
        $stmt->execute([$id]);

        return [];
    }

    /** @return list<array{id:int,name:string,kind:string}> */
    public static function moveTargets(string $kind): array
    {
        $parentKind = match ($kind) {
            'ilce' => 'il',
            'mahalle', 'koy' => 'ilce',
            default => '',
        };
        if ($parentKind === '') {
            return [];
        }
        $stmt = self::pdo()->prepare('SELECT id, parent_id, kind, name FROM locations WHERE kind = ? ORDER BY name');
        $stmt->execute([$parentKind]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'id' => (int) $row['id'],
                'parent_id' => (int) $row['parent_id'],
                'kind' => (string) $row['kind'],
                'name' => (string) $row['name'],
            ];
        }
        if ($parentKind === 'il') {
            return $rows;
        }
        $provinces = [];
        foreach (self::pdo()->query('SELECT id, name FROM locations WHERE kind = "il"')->fetchAll() as $province) {
            if (is_array($province)) {
                $provinces[(int) $province['id']] = (string) $province['name'];
            }
        }
        foreach ($rows as &$row) {
            $province = $provinces[$row['parent_id']] ?? '';
            if ($province !== '') {
                $row['name'] = $province . ' / ' . $row['name'];
            }
        }
        unset($row);
        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /** @return array{ok:bool,message:string} */
    public static function syncOfficial(bool $retireMissing): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'Sunucuda cURL kapalı olduğu için resmi liste indirilemedi.'];
        }
        $dir = dirname(__DIR__) . '/storage/geo';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'İndirme klasörü oluşturulamadı.'];
        }
        $files = [
            'provinces.json' => 'https://api.turkiyeapi.dev/v2/datasets/2025/provinces.json',
            'districts.json' => 'https://api.turkiyeapi.dev/v2/datasets/2025/districts.json',
            'neighborhoods.json' => 'https://api.turkiyeapi.dev/v2/datasets/2025/neighborhoods.json',
            'villages.json' => 'https://api.turkiyeapi.dev/v2/datasets/2025/villages.json',
        ];
        try {
            foreach ($files as $name => $url) {
                self::download($url, $dir . '/' . $name);
            }
            $message = self::importDirectory($dir, $retireMissing);
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Resmi liste alınamadı. ' . $exception->getMessage()];
        }
        foreach (array_keys($files) as $name) {
            $path = $dir . '/' . $name;
            if (is_file($path)) {
                unlink($path);
            }
        }

        return ['ok' => true, 'message' => $message];
    }

    public static function importDirectory(string $dir, bool $retireMissing): string
    {
        set_time_limit(300);
        $levels = [
            'provinces.json' => 'il',
            'districts.json' => 'ilce',
            'neighborhoods.json' => 'mahalle',
            'villages.json' => 'koy',
        ];
        $pdo = self::pdo();
        $current = self::currentOfficial($pdo);
        $parentIds = [];
        foreach (self::pdo()->query('SELECT id, source_key FROM locations WHERE source_key IS NOT NULL')->fetchAll() as $row) {
            if (is_array($row) && (string) $row['source_key'] !== '') {
                $parentIds[(string) $row['source_key']] = (int) $row['id'];
            }
        }
        $seen = [];
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $update = $pdo->prepare('UPDATE locations SET parent_id = ?, name = ?, postal_code = ? WHERE id = ?');
        foreach ($levels as $file => $kind) {
            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                throw new RuntimeException($file . ' bulunamadı.');
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            if (!is_array($decoded)) {
                throw new RuntimeException($file . ' okunamadı.');
            }
            $pending = [];
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    $skipped++;
                    continue;
                }
                $sourceId = (int) ($item['id'] ?? 0);
                $name = self::cleanName((string) ($item['name'] ?? ''));
                if ($sourceId < 1 || $name === null) {
                    $skipped++;
                    continue;
                }
                $parentKey = self::parentKey($kind, $item);
                $parentId = 0;
                if ($parentKey !== '') {
                    if (!isset($parentIds[$parentKey])) {
                        $skipped++;
                        continue;
                    }
                    $parentId = $parentIds[$parentKey];
                }
                $postal = '';
                if (in_array($kind, ['mahalle', 'koy'], true)) {
                    $code = (string) ($item['postalCode'] ?? '');
                    $postal = preg_match('/^\d{5}$/', $code) === 1 ? $code : '';
                }
                $sourceKey = $kind . ':' . $sourceId;
                $seen[$sourceKey] = true;
                $existing = $current[$sourceKey] ?? null;
                if (is_array($existing)) {
                    if ($existing['parent_id'] !== $parentId || $existing['name'] !== $name || $existing['postal_code'] !== $postal) {
                        $update->execute([$parentId, $name, $postal, $existing['id']]);
                        $updated++;
                        $current[$sourceKey]['parent_id'] = $parentId;
                        $current[$sourceKey]['name'] = $name;
                        $current[$sourceKey]['postal_code'] = $postal;
                    }
                    continue;
                }
                $pending[] = [$parentId, $kind, $name, $postal, $sourceKey];
            }
            unset($decoded);
            foreach (array_chunk($pending, 300) as $chunk) {
                self::insertChunk($pdo, $chunk);
                $inserted += count($chunk);
            }
            foreach ($pdo->query('SELECT id, source_key FROM locations WHERE kind = ' . $pdo->quote($kind) . ' AND source_key IS NOT NULL')->fetchAll() as $row) {
                if (is_array($row)) {
                    $parentIds[(string) $row['source_key']] = (int) $row['id'];
                }
            }
        }
        $retired = 0;
        $retireNote = '';
        if ($retireMissing && count($seen) < 40000) {
            $retireNote = ' İndirilen liste eksik göründüğü için eski kayıtlar gizlenmedi.';
        } elseif ($retireMissing) {
            $retired = self::retireMissing($pdo, $seen);
        } else {
            $retired = self::countMissing($pdo, $seen);
        }
        save_setting('locations_synced_at', time());
        save_setting('locations_source', 'TurkiyeAPI 2025');
        $totals = self::totals();
        $message = $totals['il'] . ' il, ' . $totals['ilce'] . ' ilçe, ' . number_format($totals['mahalle'], 0, ',', '.')
            . ' mahalle ve ' . number_format($totals['koy'], 0, ',', '.') . ' köy listede.'
            . ' Bu işlemde ' . $inserted . ' kayıt eklendi, ' . $updated . ' kayıt güncellendi.';
        if ($skipped > 0) {
            $message .= ' ' . $skipped . ' satır üst kaydı olmadığı için atlandı.';
        }
        if ($retireNote !== '') {
            $message .= $retireNote;
        } elseif ($retireMissing) {
            $message .= ' Listede artık olmayan ' . $retired . ' resmi kayıt gizlendi.';
        } elseif ($retired > 0) {
            $message .= ' Listede artık olmayan ' . $retired . ' resmi kayıt duruyor.';
        }

        return $message;
    }

    /** @param array<string, mixed> $row
     *  @return array{id:int,parent_id:int,kind:string,name:string,postal_code:string,source_key:string,is_active:int}
     */
    private static function row(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'parent_id' => (int) ($row['parent_id'] ?? 0),
            'kind' => (string) ($row['kind'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'postal_code' => (string) ($row['postal_code'] ?? ''),
            'source_key' => (string) ($row['source_key'] ?? ''),
            'is_active' => (int) ($row['is_active'] ?? 0),
        ];
    }

    /** @param list<array{id:int,kind:string,name:string,postal_code:string,is_active:int}> $rows
     *  @return list<array{id:int,kind:string,name:string,label:string,postal:string,active:bool}>
     */
    private static function withLabels(array $rows): array
    {
        $counts = [];
        $bases = [];
        foreach ($rows as $row) {
            $base = self::baseLabel($row['kind'], $row['name']);
            $bases[$row['id']] = $base;
            $counts[$base] = ($counts[$base] ?? 0) + 1;
        }
        $choices = [];
        foreach ($rows as $row) {
            $label = $bases[$row['id']] ?? $row['name'];
            if (($counts[$label] ?? 0) > 1) {
                $label .= $row['postal_code'] !== '' ? ' (' . $row['postal_code'] . ')' : ' #' . $row['id'];
            }
            $choices[] = [
                'id' => $row['id'],
                'kind' => $row['kind'],
                'name' => $row['name'],
                'label' => $label,
                'postal' => $row['postal_code'],
                'active' => $row['is_active'] === 1,
            ];
        }

        return $choices;
    }

    private static function baseLabel(string $kind, string $name): string
    {
        if ($kind === 'mahalle' && preg_match('/mahallesi$/iu', $name) !== 1) {
            return $name . ' Mahallesi';
        }
        if ($kind === 'koy' && preg_match('/köyü$/iu', $name) !== 1) {
            return $name . ' Köyü';
        }

        return $name;
    }

    private static function plainName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $name = preg_replace('/\s*\(\d{5}\)$/u', '', $name) ?? $name;
        $name = preg_replace('/\s*#\d+$/u', '', $name) ?? $name;
        $name = preg_replace('/\s+(mahallesi|mah\.?|köyü|köy)$/iu', '', $name) ?? $name;

        return trim($name);
    }

    private static function known(string $kind): bool
    {
        return in_array($kind, ['il', 'ilce', 'mahalle', 'koy'], true);
    }

    private static function kindEditable(string $current, string $next): bool
    {
        if ($current === $next) {
            return true;
        }

        return in_array($current, ['mahalle', 'koy'], true) && in_array($next, ['mahalle', 'koy'], true);
    }

    private static function isDescendant(int $id, int $parentId): bool
    {
        $guard = 0;
        while ($parentId > 0 && $guard < 4) {
            if ($parentId === $id) {
                return true;
            }
            $parent = self::find($parentId);
            if ($parent === null) {
                return false;
            }
            $parentId = $parent['parent_id'];
            $guard++;
        }

        return false;
    }

    private static function nameTaken(int $parentId, string $kind, string $name, int $exceptId): bool
    {
        $stmt = self::pdo()->prepare(
            'SELECT id FROM locations WHERE parent_id = ? AND kind = ? AND name = ? AND id <> ? LIMIT 1'
        );
        $stmt->execute([$parentId, $kind, $name, $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    private static function cleanName(string $name): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '');
        $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($length < 2 || $length > 80 || preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .\'\-()]{1,79}$/u', $name) !== 1) {
            return null;
        }

        return $name;
    }

    private static function cleanPostal(string $postal, string $kind): ?string
    {
        $postal = trim($postal);
        if (!in_array($kind, ['mahalle', 'koy'], true)) {
            return '';
        }
        if ($postal === '') {
            return '';
        }
        if (preg_match('/^\d{5}$/', $postal) !== 1) {
            return null;
        }

        return $postal;
    }

    private static function clip(string $value, int $max): string
    {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }

    private static function like(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** @param array<string, mixed> $item */
    private static function parentKey(string $kind, array $item): string
    {
        if ($kind === 'ilce') {
            $id = (int) ($item['provinceId'] ?? 0);

            return $id > 0 ? 'il:' . $id : '';
        }
        if ($kind === 'mahalle' || $kind === 'koy') {
            $id = (int) ($item['districtId'] ?? 0);

            return $id > 0 ? 'ilce:' . $id : '';
        }

        return '';
    }

    /** @return array<string, array{id:int,parent_id:int,name:string,postal_code:string}> */
    private static function currentOfficial(PDO $pdo): array
    {
        $current = [];
        $rows = $pdo->query('SELECT id, parent_id, name, postal_code, source_key FROM locations WHERE source_key IS NOT NULL')->fetchAll();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['source_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $current[$key] = [
                'id' => (int) $row['id'],
                'parent_id' => (int) $row['parent_id'],
                'name' => (string) $row['name'],
                'postal_code' => (string) $row['postal_code'],
            ];
        }

        return $current;
    }

    /** @param list<array{0:int,1:string,2:string,3:string,4:string}> $chunk */
    private static function insertChunk(PDO $pdo, array $chunk): void
    {
        if ($chunk === []) {
            return;
        }
        $marks = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,1)'));
        $params = [];
        foreach ($chunk as $row) {
            array_push($params, $row[0], $row[1], $row[2], $row[3], $row[4]);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO locations (parent_id, kind, name, postal_code, source_key, is_active) VALUES ' . $marks
        );
        $stmt->execute($params);
    }

    /** @param array<string, true> $seen */
    private static function retireMissing(PDO $pdo, array $seen): int
    {
        $pdo->exec('CREATE TEMPORARY TABLE IF NOT EXISTS location_seen (source_key VARCHAR(40) NOT NULL PRIMARY KEY) ENGINE=MEMORY');
        $pdo->exec('TRUNCATE TABLE location_seen');
        foreach (array_chunk(array_keys($seen), 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '(?)'));
            $stmt = $pdo->prepare('INSERT INTO location_seen (source_key) VALUES ' . $marks);
            $stmt->execute($chunk);
        }
        $count = $pdo->exec(
            'UPDATE locations AS place
             LEFT JOIN location_seen AS seen ON seen.source_key = place.source_key
             SET place.is_active = 0
             WHERE place.source_key IS NOT NULL AND seen.source_key IS NULL AND place.is_active = 1'
        );

        return $count === false ? 0 : $count;
    }

    /** @param array<string, true> $seen */
    private static function countMissing(PDO $pdo, array $seen): int
    {
        $missing = 0;
        $rows = $pdo->query('SELECT source_key FROM locations WHERE source_key IS NOT NULL AND is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $key) {
            if (!isset($seen[(string) $key])) {
                $missing++;
            }
        }

        return $missing;
    }

    private static function download(string $url, string $dest): void
    {
        $handle = fopen($dest, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Dosya yazılamadı.');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            fclose($handle);
            throw new RuntimeException('İndirme başlatılamadı.');
        }
        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'TuanaAricilik/1.0',
        ]);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        fclose($handle);
        if ($ok !== true || $status !== 200 || !is_file($dest) || filesize($dest) < 100) {
            if (is_file($dest)) {
                unlink($dest);
            }
            throw new RuntimeException('İndirme tamamlanamadı.');
        }
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
