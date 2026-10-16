<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';
require_once __DIR__ . '/app/OrderBook.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/tesekkur/$#', $rawPath) === 1) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . absolute_url('/tesekkur') . ($query === '' ? '' : '?' . $query), true, 301);
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
if ($order === null) {
    http_response_code(404);
    $pageTitle = 'Sayfa bulunamadı';
    $metaDescription = '';
    $robots = 'noindex,follow';
    require __DIR__ . '/partials/header.php';
    require __DIR__ . '/partials/missing.php';
    require __DIR__ . '/partials/footer.php';
    return;
}

$pageTitle = 'Teşekkürler';
$metaDescription = 'Sipariş özeti';
$canonicalPath = '/tesekkur';
$bodyClass = 'is-page is-shop';
$robots = 'noindex,follow';
$shopTitle = 'Teşekkürler';
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Teşekkürler', 'url' => ''],
];

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container account-auth">
            <p class="account-note" role="status">Siparişin alındı. Özeti aşağıda duruyor.</p>
            <?php require __DIR__ . '/partials/order-summary.php'; ?>
            <p class="checkout-actions">
                <?php if ((int) $order['member_id'] > 0): ?>
                    <a class="btn" href="<?= url('/siparislerim?no=' . rawurlencode((string) $order['order_no'])) ?>">Siparişlerim</a>
                <?php else: ?>
                    <p class="account-hint">Bu sipariş üye hesabına yazılmadı. Sipariş numarasını sakla.</p>
                <?php endif; ?>
                <a class="btn btn-line" href="<?= url('/urunler') ?>">Alışverişe dön</a>
            </p>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
