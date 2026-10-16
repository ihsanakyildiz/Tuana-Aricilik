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
$description = $excerpt !== '' ? $excerpt : trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
if (function_exists('mb_substr')) {
    $description = mb_substr($description, 0, 160, 'UTF-8');
} else {
    $description = substr($description, 0, 160);
}

$pageTitle = (string) $product['title'];
$metaDescription = $description;
$canonicalPath = '/urun/' . $product['key'];
$bodyClass = 'is-page is-shop';
$robots = 'index,follow';
$image = trim((string) $product['image']);
if ($image !== '') {
    $shareImage = $image;
}
$offerPrice = $selling;
$productLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => (string) $product['title'],
    'description' => $description,
    'image' => $image !== '' ? absolute_url('/' . ltrim($image, '/')) : absolute_url('/'),
    'offers' => [
        '@type' => 'Offer',
        'priceCurrency' => 'TRY',
        'price' => $offerPrice,
        'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => absolute_url($canonicalPath),
    ],
];
if ((string) $product['sku'] !== '') {
    $productLd['sku'] = (string) $product['sku'];
}
$jsonLd = [$productLd];
$shopTitle = (string) $product['title'];
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

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <article class="shop-detail-wrap">
        <div class="container shop-detail">
            <div class="shop-gallery">
                <?php if ($gallery === []): ?>
                    <span class="shop-photo-empty shop-photo-empty-lg" aria-hidden="true"></span>
                <?php elseif (count($gallery) === 1): ?>
                    <?= media_tag((string) $gallery[0]['image'], (string) ($gallery[0]['alt'] !== '' ? $gallery[0]['alt'] : $product['title']), 'shop-stage-photo', false) ?>
                <?php else: ?>
                    <?php foreach ($gallery as $index => $shot): ?>
                        <input id="shot-<?= (int) $index ?>" name="shot" type="radio"<?= $index === 0 ? ' checked' : '' ?>>
                    <?php endforeach; ?>
                    <div class="shop-stage">
                        <?php foreach ($gallery as $shot): ?>
                            <?= media_tag((string) $shot['image'], (string) ($shot['alt'] !== '' ? $shot['alt'] : $product['title']), '', false) ?>
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
            <div class="shop-buy">
                <?php if ($categoryTitle !== ''): ?><p class="eyebrow"><?= e($categoryTitle) ?></p><?php endif; ?>
                <h2><?= e((string) $product['title']) ?></h2>
                <?php if ($excerpt !== ''): ?><p class="lead"><?= e($excerpt) ?></p><?php endif; ?>
                <p class="shop-price shop-price-lg">
                    <?= e(ProductEditor::moneyLabel($selling)) ?>
                    <?php if ($discount !== ''): ?><s><?= e(ProductEditor::moneyLabel($price)) ?></s><?php endif; ?>
                </p>
                <?php if ($product['amount'] !== '' || $unitLabel !== ''): ?>
                    <p class="shop-meta"><?= e(trim($product['amount'] . ($product['amount'] !== '' && $unitLabel !== '' ? ' · ' : '') . $unitLabel)) ?></p>
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
            <div class="container shop-copy">
                <?= post_body_html($body) ?>
            </div>
        <?php endif; ?>
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
