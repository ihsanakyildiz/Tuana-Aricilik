<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/ProductEditor.php';
require_once __DIR__ . '/app/MemberAccount.php';

public_maintenance();

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/urun/([a-z0-9\-]+)/$#', $rawPath, $slash) === 1) {
    header('Location: ' . absolute_url('/urun/' . $slash[1]), true, 301);
    exit;
}

$slug = request_slug();
$product = $slug === '' ? null : ProductEditor::open($slug);
if ($product === null || !$product['active']) {
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

$content = is_array($product['content'] ?? null) ? $product['content'] : [];
$price = (string) ($content['price'] ?? '');
$discount = (string) ($content['discount_price'] ?? '');
$selling = $discount !== '' ? $discount : $price;
$category = $product['category_key'] !== '' ? ProductCategoryEditor::open((string) $product['category_key']) : null;
$categoryTitle = is_array($category) ? $category['title'] : '';
$categoryUrl = is_array($category) && $category['active'] ? '/urunler/kategori/' . $category['key'] : '';
$units = ProductEditor::units();
$unitLabel = (string) ($units[$product['unit']] ?? '');
$available = !$product['stock_track'] || (int) $product['stock_qty'] > 0;
$gallery = is_array($product['gallery'] ?? null) ? $product['gallery'] : [];
$excerpt = trim((string) $product['excerpt']);
$body = trim((string) $product['body']);
$plain = trim(preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
$excerptFlat = trim(preg_replace('/\s+/u', ' ', $excerpt) ?? '');
$description = $excerptFlat !== '' ? $excerptFlat : $plain;
if (mb_strlen($description, 'UTF-8') > 160) {
    $description = rtrim(mb_substr($description, 0, 157, 'UTF-8')) . '…';
}
$schemaDescription = $excerptFlat !== '' ? $excerptFlat : $plain;
if (mb_strlen($schemaDescription, 'UTF-8') > 2000) {
    $schemaDescription = rtrim(mb_substr($schemaDescription, 0, 1997, 'UTF-8')) . '…';
}

$pageTitle = (string) $product['title'];
$metaDescription = $description;
$canonicalPath = '/urun/' . $product['key'];
$bodyClass = 'is-page is-shop is-product';
$robots = 'index,follow';
header('X-Robots-Tag: index, follow');
$image = trim((string) $product['image']);
$imageUrls = [];
foreach ($gallery as $shot) {
    if (!is_array($shot)) {
        continue;
    }
    $shotImage = trim((string) ($shot['image'] ?? ''));
    if ($shotImage !== '') {
        $imageUrls[] = absolute_url('/' . ltrim($shotImage, '/'));
    }
}
if ($imageUrls === [] && $image !== '') {
    $imageUrls[] = absolute_url('/' . ltrim($image, '/'));
}
if ($image !== '') {
    $shareImage = $image;
}
$stageImage = '';
foreach ($gallery as $shot) {
    if (is_array($shot) && trim((string) ($shot['image'] ?? '')) !== '') {
        $stageImage = trim((string) $shot['image']);
        break;
    }
}
if ($stageImage === '') {
    $stageImage = $image;
}
$preloadImage = $stageImage;
$ogType = 'product';
$productOffer = preg_match('/^\d+\.\d{2}$/', $selling) === 1 ? ['price' => $selling] : [];
$pageUrl = absolute_url($canonicalPath);
$siteName = (string) setting('site_name', 'Tuana Arıcılık');
$productNode = [
    '@type' => 'Product',
    '@id' => $pageUrl . '#urun',
    'name' => (string) $product['title'],
    'url' => $pageUrl,
    'brand' => [
        '@type' => 'Brand',
        'name' => $siteName,
    ],
];
if ($schemaDescription !== '') {
    $productNode['description'] = $schemaDescription;
}
if ($imageUrls !== []) {
    $productNode['image'] = $imageUrls;
}
if ((string) $product['sku'] !== '') {
    $productNode['sku'] = (string) $product['sku'];
}
if ($categoryTitle !== '') {
    $productNode['category'] = $categoryTitle;
}
if ($productOffer !== []) {
    $productNode['offers'] = [
        '@type' => 'Offer',
        'url' => $pageUrl,
        'priceCurrency' => 'TRY',
        'price' => $selling,
        'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'itemCondition' => 'https://schema.org/NewCondition',
        'seller' => [
            '@type' => 'Organization',
            'name' => $siteName,
        ],
    ];
}
$crumbNodes = [];
$crumbPosition = 1;
foreach ([
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Ürünler', 'url' => '/urunler'],
] as $crumb) {
    $crumbNodes[] = [
        '@type' => 'ListItem',
        'position' => $crumbPosition,
        'name' => $crumb['label'],
        'item' => absolute_url($crumb['url']),
    ];
    $crumbPosition++;
}
if ($categoryTitle !== '' && $categoryUrl !== '') {
    $crumbNodes[] = [
        '@type' => 'ListItem',
        'position' => $crumbPosition,
        'name' => $categoryTitle,
        'item' => absolute_url($categoryUrl),
    ];
    $crumbPosition++;
}
$crumbNodes[] = [
    '@type' => 'ListItem',
    'position' => $crumbPosition,
    'name' => (string) $product['title'],
    'item' => $pageUrl,
];
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        $productNode,
        [
            '@type' => 'BreadcrumbList',
            '@id' => $pageUrl . '#kirinti',
            'itemListElement' => $crumbNodes,
        ],
    ],
];
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Ürünler', 'url' => '/urunler'],
];
if ($categoryTitle !== '' && $categoryUrl !== '') {
    $shopCrumbs[] = ['label' => $categoryTitle, 'url' => $categoryUrl];
}
$shopCrumbs[] = ['label' => (string) $product['title'], 'url' => ''];

