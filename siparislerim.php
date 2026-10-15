<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';
require_once __DIR__ . '/app/OrderBook.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/siparislerim/$#', $rawPath) === 1) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . absolute_url('/siparislerim') . ($query === '' ? '' : '?' . $query), true, 301);
    exit;
}

public_maintenance();
MemberAccount::requireLogin();
header('X-Robots-Tag: noindex, follow');
header('Cache-Control: no-store');

$member = MemberAccount::current();
if ($member === null) {
    MemberAccount::redirect('uye-giris?sonraki=' . rawurlencode('/siparislerim'));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedNo = (string) ($_POST['order_no'] ?? '');
    if (!MemberAccount::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        MemberAccount::flash('err', 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.');
    } elseif ((string) ($_POST['action'] ?? '') !== 'iade') {
        MemberAccount::flash('err', 'Bu işlem tanınmıyor.');
    } else {
        $errors = OrderBook::geliverReturn($postedNo, (int) $member['id']);
        MemberAccount::flash($errors === [] ? 'ok' : 'err', $errors === [] ? 'İade kodun hazır.' : $errors[0]);
    }
    $back = '/siparislerim';
    if (preg_match('/^TA\d{6}[A-F0-9]{6}$/', $postedNo) === 1) {
        $back .= '?no=' . rawurlencode($postedNo);
    }
    MemberAccount::redirect(ltrim($back, '/'));
}
$orderNo = (string) ($_GET['no'] ?? '');
$order = $orderNo === '' ? null : OrderBook::findForMember($orderNo, (int) $member['id']);
$orders = OrderBook::forMember((int) $member['id']);
$csrf = MemberAccount::csrfToken();
$flash = MemberAccount::pullFlash();

$pageTitle = 'Siparişlerim';
$metaDescription = 'Verdiğin siparişler';
$canonicalPath = '/siparislerim';
$bodyClass = 'is-page is-shop';
$robots = 'noindex,follow';
$shopTitle = 'Siparişlerim';
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Siparişlerim', 'url' => ''],
];

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container account-layout">
            <aside class="account-nav" aria-label="Hesap bölümleri">
                <a href="<?= url('/hesabim#uyelik') ?>">Üyelik</a>
                <a href="<?= url('/hesabim#sifre') ?>">Şifre</a>
                <a href="<?= url('/hesabim#kargo') ?>">Kargo adresi</a>
                <a href="<?= url('/hesabim#fatura') ?>">Fatura</a>
                <a href="<?= url('/siparislerim') ?>" aria-current="page">Siparişlerim</a>
                <form method="post" action="<?= url('/uye-cikis') ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <button type="submit">Çıkış yap</button>
                </form>
            </aside>
            <div class="account-main">
                <?php if ($flash !== null): ?>
                    <p class="<?= ($flash['type'] ?? '') === 'err' ? 'account-alert' : 'account-note' ?>" role="status"><?= e($flash['text']) ?></p>
                <?php endif; ?>
                <?php if ($orderNo !== '' && $order === null): ?>
                    <div class="account-alert" role="alert"><p>Sipariş bulunamadı.</p></div>
                <?php endif; ?>
                <?php if (is_array($order)): ?>
                    <?php require __DIR__ . '/partials/order-summary.php'; ?>
                    <?php if ((string) ($order['status'] ?? '') === 'teslim_edildi'): ?>
                        <?php
                        $iade = is_array($order['geliver']['iade'] ?? null) ? $order['geliver']['iade'] : [];
                        $returnCode = (string) ($iade['barcode'] ?? '') !== '' ? (string) $iade['barcode'] : (string) ($iade['tracking_number'] ?? '');
                        ?>
                        <section class="account-panel">
                            <h2>Ürünü iade et</h2>
                            <?php if ($returnCode !== ''): ?>
                                <p class="account-lead">Paketi bu iade koduyla kargo şubesine ver.</p>
                                <p class="return-code"><?= e($returnCode) ?></p>
                                <?php if ((string) ($iade['provider'] ?? '') !== ''): ?><p><?= e((string) $iade['provider']) ?></p><?php endif; ?>
                                <?php if ((string) ($iade['label_url'] ?? '') !== ''): ?>
                                    <p><a class="btn btn-line" href="<?= e((string) $iade['label_url']) ?>" target="_blank" rel="noopener">İade etiketini aç</a></p>
                                <?php endif; ?>
                            <?php elseif ((string) ($iade['shipment_id'] ?? '') !== ''): ?>
                                <p class="account-lead">İade kaydın açıldı. Kod hazır olunca bu sayfada görünür.</p>
                            <?php elseif (OrderBook::geliverReturnable($order)): ?>
                                <p class="account-lead">Teslim aldığın ürün için kargo iade kodu oluşturulur.</p>
                                <form method="post" action="<?= url('/siparislerim') ?>">
                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="iade">
                                    <input type="hidden" name="order_no" value="<?= e((string) $order['order_no']) ?>">
                                    <button class="btn" type="submit">İade et</button>
                                </form>
                            <?php else: ?>
                                <p class="account-lead">Bu sipariş kargoyla gönderilmediği için iade kodu oluşturulamıyor.</p>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>
                    <p><a href="<?= url('/siparislerim') ?>">Tüm siparişler</a></p>
                <?php elseif ($orders === []): ?>
                    <section class="account-panel">
                        <h2>Siparişlerim</h2>
                        <p class="account-lead">Henüz siparişin yok.</p>
                        <a class="btn btn-sm" href="<?= url('/urunler') ?>">Ürünlere bak</a>
                    </section>
                <?php else: ?>
                    <section class="account-panel">
                        <h2>Siparişlerim</h2>
                        <div class="order-list">
                            <?php foreach ($orders as $row): ?>
                                <?php $created = strtotime((string) $row['created_at']); ?>
                                <a class="order-row" href="<?= url('/siparislerim?no=' . rawurlencode((string) $row['order_no'])) ?>">
                                    <strong><?= e((string) $row['order_no']) ?></strong>
                                    <span><?= e((string) $row['product_title']) ?> · <?= (int) $row['qty'] ?> adet</span>
                                    <span><?= e(OrderBook::money((string) $row['total'])) ?></span>
                                    <span><?= e(OrderBook::statusLabel((string) $row['status'])) ?></span>
                                    <span><?= $created === false ? '' : e(date('d.m.Y', $created)) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
