<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/OrderBook.php';
require_once dirname(__DIR__) . '/app/MemberAccount.php';
require_once dirname(__DIR__) . '/app/Geliver.php';

Auth::boot();
Auth::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        Auth::flash('err', 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.');
    } else {
        $errors = match ($action) {
            'geliver_create' => OrderBook::geliverAct((string) ($_POST['order_no'] ?? ''), 'create'),
            'geliver_sync' => OrderBook::geliverAct((string) ($_POST['order_no'] ?? ''), 'sync'),
            'geliver_accept' => OrderBook::geliverAct((string) ($_POST['order_no'] ?? ''), 'accept', (string) ($_POST['offer_id'] ?? '')),
            'geliver_cancel' => OrderBook::geliverAct((string) ($_POST['order_no'] ?? ''), 'cancel'),
            'geliver_return' => OrderBook::geliverReturn((string) ($_POST['order_no'] ?? '')),
            'geliver_return_sync' => OrderBook::geliverReturnSync((string) ($_POST['order_no'] ?? '')),
            default => ['Bu işlem tanınmıyor.'],
        };
        $saved = match ($action) {
            'geliver_create' => 'Geliver gönderisi oluşturuldu.',
            'geliver_sync' => 'Gönderi durumu alındı.',
            'geliver_accept' => 'Kargo etiketi alındı.',
            'geliver_cancel' => 'Geliver gönderisi iptal edildi.',
            'geliver_return' => 'İade kodu oluşturuldu.',
            'geliver_return_sync' => 'İade kodu güncellendi.',
            default => 'Kaydedildi.',
        };
        Auth::flash($errors === [] ? 'ok' : 'err', $errors === [] ? $saved : $errors[0]);
    }
    $back = 'admin/gonderi.php';
    $postedNo = (string) ($_POST['order_no'] ?? '');
    if (preg_match('/^TA\d{6}[A-F0-9]{6}$/', $postedNo) === 1) {
        $back .= '?no=' . rawurlencode($postedNo);
    }
    header('Location: ' . root_path() . '/' . $back, true, 303);
    exit;
}

$orderNo = (string) ($_GET['no'] ?? '');
$order = $orderNo === '' ? null : OrderBook::find($orderNo);
$pageTitle = 'Kargo gönderimi';
$crumbCurrent = 'Kargo gönderimi';
$activeNav = 'siparisler';
$csrf = Auth::csrfToken();
$geliverStatusLabel = static function (string $code): string {
    return match ($code) {
        'CREATED' => 'Oluşturuldu',
        'GOT_OFFERS' => 'Teklif hazır',
        'OFFER_ACCEPTED' => 'Teklif kabul edildi',
        'TRACKING_CODE_CREATED' => 'Takip kodu oluştu',
        'LABEL_PRINTED' => 'Etiket hazır',
        'SHIPPED' => 'Yola çıktı',
        'DELIVERED' => 'Teslim edildi',
        'CANCELED', 'CANCELLED' => 'İptal',
        'RETURNED' => 'İade',
        'RETURN_SHIPPED' => 'İade yolda',
        'FAILED' => 'Başarısız',
        default => $code,
    };
};

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if (!is_array($order)): ?>
    <div class="form-alert" role="alert"><p>Sipariş bulunamadı.</p></div>
