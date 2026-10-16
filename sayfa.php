<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/CategoryEditor.php';

public_maintenance();

$slug = request_slug();
if ($slug === 'iletisim') {
    require __DIR__ . '/iletisim.php';
    return;
}
$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$relativePath = $rawPath;
$publicBase = base_path();
if ($publicBase !== '' && str_starts_with($relativePath, $publicBase)) {
    $relativePath = substr($relativePath, strlen($publicBase));
}
$relativePath = '/' . ltrim($relativePath, '/');
$redirectPage = static function (string $target): void {
    $location = absolute_url($target);
    $query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    if ($query !== '') {
        $location .= '?' . $query;
    }
    header('Location: ' . $location, true, 301);
    exit;
};
if (preg_match('#^/sayfa/([a-z0-9\-]+)/?$#', $relativePath, $legacy) === 1) {
    $from = strtolower($legacy[1]);
    $alias = page_alias_key($from);
    $redirectPage('/' . ($alias !== '' ? $alias : $from));
}
if (preg_match('#^/([a-z0-9\-]+)/$#', $relativePath, $pageSlash) === 1) {
    $from = strtolower($pageSlash[1]);
    $alias = page_alias_key($from);
    $redirectPage('/' . ($alias !== '' ? $alias : $from));
}
if (preg_match('#/blog/kategori/([a-z0-9\-]+)/$#', $rawPath, $slash) === 1) {
    $target = category_url(strtolower($slash[1]));
    $search = request_post_filters()['q'];
    if ($search !== '') {
        $target .= '?q=' . rawurlencode($search);
    }
    header('Location: ' . absolute_url($target), true, 301);
    exit;
}

$filters = request_post_filters();
if ($slug === 'blog' && current_path() === '/blog' && $filters['category'] !== '') {
    $target = category_url($filters['category']);
    if ($filters['q'] !== '') {
        $target .= '?q=' . rawurlencode($filters['q']);
    }
    header('Location: ' . absolute_url($target), true, 301);
    exit;
}

$pathCategory = '';
if (preg_match('#^/blog/kategori/([a-z0-9]+(?:-[a-z0-9]+)*)$#', current_path(), $match) === 1) {
    $pathCategory = $match[1];
    $_GET['kategori'] = $pathCategory;
}

$sections = $slug === '' ? [] : sections_for($slug);
$layoutRecord = ($slug === '' || $pathCategory !== '') ? null : page_record($slug);
if (is_array($layoutRecord) && ($layoutRecord['kind'] ?? '') === 'advanced' && empty($layoutRecord['_active'])) {
    http_response_code(404);
    $pageTitle = 'Sayfa bulunamadı';
    $metaDescription = '';
    $bodyClass = 'is-page';
    $canonicalPath = null;
    $robots = 'noindex,follow';
    $jsonLd = [];
    header('X-Robots-Tag: noindex, follow');
    require __DIR__ . '/partials/header.php';
    require __DIR__ . '/partials/missing.php';
    require __DIR__ . '/partials/footer.php';
    return;
}
$settings = settings();
$pageTitle = (string) ($settings['site_name'] ?? 'Tuana Arıcılık');
$metaDescription = (string) ($settings['meta_description'] ?? '');
$bodyClass = 'is-page';
$canonicalPath = $slug === 'blog' ? '/blog' : null;
$robots = 'index,follow';
$jsonLd = [];
$categoryRecord = null;

