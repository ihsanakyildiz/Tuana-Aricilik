<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/MemberAccount.php';
require_once dirname(__DIR__) . '/app/ProductEditor.php';
require_once dirname(__DIR__) . '/app/OrderBook.php';
require_once dirname(__DIR__) . '/app/LocationCatalog.php';

Auth::boot();
Auth::requireLogin();

$formAction = '';
$formErrors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedId = (int) ($_POST['uye'] ?? 0);
    $back = 'admin/musteriler.php' . ($postedId > 0 ? '?uye=' . $postedId : '');
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        Auth::flash('err', 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.');
        header('Location: ' . root_path() . '/' . $back, true, 303);
        exit;
    }
    $formAction = (string) ($_POST['action'] ?? '');
    $formErrors = MemberAccount::saveFor($postedId, $formAction, $_POST);
    if ($formErrors === []) {
        Auth::flash('ok', 'Müşteri bilgileri kaydedildi.');
        header('Location: ' . root_path() . '/' . $back, true, 303);
        exit;
    }
}

$pageTitle = 'Müşteriler';
$crumbCurrent = 'Müşteriler';
$activeNav = 'musteriler';
$memberId = $formAction !== '' ? (int) ($_POST['uye'] ?? 0) : (int) ($_GET['uye'] ?? 0);
$member = $memberId > 0 ? MemberAccount::find($memberId) : null;
$rows = MemberAccount::all();
$adminScript = is_array($member) ? 'assets/js/address.js' : '';
$csrf = Auth::csrfToken();
if (is_array($member)) {
    $pageTitle = 'Müşteri düzenle';
    $crumbCurrent = trim((string) $member['first_name'] . ' ' . (string) $member['last_name']);
}
$kept = static function (string $action, string $key, string $fallback) use ($formAction): string {
    if ($formAction === $action && isset($_POST[$key]) && is_string($_POST[$key])) {
        return $_POST[$key];
    }

    return $fallback;
};

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($memberId > 0 && $member === null): ?>
    <div class="form-alert" role="alert"><p>Üye bulunamadı.</p></div>
