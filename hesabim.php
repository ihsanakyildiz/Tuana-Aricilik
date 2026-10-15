<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';
require_once __DIR__ . '/app/LocationCatalog.php';
require_once __DIR__ . '/app/ProductEditor.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/hesabim/$#', $rawPath) === 1) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . absolute_url('/hesabim') . ($query === '' ? '' : '?' . $query), true, 301);
    exit;
}

public_maintenance();
MemberAccount::requireLogin();
header('X-Robots-Tag: noindex, follow');
header('Cache-Control: no-store');

$errors = [];
$failedAction = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $failedAction = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!MemberAccount::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } else {
        $errors = MemberAccount::save($failedAction, $_POST);
        if ($errors === []) {
            $anchors = [
                'identity' => 'uyelik',
                'password' => 'sifre',
                'shipping' => 'kargo',
                'invoice' => 'fatura',
            ];
            $anchor = $anchors[$failedAction] ?? 'uyelik';
            MemberAccount::flash('ok', 'Bilgilerin kaydedildi.');
            $target = 'hesabim';
            $productKey = (string) ($_POST['urun'] ?? '');
            if (preg_match('/^[a-z0-9\-]+$/', $productKey) === 1) {
                $target .= '?urun=' . rawurlencode($productKey);
            }
            MemberAccount::redirect($target . '#' . $anchor);
        }
    }
}

$member = MemberAccount::current();
if ($member === null) {
    MemberAccount::redirect('uye-giris?sonraki=' . rawurlencode('/hesabim'));
}
$profile = is_array($member['profile'] ?? null) ? $member['profile'] : [];
$shipping = is_array($profile['shipping'] ?? null) ? $profile['shipping'] : [];
$invoice = is_array($profile['invoice'] ?? null) ? $profile['invoice'] : [];
$ready = MemberAccount::ready($member);

$focus = null;
$productKey = (string) ($_POST['urun'] ?? $_GET['urun'] ?? '');
if (preg_match('/^[a-z0-9\-]+$/', $productKey) === 1) {
    $product = ProductEditor::open($productKey);
    if (is_array($product) && !empty($product['active'])) {
        $focus = $product;
    }
}

$value = static function (string $action, string $key, string $stored) use ($failedAction): string {
    if ($failedAction === $action && isset($_POST[$key])) {
        return trim((string) $_POST[$key]);
    }

    return $stored;
};

$placeId = static function (string $action, string $field, array $stored, string $idKey, string $nameKey, int $parentId, string $kind) use ($failedAction): int {
    if ($failedAction === $action) {
        return max(0, (int) ($_POST[$field] ?? 0));
    }
    $id = (int) ($stored[$idKey] ?? 0);
    if ($id > 0 || $parentId < 0) {
        return $id;
    }
    $name = (string) ($stored[$nameKey] ?? '');
    if ($name === '' || $kind === '') {
        return 0;
    }

    return LocationCatalog::matchId(max(0, $parentId), $kind, $name);
};

