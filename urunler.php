<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/ProductEditor.php';
require_once __DIR__ . '/app/MemberAccount.php';

public_maintenance();

function shop_clip(string $value, int $max): string
{
    $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }

    return substr($value, 0, $max);
}

function shop_money(string $value): ?string
{
    $value = str_replace([' ', '₺', 'TL', 'tl'], '', trim($value));
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d{1,3}(?:\.\d{3})+),(\d{1,2})$/', $value, $match) === 1) {
        $whole = str_replace('.', '', $match[1]);
        $fraction = str_pad($match[2], 2, '0');
    } elseif (preg_match('/^(\d{1,3}(?:\.\d{3})+)$/', $value, $match) === 1) {
        $whole = str_replace('.', '', $match[1]);
        $fraction = '00';
    } elseif (preg_match('/^(\d{1,7}),(\d{1,2})$/', $value, $match) === 1 || preg_match('/^(\d{1,7})\.(\d{1,2})$/', $value, $match) === 1) {
        $whole = $match[1];
        $fraction = str_pad($match[2], 2, '0');
    } elseif (preg_match('/^(\d{1,7})$/', $value, $match) === 1) {
        $whole = $match[1];
        $fraction = '00';
    } else {
        return null;
    }
    $whole = ltrim($whole, '0');
    if ($whole === '') {
        $whole = '0';
    }
    if (strlen($whole) > 7) {
        return null;
    }

    return $whole . '.' . $fraction;
}

function shop_money_field(string $canonical): string
{
    if ($canonical === '' || !str_contains($canonical, '.')) {
        return '';
    }
    [$whole, $fraction] = explode('.', $canonical, 2);
    $whole = number_format((int) $whole, 0, '', '.');

    return $fraction === '00' ? $whole : $whole . ',' . $fraction;
}

function shop_query(array $params): string
{
    $kept = [];
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $kept[$key] = $value;
    }
    if ($kept === []) {
        return '';
    }

    return '?' . http_build_query($kept);
}

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/urunler/kategori/([a-z0-9\-]+)/$#', $rawPath, $slash) === 1) {
    header('Location: ' . absolute_url('/urunler/kategori/' . $slash[1]), true, 301);
    exit;
}
if (preg_match('#/urunler/$#', $rawPath) === 1) {
    header('Location: ' . absolute_url('/urunler'), true, 301);
    exit;
}

