<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

public_maintenance();

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/yazi/([a-z0-9\-]+)/$#', $rawPath, $slash) === 1) {
    $from = strtolower($slash[1]);
    $alias = post_alias_key($from);
    header('Location: ' . absolute_url('/yazi/' . ($alias !== '' ? $alias : $from)), true, 301);
    exit;
}

$slug = request_slug();
$post = $slug === '' ? null : block_by_key($slug);
if ($post === null || ($post['_type'] ?? '') !== 'post') {
    $alias = post_alias_key($slug);
    if ($alias !== '' && $alias !== $slug) {
        header('Location: ' . absolute_url('/yazi/' . $alias), true, 301);
        exit;
    }
    http_response_code(404);
    $pageTitle = 'Sayfa bulunamadı';
    $metaDescription = '';
    $robots = 'noindex,follow';
    header('X-Robots-Tag: noindex, follow');
    require __DIR__ . '/partials/header.php';
    require __DIR__ . '/partials/missing.php';
    require __DIR__ . '/partials/footer.php';
    return;
}

$settings = settings();
$key = (string) ($post['_key'] ?? $slug);
$rawTitle = trim((string) ($post['title'] ?? ''));
$headline = $rawTitle !== '' ? $rawTitle : 'Yazı';
$seoTitle = trim((string) ($post['seo_title'] ?? ''));
$seoDescription = trim((string) ($post['seo_description'] ?? ''));
if ($seoDescription === '') {
    $seoDescription = trim((string) ($post['excerpt'] ?? ''));
}
if ($seoDescription === '') {
    $seoDescription = post_lead($post);
}
if (function_exists('mb_substr')) {
    $seoDescription = mb_substr($seoDescription, 0, 160, 'UTF-8');
} else {
    $seoDescription = substr($seoDescription, 0, 160);
}
$pageTitle = $seoTitle !== '' ? $seoTitle : $headline;
$metaDescription = $seoDescription;
$category = trim((string) ($post['category'] ?? ''));
$categoryKey = trim((string) ($post['category_key'] ?? ''));
$activeCategories = post_categories();
$categoryUrl = $categoryKey !== '' && isset($activeCategories[$categoryKey]) ? category_url($categoryKey) : '';
$bodyClass = 'is-page is-article';
$canonicalPath = '/yazi/' . $key;
$ogType = 'article';
$moment = post_moment($post);
$articlePublished = $moment['iso'] ?? '';
$image = trim((string) ($post['image'] ?? ''));
if ($image !== '') {
    $shareImage = $image;
}
$noise = $_GET;
unset($noise['slug']);
$indexable = post_index_enabled($post) && post_has_body($post) && $rawTitle !== '' && $noise === [];
$robots = $indexable ? 'index,follow,max-image-preview:large' : 'noindex,follow';
$jsonLd = [];
if ($indexable) {
    $pageUrl = absolute_url($canonicalPath);
    $crumbs = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => absolute_url('/')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => absolute_url('/blog')],
    ];
    $position = 3;
    if ($category !== '' && $categoryUrl !== '') {
        $crumbs[] = ['@type' => 'ListItem', 'position' => $position, 'name' => $category, 'item' => absolute_url($categoryUrl)];
        $position++;
    }
    $crumbs[] = ['@type' => 'ListItem', 'position' => $position, 'name' => $headline, 'item' => $pageUrl];
    $article = [
        '@type' => 'BlogPosting',
        '@id' => $pageUrl . '#yazi',
        'mainEntityOfPage' => $pageUrl,
        'headline' => $headline,
        'description' => $metaDescription,
        'inLanguage' => 'tr-TR',
        'url' => $pageUrl,
        'author' => [
            '@type' => 'Organization',
            'name' => (string) ($post['author'] ?? $settings['site_name'] ?? 'Tuana Arıcılık'),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => (string) ($settings['site_name'] ?? 'Tuana Arıcılık'),
        ],
    ];
    if ($articlePublished !== '') {
        $article['datePublished'] = $articlePublished;
    }
    $modified = trim((string) ($post['_updated'] ?? ''));
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $modified, $stamp) === 1) {
        $article['dateModified'] = $stamp[1] . 'T' . $stamp[2] . '+03:00';
    } elseif ($articlePublished !== '') {
        $article['dateModified'] = $articlePublished;
    }
    if ($image !== '') {
        $article['image'] = absolute_url('/' . ltrim($image, '/'));
    }
    if ($category !== '') {
        $article['articleSection'] = $category;
    }
    $logo = trim((string) setting('logo', ''));
    if ($logo !== '') {
        $article['publisher']['logo'] = [
            '@type' => 'ImageObject',
            'url' => absolute_url('/' . ltrim($logo, '/')),
        ];
    }
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            $article,
            [
                '@type' => 'BreadcrumbList',
                '@id' => $pageUrl . '#kirinti',
                'itemListElement' => $crumbs,
            ],
        ],
    ];
}

$bannerCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Blog', 'url' => '/blog'],
];
if ($category !== '') {
    $bannerCrumbs[] = ['label' => $category, 'url' => $categoryUrl];
}
$bannerCrumbs[] = ['label' => $headline, 'url' => ''];

header('X-Robots-Tag: ' . $robots);
require __DIR__ . '/partials/header.php';
render_section([
    'section_type' => 'pagehead',
    'section_key' => 'yazi-ust',
    'content' => [
        'anchor' => 'yazi',
        'title' => $headline,
        'image' => 'assets/images/about.jpg',
        'image_alt' => 'Kavanozda süzme bal ve tahta bal kaşığı',
        'crumbs' => $bannerCrumbs,
        'meta' => [
            ['icon' => 'calendar', 'text' => (string) ($post['date'] ?? '')],
            ['icon' => 'clock', 'text' => (string) ($post['time'] ?? '')],
            ['icon' => 'comment', 'text' => (string) ($post['comments'] ?? 'Yorum yok')],
        ],
        'motion' => [
            'distance' => 28,
            'duration' => 760,
            'once' => true,
            'slots' => ['copy' => 'rise'],
        ],
    ],
]);

echo '<div class="page-canvas">';
require __DIR__ . '/partials/article.php';

$embedKeys = is_array($post['embed_keys'] ?? null) ? $post['embed_keys'] : [];
if (blocks_by_keys($embedKeys, 'card') !== []) {
    $data = [
        'anchor' => 'yazidaki-kartlar',
        'block_keys' => $embedKeys,
        'motion' => [
            'stagger' => 140,
            'distance' => 48,
            'duration' => 760,
            'once' => true,
            'slots' => ['items' => 'rise'],
        ],
    ];
    $sectionKey = 'yazidaki-kartlar';
    include __DIR__ . '/partials/sections/features.php';
}

foreach (sections_for('article') as $shared) {
    render_section($shared);
}
echo '</div>';

require __DIR__ . '/partials/footer.php';
