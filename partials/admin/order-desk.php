<?php

declare(strict_types=1);

$shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
$invoice = is_array($order['invoice'] ?? null) ? $order['invoice'] : [];
$geliver = is_array($order['geliver'] ?? null) ? $order['geliver'] : [];
$created = strtotime((string) ($order['created_at'] ?? ''));
$customer = trim((string) ($order['first_name'] ?? '') . ' ' . (string) ($order['last_name'] ?? ''));
if ($customer === '') {
    $customer = 'Üye';
}
$initial = mb_strtoupper(mb_substr($customer, 0, 1, 'UTF-8'), 'UTF-8');
$avatar = (int) ($order['member_id'] ?? 0) % 6;
$progress = match ((string) ($order['status'] ?? '')) {
    'odeme_bekliyor' => 0,
    'odendi', 'teslimde_odenecek', 'hazirlaniyor' => 1,
    'kargoda' => 2,
    'teslim_edildi' => 3,
    default => -1,
};
$steps = ['Ödeme', 'Hazırlık', 'Kargo', 'Teslim'];
$shipmentId = (string) ($geliver['shipment_id'] ?? '');
if ((string) ($order['payment_method'] ?? '') === 'elden') {
    $shipLine = 'Elden teslim. Kargo gönderisi açılmaz.';
} elseif ($shipmentId !== '') {
    $shipBits = array_values(array_filter([
        (string) ($geliver['provider'] ?? ''),
        (string) ($geliver['tracking_number'] ?? ''),
    ], static fn (string $bit): bool => $bit !== ''));
    $shipLine = $shipBits !== [] ? implode(' · ', $shipBits) : 'Gönderi açıldı. Teklif ve etiket gönderim sayfasında.';
} elseif ((string) ($order['status'] ?? '') === 'odeme_bekliyor') {
    $shipLine = 'Ödeme onaylandıktan sonra gönderi açılır.';
} else {
    $shipLine = 'Gönderi henüz oluşturulmadı.';
}
$place = trim(MemberAccount::addressLine($shipping));
$postal = trim((string) ($shipping['postal'] ?? ''));
if ($postal !== '') {
    $place = trim($place . ' ' . $postal);
}
$orderNo = (string) ($order['order_no'] ?? '');
?>
<header class="sale-head">
    <div>
        <p class="sale-kicker"><a href="<?= root_url('admin/siparisler.php') ?>">Siparişler</a></p>
        <h2 class="sale-title"><?= e($orderNo) ?> <span class="catalog-status <?= e($orderStatusClass((string) $order['status'])) ?>"><?= e(OrderBook::statusLabel((string) $order['status'])) ?></span></h2>
        <p class="sale-meta"><?= $created === false ? '' : e(date('d.m.Y H:i', $created)) ?></p>
    </div>
    <a class="sale-btn" href="<?= root_url('admin/gonderi.php?no=' . rawurlencode($orderNo)) ?>"><?= admin_icon('archive') ?> Kargo gönderimi</a>
</header>
<section class="catalog-kpis is-4" aria-label="Sipariş tutarları">
    <article class="catalog-kpi">
        <span class="metric-ico is-purple"><?= admin_icon('card') ?></span>
        <div>
            <p>Toplam</p>
            <strong><?= e(OrderBook::money((string) $order['total'])) ?></strong>
            <small><?= e(OrderBook::paymentLabel((string) $order['payment_method'])) ?></small>
        </div>
    </article>
    <article class="catalog-kpi">
        <span class="metric-ico is-blue"><?= admin_icon('bag') ?></span>
        <div>
            <p>Ürün</p>
            <strong><?= e(OrderBook::money((string) $order['unit_price'])) ?></strong>
            <small><?= e((string) (int) $order['qty']) ?> adet</small>
        </div>
    </article>
    <article class="catalog-kpi">
        <span class="metric-ico is-teal"><?= admin_icon('archive') ?></span>
        <div>
            <p>Kargo tutarı</p>
            <strong><?= (string) $order['cargo_name'] !== '' ? e(OrderBook::money((string) $order['cargo_price'])) : '—' ?></strong>
            <small><?= (string) $order['cargo_name'] !== '' ? e((string) $order['cargo_name']) : 'Eklenmedi' ?></small>
        </div>
    </article>
    <article class="catalog-kpi">
        <span class="metric-ico is-gold"><?= admin_icon('clock') ?></span>
        <div>
            <p>Kayıt</p>
            <strong><?= $created === false ? '—' : e(date('d.m.Y', $created)) ?></strong>
            <small><?= $created === false ? '' : e(date('H:i', $created)) ?></small>
        </div>
    </article>
