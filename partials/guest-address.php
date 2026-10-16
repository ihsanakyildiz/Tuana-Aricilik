<?php

declare(strict_types=1);

$loginNext = '/uye-giris?sonraki=' . rawurlencode('/siparis?urun=' . $productKey);
$guestEmail = (string) ($shipping['email'] ?? '');
$invoiceKind = (string) ($invoice['kind'] ?? 'bireysel');
if (!in_array($invoiceKind, ['bireysel', 'kurumsal'], true)) {
    $invoiceKind = 'bireysel';
}
$invoiceSame = $failedAction === 'guest' ? isset($_POST['invoice_same']) : (!array_key_exists('same', $invoice) || !empty($invoice['same']));
?>
<section class="account-panel">
    <h2><?= $guestEditing ? 'Teslimat ve fatura' : 'Bilgileri kontrol et' ?></h2>
    <?php if (!$guestEditing && $shippingReady): ?>
        <p class="account-lead">Üye olmadan sipariş veriyorsun. Bilgiler doğruysa ödemeye geç.</p>
        <div class="address-card">
            <strong><?= e((string) ($shipping['full_name'] ?? '')) ?></strong>
            <p><?= e($guestEmail) ?></p>
            <p><?= e(MemberAccount::phoneLabel((string) ($shipping['phone'] ?? ''))) ?></p>
            <p><?= e((string) ($shipping['line'] ?? '')) ?></p>
            <p><?= e(trim(MemberAccount::addressLine($shipping) . (((string) ($shipping['postal'] ?? '')) !== '' ? ' ' . (string) $shipping['postal'] : ''))) ?></p>
        </div>
        <?php if (trim((string) ($invoice['title'] ?? '')) !== ''): ?>
            <p class="account-hint"><?= ($invoice['kind'] ?? '') === 'kurumsal' ? 'Kurumsal fatura' : 'Bireysel fatura' ?>: <?= e((string) $invoice['title']) ?></p>
        <?php endif; ?>
        <div class="checkout-actions">
            <a class="btn btn-line" href="<?= url('/siparis?urun=' . rawurlencode($productKey) . '&duzenle=1') ?>">Bilgileri güncelle</a>
            <a class="btn" href="<?= url('/siparis?urun=' . rawurlencode($productKey) . '&adim=odeme') ?>">Ödemeye geç</a>
        </div>
        <p class="account-hint">Kayıtlı adresin varsa <a href="<?= url($loginNext) ?>">giriş yap</a>.</p>
    <?php else: ?>
        <?php
        $shipCity = $failedAction === 'guest' ? (int) ($_POST['ship_city'] ?? 0) : (int) ($shipping['city_id'] ?? 0);
        $shipDistrict = $failedAction === 'guest' ? (int) ($_POST['ship_district'] ?? 0) : (int) ($shipping['district_id'] ?? 0);
        $shipPlace = $failedAction === 'guest' ? (int) ($_POST['ship_place'] ?? 0) : (int) ($shipping['place_id'] ?? 0);
        $shipCities = LocationCatalog::choices(0, $shipCity);
        $shipDistricts = $shipCity > 0 ? LocationCatalog::choices($shipCity, $shipDistrict) : [];
        $shipPlaces = $shipDistrict > 0 ? LocationCatalog::choices($shipDistrict, $shipPlace) : [];
        $kind = $value('invoice_kind', $invoiceKind);
        if (!in_array($kind, ['bireysel', 'kurumsal'], true)) {
            $kind = 'bireysel';
        }
        $invoiceCity = $failedAction === 'guest' ? (int) ($_POST['invoice_city'] ?? 0) : (int) ($invoice['city_id'] ?? 0);
        $invoiceDistrict = $failedAction === 'guest' ? (int) ($_POST['invoice_district'] ?? 0) : (int) ($invoice['district_id'] ?? 0);
        $invoicePlace = $failedAction === 'guest' ? (int) ($_POST['invoice_place'] ?? 0) : (int) ($invoice['place_id'] ?? 0);
        $invoiceCities = LocationCatalog::choices(0, $invoiceCity);
        $invoiceDistricts = $invoiceCity > 0 ? LocationCatalog::choices($invoiceCity, $invoiceDistrict) : [];
        $invoicePlaces = $invoiceDistrict > 0 ? LocationCatalog::choices($invoiceDistrict, $invoicePlace) : [];
        ?>
        <p class="account-lead">Hesap açmadan sipariş verebilirsin. E-posta, teslimat ve fatura bu siparişe yazılır.</p>
        <form class="shop-filters invoice-form" method="post" action="<?= url('/siparis?urun=' . rawurlencode($productKey)) ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="guest">
            <input type="hidden" name="urun" value="<?= e($productKey) ?>">
            <label class="shop-field">
                <span>E-posta</span>
                <input type="email" name="guest_email" value="<?= e($value('guest_email', $guestEmail)) ?>" maxlength="100" autocomplete="email" required>
            </label>
            <div data-address data-endpoint="<?= url('/konum.php') ?>">
                <div class="account-fields-2">
                    <label class="shop-field">
                        <span>Alıcı adı</span>
                        <input type="text" name="ship_name" value="<?= e($value('ship_name', (string) ($shipping['full_name'] ?? ''))) ?>" maxlength="60" autocomplete="name" required>
                    </label>
                    <label class="shop-field">
                        <span>Telefon</span>
                        <input type="tel" name="ship_phone" value="<?= e($value('ship_phone', MemberAccount::phoneLabel((string) ($shipping['phone'] ?? '')))) ?>" maxlength="20" autocomplete="tel" required>
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
                        <select name="ship_district" data-level="ilce" required<?= $shipDistricts === [] ? ' disabled' : '' ?>>
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
            </div>
            <h3>Fatura</h3>
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
                <input type="text" name="invoice_title" value="<?= e($value('invoice_title', (string) ($invoice['title'] ?? ''))) ?>" maxlength="120" required>
            </label>
            <div class="invoice-tax-row">
                <label class="shop-field only-corporate">
                    <span>Vergi dairesi</span>
                    <input type="text" name="invoice_tax_office" value="<?= e($value('invoice_tax_office', (string) ($invoice['tax_office'] ?? ''))) ?>" maxlength="40"<?= $kind === 'kurumsal' ? ' required' : ' disabled' ?>>
                </label>
                <label class="shop-field">
                    <span class="only-personal">T.C. kimlik no</span>
                    <span class="only-corporate">Vergi no</span>
                    <input type="text" name="invoice_tax_no" value="<?= e($value('invoice_tax_no', (string) ($invoice['tax_no'] ?? ''))) ?>" maxlength="11" inputmode="numeric" required>
                </label>
            </div>
            <label class="check only-personal">
                <input id="invoice-same" type="checkbox" name="invoice_same" value="1"<?= $invoiceSame ? ' checked' : '' ?>>
                <span>Fatura adresi kargo adresiyle aynı</span>
            </label>
            <div class="invoice-address" data-address data-endpoint="<?= url('/konum.php') ?>">
                <div class="account-fields-3">
                    <label class="shop-field">
                        <span>İl</span>
                        <select name="invoice_city" data-level="il">
                            <option value="">İl seçin</option>
                            <?php foreach ($invoiceCities as $choice): ?>
                                <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $invoiceCity ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="shop-field">
                        <span>İlçe</span>
                        <select name="invoice_district" data-level="ilce"<?= $invoiceDistricts === [] ? ' disabled' : '' ?>>
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
                    <textarea name="invoice_line" maxlength="180" rows="3"><?= e($value('invoice_line', (string) ($invoice['line'] ?? ''))) ?></textarea>
                </label>
                <label class="shop-field">
                    <span>Posta kodu</span>
                    <input type="text" name="invoice_postal" data-postal value="<?= e($value('invoice_postal', (string) ($invoice['postal'] ?? ''))) ?>" maxlength="5" inputmode="numeric">
                </label>
            </div>
            <div class="checkout-actions">
                <?php if ($shippingReady): ?><a class="btn btn-line" href="<?= url('/siparis?urun=' . rawurlencode($productKey)) ?>">Vazgeç</a><?php endif; ?>
                <button class="btn" type="submit">Bilgileri kaydet</button>
            </div>
        </form>
        <p class="account-hint">Üyeliğin varsa <a href="<?= url($loginNext) ?>">giriş yap</a>. Kayıtlı adresin kullanılır.</p>
    <?php endif; ?>
</section>
