<?php

declare(strict_types=1);

$returnPath = (string) ($returnPath ?? '/odeme-sonuc');
$lockedAccess = (string) ($access ?? '');
$lockedNext = $returnPath . '?no=' . rawurlencode((string) ($orderNo ?? ''));
if ($lockedAccess !== '') {
    $lockedNext .= '&anahtar=' . rawurlencode($lockedAccess);
}
$lockedNext = MemberAccount::safeNext($lockedNext);
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container account-auth">
            <section class="shop-panel">
                <h2>Giriş gerekli</h2>
                <p class="account-lead">Ödeme sayfasından dönerken üye oturumun görünmedi. Siparişini görmek için yeniden giriş yap.</p>
                <p class="checkout-actions"><a class="btn" href="<?= url('/uye-giris' . ($lockedNext === '' ? '' : '?sonraki=' . rawurlencode($lockedNext))) ?>">Giriş yap</a></p>
            </section>
        </div>
    </section>
</div>
