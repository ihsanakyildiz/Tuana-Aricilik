<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/ProductEditor.php';
require_once __DIR__ . '/app/MemberAccount.php';
require_once __DIR__ . '/app/LocationCatalog.php';
require_once __DIR__ . '/app/OrderBook.php';
require_once __DIR__ . '/app/Paytr.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/siparis/$#', $rawPath) === 1) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . absolute_url('/siparis') . ($query === '' ? '' : '?' . $query), true, 301);
    exit;
}

public_maintenance();
MemberAccount::requireLogin();
header('X-Robots-Tag: noindex, follow');
header('Cache-Control: no-store');

$productKey = (string) ($_POST['urun'] ?? $_GET['urun'] ?? '');
if (preg_match('/^[a-z0-9\-]+$/', $productKey) !== 1) {
    $productKey = '';
}
$product = $productKey === '' ? null : ProductEditor::open($productKey);
if ($product === null || empty($product['active'])) {
    http_response_code(404);
    $pageTitle = 'Sayfa bulunamadı';
    $metaDescription = '';
    $robots = 'noindex,follow';
    require __DIR__ . '/partials/header.php';
    require __DIR__ . '/partials/missing.php';
    require __DIR__ . '/partials/footer.php';
    return;
}

$content = is_array($product['content'] ?? null) ? $product['content'] : [];
$rawPrice = (string) ($content['discount_price'] ?? '');
if ($rawPrice === '') {
    $rawPrice = (string) ($content['price'] ?? '');
}
$unitCents = ProductEditor::moneyCents($rawPrice);
$available = !$product['stock_track'] || (int) $product['stock_qty'] > 0;
$maxQty = $product['stock_track'] ? max(0, (int) $product['stock_qty']) : 99;

$member = MemberAccount::current();
if ($member === null) {
    MemberAccount::redirect('uye-giris?sonraki=' . rawurlencode('/siparis?urun=' . $productKey));
}
$profile = is_array($member['profile'] ?? null) ? $member['profile'] : [];
$shipping = is_array($profile['shipping'] ?? null) ? $profile['shipping'] : [];
$shippingReady = !empty($member['shipping_ready']);
$invoiceReady = !empty($member['invoice_ready']);