<?php endif; ?>
<?php if (is_array($member)): ?>
    <?php
    $profile = is_array($member['profile'] ?? null) ? $member['profile'] : [];
    $shipping = is_array($profile['shipping'] ?? null) ? $profile['shipping'] : [];
    $invoice = is_array($profile['invoice'] ?? null) ? $profile['invoice'] : [];
    $created = strtotime((string) $member['created_at']);
    ?>
    <?php
    $shipKind = (string) ($shipping['place_kind'] ?? '');
    if (!in_array($shipKind, ['mahalle', 'koy'], true)) {
        $shipKind = 'mahalle';
    }
    $shipCity = $formAction === 'shipping' ? (int) ($_POST['ship_city'] ?? 0) : (int) ($shipping['city_id'] ?? 0);
    if ($shipCity < 1 && $formAction !== 'shipping') {
        $shipCity = LocationCatalog::matchId(0, 'il', (string) ($shipping['city'] ?? ''));
    }
    $shipDistrict = $formAction === 'shipping' ? (int) ($_POST['ship_district'] ?? 0) : (int) ($shipping['district_id'] ?? 0);
    if ($shipDistrict < 1 && $formAction !== 'shipping') {
        $shipDistrict = LocationCatalog::matchId($shipCity, 'ilce', (string) ($shipping['district'] ?? ''));
    }
    $shipPlace = $formAction === 'shipping' ? (int) ($_POST['ship_place'] ?? 0) : (int) ($shipping['place_id'] ?? 0);
    if ($shipPlace < 1 && $formAction !== 'shipping') {
        $shipPlace = LocationCatalog::matchId($shipDistrict, $shipKind, (string) ($shipping['neighborhood'] ?? ''));
        if ($shipPlace < 1) {
            $shipPlace = LocationCatalog::matchId($shipDistrict, 'koy', (string) ($shipping['neighborhood'] ?? ''));
        }
    }
    $shipCities = LocationCatalog::choices(0, $shipCity);
    $shipDistricts = $shipCity > 0 ? LocationCatalog::choices($shipCity, $shipDistrict) : [];
    $shipPlaces = $shipDistrict > 0 ? LocationCatalog::choices($shipDistrict, $shipPlace) : [];
    $kind = $kept('invoice', 'invoice_kind', (string) ($invoice['kind'] ?? 'bireysel'));
    if (!in_array($kind, ['bireysel', 'kurumsal'], true)) {
        $kind = 'bireysel';
    }
    $same = $formAction === 'invoice' ? isset($_POST['invoice_same']) : (!array_key_exists('same', $invoice) || !empty($invoice['same']));
    $invoiceKind = (string) ($invoice['place_kind'] ?? '');
    if (!in_array($invoiceKind, ['mahalle', 'koy'], true)) {
        $invoiceKind = 'mahalle';
    }
    $invoiceCity = $formAction === 'invoice' ? (int) ($_POST['invoice_city'] ?? 0) : (int) ($invoice['city_id'] ?? 0);
    if ($invoiceCity < 1 && $formAction !== 'invoice') {
        $invoiceCity = LocationCatalog::matchId(0, 'il', (string) ($invoice['city'] ?? ''));
    }
    $invoiceDistrict = $formAction === 'invoice' ? (int) ($_POST['invoice_district'] ?? 0) : (int) ($invoice['district_id'] ?? 0);
    if ($invoiceDistrict < 1 && $formAction !== 'invoice') {
        $invoiceDistrict = LocationCatalog::matchId($invoiceCity, 'ilce', (string) ($invoice['district'] ?? ''));
    }
    $invoicePlace = $formAction === 'invoice' ? (int) ($_POST['invoice_place'] ?? 0) : (int) ($invoice['place_id'] ?? 0);
    if ($invoicePlace < 1 && $formAction !== 'invoice') {
        $invoicePlace = LocationCatalog::matchId($invoiceDistrict, $invoiceKind, (string) ($invoice['neighborhood'] ?? ''));
        if ($invoicePlace < 1) {
            $invoicePlace = LocationCatalog::matchId($invoiceDistrict, 'koy', (string) ($invoice['neighborhood'] ?? ''));
        }
    }
    $invoiceCities = LocationCatalog::choices(0, $invoiceCity);
    $invoiceDistricts = $invoiceCity > 0 ? LocationCatalog::choices($invoiceCity, $invoiceDistrict) : [];
    $invoicePlaces = $invoiceDistrict > 0 ? LocationCatalog::choices($invoiceDistrict, $invoicePlace) : [];
    $displayName = trim((string) $member['first_name'] . ' ' . (string) $member['last_name']);
    ?>
    <header class="sale-head">
        <div>
            <p class="sale-kicker"><a href="<?= root_url('admin/musteriler.php') ?>">Müşteriler</a></p>
            <h2 class="sale-title"><?= e($displayName !== '' ? $displayName : 'Üye') ?></h2>
            <p class="sale-meta">Kayıt <?= $created === false ? '—' : e(date('d.m.Y H:i', $created)) ?></p>
        </div>
    </header>
    <?php if ($formErrors !== []): ?>
        <div class="form-alert" role="alert"><?php foreach ($formErrors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <div class="sale-grid member-edit">
        <div>
            <section class="sale-card">
                <h3>Üyelik</h3>
                <form method="post" action="<?= root_url('admin/musteriler.php') ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="identity">
                    <input type="hidden" name="uye" value="<?= (int) $member['id'] ?>">
                    <div class="fields-2">
                        <label class="field">Ad <input type="text" name="first_name" maxlength="60" value="<?= e($kept('identity', 'first_name', (string) $member['first_name'])) ?>" required></label>
                        <label class="field">Soyad <input type="text" name="last_name" maxlength="60" value="<?= e($kept('identity', 'last_name', (string) $member['last_name'])) ?>" required></label>
                    </div>
                    <label class="field">E-posta <input type="email" name="email" maxlength="190" value="<?= e($kept('identity', 'email', (string) $member['email'])) ?>" required></label>
                    <label class="field">Telefon <input type="tel" name="phone" maxlength="20" value="<?= e($kept('identity', 'phone', MemberAccount::phoneLabel((string) $member['phone']))) ?>" required></label>
                    <button class="sale-btn" type="submit">Üyeliği kaydet</button>
                </form>
            </section>
            <section class="sale-card">
                <h3>Kargo adresi</h3>
                <form method="post" action="<?= root_url('admin/musteriler.php') ?>" data-address data-endpoint="<?= root_url('konum.php') ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="shipping">
                    <input type="hidden" name="uye" value="<?= (int) $member['id'] ?>">
                    <div class="fields-2">
                        <label class="field">Alıcı adı <input type="text" name="ship_name" maxlength="60" value="<?= e($kept('shipping', 'ship_name', (string) ($shipping['full_name'] ?? ''))) ?>" required></label>
                        <label class="field">Telefon <input type="tel" name="ship_phone" maxlength="20" value="<?= e($kept('shipping', 'ship_phone', (string) ($shipping['phone'] ?? '') !== '' ? MemberAccount::phoneLabel((string) $shipping['phone']) : '')) ?>" required></label>
                    </div>
                    <div class="fields-3">
                        <label class="field">İl
                            <select name="ship_city" data-level="il" required>
                                <option value="">İl seçin</option>
                                <?php foreach ($shipCities as $choice): ?>
                                    <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $shipCity ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">İlçe
                            <select name="ship_district" data-level="ilce" required<?= $shipDistricts === [] ? ' disabled' : '' ?>>
                                <option value=""><?= $shipCity > 0 ? 'İlçe seçin' : 'Önce il seçin' ?></option>
                                <?php foreach ($shipDistricts as $choice): ?>
                                    <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $shipDistrict ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">Mahalle / Köy
                            <select name="ship_place" data-level="yer" required<?= $shipPlaces === [] ? ' disabled' : '' ?>>
                                <option value=""><?= $shipDistrict > 0 ? 'Mahalle veya köy seçin' : 'Önce ilçe seçin' ?></option>
                                <?php foreach ($shipPlaces as $choice): ?>
                                    <option value="<?= (int) $choice['id'] ?>" data-postal="<?= e($choice['postal']) ?>"<?= (int) $choice['id'] === $shipPlace ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label class="field">Açık adres <textarea name="ship_line" maxlength="180" rows="3" required><?= e($kept('shipping', 'ship_line', (string) ($shipping['line'] ?? ''))) ?></textarea></label>
                    <div class="fields-2">
                        <label class="field">Posta kodu <input type="text" name="ship_postal" data-postal inputmode="numeric" maxlength="5" value="<?= e($kept('shipping', 'ship_postal', (string) ($shipping['postal'] ?? ''))) ?>"></label>
                        <label class="field">Kargo notu <input type="text" name="ship_note" maxlength="180" value="<?= e($kept('shipping', 'ship_note', (string) ($shipping['note'] ?? ''))) ?>"></label>
                    </div>
                    <button class="sale-btn" type="submit">Kargo adresini kaydet</button>
                </form>
            </section>
        </div>
        <aside>
            <section class="sale-card">
                <h3>Fatura</h3>
                <form class="invoice-form" method="post" action="<?= root_url('admin/musteriler.php') ?>" data-address data-endpoint="<?= root_url('konum.php') ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="invoice">
                    <input type="hidden" name="uye" value="<?= (int) $member['id'] ?>">
                    <label class="field">Fatura türü
                        <select name="invoice_kind">
                            <option value="bireysel"<?= $kind === 'bireysel' ? ' selected' : '' ?>>Bireysel</option>
                            <option value="kurumsal"<?= $kind === 'kurumsal' ? ' selected' : '' ?>>Kurumsal</option>
                        </select>
                    </label>
                    <label class="field">
                        <span class="only-personal">Ad soyad</span>
                        <span class="only-corporate">Şirket adı</span>
                        <input type="text" name="invoice_title" maxlength="120" value="<?= e($kept('invoice', 'invoice_title', (string) ($invoice['title'] ?? ''))) ?>" required>
                    </label>
                    <div class="invoice-tax-row">
                        <label class="field only-corporate">Vergi dairesi <input type="text" name="invoice_tax_office" maxlength="40" value="<?= e($kept('invoice', 'invoice_tax_office', (string) ($invoice['tax_office'] ?? ''))) ?>"<?= $kind === 'kurumsal' ? ' required' : ' disabled' ?>></label>
                        <label class="field">
                            <span class="only-personal">T.C. kimlik no</span>
                            <span class="only-corporate">Vergi no</span>
                            <input type="text" name="invoice_tax_no" maxlength="11" inputmode="numeric" value="<?= e($kept('invoice', 'invoice_tax_no', (string) ($invoice['tax_no'] ?? ''))) ?>" required>
                        </label>
                    </div>
                    <label class="check only-personal">
                        <input id="invoice-same" type="checkbox" name="invoice_same" value="1"<?= $same ? ' checked' : '' ?>>
                        <span>Fatura adresi kargo adresiyle aynı</span>
                    </label>
                    <div class="invoice-address">
                        <div class="fields-3">
                            <label class="field">İl
                                <select name="invoice_city" data-level="il">
                                    <option value="">İl seçin</option>
                                    <?php foreach ($invoiceCities as $choice): ?>
                                        <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $invoiceCity ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">İlçe
                                <select name="invoice_district" data-level="ilce"<?= $invoiceDistricts === [] ? ' disabled' : '' ?>>
                                    <option value=""><?= $invoiceCity > 0 ? 'İlçe seçin' : 'Önce il seçin' ?></option>
                                    <?php foreach ($invoiceDistricts as $choice): ?>
                                        <option value="<?= (int) $choice['id'] ?>"<?= (int) $choice['id'] === $invoiceDistrict ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">Mahalle / Köy
                                <select name="invoice_place" data-level="yer"<?= $invoicePlaces === [] ? ' disabled' : '' ?>>
                                    <option value=""><?= $invoiceDistrict > 0 ? 'Mahalle veya köy seçin' : 'Önce ilçe seçin' ?></option>
                                    <?php foreach ($invoicePlaces as $choice): ?>
                                        <option value="<?= (int) $choice['id'] ?>" data-postal="<?= e($choice['postal']) ?>"<?= (int) $choice['id'] === $invoicePlace ? ' selected' : '' ?>><?= e($choice['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <label class="field">Açık adres <textarea name="invoice_line" maxlength="180" rows="3"><?= e($kept('invoice', 'invoice_line', (string) ($invoice['line'] ?? ''))) ?></textarea></label>
                        <label class="field">Posta kodu <input type="text" name="invoice_postal" data-postal inputmode="numeric" maxlength="5" value="<?= e($kept('invoice', 'invoice_postal', (string) ($invoice['postal'] ?? ''))) ?>"></label>
                    </div>
                    <button class="sale-btn" type="submit">Faturayı kaydet</button>
                </form>
            </section>
        </aside>
    </div>
<?php else:
    $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    if (mb_strlen($query, 'UTF-8') > 80) {
        $query = mb_substr($query, 0, 80, 'UTF-8');
    }
    $pageSize = is_string($_GET['adet'] ?? null) ? (int) $_GET['adet'] : 8;
    if (!in_array($pageSize, [8, 16, 32], true)) {
        $pageSize = 8;
    }
    $page = max(1, (int) ($_GET['sayfa'] ?? 1));
    $kind = is_string($_GET['tur'] ?? null) ? $_GET['tur'] : '';
    if (!in_array($kind, ['uye', 'misafir'], true)) {
        $kind = '';
    }
    $needle = mb_strtolower($query, 'UTF-8');
    $spent = [];
    $pdo = db_pdo();
    if ($pdo instanceof PDO) {
        try {
            $totals = $pdo->query("SELECT member_id, COUNT(*) AS orders, COALESCE(SUM(total), 0) AS spent FROM orders WHERE status <> 'iptal' GROUP BY member_id")->fetchAll();
            foreach ($totals as $total) {
                if (!is_array($total)) {
                    continue;
                }
                $spent[(int) $total['member_id']] = [
                    'orders' => (int) $total['orders'],
                    'spent' => number_format((float) $total['spent'], 2, '.', ''),
                ];
            }
        } catch (PDOException) {
            $spent = [];
        }
    }
    $money = static function (string $amount): string {
        if (preg_match('/^\d+\.\d{2}$/', $amount) !== 1) {
            $amount = number_format((float) $amount, 2, '.', '');
        }
        $label = ProductEditor::moneyLabel($amount);

        return $label !== '' ? $label : '0,00 TL';
    };
    $people = [];
    if ($kind !== 'misafir') {
        foreach ($rows as $row) {
            $row['guest'] = false;
            $people[] = $row;
        }
    }
    if ($kind !== 'uye') {
        foreach (OrderBook::guests() as $guest) {
            $guest['id'] = 0;
            $people[] = $guest;
        }
    }
    usort($people, static function (array $left, array $right): int {
        return strcmp((string) $right['created_at'], (string) $left['created_at']);
    });
    $matched = [];
    foreach ($people as $row) {
        $guest = !empty($row['guest']);
        $city = $guest
            ? trim((string) ($row['city'] ?? ''))
            : trim((string) ((is_array($row['profile']['shipping'] ?? null) ? $row['profile']['shipping'] : [])['city'] ?? ''));
        if ($needle !== '') {
            $haystack = mb_strtolower(trim((string) $row['first_name'] . ' ' . (string) $row['last_name'] . ' ' . (string) $row['email'] . ' ' . (string) $row['phone'] . ' ' . $city), 'UTF-8');
            if (mb_strpos($haystack, $needle) === false) {
                continue;
            }
        }
        $row['city'] = $city;
        $matched[] = $row;
    }
    $pageCount = max(1, (int) ceil(count($matched) / $pageSize));
    if ($page > $pageCount) {
        $page = $pageCount;
    }
    $pageRows = array_slice($matched, ($page - 1) * $pageSize, $pageSize);
    $catalogUrl = static function (array $extra = []) use ($query, $kind, $pageSize, $page): string {
        $params = [
            'q' => $query,
            'tur' => $kind,
            'adet' => (string) $pageSize,
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

        return root_url('admin/musteriler.php' . ($parts !== [] ? '?' . implode('&', $parts) : ''));
    };
    $rangeStart = $matched === [] ? 0 : (($page - 1) * $pageSize) + 1;
    $rangeEnd = min(count($matched), $page * $pageSize);
    ?>
    <div class="catalog-toolbar">
        <form class="catalog-filters" method="get" action="<?= root_url('admin/musteriler.php') ?>">
            <label class="catalog-search">
                <span class="search-ico"><?= admin_icon('search') ?></span>
                <input name="q" type="search" value="<?= e($query) ?>" placeholder="Müşteri adı, e-posta veya telefon" maxlength="80">
            </label>
            <select name="tur" data-auto aria-label="Üyelik">
                <option value="">Tümü</option>
                <option value="uye"<?= $kind === 'uye' ? ' selected' : '' ?>>Üyeler</option>
                <option value="misafir"<?= $kind === 'misafir' ? ' selected' : '' ?>>Üye olmadan</option>
            </select>
            <select name="adet" data-auto aria-label="Sayfa boyutu">
                <?php foreach ([8, 16, 32] as $size): ?>
                    <option value="<?= (string) $size ?>"<?= $pageSize === $size ? ' selected' : '' ?>><?= (string) $size ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="catalog-sheet">
        <?php if ($pageRows === []): ?>
            <p class="catalog-empty"><?php
                if ($needle !== '') {
                    echo 'Bu aramaya uyan müşteri yok.';
                } elseif ($kind === 'misafir') {
                    echo 'Üye olmadan verilmiş sipariş yok.';
                } elseif ($kind === 'uye' || $rows === []) {
                    echo 'Henüz üye yok.';
                } else {
                    echo 'Henüz müşteri yok.';
                }
            ?></p>
        <?php else: ?>
            <div class="catalog-scroll">
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th>Müşteri</th>
                            <th>E-posta</th>
                            <th>Telefon</th>
                            <th>İl</th>
                            <th>Kayıt</th>
                            <th>Sipariş</th>
                            <th>Harcama</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageRows as $row): ?>
                            <?php
                            $guest = !empty($row['guest']);
                            $name = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
                            $initial = mb_strtoupper(mb_substr($name !== '' ? $name : (string) $row['email'], 0, 1, 'UTF-8'), 'UTF-8');
                            $stamp = strtotime((string) $row['created_at']);
                            $stats = $guest
                                ? ['orders' => (int) $row['orders'], 'spent' => (string) $row['spent']]
                                : ($spent[(int) $row['id']] ?? ['orders' => 0, 'spent' => '0.00']);
                            $phone = MemberAccount::phoneLabel((string) $row['phone']);
                            $avatar = $guest
                                ? (abs(crc32(strtolower((string) $row['email'] . (string) $row['order_no']))) % 6)
                                : ((int) $row['id'] % 6);
                            $orderHref = $guest
                                ? ((int) $row['orders'] > 1 && (string) $row['email'] !== ''
                                    ? root_url('admin/siparisler.php?q=' . rawurlencode((string) $row['email']) . '&uye=misafir')
                                    : root_url('admin/siparisler.php?no=' . rawurlencode((string) $row['order_no'])))
                                : '';
                            ?>
                            <tr>
                                <td>
                                    <div class="catalog-product">
                                        <span class="catalog-avatar is-<?= (string) $avatar ?>"><?= e($initial !== '' ? $initial : '?') ?></span>
                                        <span>
                                            <strong><?= e($name !== '' ? $name : ($guest ? 'Üye olmadan' : 'Üye')) ?></strong>
                                            <?php if ($guest): ?><span class="catalog-sub">Üye olmadan</span><?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= e((string) $row['email']) ?></td>
                                <td><?= e($phone !== '' ? $phone : '—') ?></td>
                                <td><?= (string) $row['city'] !== '' ? e((string) $row['city']) : '—' ?></td>
                                <td>
                                    <?= $stamp === false ? '—' : e(date('d.m.Y H:i', $stamp)) ?>
                                    <?php if ($guest): ?><span class="catalog-sub">Son sipariş</span><?php endif; ?>
                                </td>
                                <td><?= e((string) $stats['orders']) ?></td>
                                <td class="catalog-price"><strong><?= e($money((string) $stats['spent'])) ?></strong></td>
                                <td>
                                    <div class="catalog-actions">
                                        <?php if ($guest): ?>
                                            <a class="catalog-icon" href="<?= $orderHref ?>" aria-label="Siparişi aç" title="Sipariş"><?= admin_icon('receipt') ?></a>
                                        <?php else: ?>
                                            <a class="catalog-icon" href="<?= root_url('admin/musteriler.php?uye=' . (int) $row['id']) ?>" aria-label="Müşteriyi düzenle" title="Düzenle"><?= admin_icon('pencil') ?></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="catalog-pager">
            <p><?= e((string) $rangeStart) ?>–<?= e((string) $rangeEnd) ?> / <?= e((string) count($matched)) ?> müşteri</p>
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
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
