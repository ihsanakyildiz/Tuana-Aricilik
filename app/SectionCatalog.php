<?php

declare(strict_types=1);

final class SectionCatalog
{
    public const PAGE = 'home';

    /** @var array<string, array{label:string,text:string,anchor:string}> */
    public const DESIGNS = [
        'features' => [
            'label' => 'Öne çıkan kartlar',
            'text' => 'Kısa başlık, açıklama ve düğme taşıyan bilgi kartları.',
            'anchor' => 'one-cikanlar',
        ],
        'about' => [
            'label' => 'Hakkımızda',
            'text' => 'Solda görsel, sağda başlık ve iki paragraflık metin.',
            'anchor' => 'hakkimizda',
        ],
        'banner' => [
            'label' => 'Vurgu bandı',
            'text' => 'Geniş fotoğrafın üzerinde kısa bir vurgu metni.',
            'anchor' => 'dogal-bal',
        ],
        'services' => [
            'label' => 'Hizmetler',
            'text' => 'Başlık ve istediğin kadar hizmet kartı. Yan yana adet ve kaydırma seçilir.',
            'anchor' => 'hizmetler',
        ],
        'products' => [
            'label' => 'Ürün vitrini',
            'text' => 'Söz alanı ve yanında ürün kartları. Kategori, sıra, yan yana adet ve kaydırma seçilir.',
            'anchor' => 'urunler',
        ],
        'why' => [
            'label' => 'Neden biz',
            'text' => 'Görsel solda ya da sağda, yanında madde listesi.',
            'anchor' => 'neden-biz',
        ],
        'benefits' => [
            'label' => 'Profil çubukları',
            'text' => 'Metin, fotoğraf ve yüzde çubukları.',
            'anchor' => 'faydalar',
        ],
        'promo' => [
            'label' => 'Promosyon',
            'text' => 'Görselin üzerinde kayan kampanya metni ve düğme.',
            'anchor' => 'promosyon',
        ],
        'testimonials' => [
            'label' => 'Yorumlar',
            'text' => 'Başlık ve kayan müşteri notları. Yorum eklenir, gizlenir, sıralanır.',
            'anchor' => 'yorumlar',
        ],
    ];

    /** @var array<string, string> */
    public const EXTRA = [
        'hero' => 'Karşılama',
        'blog' => 'Yazı listesi',
        'pagehead' => 'Sayfa bandı',
        'grid' => 'Grid satırı',
    ];

    public static function has(string $type): bool
    {
        return isset(self::DESIGNS[$type]);
    }

    public static function label(string $type): string
    {
        return self::DESIGNS[$type]['label'] ?? self::EXTRA[$type] ?? 'Bölüm';
    }

    /** @return array{section_type:string,section_key:string,content:array<string,mixed>}|null */
    public static function preview(string $type): ?array
    {
        if (!self::has($type)) {
            return null;
        }
        $content = self::liveContent($type);
        if ($content === []) {
            $content = self::seedContent($type);
        }

        return [
            'section_type' => $type,
            'section_key' => $type,
            'content' => $content,
        ];
    }

    /** @return array<string, mixed> */
    public static function seedContent(string $type): array
    {
        foreach (load_seed()['sections'] ?? [] as $section) {
            if (!is_array($section)) {
                continue;
            }
            if (($section['page_slug'] ?? '') === self::PAGE && ($section['section_type'] ?? '') === $type) {
                $content = $section['content'] ?? [];

                return is_array($content) ? $content : [];
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private static function liveContent(string $type): array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT content FROM sections
             WHERE page_slug = ? AND section_type = ? AND is_active = 1
             ORDER BY sort_order ASC, id ASC
             LIMIT 1'
        );
        $stmt->execute([self::PAGE, $type]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = decode_value($raw);

        return is_array($decoded) ? $decoded : [];
    }
}