$related = [];
foreach (ProductEditor::all() as $row) {
    if (!$row['active'] || $row['key'] === $product['key'] || $row['category_key'] !== $product['category_key']) {
        continue;
    }
    $related[] = $row;
    if (count($related) === 3) {
        break;
    }
}

$imageAttrs = static function (string $path): string {
    $relative = ltrim(str_replace('\\', '/', $path), '/');
    if ($relative === '' || str_contains($relative, '..') || preg_match('#^assets/(images|uploads)/[A-Za-z0-9_./-]+$#', $relative) !== 1) {
        return '';
    }
    $file = __DIR__ . '/' . $relative;
    if (!is_file($file)) {
        return '';
    }
    $size = @getimagesize($file);
    if (!is_array($size) || (int) $size[0] < 1 || (int) $size[1] < 1) {
        return '';
    }

    return ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"';
};
$discountRate = 0;
if ($discount !== '' && (float) $price > 0 && (float) $selling > 0 && (float) $selling < (float) $price) {
    $discountRate = (int) round((1 - ((float) $selling / (float) $price)) * 100);
}
$facts = [];
$amountLabel = trim($product['amount'] . ($product['amount'] !== '' && $unitLabel !== '' ? ' ' : '') . $unitLabel);
if ($amountLabel !== '') {
    $facts[] = $amountLabel;
}
if ((string) $product['sku'] !== '') {
    $facts[] = 'Stok kodu ' . (string) $product['sku'];
}

