<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/ProductEditor.php';

Auth::boot();
Auth::requireLogin();

$errors = [];
$mode = 'list';
$product = null;
$posted = [
    'title' => '',
    'slug' => '',
    'status' => 'draft',
    'excerpt' => '',
    'body' => '',
    'category_key' => '',
    'sku' => '',
    'price' => '',
    'discount_price' => '',
    'unit' => 'adet',
    'amount' => '',
    'stock_track' => true,
    'stock_qty' => '0',
    'key' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
        $mode = $action === 'save' ? 'editor' : 'list';
    } elseif ($action === 'save') {
        $saved = ProductEditor::save($_POST, $_FILES);
        $errors = $saved['errors'];
        $posted = $saved['values'];
        if ($errors === []) {
            Auth::flash('ok', $posted['key'] === '' ? 'Ürün eklendi.' : 'Ürün kaydedildi.');
            Auth::redirect('admin/urunler.php?urun=' . rawurlencode($saved['key']));
        }
        $mode = 'editor';
        if ($saved['key'] !== '') {
            $product = ProductEditor::open($saved['key']);
        }
    } elseif ($action === 'delete') {
        $key = is_string($_POST['product_key'] ?? null) ? $_POST['product_key'] : '';
        $errors = ProductEditor::remove($key);
        if ($errors === []) {
            Auth::flash('ok', 'Ürün silindi.');
            Auth::redirect('admin/urunler.php');
        }
    } elseif ($action === 'delete-many') {
        $keys = $_POST['product_key'] ?? [];
        $keys = is_array($keys) ? $keys : [];
        $removed = 0;
        foreach ($keys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $result = ProductEditor::remove($key);
            if ($result === []) {
                $removed++;
            }
        }
        if ($removed > 0) {
            Auth::flash('ok', $removed === 1 ? 'Ürün silindi.' : $removed . ' ürün silindi.');
            Auth::redirect('admin/urunler.php');
        }
        $errors[] = 'Silinecek ürün seçilmedi.';
    } elseif ($action === 'toggle') {
        $key = is_string($_POST['product_key'] ?? null) ? $_POST['product_key'] : '';
        ProductEditor::setActive($key, isset($_POST['active']));
        Auth::redirect('admin/urunler.php');
    } elseif ($action === 'move') {
        $key = is_string($_POST['product_key'] ?? null) ? $_POST['product_key'] : '';
        ProductEditor::move($key, (int) ($_POST['direction'] ?? 0));
        Auth::redirect('admin/urunler.php');
    }
}

if ($mode === 'list' && isset($_GET['yeni'])) {
    $mode = 'editor';
} elseif ($mode === 'list' && is_string($_GET['urun'] ?? null) && $_GET['urun'] !== '') {
    $product = ProductEditor::open($_GET['urun']);
    if ($product === null) {
        $errors[] = 'Ürün bulunamadı.';
    } else {
        $mode = 'editor';
        $posted = [
            'title' => $product['title'],
            'slug' => $product['key'],
            'status' => $product['active'] ? 'publish' : 'draft',
            'excerpt' => $product['excerpt'],
            'body' => $product['body'],
            'category_key' => $product['category_key'],
            'sku' => $product['sku'],
            'price' => $product['price'],
            'discount_price' => $product['discount_price'],
            'unit' => $product['unit'],
            'amount' => $product['amount'],
            'stock_track' => $product['stock_track'],
            'stock_qty' => (string) $product['stock_qty'],
            'key' => $product['key'],
        ];
    }
}

