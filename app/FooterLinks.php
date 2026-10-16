<?php

declare(strict_types=1);

final class FooterLinks
{
    public const COLUMNS = 2;

    public const MAX_LINKS = 12;

    /** @return list<array{title:string,links:list<array{label:string,url:string}>}> */
    public static function columns(): array
    {
        $visible = [];
        foreach (self::stored() as $column) {
            if ($column['links'] === []) {
                continue;
            }
            $visible[] = $column;
        }

        return $visible;
    }

    /** @return list<array{title:string,links:list<array{label:string,url:string}>}> */
    public static function editor(): array
    {
        $stored = self::stored();
        $columns = [];
        for ($index = 0; $index < self::COLUMNS; $index++) {
            $columns[] = $stored[$index] ?? ['title' => '', 'links' => []];
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $post
     * @return array{errors:list<string>,columns:list<array{title:string,links:list<array{label:string,url:string}>}>}
     */
    public static function save(array $post): array
    {
        $titles = self::posted($post['col_title'] ?? null);
        $labelGroups = self::postedGroups($post['link_label'] ?? null);
        $urlGroups = self::postedGroups($post['link_url'] ?? null);
        $errors = [];
        $columns = [];
        for ($index = 0; $index < self::COLUMNS; $index++) {
            $title = self::clip($titles[$index] ?? '', 40);
            $labels = $labelGroups[$index] ?? [];
            $urls = $urlGroups[$index] ?? [];
            $count = max(count($labels), count($urls));
            if ($count > self::MAX_LINKS) {
                $errors[] = 'Bir sütunda en fazla ' . self::MAX_LINKS . ' bağlantı olabilir.';
                $count = self::MAX_LINKS;
            }
            $links = [];
            for ($row = 0; $row < $count; $row++) {
                $label = self::clip($labels[$row] ?? '', 80);
                $url = self::clip($urls[$row] ?? '', 180);
                if ($label === '' && $url === '') {
                    continue;
                }
                if ($label === '') {
                    $errors[] = 'Bağlantı yazısını doldur.';
                    $label = $url;
                }
                if (!self::linkOk($url)) {
                    $errors[] = 'Bağlantı adresi / ile başlamalı, # ile sayfa içi olmalı ya da https:// adresi olmalı.';
                }
                $links[] = ['label' => $label, 'url' => $url];
            }
            if ($links !== [] && $title === '') {
                $errors[] = 'Bağlantısı olan sütunun başlığını yaz.';
            }
            $columns[] = ['title' => $title, 'links' => $links];
        }
        if (count($errors) > 6) {
            $errors = array_slice($errors, 0, 6);
            $errors[] = 'Başka satırlarda da düzeltme gerekiyor.';
        }
        if ($errors === []) {
            save_setting('footer_columns', $columns);
        }

        return ['errors' => $errors, 'columns' => $columns];
    }

    /** @return list<array{title:string,links:list<array{label:string,url:string}>}> */
    private static function stored(): array
    {
        $raw = setting('footer_columns', []);
        if (!is_array($raw)) {
            return [];
        }
        $columns = [];
        foreach ($raw as $column) {
            if (count($columns) >= self::COLUMNS) {
                break;
            }
            if (!is_array($column)) {
                continue;
            }
            $links = [];
            $rawLinks = $column['links'] ?? [];
            if (is_array($rawLinks)) {
                foreach ($rawLinks as $link) {
                    if (count($links) >= self::MAX_LINKS || !is_array($link)) {
                        continue;
                    }
                    $label = self::clip((string) ($link['label'] ?? ''), 80);
                    $url = self::clip((string) ($link['url'] ?? ''), 180);
                    if ($label === '' || !self::linkOk($url)) {
                        continue;
                    }
                    $links[] = ['label' => $label, 'url' => $url];
                }
            }
            $columns[] = [
                'title' => self::clip((string) ($column['title'] ?? ''), 40),
                'links' => $links,
            ];
        }

        return $columns;
    }

    /** @return list<string> */
    private static function posted(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            $list[] = is_string($item) ? $item : '';
        }

        return $list;
    }

    /** @return array<int, list<string>> */
    private static function postedGroups(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $groups = [];
        foreach ($value as $index => $group) {
            if (!is_int($index) && !(is_string($index) && ctype_digit($index))) {
                continue;
            }
            $groups[(int) $index] = self::posted($group);
        }

        return $groups;
    }

    private static function linkOk(string $url): bool
    {
        if ($url === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $url) === 1 || str_contains($url, "\n") || str_contains($url, "\0")) {
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

    private static function clip(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if (mb_strlen($value, 'UTF-8') > $max) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        }

        return trim($value);
    }
}