require __DIR__ . '/partials/header.php';
?>
<div class="page-canvas">
    <article class="shop-detail-wrap">
        <div class="container">
            <?php if ($shopCrumbs !== []): ?>
                <nav class="shop-crumbs" aria-label="Konum">
                    <?php foreach ($shopCrumbs as $index => $crumb): ?>
                        <?php if (!is_array($crumb)) { continue; } ?>
                        <?php if ($index > 0): ?><span class="sep" aria-hidden="true">›</span><?php endif; ?>
                        <?php $crumbUrl = trim((string) ($crumb['url'] ?? '')); ?>
                        <?php if ($crumbUrl !== ''): ?>
                            <a href="<?= url($crumbUrl) ?>"><?= e((string) ($crumb['label'] ?? '')) ?></a>
                        <?php else: ?>
                            <span aria-current="page"><?= e((string) ($crumb['label'] ?? '')) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="shop-detail">
                <div class="shop-gallery">
                    <?php if ($gallery === []): ?>
                        <span class="shop-photo-empty shop-photo-empty-lg" aria-hidden="true"></span>
                    <?php elseif (count($gallery) === 1): ?>
                        <?= media_tag((string) $gallery[0]['image'], (string) ($gallery[0]['alt'] !== '' ? $gallery[0]['alt'] : $product['title']), 'shop-stage-photo', false, ' fetchpriority="high"' . $imageAttrs((string) $gallery[0]['image'])) ?>
                    <?php else: ?>
                        <?php foreach ($gallery as $index => $shot): ?>
                            <input id="shot-<?= (int) $index ?>" name="shot" type="radio"<?= $index === 0 ? ' checked' : '' ?>>
                        <?php endforeach; ?>
                        <div class="shop-stage">
                            <?php foreach ($gallery as $index => $shot): ?>
                                <?= media_tag((string) $shot['image'], (string) ($shot['alt'] !== '' ? $shot['alt'] : $product['title']), '', $index !== 0, ($index === 0 ? ' fetchpriority="high"' : '') . $imageAttrs((string) $shot['image'])) ?>
                            <?php endforeach; ?>
                        </div>
                        <div class="shop-thumbs">
                            <?php foreach ($gallery as $index => $shot): ?>
                                <label for="shot-<?= (int) $index ?>">
                                    <?= media_tag((string) $shot['image'], '') ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="shop-buy-card">
                    <?php if ($categoryTitle !== '' && $categoryUrl !== ''): ?>
                        <p class="eyebrow"><a href="<?= url($categoryUrl) ?>"><?= e($categoryTitle) ?></a></p>
                    <?php elseif ($categoryTitle !== ''): ?>
                        <p class="eyebrow"><?= e($categoryTitle) ?></p>
                    <?php endif; ?>
                    <h1><?= e((string) $product['title']) ?></h1>
                    <?php if ($excerpt !== ''): ?><p class="lead"><?= e($excerpt) ?></p><?php endif; ?>
                    <p class="shop-price shop-price-lg">
                        <?= e(ProductEditor::moneyLabel($selling)) ?>
                        <?php if ($discount !== ''): ?><s><?= e(ProductEditor::moneyLabel($price)) ?></s><?php endif; ?>
                        <?php if ($discountRate >= 1): ?><span class="shop-off<?= $discountRate >= 20 ? ' is-hot' : '' ?>">%<?= e((string) $discountRate) ?></span><?php endif; ?>
                    </p>
                    <?php if ($facts !== []): ?>
                        <ul class="shop-facts">
                            <?php foreach ($facts as $fact): ?><li><?= e($fact) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <p class="shop-stock<?= $available ? '' : ' is-out' ?>"><?= $available ? 'Stokta' : 'Tükendi' ?></p>
                    <?php if ($available): ?>
                        <a class="btn" href="<?= url('/siparis?urun=' . rawurlencode((string) $product['key'])) ?>">Satın al</a>
                        <p class="shop-meta">Üye olmadan da sipariş verebilirsin.</p>
                    <?php else: ?>
                        <span class="btn shop-sold">Tükendi</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($body !== ''): ?>
                <section class="shop-story">
                    <h2>Ürün bilgisi</h2>
                    <div class="shop-copy">
                        <?= post_body_html($body) ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        <?php if ($related !== []): ?>
            <div class="container shop-related">
                <h2>Aynı kategoriden</h2>
                <div class="shop-grid">
                    <?php $cardTag = 'h3'; ?>
                    <?php foreach ($related as $card): ?>
                        <?php require __DIR__ . '/partials/shop-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </article>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
