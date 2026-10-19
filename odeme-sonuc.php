<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';
require_once __DIR__ . '/app/OrderBook.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/odeme-sonuc/$#', $rawPath) === 1) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . absolute_url('/odeme-sonuc') . ($query === '' ? '' : '?' . $query), true, 301);
    exit;
}

public_maintenance();
MemberAccount::boot(true);
header('X-Robots-Tag: noindex, follow');
header('Cache-Control: no-store');

$access = (string) ($_GET['anahtar'] ?? '');
if (strlen($access) > 64) {
    $access = '';
}
$orderNo = (string) ($_GET['no'] ?? '');
$order = OrderBook::forViewer($orderNo, $access);
if ($order === null && OrderBook::needsLogin($orderNo, true)) {
    $pageTitle = 'Giriş gerekli';
    $metaDescription = '';
    $canonicalPath = '/odeme-sonuc';
    $bodyClass = 'is-page is-shop';
    $robots = 'noindex,follow';
    $shopTitle = 'Ödeme sonucu';
    $shopCrumbs = [
        ['label' => 'Ana Sayfa', 'url' => '/'],
        ['label' => 'Ödeme sonucu', 'url' => ''],
    ];
    $returnPath = '/odeme-sonuc';
    require __DIR__ . '/partials/header.php';
    require __DIR__ . '/partials/shop-banner.php';
    require __DIR__ . '/partials/order-locked.php';
    require __DIR__ . '/partials/footer.php';
    return;
}
if ($order === null || (string) $order['payment_method'] !== 'kart') {
    http_response_code(404);
    $pageTitle = 'Sayfa bulunamadı';
    $metaDescription = '';
    $robots = 'noindex,follow';
    require __DIR__ . '/partials/header.php';
    require __DIR__ . '/partials/missing.php';
    require __DIR__ . '/partials/footer.php';
    return;
}

if ((string) $order['status'] === 'odendi') {
    MemberAccount::redirect('tesekkur?' . OrderBook::resumeQuery($order));
}

$pageTitle = 'Ödeme sonucu';
$metaDescription = 'Kart ödeme sonucu';
$canonicalPath = '/odeme-sonuc';
$bodyClass = 'is-page is-shop';
$robots = 'noindex,follow';
$shopTitle = 'Ödeme sonucu';
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Ödeme sonucu', 'url' => ''],
];
$waiting = (string) $order['status'] === 'odeme_bekliyor';
$pageScript = $waiting ? 'js/paytr-wait.js' : '';

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container account-auth"<?= $waiting ? ' data-paytr-wait="1"' : '' ?>>
            <?php if ($waiting): ?>
                <p class="account-note" role="status">Ödeme bildirimi bekleniyor. Sonuç PayTR bildirimi geldikten sonra kesinleşir.</p>
            <?php else: ?>
                <p class="account-note" role="status">Kart ödemesi tamamlanmadı.<?= (string) $order['paytr_note'] !== '' ? ' ' . e((string) $order['paytr_note']) : '' ?></p>
            <?php endif; ?>
            <?php require __DIR__ . '/partials/order-summary.php'; ?>
            <p class="checkout-actions">
                <?php if ($waiting): ?>
                    <a class="btn" href="<?= url('/odeme-kart?' . OrderBook::resumeQuery($order)) ?>">Ödeme sayfasına dön</a>
                <?php endif; ?>
                <?php if ((int) $order['member_id'] > 0): ?>
                    <a class="btn btn-line" href="<?= url('/siparislerim?no=' . rawurlencode($orderNo)) ?>">Siparişlerim</a>
                <?php else: ?>
                    <a class="btn btn-line" href="<?= url('/urunler') ?>">Alışverişe dön</a>
                <?php endif; ?>
            </p>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