<?php else: ?>
    <?php
    $shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
    $geliver = is_array($order['geliver'] ?? null) ? $order['geliver'] : [];
    $parcel = Geliver::config();
    $shipmentId = (string) ($geliver['shipment_id'] ?? '');
    $offers = is_array($geliver['offers'] ?? null) ? $geliver['offers'] : [];
    $labelReady = (string) ($geliver['offer_id'] ?? '') !== '' || (string) ($geliver['label_url'] ?? '') !== '';
    $pickup = (string) $order['payment_method'] === 'elden';
    $waitingPay = (string) $order['status'] === 'odeme_bekliyor';
    $step = 1;
    if (!$pickup && !$waitingPay) {
        $step = 2;
    }
    if ($shipmentId !== '') {
        $step = 3;
    }
    if ($labelReady) {
        $step = 4;
    }
    $rail = ['Ödeme', 'Gönderi', 'Teklif', 'Etiket'];
    $place = trim(MemberAccount::addressLine($shipping));
    $postal = trim((string) ($shipping['postal'] ?? ''));
    if ($postal !== '') {
        $place = trim($place . ' ' . $postal);
    }
    ?>
    <header class="sale-head">
        <div>
            <p class="sale-kicker"><a href="<?= root_url('admin/siparisler.php?no=' . rawurlencode($orderNo)) ?>"><?= e($orderNo) ?></a></p>
            <p class="sale-meta"><?= e((string) $order['product_title']) ?> · <?= e((string) (int) $order['qty']) ?> adet</p>
        </div>
        <a class="sale-btn is-ghost" href="<?= root_url('admin/siparisler.php?no=' . rawurlencode($orderNo)) ?>">Siparişe dön</a>
    </header>
    <?php if ($parcel['test_mode']): ?>
        <p class="sale-banner">Test modu açık. Bu ekrandan gerçek etiket satın alınmaz.</p>
    <?php endif; ?>
    <?php if (!$pickup): ?>
        <ol class="sale-rail">
            <?php foreach ($rail as $index => $label): ?>
                <?php $number = $index + 1; ?>
                <li class="<?= $number < $step ? 'is-done' : ($number === $step ? 'is-now' : '') ?>"><?= e((string) $number) ?>. <?= e($label) ?></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <div class="sale-grid">
        <section class="sale-card">
            <h3>Gönderi</h3>
            <?php if ($pickup): ?>
                <p class="sale-lock">Elden teslimatta kargo gönderisi açılmaz.</p>
            <?php elseif ($waitingPay && $shipmentId === ''): ?>
                <p class="sale-lock">Ödeme onaylandıktan sonra gönderi açılır.</p>
            <?php elseif ($shipmentId === '' && !OrderBook::geliverAllowed($order)): ?>
                <p class="sale-lock">Bu sipariş için yeni gönderi açılmaz.</p>
            <?php elseif ($shipmentId === '' && !Geliver::ready()): ?>
                <p class="sale-note">Gönderi açmak için Geliver tokenını ve gönderici adresini kaydet.</p>
                <a class="sale-btn" href="<?= root_url('admin/kargo.php') ?>">Kargo ayarları</a>
            <?php elseif ($shipmentId === ''): ?>
                <p class="sale-note">Gönderi açılınca kargo firmalarının teklifleri bu tabloda listelenir. Etiket, seçtiğin teklif onaylanınca alınır.</p>
                <form method="post" action="<?= root_url('admin/gonderi.php') ?>">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="geliver_create">
                    <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                    <button class="sale-btn" type="submit">Gönderi oluştur</button>
                </form>
            <?php else: ?>
                <dl class="sale-lines">
                    <?php if ((string) ($geliver['status'] ?? '') !== ''): ?>
                        <div><dt>Durum</dt><dd><?= e($geliverStatusLabel((string) $geliver['status'])) ?><?= !empty($geliver['test']) ? ' · Test' : '' ?></dd></div>
                    <?php endif; ?>
                    <?php if ((string) ($geliver['provider'] ?? '') !== ''): ?>
                        <div><dt>Firma</dt><dd><?= e((string) $geliver['provider']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ((string) ($geliver['tracking_number'] ?? '') !== ''): ?>
                        <div><dt>Takip no</dt><dd><?= e((string) $geliver['tracking_number']) ?></dd></div>
                    <?php endif; ?>
                    <?php if ($labelReady && (string) ($geliver['amount'] ?? '') !== ''): ?>
                        <div><dt>Etiket tutarı</dt><dd><?= e(trim((string) $geliver['amount'] . ' ' . (string) ($geliver['currency'] ?? ''))) ?></dd></div>
                    <?php endif; ?>
                </dl>
                <?php if ((string) ($geliver['note'] ?? '') !== ''): ?>
                    <p class="sale-note"><?= e((string) $geliver['note']) ?></p>
                <?php endif; ?>
                <div class="sale-actions">
                    <?php if ((string) ($geliver['tracking_url'] ?? '') !== ''): ?>
                        <a class="sale-btn is-ghost" href="<?= e((string) $geliver['tracking_url']) ?>" target="_blank" rel="noopener">Takip sayfası</a>
                    <?php endif; ?>
                    <?php if ((string) ($geliver['label_url'] ?? '') !== ''): ?>
                        <a class="sale-btn" href="<?= e((string) $geliver['label_url']) ?>" target="_blank" rel="noopener">Etiketi aç</a>
                    <?php endif; ?>
                </div>
                <?php if (!$labelReady): ?>
                    <?php if ($offers === []): ?>
                        <p class="sale-note">Teklifler henüz hazır değil.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="ship-offers">
                                <thead>
                                    <tr><th>Firma</th><th>Süre</th><th>Tutar</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($offers as $offer): ?>
                                        <?php if (!is_array($offer)) { continue; } ?>
                                        <?php
                                        $offerName = (string) ($offer['service'] ?? '') !== '' ? (string) $offer['service'] : (string) ($offer['provider'] ?? '');
                                        $offerAmount = (string) ($offer['amount'] ?? '');
                                        $offerMoney = preg_match('/^\d+\.\d{2}$/', $offerAmount) === 1 ? OrderBook::money($offerAmount) : trim($offerAmount . ' ' . (string) ($offer['currency'] ?? ''));
                                        ?>
                                        <tr>
                                            <td><?= e($offerName) ?><?= !empty($offer['cheapest']) ? ' · En uygun' : '' ?></td>
                                            <td><?= e((string) ($offer['eta'] ?? '')) ?></td>
                                            <td><?= e($offerMoney) ?></td>
                                            <td>
                                                <form method="post" action="<?= root_url('admin/gonderi.php') ?>">
                                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                                    <input type="hidden" name="action" value="geliver_accept">
                                                    <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                                                    <input type="hidden" name="offer_id" value="<?= e((string) ($offer['id'] ?? '')) ?>">
                                                    <button class="sale-btn" type="submit">Bu teklifi al</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="sale-actions">
                    <form method="post" action="<?= root_url('admin/gonderi.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="geliver_sync">
                        <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                        <button class="sale-btn is-ghost" type="submit">Durumu al</button>
                    </form>
                    <?php if ((string) ($geliver['tracking_number'] ?? '') === ''): ?>
                        <form method="post" action="<?= root_url('admin/gonderi.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="geliver_cancel">
                            <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                            <button class="sale-btn is-danger" type="submit">Gönderiyi iptal et</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (!empty($geliver['test'])): ?>
                    <p class="sale-note">Test modunda durumu almak gönderiyi bir adım ilerletir. Siparişin kendisi iptal olmaz.</p>
                <?php elseif ((string) ($geliver['tracking_number'] ?? '') === ''): ?>
                    <p class="sale-note">Gönderiyi iptal etmek sipariş durumunu değiştirmez.</p>
                <?php endif; ?>
            <?php endif; ?>
            <?php
            $iade = is_array($geliver['iade'] ?? null) ? $geliver['iade'] : [];
            $returnCode = (string) ($iade['barcode'] ?? '') !== '' ? (string) $iade['barcode'] : (string) ($iade['tracking_number'] ?? '');
            ?>
            <?php if ((string) $order['status'] === 'teslim_edildi' || $returnCode !== '' || (string) ($iade['shipment_id'] ?? '') !== ''): ?>
                <h3>İade kodu</h3>
                <?php if ($returnCode !== ''): ?>
                    <p class="sale-code"><?= e($returnCode) ?></p>
                    <p class="sale-note">Müşteri bu kodla paketi kargo şubesine verir.<?= (string) ($iade['provider'] ?? '') !== '' ? ' Firma: ' . e((string) $iade['provider']) : '' ?></p>
                    <?php if ((string) ($iade['label_url'] ?? '') !== ''): ?>
                        <p><a class="sale-btn is-ghost" href="<?= e((string) $iade['label_url']) ?>" target="_blank" rel="noopener">İade etiketini aç</a></p>
                    <?php endif; ?>
                <?php elseif ((string) ($iade['shipment_id'] ?? '') !== ''): ?>
                    <p class="sale-note">İade kaydı açıldı. Kod hazır olunca burada görünür.</p>
                <?php elseif (OrderBook::geliverReturnable($order)): ?>
                    <p class="sale-note">Teslim edilen ürün için Geliver iade kodu oluşturur. Kod, paketin verileceği kargo kaydıdır.</p>
                    <form method="post" action="<?= root_url('admin/gonderi.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="geliver_return">
                        <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                        <button class="sale-btn" type="submit">İade kodu oluştur</button>
                    </form>
                <?php else: ?>
                    <p class="sale-note">İade kodu, etiketi alınmış ve teslim edilmiş gönderiler için açılır.</p>
                <?php endif; ?>
                <?php if ((string) ($iade['shipment_id'] ?? '') !== ''): ?>
                    <form method="post" action="<?= root_url('admin/gonderi.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="geliver_return_sync">
                        <input type="hidden" name="order_no" value="<?= e($orderNo) ?>">
                        <button class="sale-btn is-ghost" type="submit">İade kodunu güncelle</button>
                    </form>
                    <?php if (!empty($geliver['test']) || $parcel['test_mode']): ?>
                        <p class="sale-note">Test modunda bu düğme iade gönderisini bir adım ilerletir.</p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <aside class="sale-side">
            <section class="sale-card">
                <h3>Alıcı</h3>
                <p><strong><?= e((string) ($shipping['full_name'] ?? '')) ?></strong></p>
                <p><?= e(MemberAccount::phoneLabel((string) ($shipping['phone'] ?? ''))) ?></p>
                <p><?= e((string) ($shipping['line'] ?? '')) ?></p>
                <p><?= e($place) ?></p>
            </section>
            <section class="sale-card">
                <h3>Paket</h3>
                <dl class="sale-lines">
                    <div><dt>Ölçü</dt><dd><?= e($parcel['length'] . ' × ' . $parcel['width'] . ' × ' . $parcel['height']) ?> cm</dd></div>
                    <div><dt>Ağırlık</dt><dd><?= e($parcel['weight']) ?> kg</dd></div>
                    <div><dt>Gönderici</dt><dd><?= $parcel['sender_id'] !== '' ? 'Kayıtlı' : 'Eksik' ?></dd></div>
                </dl>
                <a class="sale-btn is-ghost" href="<?= root_url('admin/kargo.php') ?>">Paket ayarları</a>
            </section>
        </aside>
    </div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
