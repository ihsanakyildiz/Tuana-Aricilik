<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/OrderBook.php';
require_once dirname(__DIR__) . '/app/Geliver.php';

Auth::boot();
Auth::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        Auth::flash('err', 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.');
    } else {
        $errors = match ($action) {
            'cargo_add', 'cargo_toggle', 'cargo_delete' => OrderBook::saveCargo($action, $_POST),
            'geliver' => Geliver::save($_POST),
            default => ['Bu işlem tanınmıyor.'],
        };
        $saved = $action === 'geliver' ? 'Geliver ayarları kaydedildi.' : 'Kargo seçenekleri kaydedildi.';
        Auth::flash($errors === [] ? 'ok' : 'err', $errors === [] ? $saved : $errors[0]);
    }
    header('Location: ' . root_path() . '/admin/kargo.php', true, 303);
    exit;
}

$pageTitle = 'Kargo seçenekleri';
$crumbCurrent = 'Kargo seçenekleri';
$activeNav = 'kargo';
$cargo = OrderBook::cargoMethods(false);
$geliver = Geliver::config();
$csrf = Auth::csrfToken();

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<div class="slide-head">
    <h2>Kargo seçenekleri</h2>
</div>
<section class="settings-card">
    <h2>Geliver</h2>
    <p class="settings-lead">Müşterinin ödediği kargo tutarı aşağıdaki listeden gelir. Geliver, onaylanan sipariş için kargo teklifi alır ve seçilen teklifin etiketini satın alır. Token, Geliver panelindeki Geliştirici Araçları, API Tokenları sayfasından alınır.</p>
    <p class="settings-lead">Bildirim URL: <?= e(Geliver::callbackUrl()) ?></p>
    <p class="settings-lead">Test modu açıkken gerçek etiket satın alınmaz. Test gönderisinde durumu almak, Geliver tarafında gönderiyi bir adım ilerletir. Localhost adresine Geliver bildirim gönderemez; canlı takip için sitenin herkese açık adresi bu URL’de görünmelidir.</p>
    <form method="post" action="<?= root_url('admin/kargo.php') ?>" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="geliver">
        <label class="field">API token <input type="password" name="token" maxlength="200" value="" placeholder="<?= $geliver['token'] !== '' ? 'Kayıtlı. Değiştirmek için yeni değeri yaz.' : '' ?>" autocomplete="new-password"></label>
        <label class="check">
            <input type="checkbox" name="test_mode" value="1"<?= $geliver['test_mode'] ? ' checked' : '' ?>>
            <span>Test modu</span>
        </label>
        <label class="field">En (cm) <input type="text" name="length" inputmode="decimal" maxlength="8" value="<?= e($geliver['length']) ?>" required></label>
        <label class="field">Boy (cm) <input type="text" name="width" inputmode="decimal" maxlength="8" value="<?= e($geliver['width']) ?>" required></label>
        <label class="field">Yükseklik (cm) <input type="text" name="height" inputmode="decimal" maxlength="8" value="<?= e($geliver['height']) ?>" required></label>
        <label class="field">Ağırlık (kg) <input type="text" name="weight" inputmode="decimal" maxlength="8" value="<?= e($geliver['weight']) ?>" required></label>
        <label class="field">Gönderici adı <input type="text" name="sender_name" maxlength="80" value="<?= e($geliver['sender']['name']) ?>" required></label>
        <label class="field">Gönderici e-posta <input type="email" name="sender_email" maxlength="100" value="<?= e($geliver['sender']['email']) ?>" required></label>
        <label class="field">Gönderici telefon <input type="text" name="sender_phone" maxlength="20" value="<?= e($geliver['sender']['phone']) ?>" required></label>
        <label class="field">Gönderici adresi <input type="text" name="sender_address" maxlength="180" value="<?= e($geliver['sender']['address']) ?>" required></label>
        <label class="field">İl <input type="text" name="sender_city" maxlength="40" value="<?= e($geliver['sender']['city']) ?>" required></label>
        <label class="field">İlçe <input type="text" name="sender_district" maxlength="40" value="<?= e($geliver['sender']['district']) ?>" required></label>
        <label class="field">Posta kodu <input type="text" name="sender_zip" inputmode="numeric" maxlength="5" value="<?= e($geliver['sender']['zip']) ?>" required></label>
        <button class="login-submit" type="submit">Geliver ayarlarını kaydet</button>
    </form>
    <?php if ($geliver['sender_id'] !== ''): ?><p>Gönderici adresi kayıtlı.</p><?php endif; ?>
</section>
<section class="settings-card">
    <h2>Müşteriye görünen kargo tutarları</h2>
    <p class="settings-lead">Müşteri ödeme adımında açık firmaları ve tutarlarını görür.</p>
    <?php if ($cargo === []): ?>
        <p>Henüz kargo firması yok.</p>
    <?php else: ?>
        <table class="loc-table">
            <thead><tr><th>Firma</th><th>Tutar</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($cargo as $method): ?>
                    <tr>
                        <td><?= e($method['name']) ?><?= $method['active'] ? '' : ' · gizli' ?></td>
                        <td><?= e(OrderBook::money($method['price'])) ?></td>
                        <td>
                            <form method="post" action="<?= root_url('admin/kargo.php') ?>">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="cargo_id" value="<?= e($method['id']) ?>">
                                <button type="submit" name="action" value="cargo_toggle"><?= $method['active'] ? 'Gizle' : 'Göster' ?></button>
                                <button type="submit" name="action" value="cargo_delete">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <form method="post" action="<?= root_url('admin/kargo.php') ?>">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="cargo_add">
        <label class="field">Firma <input type="text" name="cargo_name" maxlength="80" required></label>
        <label class="field">Tutar <input type="text" name="cargo_price" inputmode="decimal" placeholder="89,90" required></label>
        <button class="login-submit" type="submit">Kargo ekle</button>
    </form>
</section>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
