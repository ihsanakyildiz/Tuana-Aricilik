<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/OrderBook.php';
require_once dirname(__DIR__) . '/app/MemberAccount.php';

Auth::boot();
Auth::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        Auth::flash('err', 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.');
    } else {
        $errors = match ($action) {
            'status' => OrderBook::setStatus((string) ($_POST['order_no'] ?? ''), (string) ($_POST['status'] ?? '')),
            'refund', 'refund_full' => OrderBook::refund((string) ($_POST['order_no'] ?? ''), (string) ($_POST['amount'] ?? ''), $action === 'refund_full'),
            default => ['Bu işlem tanınmıyor.'],
        };
        $saved = match ($action) {
            'refund', 'refund_full' => 'İade PayTR tarafına iletildi.',
            default => 'Kaydedildi.',
        };
        Auth::flash($errors === [] ? 'ok' : 'err', $errors === [] ? $saved : $errors[0]);
    }
    $back = 'admin/siparisler.php';
    $orderNo = (string) ($_POST['order_no'] ?? '');
    $detailActions = ['status', 'refund', 'refund_full'];
    if (preg_match('/^TA\d{6}[A-F0-9]{6}$/', $orderNo) === 1 && in_array($action, $detailActions, true)) {
        $back .= '?no=' . rawurlencode($orderNo);
    }
    header('Location: ' . root_path() . '/' . $back, true, 303);
    exit;
}

$pageTitle = 'Siparişler';
$crumbCurrent = 'Siparişler';
$activeNav = 'siparisler';
$orderNo = (string) ($_GET['no'] ?? '');
$order = $orderNo === '' ? null : OrderBook::find($orderNo);
$orders = OrderBook::all();
$csrf = Auth::csrfToken();
if (is_array($order)) {
    $pageTitle = 'Sipariş detayı';
    $crumbCurrent = (string) $order['order_no'];
}
$orderStatusClass = static function (string $status): string {
    return match ($status) {
        'odendi', 'teslim_edildi' => 'is-yayinda',
        'kargoda' => 'is-ship',
        'hazirlaniyor' => 'is-prep',
        'iade', 'iptal' => 'is-tukendi',
        default => 'is-taslak',
    };
};

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($orderNo !== '' && $order === null): ?>
    <div class="form-alert" role="alert"><p>Sipariş bulunamadı.</p></div>
<?php endif; ?>
<?php if (is_array($order)): ?>
    <?php require dirname(__DIR__) . '/partials/admin/order-desk.php'; ?>