$filter = strtolower(trim((string) ($_GET['kategori'] ?? '')));
$categoryTitle = '';
if ($filter !== '') {
    $category = ProductCategoryEditor::open($filter);
    if ($category === null || !$category['active']) {
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
    $categoryTitle = $category['title'];
}

$catalogPath = $filter === '' ? '/urunler' : '/urunler/kategori/' . $filter;
$allowedSort = ['eski', 'artan', 'azalan', 'ad'];
$allowedStock = ['var', 'yok'];
$query = [];
$dirty = false;
foreach (['q', 'min', 'max', 'stok', 'sira', 'indirim'] as $key) {
    if (!array_key_exists($key, $_GET)) {
        continue;
    }
    $value = shop_clip((string) $_GET[$key], $key === 'q' ? 80 : 20);
    $drop = $value === ''
        || ($key === 'sira' && ($value === 'yeni' || !in_array($value, $allowedSort, true)))
        || ($key === 'stok' && !in_array($value, $allowedStock, true))
        || ($key === 'indirim' && $value !== '1');
    if ($drop) {
        $dirty = true;
        continue;
    }
    $query[$key] = $value;
}
if ($dirty) {
    header('Location: ' . absolute_url($catalogPath . shop_query($query)), true, 302);
    exit;
}

$search = $query['q'] ?? '';
$minRaw = $query['min'] ?? '';
$maxRaw = $query['max'] ?? '';
$min = shop_money($minRaw);
$max = shop_money($maxRaw);
$priceError = ($minRaw !== '' && $min === null) || ($maxRaw !== '' && $max === null);
$floor = !$priceError && $min !== null && $min !== '' ? (float) $min : null;
$ceiling = !$priceError && $max !== null && $max !== '' ? (float) $max : null;
if ($floor !== null && $ceiling !== null && $floor > $ceiling) {
    [$floor, $ceiling] = [$ceiling, $floor];
}
$stock = $query['stok'] ?? '';
$sort = $query['sira'] ?? 'yeni';
$saleOnly = ($query['indirim'] ?? '') === '1';
$hasFilter = $search !== '' || $minRaw !== '' || $maxRaw !== '' || $stock !== '' || $sort !== 'yeni' || $saleOnly;

$categories = [];
foreach (ProductCategoryEditor::all() as $category) {
    if ($category['active']) {
        $categories[] = $category;
    }
}
$published = array_values(array_filter(
    ProductEditor::all(),
    static fn (array $row): bool => $row['active']
));
$counts = [];
foreach ($published as $product) {
    $key = (string) $product['category_key'];
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

$products = $published;
if ($filter !== '') {
    $products = array_values(array_filter(
        $products,
        static fn (array $row): bool => $row['category_key'] === $filter
    ));
}
if ($search !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);
    $products = array_values(array_filter(
        $products,
        static function (array $row) use ($needle): bool {
            $title = (string) $row['title'];
            $haystack = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);

            return str_contains($haystack, $needle);
        }
    ));
}
if ($floor !== null) {
    $products = array_values(array_filter(
        $products,
        static function (array $row) use ($floor): bool {
            $selling = $row['discount_price'] !== '' ? (float) $row['discount_price'] : (float) $row['price'];

            return $selling >= $floor;
        }
    ));
}
if ($ceiling !== null) {
    $products = array_values(array_filter(
        $products,
        static function (array $row) use ($ceiling): bool {
            $selling = $row['discount_price'] !== '' ? (float) $row['discount_price'] : (float) $row['price'];

            return $selling <= $ceiling;
        }
    ));
}
if ($stock === 'var') {
    $products = array_values(array_filter(
        $products,
        static fn (array $row): bool => !$row['stock_track'] || (int) $row['stock_qty'] > 0
    ));
} elseif ($stock === 'yok') {
    $products = array_values(array_filter(
        $products,
        static fn (array $row): bool => $row['stock_track'] && (int) $row['stock_qty'] < 1
    ));
}
if ($saleOnly) {
    $products = array_values(array_filter(
        $products,
        static fn (array $row): bool => $row['discount_price'] !== ''
    ));
}
usort($products, static function (array $left, array $right) use ($sort): int {
    $leftPrice = $left['discount_price'] !== '' ? (float) $left['discount_price'] : (float) $left['price'];
    $rightPrice = $right['discount_price'] !== '' ? (float) $right['discount_price'] : (float) $right['price'];
    $compare = match ($sort) {
        'eski' => ((int) $left['id']) <=> ((int) $right['id']),
        'artan' => $leftPrice <=> $rightPrice,
        'azalan' => $rightPrice <=> $leftPrice,
        'ad' => strcasecmp((string) $left['title'], (string) $right['title']),
        default => ((int) $right['id']) <=> ((int) $left['id']),
    };
    if ($compare === 0) {
        $compare = ((int) $right['id']) <=> ((int) $left['id']);
    }

    return $compare;
});

$filterQuery = [
    'q' => $search,
    'min' => $minRaw,
    'max' => $maxRaw,
    'stok' => $stock,
    'sira' => $sort === 'yeni' ? '' : $sort,
    'indirim' => $saleOnly ? '1' : '',
];
$clearUrl = $catalogPath;
$resultCount = count($products);

$pageTitle = $categoryTitle !== '' ? $categoryTitle : 'Ürünler';
$metaDescription = $categoryTitle !== ''
    ? $categoryTitle . ' ürünleri'
    : 'Tuana Arıcılık ürünleri';