if ($pathCategory !== '') {
    $categoryRecord = CategoryEditor::find($pathCategory);
    if ($categoryRecord === null || !$categoryRecord['active']) {
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
}

if ($sections === []) {
    if (is_array($layoutRecord) && ($layoutRecord['kind'] ?? '') === 'advanced') {
        $headline = trim((string) ($layoutRecord['title'] ?? ''));
        if ($headline === '') {
            $headline = 'Sayfa';
        }
        $pageTitle = $headline;
        $metaDescription = '';
        $canonicalPath = '/' . $slug;
        $robots = 'noindex,follow';
        header('X-Robots-Tag: noindex, follow');
        require __DIR__ . '/partials/header.php';
        render_section([
            'section_type' => 'pagehead',
            'section_key' => 'sayfa-ust',
            'content' => [
                'anchor' => 'sayfa',
                'title' => $headline,
                'image' => 'assets/images/about.jpg',
                'image_alt' => $headline,
                'crumbs' => [
                    ['label' => 'Ana Sayfa', 'url' => '/'],
                    ['label' => $headline, 'url' => ''],
                ],
            ],
        ]);
        require __DIR__ . '/partials/footer.php';
        return;
    }
    $classic = ($slug === '' || $pathCategory !== '') ? null : block_by_key($slug);
    if (is_array($classic) && ($classic['_type'] ?? '') === 'page') {
        $settings = settings();
        $headline = trim((string) ($classic['title'] ?? ''));
        if ($headline === '') {
            $headline = 'Sayfa';
        }
        $seoTitle = trim((string) ($classic['seo_title'] ?? ''));
        $seoDescription = trim((string) ($classic['seo_description'] ?? ''));
        if ($seoDescription === '') {
            $seoDescription = trim((string) ($classic['excerpt'] ?? ''));
        }
        if ($seoDescription === '') {
            $seoDescription = post_lead($classic);
        }
        if (function_exists('mb_substr')) {
            $seoDescription = mb_substr($seoDescription, 0, 160, 'UTF-8');
        } else {
            $seoDescription = substr($seoDescription, 0, 160);
        }
        $pageTitle = $seoTitle !== '' ? $seoTitle : $headline;
        $metaDescription = $seoDescription;
        $bodyClass = 'is-page is-classic';
        $canonicalPath = '/' . (string) ($classic['_key'] ?? $slug);
        $image = trim((string) ($classic['image'] ?? ''));
        if ($image !== '') {
            $shareImage = $image;
        }
        $noise = $_GET;
        unset($noise['slug']);
        $indexable = post_index_enabled($classic) && post_has_body($classic) && trim((string) ($classic['title'] ?? '')) !== '' && $noise === [];
        $robots = $indexable ? 'index,follow,max-image-preview:large' : 'noindex,follow';
        $jsonLd = [];
        if ($indexable) {
            $pageUrl = absolute_url($canonicalPath);
            $webPage = [
                '@type' => 'WebPage',
                '@id' => $pageUrl . '#sayfa',
                'url' => $pageUrl,
                'name' => $headline,
                'description' => $metaDescription,
                'inLanguage' => 'tr-TR',
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => (string) ($settings['site_name'] ?? 'Tuana Arıcılık'),
                    'url' => absolute_url('/'),
                ],
            ];
            $modified = trim((string) ($classic['_updated'] ?? ''));
            if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $modified, $stamp) === 1) {
                $webPage['dateModified'] = $stamp[1] . 'T' . $stamp[2] . '+03:00';
            }
            if ($image !== '') {
                $webPage['image'] = absolute_url('/' . ltrim($image, '/'));
            }
            $jsonLd = [
                '@context' => 'https://schema.org',
                '@graph' => [
                    $webPage,
                    [
                        '@type' => 'BreadcrumbList',
                        '@id' => $pageUrl . '#kirinti',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => absolute_url('/')],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => $headline, 'item' => $pageUrl],
                        ],
                    ],
                ],
            ];
        }
        $bannerImage = $image !== '' ? $image : 'assets/images/about.jpg';
        $bannerAlt = trim((string) ($classic['image_alt'] ?? ''));
        if ($bannerAlt === '') {
            $bannerAlt = $headline;
        }
        header('X-Robots-Tag: ' . $robots);
        require __DIR__ . '/partials/header.php';
        render_section([
            'section_type' => 'pagehead',
            'section_key' => 'sayfa-ust',
            'content' => [
                'anchor' => 'sayfa',
                'title' => $headline,
                'image' => $bannerImage,
                'image_alt' => $bannerAlt,
                'crumbs' => [
                    ['label' => 'Ana Sayfa', 'url' => '/'],
                    ['label' => $headline, 'url' => ''],
                ],
                'motion' => [
                    'stagger' => 0,
                    'distance' => 28,
                    'duration' => 760,
                    'once' => true,
                    'slots' => ['copy' => 'rise'],
                ],
            ],
        ]);
        $page = $classic;
        require __DIR__ . '/partials/classic-page.php';
        require __DIR__ . '/partials/footer.php';
        return;
    }
    $alias = $pathCategory === '' ? page_alias_key($slug) : '';
    if ($alias !== '' && $alias !== $slug) {
        header('Location: ' . absolute_url('/' . $alias), true, 301);
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

foreach ($sections as $section) {
    if (($section['section_type'] ?? '') !== 'pagehead') {
        continue;
    }
    $head = is_array($section['content'] ?? null) ? $section['content'] : [];
    if (!empty($head['title'])) {
        $pageTitle = (string) $head['title'];
    }
    if (!empty($head['description'])) {
        $metaDescription = (string) $head['description'];
    }
    $headImage = trim((string) ($head['image'] ?? ''));
    if ($headImage !== '' && preg_match('#^assets/(images|uploads)/#', $headImage) === 1) {
        $shareImage = $headImage;
    }
}

if (is_array($layoutRecord) && ($layoutRecord['kind'] ?? '') === 'advanced' && $pathCategory === '') {
    $headline = trim((string) ($layoutRecord['title'] ?? ''));
    $seoTitle = trim((string) ($layoutRecord['seo_title'] ?? ''));
    $seoDescription = trim((string) ($layoutRecord['seo_description'] ?? ''));
    if ($seoDescription === '') {
        $seoDescription = trim((string) ($layoutRecord['excerpt'] ?? ''));
    }
    if (function_exists('mb_substr')) {
        $seoDescription = mb_substr($seoDescription, 0, 160, 'UTF-8');
    } else {
        $seoDescription = substr($seoDescription, 0, 160);
    }
    if ($seoTitle !== '') {
        $pageTitle = $seoTitle;
    } elseif ($pageTitle === (string) ($settings['site_name'] ?? 'Tuana Arıcılık') && $headline !== '') {
        $pageTitle = $headline;
    }
    if ($seoDescription !== '') {
        $metaDescription = $seoDescription;
    }
    $canonicalPath = '/' . $slug;
    $noise = $_GET;
    unset($noise['slug']);
    $indexable = post_index_enabled($layoutRecord) && $headline !== '' && $sections !== [] && $noise === [];
    $robots = $indexable ? 'index,follow,max-image-preview:large' : 'noindex,follow';
    if ($indexable) {
        $pageUrl = absolute_url($canonicalPath);
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $pageUrl . '#sayfa',
                    'url' => $pageUrl,
                    'name' => $pageTitle,
                    'description' => $metaDescription,
                    'inLanguage' => 'tr-TR',
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        'name' => (string) ($settings['site_name'] ?? 'Tuana Arıcılık'),
                        'url' => absolute_url('/'),
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $pageUrl . '#kirinti',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => absolute_url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $headline !== '' ? $headline : $pageTitle, 'item' => $pageUrl],
                    ],
                ],
            ],
        ];
    }
}