<?php else:
    $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    if (mb_strlen($query, 'UTF-8') > 80) {
        $query = mb_substr($query, 0, 80, 'UTF-8');
    }
    $statusFilter = is_string($_GET['durum'] ?? null) ? $_GET['durum'] : '';
    if (!array_key_exists($statusFilter, OrderBook::statuses())) {
        $statusFilter = '';
    }
    $paymentFilter = is_string($_GET['odeme'] ?? null) ? $_GET['odeme'] : '';
    if (!array_key_exists($paymentFilter, OrderBook::payments())) {
        $paymentFilter = '';
    }
    $buyerFilter = is_string($_GET['uye'] ?? null) ? $_GET['uye'] : '';
    if (!in_array($buyerFilter, ['uye', 'misafir'], true)) {
        $buyerFilter = '';
    }
    $pageSize = is_string($_GET['adet'] ?? null) ? (int) $_GET['adet'] : 8;
    if (!in_array($pageSize, [8, 16, 32], true)) {
        $pageSize = 8;
    }
    $view = ($_GET['gorunum'] ?? '') === 'kart' ? 'kart' : 'liste';
    $page = max(1, (int) ($_GET['sayfa'] ?? 1));
    $needle = mb_strtolower($query, 'UTF-8');
    $matched = [];
    foreach ($orders as $row) {
        if ($statusFilter !== '' && (string) $row['status'] !== $statusFilter) {
            continue;
        }
        if ($paymentFilter !== '' && (string) $row['payment_method'] !== $paymentFilter) {
            continue;
        }
        $guestOrder = (int) $row['member_id'] === 0;
        if ($buyerFilter === 'uye' && $guestOrder) {
            continue;
        }
        if ($buyerFilter === 'misafir' && !$guestOrder) {
            continue;
        }
        if ($needle !== '') {
            $haystack = mb_strtolower(trim((string) $row['order_no'] . ' ' . (string) $row['first_name'] . ' ' . (string) $row['last_name'] . ' ' . (string) $row['email'] . ' ' . (string) $row['product_title']), 'UTF-8');
            if (mb_strpos($haystack, $needle) === false) {
                continue;
            }
        }
        $matched[] = $row;
    }
    $pageCount = max(1, (int) ceil(count($matched) / $pageSize));
    if ($page > $pageCount) {
        $page = $pageCount;
    }
    $pageRows = array_slice($matched, ($page - 1) * $pageSize, $pageSize);
    $orderTotal = 0;
    $waiting = 0;
    $revenueRaw = '0.00';
    $todayRaw = '0.00';
    $pdo = db_pdo();
    if ($pdo instanceof PDO) {
        try {
            $orderTotal = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('iptal', 'iade')")->fetchColumn();
            $waiting = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'odeme_bekliyor'")->fetchColumn();
            $revenueRaw = number_format((float) $pdo->query("SELECT COALESCE(SUM(GREATEST(total - refunded, 0)), 0) FROM orders WHERE status NOT IN ('iptal', 'iade')")->fetchColumn(), 2, '.', '');
            $todayRaw = number_format((float) $pdo->query("SELECT COALESCE(SUM(GREATEST(total - refunded, 0)), 0) FROM orders WHERE status NOT IN ('iptal', 'iade') AND DATE(created_at) = CURDATE()")->fetchColumn(), 2, '.', '');
        } catch (PDOException) {
            $orderTotal = 0;
        }
    }
    $money = static function (string $amount): string {
        if (preg_match('/^\d+\.\d{2}$/', $amount) !== 1) {
            $amount = number_format((float) $amount, 2, '.', '');
        }
        $label = ProductEditor::moneyLabel($amount);

        return $label !== '' ? $label : '0,00 TL';
    };
    $catalogUrl = static function (array $extra = []) use ($query, $statusFilter, $paymentFilter, $buyerFilter, $pageSize, $view, $page): string {
        $params = [
            'q' => $query,
            'durum' => $statusFilter,
            'odeme' => $paymentFilter,
            'uye' => $buyerFilter,
            'adet' => (string) $pageSize,
            'gorunum' => $view === 'kart' ? 'kart' : '',
            'sayfa' => (string) $page,
        ];
        foreach ($extra as $key => $value) {
            $params[(string) $key] = (string) $value;
        }
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === '' || ($key === 'adet' && $value === '8') || ($key === 'sayfa' && $value === '1')) {
                continue;
            }
            $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return root_url('admin/siparisler.php' . ($parts !== [] ? '?' . implode('&', $parts) : ''));
    };
    $rangeStart = $matched === [] ? 0 : (($page - 1) * $pageSize) + 1;
    $rangeEnd = min(count($matched), $page * $pageSize);
    ?>
    <section class="catalog-kpis is-4" aria-label="Sipariş özeti">
        <article class="catalog-kpi">
            <span class="metric-ico is-blue"><?= admin_icon('receipt') ?></span>
            <div>
                <p>Sipariş</p>
                <strong><?= e((string) $orderTotal) ?></strong>
                <small>İptal hariç</small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-green"><?= admin_icon('trend') ?></span>
            <div>
                <p>Satış</p>
                <strong><?= e($money($todayRaw)) ?></strong>
                <small>Bugünkü satış</small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-gold"><?= admin_icon('card') ?></span>
            <div>
                <p>Bekleyen</p>
                <strong><?= e((string) $waiting) ?></strong>
                <small>Ödeme bekliyor</small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-purple"><?= admin_icon('chart') ?></span>
            <div>
                <p>Ciro</p>
                <strong><?= e($money($revenueRaw)) ?></strong>
                <small>İptal hariç toplam</small>
            </div>
        </article>
    </section>
    <div class="catalog-toolbar">
        <form class="catalog-filters" method="get" action="<?= root_url('admin/siparisler.php') ?>">
            <?php if ($view === 'kart'): ?><input type="hidden" name="gorunum" value="kart"><?php endif; ?>
            <label class="catalog-search">
                <span class="search-ico"><?= admin_icon('search') ?></span>
                <input name="q" type="search" value="<?= e($query) ?>" placeholder="Sipariş no, müşteri veya ürün" maxlength="80">
            </label>
            <select name="durum" data-auto aria-label="Durum">
                <option value="">Durum</option>
                <?php foreach (OrderBook::statuses() as $status => $label): ?>
                    <option value="<?= e($status) ?>"<?= $statusFilter === $status ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="odeme" data-auto aria-label="Ödeme">
                <option value="">Ödeme</option>
                <?php foreach (OrderBook::payments() as $method => $label): ?>
                    <option value="<?= e($method) ?>"<?= $paymentFilter === $method ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="uye" data-auto aria-label="Üyelik">
                <option value="">Üyelik</option>
                <option value="uye"<?= $buyerFilter === 'uye' ? ' selected' : '' ?>>Üye</option>
                <option value="misafir"<?= $buyerFilter === 'misafir' ? ' selected' : '' ?>>Üye olmadan</option>
            </select>
            <select name="adet" data-auto aria-label="Sayfa boyutu">
                <?php foreach ([8, 16, 32] as $size): ?>
                    <option value="<?= (string) $size ?>"<?= $pageSize === $size ? ' selected' : '' ?>><?= (string) $size ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="catalog-views" role="group" aria-label="Görünüm">
            <a class="<?= $view === 'liste' ? 'is-on' : '' ?>" href="<?= $catalogUrl(['gorunum' => '', 'sayfa' => '1']) ?>" aria-label="Liste"><?= admin_icon('list') ?></a>
            <a class="<?= $view === 'kart' ? 'is-on' : '' ?>" href="<?= $catalogUrl(['gorunum' => 'kart', 'sayfa' => '1']) ?>" aria-label="Kartlar"><?= admin_icon('grid') ?></a>
        </div>
    </div>
    <div class="catalog-sheet" data-catalog>
        <?php if ($pageRows === []): ?>
            <p class="catalog-empty"><?= $orders === [] ? 'Henüz sipariş yok.' : 'Bu filtreye uyan sipariş yok.' ?></p>
        <?php elseif ($view === 'kart'): ?>
            <div class="catalog-cards">
                <?php foreach ($pageRows as $row): ?>
                    <?php
                    $buyer = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
                    if ($buyer === '') {
                        $buyer = (int) $row['member_id'] === 0 ? 'Üye olmadan' : ((string) $row['email'] !== '' ? (string) $row['email'] : 'Üye');
                    }
                    ?>
                    <article class="catalog-card">
                        <?php if ((string) $row['product_image'] !== ''): ?>
                            <img src="<?= root_url((string) $row['product_image']) ?>" alt="">
                        <?php else: ?>
                            <span class="catalog-photo"></span>
                        <?php endif; ?>
                        <div class="catalog-card-body">
                            <strong><?= e((string) $row['order_no']) ?></strong>
                            <small><?= e($buyer) ?><?= (int) $row['member_id'] === 0 && $buyer !== 'Üye olmadan' ? ' · Üye olmadan' : '' ?></small>
                            <b><?= e($money((string) $row['total'])) ?></b>
                            <span class="catalog-status <?= e($orderStatusClass((string) $row['status'])) ?>"><?= e(OrderBook::statusLabel((string) $row['status'])) ?></span>
                            <div class="catalog-actions">
                                <a class="catalog-icon" href="<?= root_url('admin/siparisler.php?no=' . rawurlencode((string) $row['order_no'])) ?>" aria-label="Siparişi aç"><?= admin_icon('eye') ?></a>
                                <a class="catalog-icon" href="<?= root_url('admin/gonderi.php?no=' . rawurlencode((string) $row['order_no'])) ?>" aria-label="Kargo gönderimi"><?= admin_icon('archive') ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="catalog-scroll">
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th>Sipariş</th>
                            <th>Müşteri</th>
                            <th>Ürün</th>
                            <th>Tutar</th>
                            <th>Ödeme</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageRows as $row): ?>
                            <?php
                            $buyer = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
                            if ($buyer === '') {
                                $buyer = (int) $row['member_id'] === 0 ? 'Üye olmadan' : 'Üye';
                            }
                            $stamp = strtotime((string) $row['created_at']);
                            ?>
                            <tr>
                                <td><a href="<?= root_url('admin/siparisler.php?no=' . rawurlencode((string) $row['order_no'])) ?>"><?= e((string) $row['order_no']) ?></a></td>
                                <td>
                            <strong><?= e($buyer) ?></strong>
                            <?php if ((int) $row['member_id'] === 0 && $buyer !== 'Üye olmadan'): ?><small class="catalog-sub">Üye olmadan</small><?php endif; ?>
                            <?php if ((string) $row['email'] !== ''): ?><small class="catalog-sub"><?= e((string) $row['email']) ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <div class="catalog-product">
                                        <?php if ((string) $row['product_image'] !== ''): ?>
                                            <img src="<?= root_url((string) $row['product_image']) ?>" alt="">
                                        <?php else: ?>
                                            <span class="catalog-photo"></span>
                                        <?php endif; ?>
                                        <span>
                                            <strong><?= e((string) $row['product_title']) ?></strong>
                                            <small><?= e((string) (int) $row['qty']) ?> adet</small>
                                        </span>
                                    </div>
                                </td>
                                <td class="catalog-price"><strong><?= e($money((string) $row['total'])) ?></strong></td>
                                <td><?= e(OrderBook::paymentLabel((string) $row['payment_method'])) ?></td>
                                <td><span class="catalog-status <?= e($orderStatusClass((string) $row['status'])) ?>"><?= e(OrderBook::statusLabel((string) $row['status'])) ?></span></td>
                                <td><?= $stamp === false ? '—' : e(date('d.m.Y H:i', $stamp)) ?></td>
                                <td>
                                    <div class="catalog-actions">
                                        <a class="catalog-icon" href="<?= root_url('admin/siparisler.php?no=' . rawurlencode((string) $row['order_no'])) ?>" aria-label="Siparişi aç" title="Siparişi aç"><?= admin_icon('eye') ?></a>
                                        <a class="catalog-icon" href="<?= root_url('admin/gonderi.php?no=' . rawurlencode((string) $row['order_no'])) ?>" aria-label="Kargo gönderimi" title="Kargo gönderimi"><?= admin_icon('archive') ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="catalog-pager">
            <p><?= e((string) $rangeStart) ?>–<?= e((string) $rangeEnd) ?> / <?= e((string) count($matched)) ?> sipariş</p>
            <?php if ($pageCount > 1): ?>
                <nav class="catalog-pages" aria-label="Sayfalar">
                    <?php if ($page > 1): ?><a href="<?= $catalogUrl(['sayfa' => (string) ($page - 1)]) ?>">Önceki</a><?php endif; ?>
                    <?php for ($number = 1; $number <= $pageCount; $number++): ?>
                        <?php if ($number === $page): ?>
                            <span class="is-on"><?= e((string) $number) ?></span>
                        <?php else: ?>
                            <a href="<?= $catalogUrl(['sayfa' => (string) $number]) ?>"><?= e((string) $number) ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $pageCount): ?><a href="<?= $catalogUrl(['sayfa' => (string) ($page + 1)]) ?>">Sonraki</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
