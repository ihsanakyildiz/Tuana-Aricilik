<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';
require_once __DIR__ . '/app/OrderBook.php';
require_once __DIR__ . '/app/Paytr.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/odeme-kart/$#', $rawPath) === 1) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . absolute_url('/odeme-kart') . ($query === '' ? '' : '?' . $query), true, 301);
    exit;
}

public_maintenance();
MemberAccount::requireLogin();
header('X-Robots-Tag: noindex, follow');
header('Cache-Control: no-store');

$member = MemberAccount::current();
$orderNo = (string) ($_GET['no'] ?? $_POST['no'] ?? '');
$order = $member === null ? null : OrderBook::findForMember($orderNo, (int) $member['id']);
$missing = $order === null || (string) $order['payment_method'] !== 'kart';

if (!$missing && (string) $order['status'] === 'odendi') {
    MemberAccount::redirect('tesekkur?no=' . rawurlencode($orderNo));
}

$errors = [];
if (!$missing && (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
    if (!MemberAccount::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } elseif ((string) ($_POST['action'] ?? '') === 'cancel') {
        $errors = OrderBook::cancelCard($orderNo, (int) $member['id']);
        if ($errors === []) {
            MemberAccount::redirect('odeme-sonuc?no=' . rawurlencode($orderNo));
        }
    } else {
        $errors[] = 'Bu işlem tanınmıyor.';
    }
    $order = OrderBook::findForMember($orderNo, (int) $member['id']);
}

$token = null;
$tokenError = '';
if (!$missing && is_array($order) && (string) $order['status'] === 'odeme_bekliyor' && is_array($member)) {
    $started = Paytr::token($order, $member);
    $token = $started['token'];
    $tokenError = $started['error'];
}

if ($missing) {
    http_response_code(404);
    $pageTitle = 'Sayfa bulunamadı';
    $metaDescription = '';
    $robots = 'noindex,follow';
    require __DIR__ . '/partials/header.php';
    require __DIR__ . '/partials/missing.php';
    require __DIR__ . '/partials/footer.php';
    return;
}

$pageTitle = 'Güvenli ödeme';
$metaDescription = 'PayTR ödeme formu';
$canonicalPath = '/odeme-kart';
$bodyClass = 'is-page is-shop';
$robots = 'noindex,follow';
$shopTitle = 'Güvenli ödeme';
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Güvenli ödeme', 'url' => ''],
];
$pageScript = is_string($token) ? 'js/paytr.js' : '';
$csrf = MemberAccount::csrfToken();

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container paytr-wrap">
            <?php if ($errors !== []): ?>
                <p class="account-note" role="alert"><?= e($errors[0]) ?></p>
            <?php endif; ?>
            <?php if ((string) $order['status'] === 'iptal'): ?>
                <p class="account-note" role="status">Bu kart ödemesi tamamlanmadı.<?= (string) $order['paytr_note'] !== '' ? ' ' . e((string) $order['paytr_note']) : '' ?></p>
                <p class="checkout-actions"><a class="btn" href="<?= url('/urunler') ?>">Alışverişe dön</a></p>
            <?php elseif ($tokenError !== ''): ?>
                <p class="account-note" role="alert"><?= e($tokenError) ?></p>
            <?php elseif (is_string($token)): ?>
                <p class="account-lead">Kart bilgisi bu sitede tutulmaz. Ödeme PayTR güvenli sayfasında tamamlanır.</p>
                <iframe id="paytriframe" class="paytr-frame" title="PayTR güvenli ödeme" src="<?= e('https://www.paytr.com/odeme/guvenli/' . $token) ?>" frameborder="0" scrolling="yes"></iframe>
            <?php endif; ?>
            <?php if ((string) $order['status'] === 'odeme_bekliyor'): ?>
                <form method="post" action="<?= url('/odeme-kart?no=' . rawurlencode($orderNo)) ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="no" value="<?= e($orderNo) ?>">
                    <button class="btn btn-line" type="submit">Ödemeden vazgeç</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