$publicQuery = $_GET;
unset($publicQuery['slug'], $publicQuery['kategori']);
if ($slug === 'blog' && $pathCategory === '' && $publicQuery !== []) {
    $robots = 'noindex,follow';
}

if (is_array($categoryRecord)) {
    $name = $categoryRecord['title'];
    $seoTitle = trim((string) $categoryRecord['seo_title']);
    $seoDescription = trim((string) $categoryRecord['seo_description']);
    $pageTitle = $seoTitle !== '' ? $seoTitle : $name;
    $metaDescription = $seoDescription !== '' ? $seoDescription : $name . ' kategorisindeki yazılar.';
    $canonicalPath = category_url($pathCategory);
    $categoryPosts = filter_posts(blocks_of_type('post'), ['q' => '', 'category' => $pathCategory]);
    $noise = $_GET;
    unset($noise['slug'], $noise['kategori'], $noise['q']);
    $indexable = $categoryRecord['index'] && $categoryPosts !== [] && $filters['q'] === '' && $noise === [];
    $robots = $indexable ? 'index,follow,max-image-preview:large' : 'noindex,follow';
    if ($categoryRecord['image'] !== '') {
        $shareImage = $categoryRecord['image'];
    }
    foreach ($sections as &$section) {
        if (($section['section_type'] ?? '') !== 'pagehead' || !is_array($section['content'] ?? null)) {
            continue;
        }
        $section['content']['title'] = $name;
        $section['content']['description'] = $metaDescription;
        if ($categoryRecord['image'] !== '') {
            $section['content']['image'] = $categoryRecord['image'];
            $section['content']['image_alt'] = $categoryRecord['image_alt'] !== '' ? $categoryRecord['image_alt'] : $name;
        }
        $section['content']['crumbs'] = [
            ['label' => 'Ana Sayfa', 'url' => '/'],
            ['label' => 'Blog', 'url' => '/blog'],
            ['label' => $name, 'url' => ''],
        ];
    }
    unset($section);

    if ($indexable) {
        $items = [];
        $position = 1;
        foreach ($categoryPosts as $post) {
            if (!is_array($post)) {
                continue;
            }
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'url' => absolute_url((string) ($post['url'] ?? '/blog')),
                'name' => (string) ($post['title'] ?? ''),
            ];
            $position++;
        }
        $pageUrl = absolute_url($canonicalPath);
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => $pageUrl . '#kategori',
                    'url' => $pageUrl,
                    'name' => $name,
                    'description' => $metaDescription,
                    'inLanguage' => 'tr-TR',
                    'isPartOf' => [
                        '@type' => 'Blog',
                        'name' => 'Blog',
                        'url' => absolute_url('/blog'),
                    ],
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'itemListElement' => $items,
                    ],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $pageUrl . '#kirinti',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana Sayfa', 'item' => absolute_url('/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => absolute_url('/blog')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => $pageUrl],
                    ],
                ],
            ],
        ];
    }
}

header('X-Robots-Tag: ' . $robots);
require __DIR__ . '/partials/header.php';
render_page($sections);
require __DIR__ . '/partials/footer.php';
