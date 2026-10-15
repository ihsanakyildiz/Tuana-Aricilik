<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/OrderBook.php';
require_once dirname(__DIR__) . '/app/Paytr.php';

Auth::boot();
Auth::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        Auth::flash('err', 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.');
    } else {
        $errors = match ($action) {
            'payments' => OrderBook::savePayments($_POST),
            'paytr' => Paytr::save($_POST),
            'bank_add', 'bank_delete' => OrderBook::saveBank($action, $_POST),
            default => ['Bu işlem tanınmıyor.'],
        };
        Auth::flash($errors === [] ? 'ok' : 'err', $errors === [] ? 'Ödeme ayarları kaydedildi.' : $errors[0]);
    }
    header('Location: ' . root_path() . '/admin/odeme.php', true, 303);
    exit;
}

$pageTitle = 'Ödeme yöntemleri';
$crumbCurrent = 'Ödeme yöntemleri';
$activeNav = 'odeme-yontem';
$enabled = OrderBook::enabledPayments();
$banks = OrderBook::banks();
$paytr = Paytr::config();
$csrf = Auth::csrfToken();

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<div class="slide-head">
    <h2>Ödeme yöntemleri</h2>
</div>
<section class="settings-card">
    <p class="settings-lead">Açık yöntemler sipariş sayfasında müşteriye görünür.</p>
    <form method="post" action="<?= root_url('admin/odeme.php') ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="payments">
        <div class="pay-toggles">
            <?php foreach (OrderBook::payments() as $key => $label): ?>
                <label class="check">
                    <input type="checkbox" name="payments[<?= e($key) ?>]" value="1"<?= isset($enabled[$key]) ? ' checked' : '' ?>>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <button class="login-submit" type="submit">Yöntemleri kaydet</button>
    </form>
</section>
<section class="settings-card">
    <h2>PayTR</h2>
    <p class="settings-lead">Kart ödemesi PayTR iFrame ile alınır. Kart numarası bu sunucuya gelmez. Sipariş, yalnızca PayTR bildirim adresine gelen imzalı sonuçla onaylanır veya iptal edilir.</p>
    <p class="settings-lead">Bildirim URL: <?= e(Paytr::callbackUrl()) ?></p>
    <p class="settings-lead">Bu adresi PayTR mağaza panelinde Destek ve Kurulum, Ayarlar, Bildirim URL alanına yaz. Mağaza numarası, anahtar ve salt değerini panelin Bilgi sayfasından al. Test modu açıkken gerçek tahsilat olmaz. Yerel sitede PayTR bildirim gönderemez; bildirim için sitenin herkese açık adresi gerekir. Yerel denemede PayTR genel IP ister; dış IP adresini test IP alanına yaz.</p>
    <form method="post" action="<?= root_url('admin/odeme.php') ?>" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="paytr">
        <label class="field">Mağaza numarası <input type="text" name="merchant_id" inputmode="numeric" maxlength="12" value="<?= e($paytr['merchant_id']) ?>" required></label>
        <label class="field">Mağaza anahtarı <input type="password" name="merchant_key" maxlength="128" value="" placeholder="<?= $paytr['merchant_key'] !== '' ? 'Kayıtlı. Değiştirmek için yeni değeri yaz.' : '' ?>" autocomplete="new-password"></label>
        <label class="field">Mağaza salt <input type="password" name="merchant_salt" maxlength="128" value="" placeholder="<?= $paytr['merchant_salt'] !== '' ? 'Kayıtlı. Değiştirmek için yeni değeri yaz.' : '' ?>" autocomplete="new-password"></label>
        <label class="field">Yerel test IP <input type="text" name="test_ip" maxlength="39" value="<?= e($paytr['test_ip']) ?>" placeholder="Genel IP"></label>
        <label class="check">
            <input type="checkbox" name="test_mode" value="1"<?= $paytr['test_mode'] ? ' checked' : '' ?>>
            <span>Test modu</span>
        </label>
        <label class="check">
            <input type="checkbox" name="no_installment" value="1"<?= $paytr['no_installment'] ? ' checked' : '' ?>>
            <span>Taksit gösterme</span>
        </label>
        <label class="field">
            En fazla taksit
            <select name="max_installment">
                <?php foreach (Paytr::installmentChoices() as $choice): ?>
                    <option value="<?= (string) $choice ?>"<?= $choice === $paytr['max_installment'] ? ' selected' : '' ?>><?= $choice === 0 ? 'PayTR sınırı' : (string) $choice ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="login-submit" type="submit">PayTR ayarlarını kaydet</button>
    </form>
</section>
<section class="settings-card">
    <h2>Havale hesapları</h2>
    <p class="settings-lead">Havale seçen müşteri bu hesapları ödeme adımında ve teşekkür sayfasında görür.</p>
    <?php if ($banks === []): ?>
        <p>Henüz banka hesabı yok.</p>
    <?php endif; ?>
    <?php foreach ($banks as $bank): ?>
        <p>
            <?= e($bank['bank']) ?> · <?= e($bank['holder']) ?> · <?= e($bank['iban']) ?>
            <form method="post" action="<?= root_url('admin/odeme.php') ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="bank_delete">
                <input type="hidden" name="bank_id" value="<?= e($bank['id']) ?>">
                <button type="submit">Sil</button>
            </form>
        </p>
    <?php endforeach; ?>
    <form method="post" action="<?= root_url('admin/odeme.php') ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="bank_add">
        <label class="field">Banka <input type="text" name="bank_name" maxlength="80" required></label>
        <label class="field">Hesap sahibi <input type="text" name="bank_holder" maxlength="80" required></label>
        <label class="field">IBAN <input type="text" name="bank_iban" maxlength="32" required></label>
        <button class="login-submit" type="submit">Hesap ekle</button>
    </form>
</section>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