$categories = ProductCategoryEditor::all();
$filter = $mode === 'list' && is_string($_GET['kategori'] ?? null) ? $_GET['kategori'] : '';
$rows = ProductEditor::all();
$units = ProductEditor::units();
$gallery = is_array($product) && is_array($product['gallery'] ?? null) ? $product['gallery'] : [];
$pageTitle = $mode === 'editor' ? ($posted['key'] === '' ? 'Yeni ürün' : 'Ürünü düzenle') : 'Ürünler';
$crumbCurrent = 'Ürünler';
$activeNav = 'urunler';

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($mode === 'editor'): ?>
    <p class="slide-back"><a href="<?= root_url('admin/urunler.php') ?>">Ürünlere dön</a></p>
    <?php if ($categories === []): ?>
        <div class="form-alert" role="status">
            <p>Ürün kaydetmek için önce bir <a href="<?= root_url('admin/urun-kategorileri.php?yeni=1') ?>">ürün kategorisi</a> ekle.</p>
        </div>
    <?php endif; ?>
    <form class="post-editor product-editor" method="post" action="<?= root_url('admin/urunler.php') ?>" enctype="multipart/form-data" data-post-editor>
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="product_key" value="<?= e((string) $posted['key']) ?>">
        <div class="post-main">
            <section class="settings-card">
                <label class="field post-title-field" for="product-title">
                    <span>Ürün adı</span>
                    <input class="post-title-input" id="product-title" name="title" type="text" maxlength="140" value="<?= e((string) $posted['title']) ?>" data-post-title required>
                </label>
                <label class="field" for="product-slug">
                    <span>Adres</span>
                    <input id="product-slug" name="slug" type="text" maxlength="64" value="<?= e((string) $posted['slug']) ?>" placeholder="Boşsa addan yazılır" data-post-slug data-auto="<?= $posted['key'] === '' ? '1' : '0' ?>">
                </label>
                <p class="field-hint">Yayındaki ürün bu adresle sitede açılır.</p>
                <label class="field" for="product-excerpt">
                    <span>Kısa açıklama <em class="seo-count" data-count-for="product-excerpt">0/300</em></span>
                    <textarea id="product-excerpt" name="excerpt" maxlength="300" rows="3" data-limit="300"><?= e((string) $posted['excerpt']) ?></textarea>
                </label>
            </section>
            <section class="settings-card">
                <h2>Açıklama</h2>
                <div class="field">
                    <span id="product-body-label">Ürün metni</span>
                    <textarea class="post-body" id="post-body" name="body" rows="14" aria-labelledby="product-body-label" data-editor data-placeholder="Ürün açıklamasını buraya yazın" data-upload="<?= e(root_url('admin/urun-gorsel.php')) ?>" data-csrf="<?= e(Auth::csrfToken()) ?>"><?= e((string) $posted['body']) ?></textarea>
                </div>
                <p class="field-hint">Başlık, liste, tablo, bağlantı ve görsel ekleyebilirsin. Kayıtta güvenli olmayan kod ayıklanır.</p>
            </section>
            <section class="settings-card">
                <h2>Görsel galerisi</h2>
                <p class="settings-lead">İlk görsel kapaktır. Sırayı oklarla değiştir, açıklamayı görselin yanında yaz. En fazla 8 görsel.</p>
                <div class="product-gallery" data-gallery>
                    <?php foreach ($gallery as $shot): ?>
                        <article class="product-shot" data-gallery-item>
                            <img src="<?= root_url((string) $shot['image']) ?>" alt="">
                            <input type="hidden" name="gallery_id[]" value="<?= e((string) $shot['id']) ?>">
                            <label class="field">
                                <span>Görsel açıklaması</span>
                                <input name="gallery_alt[]" type="text" maxlength="140" value="<?= e((string) $shot['alt']) ?>">
                            </label>
                            <div class="slide-actions">
                                <button type="button" data-gallery-up aria-label="Yukarı taşı">↑</button>
                                <button type="button" data-gallery-down aria-label="Aşağı taşı">↓</button>
                                <button type="button" data-gallery-remove>Kaldır</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($gallery === []): ?>
                    <p class="field-hint">Henüz galeri görseli yok.</p>
                <?php endif; ?>
                <div class="field">
                    <span>Yeni görseller</span>
                    <label class="file-pick">
                        <input id="product-gallery" name="gallery_new[]" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" multiple>
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
            </section>
        </div>
        <aside class="post-side">
            <section class="settings-card">
                <h2>Yayın</h2>
                <label class="field" for="product-status">
                    <span>Durum</span>
                    <select id="product-status" name="status">
                        <option value="draft"<?= $posted['status'] !== 'publish' ? ' selected' : '' ?>>Taslak</option>
                        <option value="publish"<?= $posted['status'] === 'publish' ? ' selected' : '' ?>>Yayında</option>
                    </select>
                </label>
                <div class="settings-save post-publish">
                    <a class="login-submit slide-add" href="<?= root_url('admin/urunler.php') ?>">Vazgeç</a>
                    <button class="login-submit" type="submit"><?= $posted['key'] === '' ? 'Ürün ekle' : 'Ürünü kaydet' ?></button>
                </div>
                <?php if ($posted['key'] !== '' && $posted['status'] === 'publish'): ?>
                    <p class="field-hint"><a href="<?= root_url('/urun/' . $posted['key']) ?>" target="_blank" rel="noopener">Ürünü sitede gör</a></p>
                <?php endif; ?>
            </section>
            <section class="settings-card">
                <h2>Kategori</h2>
                <?php if ($categories === []): ?>
                    <p class="field-hint">Henüz ürün kategorisi yok.</p>
                <?php else: ?>
                    <label class="field" for="product-category">
                        <span>Ürün kategorisi</span>
                        <select id="product-category" name="category_key" required>
                            <option value="">Seç</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e($category['key']) ?>"<?= $posted['category_key'] === $category['key'] ? ' selected' : '' ?>><?= e($category['title']) ?><?= $category['active'] ? '' : ' (gizli)' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
            </section>
            <section class="settings-card">
                <h2>Fiyat</h2>
                <label class="field" for="product-price">
                    <span>Satış fiyatı (TL)</span>
                    <input id="product-price" name="price" type="text" inputmode="decimal" maxlength="16" value="<?= e((string) $posted['price']) ?>" placeholder="450,00" required>
                </label>
                <label class="field" for="product-discount">
                    <span>İndirimli satış fiyatı (TL)</span>
                    <input id="product-discount" name="discount_price" type="text" inputmode="decimal" maxlength="16" value="<?= e((string) $posted['discount_price']) ?>" placeholder="Boş bırakılabilir">
                </label>
                <p class="field-hint">İndirimli fiyat doluysa müşteri onu öder. Satış fiyatı üstü çizili görünür.</p>
                <div class="fields-2">
                    <label class="field" for="product-unit">
                        <span>Satış birimi</span>
                        <select id="product-unit" name="unit">
                            <?php foreach ($units as $unitId => $unitLabel): ?>
                                <option value="<?= e($unitId) ?>"<?= $posted['unit'] === $unitId ? ' selected' : '' ?>><?= e($unitLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="product-amount">
                        <span>Net miktar</span>
                        <input id="product-amount" name="amount" type="text" maxlength="40" value="<?= e((string) $posted['amount']) ?>" placeholder="850 g">
                    </label>
                </div>
            </section>
            <section class="settings-card">
                <h2>Stok</h2>
                <label class="field" for="product-sku">
                    <span>Stok kodu</span>
                    <input id="product-sku" name="sku" type="text" maxlength="32" value="<?= e((string) $posted['sku']) ?>" placeholder="BAL-850">
                </label>
                <label class="check" for="stock-track"><input id="stock-track" name="stock_track" type="checkbox"<?= !empty($posted['stock_track']) ? ' checked' : '' ?>> <span>Stok takibi yap</span></label>
                <label class="field only-stock" for="product-stock">
                    <span>Stok adedi</span>
                    <input id="product-stock" name="stock_qty" type="number" min="0" max="999999" step="1" value="<?= e((string) $posted['stock_qty']) ?>">
                </label>
                <p class="field-hint only-stock">Satılan birim sayısı. Örneğin 24 kavanoz. Sıfır, ürünün tükendiğini gösterir.</p>
                <p class="field-hint stock-open">Takip kapalıyken ürün stokla sınırlanmaz.</p>
            </section>
        </aside>
    </form>
<?php else:
    $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    if (mb_strlen($query, 'UTF-8') > 80) {
        $query = mb_substr($query, 0, 80, 'UTF-8');
    }
    $statusFilter = is_string($_GET['durum'] ?? null) ? $_GET['durum'] : '';
    if (!in_array($statusFilter, ['yayinda', 'taslak', 'tukendi'], true)) {
        $statusFilter = '';
    }
    $priceBand = is_string($_GET['fiyat'] ?? null) ? $_GET['fiyat'] : '';
    if (!in_array($priceBand, ['500', '2000', 'ustu'], true)) {
        $priceBand = '';
    }
    $pageSize = is_string($_GET['adet'] ?? null) ? (int) $_GET['adet'] : 8;
    if (!in_array($pageSize, [8, 16, 32], true)) {
        $pageSize = 8;
    }
    $view = ($_GET['gorunum'] ?? '') === 'kart' ? 'kart' : 'liste';
    $page = max(1, (int) ($_GET['sayfa'] ?? 1));
    $productState = static function (array $row): array {
        if (empty($row['active'])) {
            return ['taslak', 'Taslak'];
        }
        if (!empty($row['stock_track']) && (int) $row['stock_qty'] <= 0) {
            return ['tukendi', 'Tükendi'];
        }

        return ['yayinda', 'Yayında'];
    };
    $publishedLabel = static function (string $value): string {
        $stamp = strtotime($value);
        if ($stamp === false) {
            return '—';
        }

        return date('d.m.Y H:i', $stamp);
    };
    $money = static function (string $amount): string {
        if (preg_match('/^\d+\.\d{2}$/', $amount) !== 1) {
            $amount = number_format((float) $amount, 2, '.', '');
        }
        $label = ProductEditor::moneyLabel($amount);

        return $label !== '' ? $label : '0,00 TL';
    };
    $needle = mb_strtolower($query, 'UTF-8');
    $matched = [];
    $liveCount = 0;
    foreach ($rows as $row) {
        [$stateKey] = $productState($row);
        if ($stateKey === 'yayinda') {
            $liveCount++;
        }
        if ($filter !== '' && $row['category_key'] !== $filter) {
            continue;
        }
        if ($statusFilter !== '' && $stateKey !== $statusFilter) {
            continue;
        }
        if ($needle !== '') {
            $haystack = mb_strtolower((string) $row['title'] . ' ' . (string) $row['sku'], 'UTF-8');
            if (mb_strpos($haystack, $needle) === false) {
                continue;
            }
        }
        if ($priceBand !== '') {
            $sellingRaw = $row['discount_price'] !== '' ? (string) $row['discount_price'] : (string) $row['price'];
            $cents = ProductEditor::moneyCents($sellingRaw);
            $inBand = match ($priceBand) {
                '500' => $cents > 0 && $cents < 50000,
                '2000' => $cents >= 50000 && $cents < 200000,
                'ustu' => $cents >= 200000,
                default => true,
            };
            if (!$inBand) {
                continue;
            }
        }
        $matched[] = $row;
    }
    $pageCount = max(1, (int) ceil(count($matched) / $pageSize));
    if ($page > $pageCount) {
        $page = $pageCount;
    }
    $pageRows = array_slice($matched, ($page - 1) * $pageSize, $pageSize);
    $plain = $query === '' && $filter === '' && $statusFilter === '' && $priceBand === '' && $view === 'liste' && $pageCount === 1;
    $positions = [];
    foreach ($rows as $index => $row) {
        $positions[(string) $row['key']] = $index;
    }
    $orderCounts = [];
    $orderTotal = 0;
    $revenueRaw = '0.00';
    $todayRaw = '0.00';
    $customerCount = 0;
    $pdo = db_pdo();
    if ($pdo instanceof PDO) {
        try {
            $countRows = $pdo->query("SELECT product_key, COUNT(*) AS total FROM orders WHERE status <> 'iptal' GROUP BY product_key")->fetchAll();
            foreach ($countRows as $countRow) {
                $orderCounts[(string) $countRow['product_key']] = (int) $countRow['total'];
            }
            $orderTotal = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status <> 'iptal'")->fetchColumn();
            $revenueRaw = number_format((float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'iptal'")->fetchColumn(), 2, '.', '');
            $todayRaw = number_format((float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'iptal' AND DATE(created_at) = CURDATE()")->fetchColumn(), 2, '.', '');
            $customerCount = (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
        } catch (PDOException) {
            $orderCounts = [];
        }
    }
    $catalogUrl = static function (array $extra = []) use ($query, $filter, $statusFilter, $priceBand, $pageSize, $view, $page): string {
        $params = [
            'q' => $query,
            'kategori' => $filter,
            'durum' => $statusFilter,
            'fiyat' => $priceBand,
            'adet' => (string) $pageSize,
            'gorunum' => $view === 'kart' ? 'kart' : '',
            'sayfa' => (string) $page,
        ];
        foreach ($extra as $key => $value) {
            $params[(string) $key] = (string) $value;
        }
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === '' || ($key === 'adet' && $value === '8') || ($key === 'sayfa' && $value === '1')) {
                continue;
            }
            $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return root_url('admin/urunler.php' . ($parts !== [] ? '?' . implode('&', $parts) : ''));
    };
    $rangeStart = $matched === [] ? 0 : (($page - 1) * $pageSize) + 1;
    $rangeEnd = min(count($matched), $page * $pageSize);
    ?>
    <section class="catalog-kpis" aria-label="Mağaza özeti">
        <article class="catalog-kpi">
            <span class="metric-ico is-purple"><?= admin_icon('bag') ?></span>
            <div>
                <p>Ürün</p>
                <strong><?= e((string) count($rows)) ?></strong>
                <small>Yayında <?= e((string) $liveCount) ?></small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-blue"><?= admin_icon('receipt') ?></span>
            <div>
                <p>Sipariş</p>
                <strong><?= e((string) $orderTotal) ?></strong>
                <small>İptal hariç</small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-green"><?= admin_icon('trend') ?></span>
            <div>
                <p>Satış</p>
                <strong><?= e($money($todayRaw)) ?></strong>
                <small>Bugünkü satış</small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-teal"><?= admin_icon('users') ?></span>
            <div>
                <p>Müşteri</p>
                <strong><?= e((string) $customerCount) ?></strong>
                <small>Kayıtlı üye</small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-gold"><?= admin_icon('card') ?></span>
            <div>
                <p>Ciro</p>
                <strong><?= e($money($revenueRaw)) ?></strong>
                <small>İptal hariç toplam</small>
            </div>
        </article>
    </section>
    <div class="catalog-toolbar">
        <form class="catalog-filters" method="get" action="<?= root_url('admin/urunler.php') ?>">
            <?php if ($view === 'kart'): ?><input type="hidden" name="gorunum" value="kart"><?php endif; ?>
            <label class="catalog-search">
                <span class="search-ico"><?= admin_icon('search') ?></span>
                <input name="q" type="search" value="<?= e($query) ?>" placeholder="Ürün adı veya stok kodu" maxlength="80">
            </label>
            <select name="kategori" data-auto aria-label="Kategori">
                <option value="">Kategori</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['key']) ?>"<?= $filter === $category['key'] ? ' selected' : '' ?>><?= e($category['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="durum" data-auto aria-label="Durum">
                <option value="">Durum</option>
                <option value="yayinda"<?= $statusFilter === 'yayinda' ? ' selected' : '' ?>>Yayında</option>
                <option value="taslak"<?= $statusFilter === 'taslak' ? ' selected' : '' ?>>Taslak</option>
                <option value="tukendi"<?= $statusFilter === 'tukendi' ? ' selected' : '' ?>>Tükendi</option>
            </select>
            <select name="fiyat" data-auto aria-label="Fiyat aralığı">
                <option value="">Fiyat aralığı</option>
                <option value="500"<?= $priceBand === '500' ? ' selected' : '' ?>>500 TL altı</option>
                <option value="2000"<?= $priceBand === '2000' ? ' selected' : '' ?>>500 – 2.000 TL</option>
                <option value="ustu"<?= $priceBand === 'ustu' ? ' selected' : '' ?>>2.000 TL ve üzeri</option>
            </select>
            <select name="adet" data-auto aria-label="Sayfa boyutu">
                <?php foreach ([8, 16, 32] as $size): ?>
                    <option value="<?= (string) $size ?>"<?= $pageSize === $size ? ' selected' : '' ?>><?= (string) $size ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="catalog-views" role="group" aria-label="Görünüm">
            <a class="<?= $view === 'liste' ? 'is-on' : '' ?>" href="<?= $catalogUrl(['gorunum' => '', 'sayfa' => '1']) ?>" aria-label="Liste"><?= admin_icon('list') ?></a>
            <a class="<?= $view === 'kart' ? 'is-on' : '' ?>" href="<?= $catalogUrl(['gorunum' => 'kart', 'sayfa' => '1']) ?>" aria-label="Kartlar"><?= admin_icon('grid') ?></a>
        </div>
        <a class="catalog-shop" href="<?= root_url('/urunler') ?>" target="_blank" rel="noopener">Mağazayı aç</a>
        <a class="catalog-add" href="<?= root_url('admin/urunler.php?yeni=1') ?>"><?= admin_icon('plus') ?> Ürün ekle</a>
    </div>
    <div class="catalog-sheet" data-catalog>
        <div class="catalog-bulk">
            <p><span data-selected-count>0</span> ürün seçildi</p>
            <button form="catalog-bulk" type="submit">Seçilenleri sil</button>
        </div>
        <form id="catalog-bulk" method="post" action="<?= root_url('admin/urunler.php') ?>" data-confirm="Seçilen ürünler silinsin mi?">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="delete-many">
        </form>
        <?php if ($pageRows === []): ?>
            <p class="catalog-empty"><?= $rows === [] ? 'Henüz ürün yok.' : 'Bu filtreye uyan ürün yok.' ?></p>
        <?php elseif ($view === 'kart'): ?>
            <div class="catalog-cards">
                <?php foreach ($pageRows as $row): ?>
                    <?php
                    $selling = $row['discount_price'] !== '' ? (string) $row['discount_price'] : (string) $row['price'];
                    $was = $row['discount_price'] !== '' ? (string) $row['price'] : '';
                    [$stateKey, $stateLabel] = $productState($row);
                    ?>
                    <article class="catalog-card">
                        <label class="catalog-pick">
                            <input form="catalog-bulk" data-check type="checkbox" name="product_key[]" value="<?= e((string) $row['key']) ?>">
                        </label>
                        <?php if ($row['image'] !== ''): ?>
                            <img src="<?= root_url((string) $row['image']) ?>" alt="">
                        <?php else: ?>
                            <span class="catalog-photo"></span>
                        <?php endif; ?>
                        <div class="catalog-card-body">
                            <strong><?= e((string) $row['title']) ?></strong>
                            <?php if ((string) $row['amount'] !== ''): ?><small><?= e((string) $row['amount']) ?></small><?php endif; ?>
                            <b><?= e($money($selling)) ?></b>
                            <span class="catalog-status is-<?= e($stateKey) ?>"><?= e($stateLabel) ?></span>
                            <div class="catalog-actions">
                                <?php if (!empty($row['active'])): ?>
                                    <a class="catalog-icon" href="<?= root_url('/urun/' . $row['key']) ?>" target="_blank" rel="noopener" aria-label="Sitede gör"><?= admin_icon('eye') ?></a>
                                <?php endif; ?>
                                <a class="catalog-icon" href="<?= root_url('admin/urunler.php?urun=' . rawurlencode((string) $row['key'])) ?>" aria-label="Düzenle"><?= admin_icon('pencil') ?></a>
                                <form method="post" action="<?= root_url('admin/urunler.php') ?>" data-confirm="Bu ürün silinsin mi?">
                                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_key" value="<?= e((string) $row['key']) ?>">
                                    <button class="catalog-icon is-danger" type="submit" aria-label="Sil"><?= admin_icon('trash') ?></button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="catalog-scroll">
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th><input data-check-all type="checkbox" aria-label="Sayfadaki ürünlerin tümünü seç"></th>
                            <th>Ürün</th>
                            <th>Stok kodu</th>
                            <th>Kategori</th>
                            <th>Stok</th>
                            <th>Fiyat</th>
                            <th>Sipariş</th>
                            <th>Durum</th>
                            <th>Güncelleme</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageRows as $row): ?>
                            <?php
                            $selling = $row['discount_price'] !== '' ? (string) $row['discount_price'] : (string) $row['price'];
                            $was = $row['discount_price'] !== '' ? (string) $row['price'] : '';
                            [$stateKey, $stateLabel] = $productState($row);
                            $position = $positions[(string) $row['key']] ?? 0;
                            $orderCount = $orderCounts[(string) $row['key']] ?? 0;
                            ?>
                            <tr>
                                <td><input form="catalog-bulk" data-check type="checkbox" name="product_key[]" value="<?= e((string) $row['key']) ?>" aria-label="<?= e((string) $row['title']) ?> seç"></td>
                                <td>
                                    <div class="catalog-product">
                                        <?php if ($row['image'] !== ''): ?>
                                            <img src="<?= root_url((string) $row['image']) ?>" alt="">
                                        <?php else: ?>
                                            <span class="catalog-photo"></span>
                                        <?php endif; ?>
                                        <span>
                                            <strong><?= e((string) $row['title']) ?></strong>
                                            <?php if ((string) $row['amount'] !== ''): ?><small><?= e((string) $row['amount']) ?></small><?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= (string) $row['sku'] !== '' ? e((string) $row['sku']) : '—' ?></td>
                                <td><?= (string) $row['category'] !== '' ? e((string) $row['category']) : 'Kategorisiz' ?></td>
                                <td class="<?= !empty($row['stock_track']) && (int) $row['stock_qty'] <= 0 ? 'is-out' : '' ?>">
                                    <?= !empty($row['stock_track']) ? e((string) (int) $row['stock_qty']) : 'Takipsiz' ?>
                                </td>
                                <td class="catalog-price">
                                    <strong><?= e($money($selling)) ?></strong>
                                    <?php if ($was !== ''): ?><small><?= e($money($was)) ?></small><?php endif; ?>
                                </td>
                                <td><?= e((string) $orderCount) ?></td>
                                <td><span class="catalog-status is-<?= e($stateKey) ?>"><?= e($stateLabel) ?></span></td>
                                <td><?= e($publishedLabel((string) $row['updated_at'])) ?></td>
                                <td>
                                    <div class="catalog-actions">
                                        <?php if ($plain): ?>
                                            <div class="catalog-move">
                                                <form method="post" action="<?= root_url('admin/urunler.php') ?>">
                                                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="move">
                                                    <input type="hidden" name="product_key" value="<?= e((string) $row['key']) ?>">
                                                    <input type="hidden" name="direction" value="-1">
                                                    <button class="catalog-icon" type="submit"<?= $position === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                                                </form>
                                                <form method="post" action="<?= root_url('admin/urunler.php') ?>">
                                                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="move">
                                                    <input type="hidden" name="product_key" value="<?= e((string) $row['key']) ?>">
                                                    <input type="hidden" name="direction" value="1">
                                                    <button class="catalog-icon" type="submit"<?= $position === count($rows) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['active'])): ?>
                                            <a class="catalog-icon" href="<?= root_url('/urun/' . $row['key']) ?>" target="_blank" rel="noopener" aria-label="Sitede gör" title="Sitede gör"><?= admin_icon('eye') ?></a>
                                        <?php endif; ?>
                                        <a class="catalog-icon" href="<?= root_url('admin/urunler.php?urun=' . rawurlencode((string) $row['key'])) ?>" aria-label="Düzenle" title="Düzenle"><?= admin_icon('pencil') ?></a>
                                        <form method="post" action="<?= root_url('admin/urunler.php') ?>">
                                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="product_key" value="<?= e((string) $row['key']) ?>">
                                            <?php if (empty($row['active'])): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                                            <button class="catalog-icon" type="submit" aria-label="<?= !empty($row['active']) ? 'Taslağa al' : 'Yayınla' ?>" title="<?= !empty($row['active']) ? 'Taslağa al' : 'Yayınla' ?>"><?= admin_icon(!empty($row['active']) ? 'draft' : 'plus') ?></button>
                                        </form>
                                        <form method="post" action="<?= root_url('admin/urunler.php') ?>" data-confirm="Bu ürün silinsin mi?">
                                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_key" value="<?= e((string) $row['key']) ?>">
                                            <button class="catalog-icon is-danger" type="submit" aria-label="Sil" title="Sil"><?= admin_icon('trash') ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="catalog-pager">
            <p><?= e((string) $rangeStart) ?>–<?= e((string) $rangeEnd) ?> / <?= e((string) count($matched)) ?> ürün</p>
            <?php if ($pageCount > 1): ?>
                <nav class="catalog-pages" aria-label="Sayfalar">
                    <?php if ($page > 1): ?><a href="<?= $catalogUrl(['sayfa' => (string) ($page - 1)]) ?>">Önceki</a><?php endif; ?>
                    <?php for ($number = 1; $number <= $pageCount; $number++): ?>
                        <?php if ($number === $page): ?>
                            <span class="is-on"><?= e((string) $number) ?></span>
                        <?php else: ?>
                            <a href="<?= $catalogUrl(['sayfa' => (string) $number]) ?>"><?= e((string) $number) ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $pageCount): ?><a href="<?= $catalogUrl(['sayfa' => (string) ($page + 1)]) ?>">Sonraki</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    <p class="field-hint">Yukarı ve aşağı, filtre yokken mağazadaki sırayı değiştirir. Sitede gör, yayındaki ürünü yeni sekmede açar.</p>
<?php endif; ?>
<?php if ($mode === 'editor'): ?>
    <link rel="stylesheet" href="<?= root_url('assets/vendor/jodit/jodit.min.css') ?>">
    <script src="<?= root_url('assets/vendor/jodit/jodit.min.js') ?>"></script>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