</section>
<div class="sale-grid">
    <div>
        <section class="sale-card">
            <h3>Ürün</h3>
            <div class="sale-product">
                <?php if ((string) ($order['product_image'] ?? '') !== ''): ?>
                    <img class="sale-photo" src="<?= root_url((string) $order['product_image']) ?>" alt="<?= e((string) $order['product_title']) ?>">
                <?php else: ?>
                    <span class="sale-photo"></span>
                <?php endif; ?>
                <div>
                    <strong><?= e((string) $order['product_title']) ?></strong>
                    <span><?= e((string) (int) $order['qty']) ?> adet · <?= e(OrderBook::money((string) $order['unit_price'])) ?></span>
                </div>
            </div>
            <dl class="sale-lines">
                <div><dt>Ürün</dt><dd><?= e(OrderBook::money((string) $order['unit_price'])) ?></dd></div>
                <div><dt>Kargo</dt><dd><?= (string) $order['cargo_name'] !== '' ? e(OrderBook::money((string) $order['cargo_price'])) : 'Yok' ?></dd></div>
                <div class="is-total"><dt>Toplam</dt><dd><?= e(OrderBook::money((string) $order['total'])) ?></dd></div>
            </dl>
        </section>
        <section class="sale-card">
            <h3>Durum</h3>
            <?php if ($progress < 0): ?>
                <p class="sale-lock"><?= e(OrderBook::statusLabel((string) $order['status'])) ?>. Bu sipariş teslim hattının dışında.</p>
            <?php else: ?>
                <ol class="sale-rail">
                    <?php foreach ($steps as $index => $label): ?>
                        <li class="<?= $index < $progress ? 'is-done' : ($index === $progress ? 'is-now' : '') ?>"><?= e($label) ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php if ((string) ($order['paytr_note'] ?? '') !== ''): ?>
                <p class="sale-note"><?= e((string) $order['paytr_note']) ?></p>
            <?php endif; ?>
            <form class="sale-form" method="post" action="<?= root_url('admin/siparisler.php') ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                <label class="field">
                    <span>Yeni durum</span>
                    <select name="status">
                        <?php foreach (OrderBook::statuses() as $status => $label): ?>
                            <option value="<?= e($status) ?>"<?= $status === (string) $order['status'] ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="sale-btn" type="submit">Kaydet</button>
            </form>
        </section>
        <?php if ((string) $order['payment_method'] === 'kart' && (OrderBook::canRefund($order) || ($order['refunds'] ?? []) !== [])): ?>
            <?php
            $refunds = is_array($order['refunds'] ?? null) ? $order['refunds'] : [];
            $remaining = OrderBook::refundableCents($order);
            $remainingLabel = ProductEditor::moneyLabel(intdiv($remaining, 100) . '.' . str_pad((string) ($remaining % 100), 2, '0', STR_PAD_LEFT));
            ?>
            <section class="sale-card">
                <h3>Kart iadesi</h3>
                <p class="sale-note">Parçalı iade kalan tutarı siparişte bırakır. Kalanın tamamı iade edilince sipariş iade edilir ve tutulan stok açılır.</p>
                <dl class="sale-lines">
                    <div><dt>İade edilen</dt><dd><?= e(OrderBook::money((string) $order['refunded'])) ?></dd></div>
                    <div><dt>Kalan</dt><dd><?= e($remainingLabel !== '' ? $remainingLabel : '0,00 TL') ?></dd></div>
                </dl>
                <?php foreach ($refunds as $refund): ?>
                    <?php if (!is_array($refund)) { continue; } ?>
                    <p class="sale-note"><?= e(OrderBook::money((string) ($refund['amount'] ?? ''))) ?><?= !empty($refund['test']) ? ' · Test' : '' ?><?= (string) ($refund['at'] ?? '') !== '' ? ' · ' . e((string) $refund['at']) : '' ?></p>
                <?php endforeach; ?>
                <?php if (OrderBook::canRefund($order) && Paytr::ready()): ?>
                    <form class="sale-form" method="post" action="<?= root_url('admin/siparisler.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="refund">
                        <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                        <label class="field">İade tutarı <input type="text" name="amount" inputmode="decimal" maxlength="16" placeholder="10,50" required></label>
                        <button class="sale-btn" type="submit">Bu tutarı iade et</button>
                    </form>
                    <form method="post" action="<?= root_url('admin/siparisler.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="refund_full">
                        <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                        <button class="sale-btn is-ghost" type="submit">Kalanın tamamını iade et</button>
                    </form>
                <?php elseif (OrderBook::canRefund($order)): ?>
                    <p class="sale-note">PayTR bilgileri kayıtlı olmadığı için iade gönderilemiyor.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
    <aside class="sale-side">
        <section class="sale-card">
            <h3>Müşteri</h3>
            <div class="sale-person">
                <span class="catalog-avatar is-<?= e((string) $avatar) ?>"><?= e($initial) ?></span>
                <div>
                    <strong><?= e($customer) ?></strong>
                    <span><?= e((string) $order['email']) ?></span>
                </div>
            </div>
            <p><?= e(MemberAccount::phoneLabel((string) $order['phone'])) ?></p>
            <?php if ((int) $order['member_id'] > 0): ?>
                <p><a href="<?= root_url('admin/musteriler.php?uye=' . (int) $order['member_id']) ?>">Üye kaydı</a></p>
            <?php else: ?>
                <p class="sale-note">Üye olmadan verilen sipariş.</p>
            <?php endif; ?>
        </section>
        <section class="sale-card">
            <h3>Teslimat</h3>
            <p><strong><?= e((string) ($shipping['full_name'] ?? '')) ?></strong></p>
            <p><?= e(MemberAccount::phoneLabel((string) ($shipping['phone'] ?? ''))) ?></p>
            <p><?= e((string) ($shipping['line'] ?? '')) ?></p>
            <p><?= e($place) ?></p>
            <?php if (trim((string) ($shipping['note'] ?? '')) !== ''): ?>
                <p class="sale-note"><?= e((string) $shipping['note']) ?></p>
            <?php endif; ?>
        </section>
        <section class="sale-card">
            <h3>Fatura</h3>
            <?php if (trim((string) ($invoice['title'] ?? '')) === ''): ?>
                <p class="sale-note">Fatura bilgisi siparişe eklenmedi.</p>
            <?php else: ?>
                <p><?= ($invoice['kind'] ?? '') === 'kurumsal' ? 'Kurumsal' : 'Bireysel' ?></p>
                <p><strong><?= e((string) $invoice['title']) ?></strong></p>
                <?php if (trim((string) ($invoice['tax_office'] ?? '')) !== ''): ?><p>Vergi dairesi: <?= e((string) $invoice['tax_office']) ?></p><?php endif; ?>
                <?php if (trim((string) ($invoice['tax_no'] ?? '')) !== ''): ?><p><?= e((string) $invoice['tax_no']) ?></p><?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="sale-card">
            <h3>Kargo</h3>
            <p><?= e($shipLine) ?></p>
            <?php
            $iade = is_array($geliver['iade'] ?? null) ? $geliver['iade'] : [];
            $returnCode = (string) ($iade['barcode'] ?? '') !== '' ? (string) $iade['barcode'] : (string) ($iade['tracking_number'] ?? '');
            ?>
            <?php if ($returnCode !== ''): ?>
                <p>İade kodu</p>
                <p class="sale-code"><?= e($returnCode) ?></p>
            <?php elseif (OrderBook::geliverReturnable($order)): ?>
                <form method="post" action="<?= root_url('admin/gonderi.php') ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="geliver_return">
                    <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                    <button class="sale-btn" type="submit">İade kodu oluştur</button>
                </form>
            <?php elseif ((string) $order['status'] === 'teslim_edildi'): ?>
                <p class="sale-note">İade kodu, kargo etiketi alınmış teslimatlar için açılır.</p>
            <?php endif; ?>
            <a class="sale-btn is-ghost" href="<?= root_url('admin/gonderi.php?no=' . rawurlencode($orderNo)) ?>">Gönderim sayfası</a>
        </section>
    </aside>
</div>