$errors = [];
$failedAction = '';
$step = (string) ($_GET['adim'] ?? '') === 'odeme' ? 'odeme' : 'adres';
$qty = max(1, (int) ($_POST['qty'] ?? 1));
$payment = (string) ($_POST['payment'] ?? '');
$cargoId = (string) ($_POST['cargo'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $failedAction = (string) ($_POST['action'] ?? '');
    if (!MemberAccount::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } elseif ($failedAction === 'shipping') {
        $errors = MemberAccount::save('shipping', $_POST);
        if ($errors === []) {
            MemberAccount::redirect('siparis?urun=' . rawurlencode($productKey));
        }
        $step = 'adres';
    } elseif ($failedAction === 'place') {
        $step = 'odeme';
        if (!$available) {
            $errors[] = 'Bu ürün tükendi.';
        } else {
            $result = OrderBook::place($member, $_POST);
            $errors = $result['errors'];
            if ($errors === [] && is_array($result['order'])) {
                $placedNo = rawurlencode((string) $result['order']['order_no']);
                if ((string) ($result['order']['payment_method'] ?? '') === 'kart') {
                    MemberAccount::redirect('odeme-kart?no=' . $placedNo);
                }
                MemberAccount::redirect('tesekkur?no=' . $placedNo);
            }
        }
    } else {
        $errors[] = 'Bu işlem tanınmıyor.';
    }
}

if ($step === 'odeme' && !$shippingReady) {
    MemberAccount::redirect('siparis?urun=' . rawurlencode($productKey));
}

$editing = $step === 'adres' && (!$shippingReady || isset($_GET['duzenle']) || $failedAction === 'shipping');
$cargoMethods = OrderBook::cargoMethods(true);
$banks = OrderBook::banks();
$payMethods = OrderBook::enabledPayments();
if (!isset($payMethods[$payment])) {
    $payment = count($payMethods) === 1 ? (string) array_key_first($payMethods) : '';
}
if ($cargoId === '' && $cargoMethods !== []) {
    $cargoId = $cargoMethods[0]['id'];
}
$selectedCargo = '0.00';
foreach ($cargoMethods as $method) {
    if ($method['id'] === $cargoId) {
        $selectedCargo = $method['price'];
    }
}
if ($payment === 'elden') {
    $selectedCargo = '0.00';
}

$value = static function (string $key, string $stored) use ($failedAction): string {
    if ($failedAction === 'shipping' && isset($_POST[$key])) {
        return trim((string) $_POST[$key]);
    }

    return $stored;
};

$pageTitle = 'Sipariş';
$metaDescription = 'Teslimat adresi, kargo ve ödeme';
$canonicalPath = '/siparis';
$bodyClass = 'is-page is-shop';
$robots = 'noindex,follow';
$pageScript = $step === 'odeme' ? 'js/checkout.js' : ($editing ? 'js/address.js' : '');
$shopTitle = 'Sipariş';
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Ürünler', 'url' => '/urunler'],
    ['label' => (string) $product['title'], 'url' => '/urun/' . $productKey],
    ['label' => 'Sipariş', 'url' => ''],
];
$csrf = MemberAccount::csrfToken();

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container">
            <ol class="checkout-steps">
                <li class="<?= $step === 'adres' ? 'is-on' : 'is-done' ?>">Adres</li>
                <li class="<?= $step === 'odeme' ? 'is-on' : '' ?>">Kargo ve ödeme</li>
            </ol>
            <?php if ($errors !== []): ?>
                <div class="account-alert" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!$available): ?>
                <section class="account-panel">
                    <h2><?= e((string) $product['title']) ?></h2>
                    <p>Bu ürün tükendi.</p>
                    <a href="<?= url('/urun/' . $productKey) ?>">Ürüne dön</a>
                </section>
            <?php elseif ($step === 'adres'): ?>
                <section class="account-panel">
                    <h2><?= $editing ? 'Teslimat adresi' : 'Teslimat adresini kontrol et' ?></h2>
                    <?php if (!$editing && $shippingReady): ?>
                        <p class="account-lead">Sipariş bu adrese hazırlanır. Bir hata varsa güncelle, doğruysa ödemeye geç.</p>
                        <div class="address-card">
                            <strong><?= e((string) ($shipping['full_name'] ?? '')) ?></strong>
                            <p><?= e(MemberAccount::phoneLabel((string) ($shipping['phone'] ?? ''))) ?></p>
                            <p><?= e((string) ($shipping['line'] ?? '')) ?></p>
                            <p><?= e(trim(MemberAccount::addressLine($shipping) . (((string) ($shipping['postal'] ?? '')) !== '' ? ' ' . (string) $shipping['postal'] : ''))) ?></p>
                        </div>
                        <?php if (!$invoiceReady): ?>
                            <p class="account-hint">Fatura bilgin henüz yok. İstersen ödemeden önce <a href="<?= url('/hesabim?urun=' . rawurlencode($productKey) . '#fatura') ?>">fatura bilgisini kaydet</a>.</p>
                        <?php endif; ?>
                        <div class="checkout-actions">
                            <a class="btn btn-line" href="<?= url('/siparis?urun=' . rawurlencode($productKey) . '&duzenle=1') ?>">Adresi güncelle</a>
                            <a class="btn" href="<?= url('/siparis?urun=' . rawurlencode($productKey) . '&adim=odeme') ?>">Ödemeye geç</a>
                        </div>
                    <?php else: ?>
                        <p class="account-lead">Ürünün gönderileceği adresi kaydet.</p>
                        <?php
                        $shipKind = (string) ($shipping['place_kind'] ?? '');
                        if (!in_array($shipKind, ['mahalle', 'koy'], true)) {
                            $shipKind = 'mahalle';
                        }
                        $shipCity = $failedAction === 'shipping' ? (int) ($_POST['ship_city'] ?? 0) : (int) ($shipping['city_id'] ?? 0);
                        $shipDistrict = $failedAction === 'shipping' ? (int) ($_POST['ship_district'] ?? 0) : (int) ($shipping['district_id'] ?? 0);
                        $shipPlace = $failedAction === 'shipping' ? (int) ($_POST['ship_place'] ?? 0) : (int) ($shipping['place_id'] ?? 0);
                        if ($shipCity === 0 && $failedAction !== 'shipping') {
                            $shipCity = LocationCatalog::matchId(0, 'il', (string) ($shipping['city'] ?? ''));
                        }
                        if ($shipDistrict === 0 && $failedAction !== 'shipping') {
                            $shipDistrict = LocationCatalog::matchId($shipCity, 'ilce', (string) ($shipping['district'] ?? ''));
                        }
                        if ($shipPlace === 0 && $failedAction !== 'shipping') {
                            $shipPlace = LocationCatalog::matchId($shipDistrict, $shipKind, (string) ($shipping['neighborhood'] ?? ''));
                            if ($shipPlace === 0) {
                                $shipPlace = LocationCatalog::matchId($shipDistrict, 'koy', (string) ($shipping['neighborhood'] ?? ''));
                            }
                        }
                        $shipCities = LocationCatalog::choices(0, $shipCity);
                        $shipDistricts = $shipCity > 0 ? LocationCatalog::choices($shipCity, $shipDistrict) : [];
                        $shipPlaces = $shipDistrict > 0 ? LocationCatalog::choices($shipDistrict, $shipPlace) : [];
                        $shipName = (string) ($shipping['full_name'] ?? '');
                        if ($shipName === '') {
                            $shipName = trim((string) $member['first_name'] . ' ' . (string) $member['last_name']);
                        }
                        $shipPhone = (string) ($shipping['phone'] ?? '');
                        if ($shipPhone === '') {
                            $shipPhone = (string) $member['phone'];
                        }
                        ?>
                        <form class="shop-filters" method="post" action="<?= url('/siparis?urun=' . rawurlencode($productKey)) ?>" data-address data-endpoint="<?= url('/konum.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="shipping">
                            <input type="hidden" name="urun" value="<?= e($productKey) ?>">
                            <div class="account-fields-2">
                                <label class="shop-field">
                                    <span>Alıcı adı</span>
                                    <input type="text" name="ship_name" value="<?= e($value('ship_name', $shipName)) ?>" maxlength="60" autocomplete="name" required>
                                </label>
                                <label class="shop-field">
                                    <span>Telefon</span>
                                    <input type="tel" name="ship_phone" value="<?= e($value('ship_phone', MemberAccount::phoneLabel($shipPhone))) ?>" maxlength="20" autocomplete="tel" required>
                                </label>
                            </div>
                            <div class="account-fields-3">
                                <label class="shop-field">
                                    <span>İl</span>
                                    <select name="ship_city" data-level="il" autocomplete="address-level1" required>
                                        <option value="">İl seçin</option>
                                        <?php foreach ($shipCities as $choice): ?>
                                            <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $shipCity ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="shop-field">
                                    <span>İlçe</span>
                                    <select name="ship_district" data-level="ilce" autocomplete="address-level2" required<?= $shipDistricts === [] ? ' disabled' : '' ?>>
                                        <option value=""><?= $shipCity > 0 ? 'İlçe seçin' : 'Önce il seçin' ?></option>
                                        <?php foreach ($shipDistricts as $choice): ?>
                                            <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $shipDistrict ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="shop-field">
                                    <span>Mahalle / Köy</span>
                                    <select name="ship_place" data-level="yer" required<?= $shipPlaces === [] ? ' disabled' : '' ?>>
                                        <option value=""><?= $shipDistrict > 0 ? 'Mahalle veya köy seçin' : 'Önce ilçe seçin' ?></option>
                                        <?php foreach ($shipPlaces as $choice): ?>
                                            <option value="<?= (int) $choice['id'] ?>" data-postal="<?= e($choice['postal']) ?>"<?= (int) $choice['id'] === $shipPlace ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <label class="shop-field">
                                <span>Açık adres</span>
                                <textarea name="ship_line" maxlength="180" rows="3" autocomplete="street-address" required><?= e($value('ship_line', (string) ($shipping['line'] ?? ''))) ?></textarea>
                            </label>
                            <div class="account-fields-2">
                                <label class="shop-field">
                                    <span>Posta kodu</span>
                                    <input type="text" name="ship_postal" data-postal value="<?= e($value('ship_postal', (string) ($shipping['postal'] ?? ''))) ?>" maxlength="5" inputmode="numeric" autocomplete="postal-code">
                                </label>
                                <label class="shop-field">
                                    <span>Kargo notu</span>
                                    <input type="text" name="ship_note" value="<?= e($value('ship_note', (string) ($shipping['note'] ?? ''))) ?>" maxlength="180" autocomplete="off">
                                </label>
                            </div>
                            <div class="checkout-actions">
                                <?php if ($shippingReady): ?><a class="btn btn-line" href="<?= url('/siparis?urun=' . rawurlencode($productKey)) ?>">Vazgeç</a><?php endif; ?>
                                <button class="btn" type="submit">Adresi kaydet</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <?php
                $shownQty = min($maxQty, max(1, $qty));
                $cargoCents = $payment === 'elden' ? 0 : ProductEditor::moneyCents($selectedCargo);
                $subtotalCents = $unitCents * $shownQty;
                $totalCents = $subtotalCents + $cargoCents;
                $formatCents = static function (int $cents): string {
                    $whole = intdiv($cents, 100);
                    $fraction = str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

                    return ProductEditor::moneyLabel($whole . '.' . $fraction);
                };
                ?>
                <form class="checkout" method="post" action="<?= url('/siparis?urun=' . rawurlencode($productKey) . '&adim=odeme') ?>" data-checkout data-unit="<?= (int) $unitCents ?>" autocomplete="on">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="place">
                    <input type="hidden" name="urun" value="<?= e($productKey) ?>">
                    <div class="checkout-main">
                        <?php if ($cargoMethods !== []): ?>
                            <section class="account-panel" data-cargo-box>
                                <h2>Kargo</h2>
                                <p class="account-lead">Gönderiyi taşıyacak firmayı seç. Tutar, sağdaki özete eklenir.</p>
                                <div class="choice-list">
                                    <?php foreach ($cargoMethods as $method): ?>
                                        <label class="choice-row">
                                            <input type="radio" name="cargo" value="<?= e($method['id']) ?>" data-price="<?= (int) ProductEditor::moneyCents($method['price']) ?>"<?= $method['id'] === $cargoId ? ' checked' : '' ?> required>
                                            <span><?= e($method['name']) ?></span>
                                            <strong><?= e(OrderBook::money($method['price'])) ?></strong>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php else: ?>
                            <section class="account-panel">
                                <h2>Kargo</h2>
                                <p class="account-lead">Kargo firması tanımlanmadığı için bu siparişe kargo tutarı eklenmez.</p>
                            </section>
                        <?php endif; ?>
                        <section class="account-panel">
                            <h2>Ödeme yöntemi</h2>
                            <?php if ($payMethods === []): ?>
                                <p class="account-lead">Ödeme yöntemi şu an kapalı.</p>
                            <?php else: ?>
                            <div class="pay-methods">
                                <?php if (isset($payMethods['kart'])): ?>
                                <div class="pay-option">
                                    <label class="pay-head"><input type="radio" name="payment" value="kart"<?= $payment === 'kart' ? ' checked' : '' ?> required> Kredi kartıyla ödeme</label>
                                    <div class="pay-body">
                                        <?php if (Paytr::ready()): ?>
                                            <p class="account-lead">Sonraki adımda PayTR güvenli ödeme sayfası açılır. Kart bilgisi bu sitede tutulmaz.</p>
                                        <?php else: ?>
                                            <p class="account-lead">Kredi kartı ödemesi henüz yapılandırılmadı.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($payMethods['elden'])): ?>
                                <div class="pay-option">
                                    <label class="pay-head"><input type="radio" name="payment" value="elden"<?= $payment === 'elden' ? ' checked' : '' ?><?= !isset($payMethods['kart']) ? ' required' : '' ?>> Elden teslim alma</label>
                                    <div class="pay-body">
                                        <p class="account-lead">Ürünü teslim alırken ödersin. Bu seçenekte kargo tutarı eklenmez.</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($payMethods['havale'])): ?>
                                <div class="pay-option">
                                    <label class="pay-head"><input type="radio" name="payment" value="havale"<?= $payment === 'havale' ? ' checked' : '' ?><?= !isset($payMethods['kart']) && !isset($payMethods['elden']) ? ' required' : '' ?>> Havale ile ödeme</label>
                                    <div class="pay-body">
                                        <?php if ($banks === []): ?>
                                            <p class="account-lead">Siparişin ödeme bekliyor durumunda açılır. Hesap bilgisi ayrıca iletilir.</p>
                                        <?php else: ?>
                                            <?php foreach ($banks as $bank): ?>
                                                <p class="bank-line">
                                                    <strong><?= e($bank['bank']) ?></strong>
                                                    <span><?= e($bank['holder']) ?></span>
                                                    <span><?= e(trim(chunk_split($bank['iban'], 4, ' '))) ?></span>
                                                </p>
                                            <?php endforeach; ?>
                                            <p class="account-hint">Havale açıklamasına sipariş numaranı yaz.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </section>
                    </div>
                    <aside class="account-panel checkout-summary">
                        <h2>Ödeme özeti</h2>
                        <div class="summary-product">
                            <?php if ((string) $product['image'] !== ''): ?>
                                <?= media_tag((string) $product['image'], (string) $product['title'], 'summary-photo', false) ?>
                            <?php endif; ?>
                            <div>
                                <strong><?= e((string) $product['title']) ?></strong>
                                <p><?= e(ProductEditor::moneyLabel($rawPrice)) ?></p>
                            </div>
                        </div>
                        <label class="shop-field">
                            <span>Adet</span>
                            <input type="number" name="qty" min="1" max="<?= (int) $maxQty ?>" value="<?= (int) $shownQty ?>" required>
                        </label>
                        <dl class="summary-lines">
                            <div><dt>Ara toplam</dt><dd data-subtotal><?= e($formatCents($subtotalCents)) ?></dd></div>
                            <div><dt>Kargo</dt><dd data-cargo-amount><?= e($formatCents($cargoCents)) ?></dd></div>
                            <div class="is-total"><dt>Ödenecek tutar</dt><dd data-total><?= e($formatCents($totalCents)) ?></dd></div>
                        </dl>
                        <?php if ($payMethods !== []): ?><button class="btn" type="submit" data-place>Siparişi tamamla</button><?php endif; ?>
                        <a href="<?= url('/siparis?urun=' . rawurlencode($productKey)) ?>">Adrese dön</a>
                    </aside>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