$canonicalPath = $catalogPath;
$bodyClass = 'is-page is-shop';
$robots = $hasFilter ? 'noindex,follow' : 'index,follow';
if ($hasFilter) {
    header('X-Robots-Tag: noindex, follow');
}
$shopTitle = $pageTitle;
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Ürünler', 'url' => $filter === '' ? '' : '/urunler'],
];
if ($filter !== '') {
    $shopCrumbs[] = ['label' => $categoryTitle, 'url' => ''];
}

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container shop-layout">
            <aside class="shop-side" aria-label="Mağaza süzgeci">
                <section class="shop-panel">
                    <h2>Kategoriler</h2>
                    <ul class="shop-cats">
                        <li>
                            <a href="<?= url('/urunler' . shop_query($filterQuery)) ?>"<?= $filter === '' ? ' aria-current="page"' : '' ?>>
                                <span>Tümü</span>
                                <em><?= count($published) ?></em>
                            </a>
                        </li>
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a href="<?= url('/urunler/kategori/' . $category['key'] . shop_query($filterQuery)) ?>"<?= $filter === $category['key'] ? ' aria-current="page"' : '' ?>>
                                    <span><?= e($category['title']) ?></span>
                                    <em><?= (int) ($counts[$category['key']] ?? 0) ?></em>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <section class="shop-panel">
                    <h2>Filtreler</h2>
                    <form class="shop-filters" method="get" action="<?= url($catalogPath) ?>">
                        <label class="shop-field">
                            <span>Ara</span>
                            <input type="search" name="q" value="<?= e($search) ?>" maxlength="80" placeholder="Ürün adı">
                        </label>
                        <div class="shop-price-range">
                            <label class="shop-field">
                                <span>En düşük fiyat</span>
                                <input type="text" name="min" inputmode="decimal" value="<?= e($min !== null && $min !== '' ? shop_money_field($min) : $minRaw) ?>" placeholder="Örn. 500" autocomplete="off">
                            </label>
                            <label class="shop-field">
                                <span>En yüksek fiyat</span>
                                <input type="text" name="max" inputmode="decimal" value="<?= e($max !== null && $max !== '' ? shop_money_field($max) : $maxRaw) ?>" placeholder="Örn. 5.000" autocomplete="off">
                            </label>
                        </div>
                        <?php if ($priceError): ?>
                            <p class="shop-filter-error">Fiyat aralığı sayı olmalı. Örnek: 850 veya 1.250,50</p>
                        <?php endif; ?>
                        <label class="shop-field">
                            <span>Stok</span>
                            <select name="stok">
                                <option value="">Fark etmez</option>
                                <option value="var"<?= $stock === 'var' ? ' selected' : '' ?>>Stokta</option>
                                <option value="yok"<?= $stock === 'yok' ? ' selected' : '' ?>>Tükendi</option>
                            </select>
                        </label>
                        <label class="shop-field">
                            <span>Sıralama</span>
                            <select name="sira">
                                <option value="yeni">Yeniden eskiye</option>
                                <option value="eski"<?= $sort === 'eski' ? ' selected' : '' ?>>Eskiden yeniye</option>
                                <option value="artan"<?= $sort === 'artan' ? ' selected' : '' ?>>Fiyat artan</option>
                                <option value="azalan"<?= $sort === 'azalan' ? ' selected' : '' ?>>Fiyat azalan</option>
                                <option value="ad"<?= $sort === 'ad' ? ' selected' : '' ?>>Ada göre</option>
                            </select>
                        </label>
                        <label class="shop-check">
                            <input type="checkbox" name="indirim" value="1"<?= $saleOnly ? ' checked' : '' ?>>
                            <span>Yalnızca indirimli ürünler</span>
                        </label>
                        <div class="shop-filter-actions">
                            <button class="btn btn-sm" type="submit">Uygula</button>
                            <?php if ($hasFilter): ?>
                                <a class="btn btn-line btn-sm" href="<?= url($clearUrl) ?>">Temizle</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
            </aside>
            <div class="shop-main">
                <div class="shop-bar">
                    <p><?= $resultCount === 1 ? '1 ürün' : e((string) $resultCount) . ' ürün' ?></p>
                </div>
                <?php if ($products === []): ?>
                    <p class="blog-empty"><?= $hasFilter || $filter !== '' ? 'Bu süzgece uyan ürün yok.' : 'Bu listede henüz yayında ürün yok.' ?></p>
                <?php else: ?>
                    <div class="shop-grid">
                        <?php foreach ($products as $card): ?>
                            <?php require __DIR__ . '/partials/shop-card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
