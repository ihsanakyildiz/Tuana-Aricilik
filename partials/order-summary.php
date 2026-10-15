<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/OrderBook.php';
require_once dirname(__DIR__) . '/app/MemberAccount.php';

$order = is_array($order ?? null) ? $order : [];
$shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
$invoice = is_array($order['invoice'] ?? null) ? $order['invoice'] : [];
$created = strtotime((string) ($order['created_at'] ?? ''));
?>
<section class="account-panel">
    <h2>Sipariş <?= e((string) ($order['order_no'] ?? '')) ?></h2>
    <p class="account-lead"><?= $created === false ? '' : e(date('d.m.Y H:i', $created)) ?> · <?= e(OrderBook::statusLabel((string) ($order['status'] ?? ''))) ?></p>
    <div class="summary-product">
        <?php if ((string) ($order['product_image'] ?? '') !== ''): ?>
            <?= media_tag((string) $order['product_image'], (string) ($order['product_title'] ?? ''), 'summary-photo', false) ?>
        <?php endif; ?>
        <div>
            <strong><?= e((string) ($order['product_title'] ?? '')) ?></strong>
            <p><?= (int) ($order['qty'] ?? 0) ?> adet · <?= e(OrderBook::money((string) ($order['unit_price'] ?? ''))) ?></p>
        </div>
    </div>
    <dl class="summary-lines">
        <div><dt>Kargo</dt><dd><?= (string) ($order['cargo_name'] ?? '') !== '' ? e((string) $order['cargo_name']) . ' · ' . e(OrderBook::money((string) ($order['cargo_price'] ?? ''))) : 'Kargo tutarı yok' ?></dd></div>
        <div><dt>Ödeme</dt><dd><?= e(OrderBook::paymentLabel((string) ($order['payment_method'] ?? ''))) ?></dd></div>
        <div class="is-total"><dt>Toplam</dt><dd><?= e(OrderBook::money((string) ($order['total'] ?? ''))) ?></dd></div>
    </dl>
    <h3>Teslimat</h3>
    <p><?= e((string) ($shipping['full_name'] ?? '')) ?> · <?= e(MemberAccount::phoneLabel((string) ($shipping['phone'] ?? ''))) ?></p>
    <p><?= e((string) ($shipping['line'] ?? '')) ?></p>
    <p><?= e(trim(MemberAccount::addressLine($shipping) . (((string) ($shipping['postal'] ?? '')) !== '' ? ' ' . (string) $shipping['postal'] : ''))) ?></p>
    <?php if (trim((string) ($invoice['title'] ?? '')) !== ''): ?>
        <h3>Fatura</h3>
        <p><?= ($invoice['kind'] ?? '') === 'kurumsal' ? 'Kurumsal' : 'Bireysel' ?> · <?= e((string) $invoice['title']) ?></p>
        <?php if (trim((string) ($invoice['tax_office'] ?? '')) !== ''): ?><p>Vergi dairesi: <?= e((string) $invoice['tax_office']) ?></p><?php endif; ?>
        <?php if (trim((string) ($invoice['tax_no'] ?? '')) !== ''): ?><p><?= ($invoice['kind'] ?? '') === 'kurumsal' ? 'Vergi no' : 'T.C. kimlik no' ?>: <?= e((string) $invoice['tax_no']) ?></p><?php endif; ?>
    <?php endif; ?>
    <?php if (($order['payment_method'] ?? '') === 'havale'): ?>
        <h3>Havale</h3>
        <?php $banks = OrderBook::banks(); ?>
        <?php if ($banks === []): ?>
            <p>Ödeme bekleniyor. Hesap bilgisi ayrıca iletilecek.</p>
        <?php else: ?>
            <?php foreach ($banks as $bank): ?>
                <p class="bank-line">
                    <strong><?= e($bank['bank']) ?></strong>
                    <span><?= e($bank['holder']) ?></span>
                    <span><?= e(trim(chunk_split($bank['iban'], 4, ' '))) ?></span>
                </p>
            <?php endforeach; ?>
            <p class="account-hint">Açıklamaya <?= e((string) ($order['order_no'] ?? '')) ?> yaz.</p>
        <?php endif; ?>
    <?php elseif (($order['payment_method'] ?? '') === 'elden'): ?>
        <p>Ödemeyi ürünü teslim alırken yaparsın.</p>
    <?php elseif (($order['status'] ?? '') === 'iade'): ?>
        <p>Kart ödemesinin tamamı iade edildi.</p>
    <?php elseif ((string) ($order['refunded'] ?? '0.00') !== '0.00' && ($order['payment_method'] ?? '') === 'kart'): ?>
        <p>Kart ödemesinin <?= e(OrderBook::money((string) $order['refunded'])) ?> tutarı iade edildi.</p>
    <?php elseif (($order['status'] ?? '') === 'odendi'): ?>
        <p>Kart ödemesi PayTR tarafından onaylandı.</p>
    <?php elseif (($order['status'] ?? '') === 'iptal'): ?>
        <p>Kart ödemesi tamamlanmadı.<?= (string) ($order['paytr_note'] ?? '') !== '' ? ' ' . e((string) $order['paytr_note']) : '' ?></p>
    <?php elseif (($order['payment_method'] ?? '') === 'kart'): ?>
        <p>Kart ödemesinin sonucu PayTR bildirimi geldikten sonra kesinleşir.</p>
    <?php endif; ?>
    <?php $geliver = is_array($order['geliver'] ?? null) ? $order['geliver'] : []; ?>
    <?php if ((string) ($geliver['tracking_number'] ?? '') !== ''): ?>
        <h3>Kargo takibi</h3>
        <p><?= (string) ($geliver['provider'] ?? '') !== '' ? e((string) $geliver['provider']) . ' · ' : '' ?><?= e((string) $geliver['tracking_number']) ?></p>
        <?php if ((string) ($geliver['tracking_url'] ?? '') !== ''): ?>
            <p><a href="<?= e((string) $geliver['tracking_url']) ?>" target="_blank" rel="noopener">Takip sayfası</a></p>
        <?php endif; ?>
    <?php endif; ?>
</section>