$pageTitle = 'Hesabım';
$metaDescription = 'Üyelik, kargo ve fatura bilgileri';
$canonicalPath = '/hesabim';
$bodyClass = 'is-page is-shop';
$robots = 'noindex,follow';
$pageScript = 'js/address.js';
$shopTitle = 'Hesabım';
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Hesabım', 'url' => ''],
];
$flash = MemberAccount::pullFlash();
$csrf = MemberAccount::csrfToken();

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container account-layout">
            <aside class="account-nav" aria-label="Hesap bölümleri">
                <a href="#uyelik">Üyelik</a>
                <a href="#sifre">Şifre</a>
                <a href="#kargo">Kargo adresi</a>
                <a href="#fatura">Fatura</a>
                <a href="<?= url('/siparislerim') ?>">Siparişlerim</a>
                <form method="post" action="<?= url('/uye-cikis') ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <button type="submit">Çıkış yap</button>
                </form>
            </aside>
            <div class="account-main">
                <?php if ($flash !== null): ?>
                    <p class="account-note" role="status"><?= e($flash['text']) ?></p>
                <?php endif; ?>
                <?php if ($errors !== []): ?>
                    <div class="account-alert" role="alert">
                        <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (is_array($focus)): ?>
                    <section class="account-panel account-focus">
                        <p class="eyebrow">Satın alma</p>
                        <h2><?= e((string) $focus['title']) ?></h2>
                        <?php if ($ready): ?>
                            <p>Kargo ve fatura bilgilerin hazır. Sipariş sayfasından teslimat adresini kontrol edip ödemeye geçebilirsin.</p>
                            <a class="btn btn-sm" href="<?= url('/siparis?urun=' . rawurlencode((string) $focus['key'])) ?>">Siparişe devam et</a>
                        <?php else: ?>
                            <p>Bu ürünü satın almak için kargo adresini kaydet. Fatura bilgisini de ekleyebilirsin.</p>
                        <?php endif; ?>
                        <a href="<?= url('/urun/' . $focus['key']) ?>">Ürüne dön</a>
                    </section>
                <?php endif; ?>
                <p class="account-status"><?= $ready ? 'Kargo ve fatura bilgilerin tamam.' : 'Satın alma için kargo adresi ve fatura bilgisi gerekli.' ?></p>

                <section class="account-panel" id="uyelik">
                    <h2>Üyelik bilgileri</h2>
                    <form class="shop-filters" method="post" action="<?= url('/hesabim') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="identity">
                        <?php if (is_array($focus)): ?><input type="hidden" name="urun" value="<?= e((string) $focus['key']) ?>"><?php endif; ?>
                        <div class="account-fields-2">
                            <label class="shop-field">
                                <span>Ad</span>
                                <input type="text" name="first_name" value="<?= e($value('identity', 'first_name', (string) $member['first_name'])) ?>" maxlength="60" autocomplete="given-name" required>
                            </label>
                            <label class="shop-field">
                                <span>Soyad</span>
                                <input type="text" name="last_name" value="<?= e($value('identity', 'last_name', (string) $member['last_name'])) ?>" maxlength="60" autocomplete="family-name" required>
                            </label>
                        </div>
                        <label class="shop-field">
                            <span>E-posta</span>
                            <input type="email" name="email" value="<?= e($value('identity', 'email', (string) $member['email'])) ?>" maxlength="190" autocomplete="email" required>
                        </label>
                        <label class="shop-field">
                            <span>Telefon</span>
                            <input type="tel" name="phone" value="<?= e($value('identity', 'phone', MemberAccount::phoneLabel((string) $member['phone']))) ?>" maxlength="20" autocomplete="tel" required>
                        </label>
                        <label class="shop-field">
                            <span>Mevcut şifre</span>
                            <input type="password" name="current_password" maxlength="128" autocomplete="current-password">
                        </label>
                        <p class="account-hint">E-posta değişince mevcut şifre istenir.</p>
                        <button class="btn btn-sm" type="submit">Üyeliği kaydet</button>
                    </form>
                </section>

                <section class="account-panel" id="sifre">
                    <h2>Şifre</h2>
                    <form class="shop-filters" method="post" action="<?= url('/hesabim') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="password">
                        <?php if (is_array($focus)): ?><input type="hidden" name="urun" value="<?= e((string) $focus['key']) ?>"><?php endif; ?>
                        <label class="shop-field">
                            <span>Mevcut şifre</span>
                            <input type="password" name="current_password" maxlength="128" autocomplete="current-password" required>
                        </label>
                        <div class="account-fields-2">
                            <label class="shop-field">
                                <span>Yeni şifre</span>
                                <input type="password" name="new_password" maxlength="128" autocomplete="new-password" required>
                            </label>
                            <label class="shop-field">
                                <span>Yeni şifre tekrar</span>
                                <input type="password" name="new_password_confirm" maxlength="128" autocomplete="new-password" required>
                            </label>
                        </div>
                        <button class="btn btn-sm" type="submit">Şifreyi güncelle</button>
                    </form>
                </section>

                <section class="account-panel" id="kargo">
                    <h2>Kargo adresi</h2>
                    <p class="account-lead">Ürünün gönderileceği kişi ve adres.</p>
                    <?php
                    $shipKind = (string) ($shipping['place_kind'] ?? '');
                    if (!in_array($shipKind, ['mahalle', 'koy'], true)) {
                        $shipKind = 'mahalle';
                    }
                    $shipCity = $placeId('shipping', 'ship_city', $shipping, 'city_id', 'city', 0, 'il');
                    $shipDistrict = $placeId('shipping', 'ship_district', $shipping, 'district_id', 'district', $shipCity, 'ilce');
                    $shipPlace = $placeId('shipping', 'ship_place', $shipping, 'place_id', 'neighborhood', $shipDistrict, $shipKind);
                    if ($shipPlace === 0 && $failedAction !== 'shipping') {
                        $shipPlace = LocationCatalog::matchId($shipDistrict, 'koy', (string) ($shipping['neighborhood'] ?? ''));
                    }
                    $shipCities = LocationCatalog::choices(0, $shipCity);
                    $shipDistricts = $shipCity > 0 ? LocationCatalog::choices($shipCity, $shipDistrict) : [];
                    $shipPlaces = $shipDistrict > 0 ? LocationCatalog::choices($shipDistrict, $shipPlace) : [];
                    ?>
                    <form class="shop-filters" method="post" action="<?= url('/hesabim') ?>" data-address data-endpoint="<?= url('/konum.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="shipping">
                        <?php if (is_array($focus)): ?><input type="hidden" name="urun" value="<?= e((string) $focus['key']) ?>"><?php endif; ?>
                        <div class="account-fields-2">
                            <label class="shop-field">
                                <span>Alıcı adı</span>
                                <input type="text" name="ship_name" value="<?= e($value('shipping', 'ship_name', (string) ($shipping['full_name'] ?? '') !== '' ? (string) $shipping['full_name'] : trim((string) $member['first_name'] . ' ' . (string) $member['last_name']))) ?>" maxlength="60" autocomplete="name" required>
                            </label>
                            <label class="shop-field">
                                <span>Telefon</span>
                                <input type="tel" name="ship_phone" value="<?= e($value('shipping', 'ship_phone', (string) ($shipping['phone'] ?? '') !== '' ? MemberAccount::phoneLabel((string) $shipping['phone']) : MemberAccount::phoneLabel((string) $member['phone']))) ?>" maxlength="20" autocomplete="tel" required>
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
                            <textarea name="ship_line" maxlength="180" rows="3" autocomplete="street-address" required><?= e($value('shipping', 'ship_line', (string) ($shipping['line'] ?? ''))) ?></textarea>
                        </label>
                        <div class="account-fields-2">
                            <label class="shop-field">
                                <span>Posta kodu</span>
                                <input type="text" name="ship_postal" data-postal value="<?= e($value('shipping', 'ship_postal', (string) ($shipping['postal'] ?? ''))) ?>" maxlength="5" inputmode="numeric" autocomplete="postal-code">
                            </label>
                            <label class="shop-field">
                                <span>Kargo notu</span>
                                <input type="text" name="ship_note" value="<?= e($value('shipping', 'ship_note', (string) ($shipping['note'] ?? ''))) ?>" maxlength="180" autocomplete="off">
                            </label>
                        </div>
                        <button class="btn btn-sm" type="submit">Kargo adresini kaydet</button>
                    </form>
                </section>

                <section class="account-panel" id="fatura">
                    <h2>Fatura bilgileri</h2>
                    <p class="account-lead">Bireysel faturada ad soyad ve T.C. kimlik numarası yeter. Kurumsal faturada şirket adı, vergi dairesi, vergi numarası ve fatura adresi girilir.</p>
                    <?php
                    $kind = $value('invoice', 'invoice_kind', (string) ($invoice['kind'] ?? 'bireysel'));
                    if (!in_array($kind, ['bireysel', 'kurumsal'], true)) {
                        $kind = 'bireysel';
                    }
                    $sameStored = !array_key_exists('same', $invoice) || !empty($invoice['same']);
                    $same = $failedAction === 'invoice' ? isset($_POST['invoice_same']) : $sameStored;
                    $invoiceTitle = (string) ($invoice['title'] ?? '');
                    if ($invoiceTitle === '') {
                        $invoiceTitle = trim((string) $member['first_name'] . ' ' . (string) $member['last_name']);
                    }
                    ?>
                    <form class="shop-filters invoice-form" method="post" action="<?= url('/hesabim') ?>" data-address data-endpoint="<?= url('/konum.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="invoice">
                        <?php if (is_array($focus)): ?><input type="hidden" name="urun" value="<?= e((string) $focus['key']) ?>"><?php endif; ?>
                        <label class="shop-field">
                            <span>Fatura türü</span>
                            <select name="invoice_kind">
                                <option value="bireysel"<?= $kind === 'bireysel' ? ' selected' : '' ?>>Bireysel</option>
                                <option value="kurumsal"<?= $kind === 'kurumsal' ? ' selected' : '' ?>>Kurumsal</option>
                            </select>
                        </label>
                        <label class="shop-field">
                            <span class="only-personal">Ad soyad</span>
                            <span class="only-corporate">Şirket adı</span>
                            <input type="text" name="invoice_title" value="<?= e($value('invoice', 'invoice_title', $invoiceTitle)) ?>" maxlength="120" required>
                        </label>
                        <div class="invoice-tax-row">
                            <label class="shop-field only-corporate">
                                <span>Vergi dairesi</span>
                                <input type="text" name="invoice_tax_office" value="<?= e($value('invoice', 'invoice_tax_office', (string) ($invoice['tax_office'] ?? ''))) ?>" maxlength="40"<?= $kind === 'kurumsal' ? ' required' : ' disabled' ?>>
                            </label>
                            <label class="shop-field">
                                <span class="only-personal">T.C. kimlik no</span>
                                <span class="only-corporate">Vergi no</span>
                                <input type="text" name="invoice_tax_no" value="<?= e($value('invoice', 'invoice_tax_no', (string) ($invoice['tax_no'] ?? ''))) ?>" maxlength="11" inputmode="numeric" required>
                            </label>
                        </div>
                        <label class="shop-check only-personal">
                            <input id="invoice-same" type="checkbox" name="invoice_same" value="1"<?= $same ? ' checked' : '' ?>>
                            <span>Fatura adresi kargo adresiyle aynı</span>
                        </label>
                        <div class="invoice-address">
                            <?php
                            $invoiceKind = (string) ($invoice['place_kind'] ?? '');
                            if (!in_array($invoiceKind, ['mahalle', 'koy'], true)) {
                                $invoiceKind = 'mahalle';
                            }
                            $invoiceCity = $placeId('invoice', 'invoice_city', $invoice, 'city_id', 'city', 0, 'il');
                            $invoiceDistrict = $placeId('invoice', 'invoice_district', $invoice, 'district_id', 'district', $invoiceCity, 'ilce');
                            $invoicePlace = $placeId('invoice', 'invoice_place', $invoice, 'place_id', 'neighborhood', $invoiceDistrict, $invoiceKind);
                            if ($invoicePlace === 0 && $failedAction !== 'invoice') {
                                $invoicePlace = LocationCatalog::matchId($invoiceDistrict, 'koy', (string) ($invoice['neighborhood'] ?? ''));
                            }
                            $invoiceCities = LocationCatalog::choices(0, $invoiceCity);
                            $invoiceDistricts = $invoiceCity > 0 ? LocationCatalog::choices($invoiceCity, $invoiceDistrict) : [];
                            $invoicePlaces = $invoiceDistrict > 0 ? LocationCatalog::choices($invoiceDistrict, $invoicePlace) : [];
                            ?>
                            <div class="account-fields-3">
                                <label class="shop-field">
                                    <span>İl</span>
                                    <select name="invoice_city" data-level="il" autocomplete="address-level1">
                                        <option value="">İl seçin</option>
                                        <?php foreach ($invoiceCities as $choice): ?>
                                            <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $invoiceCity ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="shop-field">
                                    <span>İlçe</span>
                                    <select name="invoice_district" data-level="ilce" autocomplete="address-level2"<?= $invoiceDistricts === [] ? ' disabled' : '' ?>>
                                        <option value=""><?= $invoiceCity > 0 ? 'İlçe seçin' : 'Önce il seçin' ?></option>
                                        <?php foreach ($invoiceDistricts as $choice): ?>
                                            <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $invoiceDistrict ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="shop-field">
                                    <span>Mahalle / Köy</span>
                                    <select name="invoice_place" data-level="yer"<?= $invoicePlaces === [] ? ' disabled' : '' ?>>
                                        <option value=""><?= $invoiceDistrict > 0 ? 'Mahalle veya köy seçin' : 'Önce ilçe seçin' ?></option>
                                        <?php foreach ($invoicePlaces as $choice): ?>
                                            <option value="<?= (int) $choice['id'] ?>" data-postal="<?= e($choice['postal']) ?>"<?= (int) $choice['id'] === $invoicePlace ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <label class="shop-field">
                                <span>Açık adres</span>
                                <textarea name="invoice_line" maxlength="180" rows="3"><?= e($value('invoice', 'invoice_line', (string) ($invoice['line'] ?? ''))) ?></textarea>
                            </label>
                            <label class="shop-field">
                                <span>Posta kodu</span>
                                <input type="text" name="invoice_postal" data-postal value="<?= e($value('invoice', 'invoice_postal', (string) ($invoice['postal'] ?? ''))) ?>" maxlength="5" inputmode="numeric">
                            </label>
                        </div>
                        <button class="btn btn-sm" type="submit">Faturayı kaydet</button>
                    </form>
                </section>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
