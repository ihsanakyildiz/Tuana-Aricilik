<?php

declare(strict_types=1);

require_once __DIR__ . '/ProductEditor.php';
require_once __DIR__ . '/MemberAccount.php';
require_once __DIR__ . '/Notices.php';
require_once __DIR__ . '/Paytr.php';
require_once __DIR__ . '/Geliver.php';

final class OrderBook
{
    /** @var array<string, string> */
    private const STATUSES = [
        'odeme_bekliyor' => 'Ödeme bekliyor',
        'odendi' => 'Ödendi',
        'teslimde_odenecek' => 'Teslimde ödenecek',
        'hazirlaniyor' => 'Hazırlanıyor',
        'kargoda' => 'Kargoda',
        'teslim_edildi' => 'Teslim edildi',
        'iade' => 'İade edildi',
        'iptal' => 'İptal',
    ];

    /** @var array<string, string> */
    private const PAYMENTS = [
        'kart' => 'Kredi kartı',
        'havale' => 'Havale',
        'elden' => 'Elden teslim',
    ];

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return self::STATUSES;
    }

    /** @return array<string, string> */
    public static function payments(): array
    {
        return self::PAYMENTS;
    }

    /** @return array<string, string> */
    public static function enabledPayments(): array
    {
        $stored = setting('payment_methods', null);
        $enabled = [];
        foreach (self::PAYMENTS as $key => $label) {
            if (!is_array($stored) || !array_key_exists($key, $stored) || !empty($stored[$key])) {
                $enabled[$key] = $label;
            }
        }

        return $enabled;
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function savePayments(array $input): array
    {
        $posted = $input['payments'] ?? [];
        $posted = is_array($posted) ? $posted : [];
        $flags = [];
        foreach (array_keys(self::PAYMENTS) as $key) {
            $flags[$key] = !empty($posted[$key]);
        }
        if (!in_array(true, $flags, true)) {
            return ['En az bir ödeme yöntemi açık kalmalı.'];
        }
        save_setting('payment_methods', $flags);

        return [];
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? 'Durum';
    }

    public static function paymentLabel(string $method): string
    {
        return self::PAYMENTS[$method] ?? 'Ödeme';
    }

    /** @return list<array{id:string,name:string,price:string,active:bool}> */
    public static function cargoMethods(bool $activeOnly = true): array
    {
        $rows = setting('cargo_methods', []);
        if (!is_array($rows)) {
            return [];
        }
        $methods = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            $price = (string) ($row['price'] ?? '');
            if (!preg_match('/^[a-f0-9]{8}$/', $id) || $name === '' || preg_match('/^\d+\.\d{2}$/', $price) !== 1) {
                continue;
            }
            $active = !empty($row['active']);
            if ($activeOnly && !$active) {
                continue;
            }
            $methods[] = [
                'id' => $id,
                'name' => $name,
                'price' => $price,
                'active' => $active,
            ];
        }

        return $methods;
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function saveCargo(string $action, array $input): array
    {
        $methods = self::cargoMethods(false);
        if ($action === 'cargo_add') {
            if (count($methods) >= 12) {
                return ['En fazla 12 kargo seçeneği eklenebilir.'];
            }
            $name = self::clip((string) ($input['cargo_name'] ?? ''), 80);
            $price = self::cargoPrice((string) ($input['cargo_price'] ?? ''));
            if (self::length($name) < 2) {
                return ['Kargo firmasının adını yaz.'];
            }
            if ($price === null) {
                return ['Kargo tutarı 0 veya daha büyük bir fiyat olmalı.'];
            }
            $methods[] = [
                'id' => bin2hex(random_bytes(4)),
                'name' => $name,
                'price' => $price,
                'active' => true,
            ];
            save_setting('cargo_methods', $methods);

            return [];
        }
        $id = (string) ($input['cargo_id'] ?? '');
        $next = [];
        $found = false;
        foreach ($methods as $method) {
            if ($method['id'] !== $id) {
                $next[] = $method;
                continue;
            }
            $found = true;
            if ($action === 'cargo_toggle') {
                $method['active'] = !$method['active'];
                $next[] = $method;
            }
        }
        if (!$found || !in_array($action, ['cargo_toggle', 'cargo_delete'], true)) {
            return ['Kargo seçeneği bulunamadı.'];
        }
        save_setting('cargo_methods', $next);

        return [];
    }

    /** @return list<array{id:string,bank:string,holder:string,iban:string}> */
    public static function banks(): array
    {
        $rows = setting('bank_accounts', []);
        if (!is_array($rows)) {
            return [];
        }
        $banks = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $iban = (string) ($row['iban'] ?? '');
            $bank = trim((string) ($row['bank'] ?? ''));
            $holder = trim((string) ($row['holder'] ?? ''));
            if (!preg_match('/^[a-f0-9]{8}$/', $id) || $bank === '' || $holder === '' || preg_match('/^TR\d{24}$/', $iban) !== 1) {
                continue;
            }
            $banks[] = [
                'id' => $id,
                'bank' => $bank,
                'holder' => $holder,
                'iban' => $iban,
            ];
        }

        return $banks;
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function saveBank(string $action, array $input): array
    {
        $banks = self::banks();
        if ($action === 'bank_add') {
            if (count($banks) >= 6) {
                return ['En fazla 6 banka hesabı eklenebilir.'];
            }
            $bank = self::clip((string) ($input['bank_name'] ?? ''), 80);
            $holder = self::clip((string) ($input['bank_holder'] ?? ''), 80);
            $iban = strtoupper(preg_replace('/\s+/', '', (string) ($input['bank_iban'] ?? '')) ?? '');
            if (self::length($bank) < 2) {
                return ['Banka adını yaz.'];
            }
            if (self::length($holder) < 2) {
                return ['Hesap sahibini yaz.'];
            }
            if (preg_match('/^TR\d{24}$/', $iban) !== 1) {
                return ['IBAN, TR ile başlayan 26 karakter olmalı.'];
            }
            $banks[] = [
                'id' => bin2hex(random_bytes(4)),
                'bank' => $bank,
                'holder' => $holder,
                'iban' => $iban,
            ];
            save_setting('bank_accounts', $banks);

            return [];
        }
        $id = (string) ($input['bank_id'] ?? '');
        $next = [];
        $found = false;
        foreach ($banks as $row) {
            if ($row['id'] === $id) {
                $found = true;
                continue;
            }
            $next[] = $row;
        }
        if ($action !== 'bank_delete' || !$found) {
            return ['Banka hesabı bulunamadı.'];
        }
        save_setting('bank_accounts', $next);

        return [];
    }

    /** @param array<string, mixed> $member
     *  @param array<string, mixed> $input
     *  @return array{errors:list<string>,order:?array<string,mixed>}
     */
    public static function place(array $member, array $input): array
    {
        $key = (string) ($input['urun'] ?? '');
        $qty = (int) ($input['qty'] ?? 1);
        $payment = (string) ($input['payment'] ?? '');
        $cargoId = (string) ($input['cargo'] ?? '');
        if (!isset(self::enabledPayments()[$payment])) {
            return ['errors' => ['Bir ödeme yöntemi seç.'], 'order' => null];
        }
        unset($input['card_name'], $input['card_number'], $input['card_expiry'], $input['card_cvc']);

        $cargoName = '';
        $cargoPrice = '0.00';
        if ($payment !== 'elden') {
            $methods = self::cargoMethods(true);
            if ($methods !== []) {
                $chosen = null;
                foreach ($methods as $method) {
                    if ($method['id'] === $cargoId) {
                        $chosen = $method;
                        break;
                    }
                }
                if ($chosen === null) {
                    return ['errors' => ['Bir kargo firması seç.'], 'order' => null];
                }
                $cargoName = $chosen['name'];
                $cargoPrice = $chosen['price'];
            }
        }

        $profile = is_array($member['profile'] ?? null) ? $member['profile'] : [];
        $shipping = is_array($profile['shipping'] ?? null) ? $profile['shipping'] : [];
        $invoice = is_array($profile['invoice'] ?? null) ? $profile['invoice'] : [];
        if (empty($member['shipping_ready'])) {
            return ['errors' => ['Sipariş için kargo adresi gerekli.'], 'order' => null];
        }
        if ((int) ($member['id'] ?? 0) === 0) {
            $shipping['access'] = bin2hex(random_bytes(16));
        }
        if ($payment === 'kart') {
            $paytrError = Paytr::checkoutError($member, $shipping);
            if ($paytrError !== null) {
                return ['errors' => [$paytrError], 'order' => null];
            }
        }

        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return ['errors' => ['Sipariş şu anda kaydedilemiyor.'], 'order' => null];
        }

        $status = match ($payment) {
            'kart', 'havale' => 'odeme_bekliyor',
            'elden' => 'teslimde_odenecek',
            default => '',
        };
        if ($status === '') {
            return ['errors' => ['Bir ödeme yöntemi seç.'], 'order' => null];
        }

        try {
            $pdo->beginTransaction();
            $held = ProductEditor::holdStock($pdo, $key, $qty);
            if ($held === null) {
                $pdo->rollBack();

                return ['errors' => ['Ürün stokta yok veya istenen adet karşılanamıyor.'], 'order' => null];
            }
            $unitCents = ProductEditor::moneyCents($held['unit_price']);
            $cargoCents = ProductEditor::moneyCents($cargoPrice);
            $totalCents = ($unitCents * $qty) + $cargoCents;
            $total = intdiv($totalCents, 100) . '.' . str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT);
            $orderNo = '';
            $insert = $pdo->prepare(
                'INSERT INTO orders (
                    order_no, member_id, status, payment_method, product_key, product_title, product_image,
                    unit_price, qty, cargo_name, cargo_price, total, stock_held, shipping_json, invoice_json
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $orderNo = 'TA' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
                try {
                    $insert->execute([
                        $orderNo,
                        (int) $member['id'],
                        $status,
                        $payment,
                        $key,
                        $held['title'],
                        $held['image'],
                        $held['unit_price'],
                        $qty,
                        $cargoName,
                        $cargoPrice,
                        $total,
                        $held['tracked'] ? 1 : 0,
                        encode_value(self::addressSnapshot($shipping)),
                        encode_value(self::invoiceSnapshot($invoice)),
                    ]);
                    break;
                } catch (PDOException $exception) {
                    if ($attempt === 4 || !str_contains($exception->getMessage(), 'uq_order_no')) {
                        throw $exception;
                    }
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['errors' => ['Sipariş kaydedilemedi. Yeniden dene.'], 'order' => null];
        }

        $order = self::findForMember($orderNo, (int) $member['id']);
        if (is_array($order) && (int) ($member['id'] ?? 0) === 0) {
            self::grantGuest($orderNo);
        }
        if (is_array($order)) {
            Notices::order($order);
            Notices::customerPlaced($order);
        }

        return ['errors' => [], 'order' => $order];
    }

    public static function grantGuest(string $orderNo): void
    {
        if (!self::validNo($orderNo)) {
            return;
        }
        MemberAccount::boot(true);
        $known = $_SESSION['guest_orders'] ?? [];
        if (!is_array($known)) {
            $known = [];
        }
        $known[$orderNo] = 1;
        $_SESSION['guest_orders'] = $known;
    }

    public static function forViewer(string $orderNo, string $access = ''): ?array
    {
        if (!self::validNo($orderNo)) {
            return null;
        }
        $member = MemberAccount::current();
        if ($member !== null) {
            $own = self::findForMember($orderNo, (int) $member['id']);
            if ($own !== null) {
                return $own;
            }
        }
        $order = self::find($orderNo);
        if ($order === null || (int) $order['member_id'] !== 0) {
            return null;
        }
        MemberAccount::boot(true);
        $known = $_SESSION['guest_orders'] ?? [];
        $sessionOk = is_array($known) && isset($known[$orderNo]);
        $stored = (string) ($order['access'] ?? '');
        $keyOk = $access !== '' && $stored !== '' && hash_equals($stored, $access);

        return $sessionOk || $keyOk ? $order : null;
    }

    public static function resumeQuery(array $order): string
    {
        $query = 'no=' . rawurlencode((string) ($order['order_no'] ?? ''));
        $access = (string) ($order['access'] ?? '');
        if ($access !== '') {
            $query .= '&anahtar=' . rawurlencode($access);
        }

        return $query;
    }

    /** @return list<string> */
    public static function setStatus(string $orderNo, string $status): array
    {
        if (!isset(self::STATUSES[$status]) || !self::validNo($orderNo)) {
            return ['Sipariş durumu güncellenemedi.'];
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return ['Sipariş durumu güncellenemedi.'];
        }
        $previous = '';
        $changed = false;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT product_key, qty, status, stock_held FROM orders WHERE order_no = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$orderNo]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                $pdo->rollBack();

                return ['Sipariş bulunamadı.'];
            }
            $current = (string) $row['status'];
            $previous = $current;
            if ($current === $status) {
                $pdo->commit();

                return [];
            }
            $held = (int) $row['stock_held'] === 1;
            $qty = (int) $row['qty'];
            $key = (string) $row['product_key'];
            $closing = in_array($status, ['iptal', 'iade'], true);
            $reopen = in_array($current, ['iptal', 'iade'], true) && !$closing;
            if ($closing && $held) {
                ProductEditor::releaseStock($pdo, $key, $qty);
                $held = false;
            } elseif ($reopen && !$held) {
                $again = ProductEditor::holdStock($pdo, $key, $qty);
                if ($again === null) {
                    $pdo->rollBack();

                    return ['Stok bu siparişi yeniden açmaya yetmiyor.'];
                }
                $held = $again['tracked'];
            }
            $update = $pdo->prepare('UPDATE orders SET status = ?, stock_held = ? WHERE order_no = ?');
            $update->execute([$status, $held ? 1 : 0, $orderNo]);
            $changed = true;
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['Sipariş durumu güncellenemedi.'];
        }
        if ($changed) {
            $fresh = self::find($orderNo);
            if (is_array($fresh)) {
                Notices::customerMoved($fresh, $previous, (string) ($fresh['geliver']['tracking_number'] ?? ''));
            }
        }

        return [];
    }

    /** @return list<string> */
    public static function cancelCard(string $orderNo, int $memberId): array
    {
        $order = self::findForMember($orderNo, $memberId);
        if ($order === null || (string) $order['payment_method'] !== 'kart' || (string) $order['status'] !== 'odeme_bekliyor') {
            return ['Bu ödeme iptal edilemedi.'];
        }
        $errors = self::setStatus($orderNo, 'iptal');
        if ($errors !== []) {
            return $errors;
        }
        self::note($orderNo, 'Ödeme sayfasından vazgeçildi.');

        return [];
    }

    public static function applyPaytr(string $orderNo, bool $paid, int $paidCents, string $note): string
    {
        if (!self::validNo($orderNo) || $paidCents < 0) {
            return 'missing';
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return 'missing';
        }
        $updated = false;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT payment_method, status, total, product_key, qty, stock_held
                 FROM orders WHERE order_no = ? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([$orderNo]);
            $row = $stmt->fetch();
            if (!is_array($row) || (string) $row['payment_method'] !== 'kart') {
                $pdo->rollBack();

                return 'missing';
            }
            if ((string) $row['status'] !== 'odeme_bekliyor') {
                $pdo->commit();

                return 'ok';
            }
            $expected = ProductEditor::moneyCents(number_format((float) $row['total'], 2, '.', ''));
            if ($paid && $paidCents < $expected) {
                $pdo->rollBack();

                return 'mismatch';
            }
            $held = (int) $row['stock_held'] === 1;
            $status = 'odendi';
            if (!$paid) {
                if ($held) {
                    ProductEditor::releaseStock($pdo, (string) $row['product_key'], (int) $row['qty']);
                }
                $held = false;
                $status = 'iptal';
            }
            $collected = $paid ? self::fromCents($paidCents) : '0.00';
            $update = $pdo->prepare('UPDATE orders SET status = ?, stock_held = ?, paytr_note = ?, paytr_collected = ? WHERE order_no = ?');
            $update->execute([$status, $held ? 1 : 0, mb_substr($note, 0, 180), $collected, $orderNo]);
            $updated = true;
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return 'missing';
        }
        if ($updated) {
            $fresh = self::find($orderNo);
            if (is_array($fresh)) {
                Notices::customerMoved($fresh, 'odeme_bekliyor', (string) ($fresh['geliver']['tracking_number'] ?? ''));
            }
        }

        return 'ok';
    }

    /** @return list<string> */
    public static function refund(string $orderNo, string $amount, bool $full): array
    {
        if (!self::validNo($orderNo)) {
            return ['Sipariş bulunamadı.'];
        }
        $order = self::find($orderNo);
        if ($order === null || (string) $order['payment_method'] !== 'kart' || !self::canRefund($order)) {
            return ['Bu sipariş için PayTR iadesi yapılamaz.'];
        }
        $remaining = self::refundableCents($order);
        if ($remaining < 1) {
            return ['İade edilecek tutar kalmadı.'];
        }
        $cents = $full ? $remaining : ProductEditor::moneyCents((string) (ProductEditor::normalizeMoney($amount) ?? ''));
        if ($cents < 1) {
            return ['İade tutarını yaz.'];
        }
        if ($cents > $remaining) {
            return ['İade tutarı kalan tutardan büyük olamaz.'];
        }
        if (!Paytr::ready()) {
            return ['PayTR bilgileri kayıtlı değil.'];
        }
        $decimal = self::fromCents($cents);
        $reference = $orderNo . 'R' . str_pad((string) (count($order['refunds']) + 1), 2, '0', STR_PAD_LEFT);
        $result = Paytr::refund($orderNo, $decimal, $reference);
        if (!$result['ok']) {
            return [$result['error'] !== '' ? $result['error'] : 'PayTR iadesi tamamlanamadı.'];
        }
        $recorded = self::recordRefund($orderNo, $decimal, $reference, $result['test']);

        return $recorded === '' ? [] : [$recorded];
    }

    /** @param array<string, mixed> $order */
    public static function refundableCents(array $order): int
    {
        $total = ProductEditor::moneyCents((string) ($order['total'] ?? ''));
        $collected = ProductEditor::moneyCents((string) ($order['paytr_collected'] ?? ''));
        $refunded = ProductEditor::moneyCents((string) ($order['refunded'] ?? ''));

        return max(0, max($total, $collected) - $refunded);
    }

    /** @param array<string, mixed> $order */
    public static function canRefund(array $order): bool
    {
        if ((string) ($order['payment_method'] ?? '') !== 'kart') {
            return false;
        }
        $status = (string) ($order['status'] ?? '');
        if (in_array($status, ['odeme_bekliyor', 'teslimde_odenecek', 'iade'], true)) {
            return false;
        }
        $collected = ProductEditor::moneyCents((string) ($order['paytr_collected'] ?? ''));
        if ($status === 'iptal' && $collected < 1) {
            return false;
        }

        return self::refundableCents($order) > 0;
    }

    /** @return list<string> */
    public static function geliverAct(string $orderNo, string $action, string $offerId = ''): array
    {
        if (!self::validNo($orderNo)) {
            return ['Sipariş bulunamadı.'];
        }
        $order = self::find($orderNo);
        if ($order === null || !self::geliverAllowed($order)) {
            return ['Bu sipariş için kargo gönderisi açılamaz.'];
        }
        $state = is_array($order['geliver'] ?? null) ? $order['geliver'] : self::emptyGeliver();
        $shipmentId = (string) ($state['shipment_id'] ?? '');
        if ($action === 'create') {
            if ($shipmentId !== '') {
                return ['Bu siparişin Geliver gönderisi zaten var.'];
            }
            $created = Geliver::create($order);
            if (!$created['ok']) {
                return [$created['error']];
            }

            return self::storeGeliver($orderNo, $created['state'], null);
        }
        if ($shipmentId === '' || !preg_match('/^[A-Za-z0-9_-]{8,80}$/', $shipmentId)) {
            return ['Önce Geliver gönderisini oluştur.'];
        }
        if ($action === 'sync') {
            $synced = Geliver::shipment($shipmentId);
            if (!$synced['ok']) {
                return [$synced['error']];
            }
            $synced['state']['offers'] = $synced['state']['offers'] !== [] ? $synced['state']['offers'] : $state['offers'];

            return self::storeGeliver($orderNo, self::mergeGeliver($state, $synced['state']), self::geliverNextStatus($synced['state']));
        }
        if ($action === 'accept') {
            if ((string) ($state['offer_id'] ?? '') !== '' || (string) ($state['label_url'] ?? '') !== '') {
                return ['Bu gönderinin etiketi zaten alındı.'];
            }
            $offers = is_array($state['offers'] ?? null) ? $state['offers'] : [];
            $bought = Geliver::accept($offerId, $offers);
            if (!$bought['ok']) {
                return [$bought['error']];
            }

            return self::storeGeliver($orderNo, self::mergeGeliver($state, $bought['state']), 'hazirlaniyor');
        }
        if ($action === 'cancel') {
            if ((string) ($state['tracking_number'] ?? '') !== '') {
                return ['Takip numarası oluşmuş gönderi buradan iptal edilmez.'];
            }
            $cancelled = Geliver::cancel($shipmentId);
            if (!$cancelled['ok']) {
                return [$cancelled['error']];
            }
            $cleared = self::emptyGeliver();
            $cleared['note'] = 'Gönderi iptal edildi.';

            return self::storeGeliver($orderNo, $cleared, null);
        }

        return ['Bu işlem tanınmıyor.'];
    }

    /** @return list<string> */
    public static function geliverReturn(string $orderNo, int $memberId = 0): array
    {
        $order = self::returnOrder($orderNo, $memberId);
        if (!is_array($order)) {
            return ['Sipariş bulunamadı.'];
        }
        if (!self::geliverReturnable($order)) {
            return ['Bu sipariş için iade kodu oluşturulamaz.'];
        }
        $state = is_array($order['geliver'] ?? null) ? $order['geliver'] : self::emptyGeliver();
        $created = Geliver::createReturn((string) $state['shipment_id']);
        if (!$created['ok']) {
            return [$created['error']];
        }
        $state['iade'] = $created['iade'];

        return self::storeGeliver($orderNo, $state, null);
    }

    /** @return list<string> */
    public static function geliverReturnSync(string $orderNo): array
    {
        $order = self::returnOrder($orderNo, 0);
        if (!is_array($order)) {
            return ['Sipariş bulunamadı.'];
        }
        $iade = self::iadeView($order['geliver']['iade'] ?? null);
        if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $iade['shipment_id'])) {
            return ['Önce iade kodunu oluştur.'];
        }
        $loaded = Geliver::returnShipment($iade['shipment_id']);
        if (!$loaded['ok']) {
            return [$loaded['error']];
        }
        $state = is_array($order['geliver'] ?? null) ? $order['geliver'] : self::emptyGeliver();
        $state['iade'] = self::mergeReturn($iade, $loaded['iade']);

        return self::storeGeliver($orderNo, $state, null);
    }

    /** @param array<string, mixed> $order */
    public static function geliverReturnable(array $order): bool
    {
        if ((string) ($order['status'] ?? '') !== 'teslim_edildi') {
            return false;
        }
        if ((string) ($order['payment_method'] ?? '') === 'elden') {
            return false;
        }
        $state = is_array($order['geliver'] ?? null) ? $order['geliver'] : [];
        $shipmentId = (string) ($state['shipment_id'] ?? '');
        $labeled = (string) ($state['offer_id'] ?? '') !== '' || (string) ($state['label_url'] ?? '') !== '' || (string) ($state['barcode'] ?? '') !== '';
        $iade = self::iadeView($state['iade'] ?? null);
        if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $shipmentId) || !$labeled) {
            return false;
        }

        return $iade['shipment_id'] === '' && $iade['barcode'] === '';
    }

    /** @param array<string, mixed> $notice
     *  @return list<string>
     */
    public static function geliverNotice(array $notice): array
    {
        $shipmentId = (string) ($notice['shipment_id'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/', $shipmentId)) {
            return [];
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return ['Sipariş kaydı güncellenemedi.'];
        }
        $stmt = $pdo->prepare("SELECT order_no FROM orders WHERE JSON_UNQUOTE(JSON_EXTRACT(geliver_json, '$.shipment_id')) = ? LIMIT 1");
        $stmt->execute([$shipmentId]);
        $orderNo = $stmt->fetchColumn();
        $isReturn = false;
        if (!is_string($orderNo) || !self::validNo($orderNo)) {
            $returnStmt = $pdo->prepare("SELECT order_no FROM orders WHERE JSON_UNQUOTE(JSON_EXTRACT(geliver_json, '$.iade.shipment_id')) = ? LIMIT 1");
            $returnStmt->execute([$shipmentId]);
            $orderNo = $returnStmt->fetchColumn();
            $isReturn = true;
        }
        if (!is_string($orderNo) || !self::validNo($orderNo)) {
            return [];
        }
        $order = self::find($orderNo);
        if ($order === null) {
            return [];
        }
        $state = is_array($order['geliver'] ?? null) ? $order['geliver'] : self::emptyGeliver();
        if ($isReturn) {
            $iade = self::iadeView($state['iade'] ?? null);
            foreach (['status', 'tracking_number', 'tracking_url', 'label_url', 'barcode'] as $key) {
                $value = (string) ($notice[$key] ?? '');
                if ($value !== '') {
                    $iade[$key] = $value;
                }
            }
            $state['iade'] = $iade;

            return self::storeGeliver($orderNo, $state, null);
        }
        foreach (['status', 'tracking_status', 'tracking_number', 'tracking_url', 'label_url', 'barcode'] as $key) {
            $value = (string) ($notice[$key] ?? '');
            if ($value !== '') {
                $state[$key] = $value;
            }
        }

        return self::storeGeliver($orderNo, $state, self::geliverNextStatus($state));
    }

    /** @param array<string, mixed> $order */
    public static function geliverAllowed(array $order): bool
    {
        if ((string) ($order['payment_method'] ?? '') === 'elden') {
            return false;
        }

        return !in_array((string) ($order['status'] ?? ''), ['odeme_bekliyor', 'iptal', 'iade', 'teslim_edildi'], true);
    }

    /** @return array<string, mixed>|null */
    public static function findForMember(string $orderNo, int $memberId): ?array
    {
        if (!self::validNo($orderNo) || $memberId < 1) {
            return null;
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT order_no, member_id, status, payment_method, product_key, product_title, product_image,
                    unit_price, qty, cargo_name, cargo_price, total, shipping_json, invoice_json, paytr_note,
                    paytr_collected, refunded, refund_json, geliver_json, created_at
             FROM orders
             WHERE order_no = ? AND member_id = ?
             LIMIT 1'
        );
        $stmt->execute([$orderNo, $memberId]);
        $row = $stmt->fetch();

        return is_array($row) ? self::present($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public static function forMember(int $memberId): array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO || $memberId < 1) {
            return [];
        }
        $stmt = $pdo->prepare(
            'SELECT order_no, member_id, status, payment_method, product_key, product_title, product_image,
                    unit_price, qty, cargo_name, cargo_price, total, shipping_json, invoice_json, paytr_note,
                    paytr_collected, refunded, refund_json, geliver_json, created_at
             FROM orders
             WHERE member_id = ?
             ORDER BY id DESC
             LIMIT 100'
        );
        $stmt->execute([$memberId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = self::present($row);
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return [];
        }
        $rows = [];
        $query = $pdo->query(
            'SELECT o.order_no, o.member_id, o.status, o.payment_method, o.product_key, o.product_title, o.product_image,
                    o.unit_price, o.qty, o.cargo_name, o.cargo_price, o.total, o.shipping_json, o.invoice_json, o.created_at,
                    m.first_name, m.last_name, m.email, m.phone
             FROM orders o
             LEFT JOIN members m ON m.id = o.member_id
             ORDER BY o.id DESC
             LIMIT 200'
        );
        foreach ($query->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = self::present($row);
            }
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $orderNo): ?array
    {
        if (!self::validNo($orderNo)) {
            return null;
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT o.order_no, o.member_id, o.status, o.payment_method, o.product_key, o.product_title, o.product_image,
                    o.unit_price, o.qty, o.cargo_name, o.cargo_price, o.total, o.shipping_json, o.invoice_json, o.paytr_note,
                    o.paytr_collected, o.refunded, o.refund_json, o.geliver_json, o.created_at,
                    m.first_name, m.last_name, m.email, m.phone
             FROM orders o
             LEFT JOIN members m ON m.id = o.member_id
             WHERE o.order_no = ?
             LIMIT 1'
        );
        $stmt->execute([$orderNo]);
        $row = $stmt->fetch();

        return is_array($row) ? self::present($row) : null;
    }

    public static function money(string $amount): string
    {
        return ProductEditor::moneyLabel($amount);
    }

    private static function cargoPrice(string $value): ?string
    {
        $parsed = ProductEditor::normalizeMoney($value);
        if ($parsed !== null) {
            return $parsed;
        }
        $trimmed = str_replace([' ', '₺', 'TL', 'tl'], '', trim($value));

        return in_array($trimmed, ['0', '0,0', '0,00', '0.0', '0.00'], true) ? '0.00' : null;
    }

    /** @param array<string, mixed> $shipping
     *  @return array<string, string>
     */
    private static function addressSnapshot(array $shipping): array
    {
        $keys = ['full_name', 'phone', 'city', 'district', 'neighborhood', 'line', 'postal', 'note'];
        $snapshot = [];
        foreach ($keys as $key) {
            $snapshot[$key] = trim((string) ($shipping[$key] ?? ''));
        }
        foreach (['email', 'access'] as $extra) {
            $value = trim((string) ($shipping[$extra] ?? ''));
            if ($value !== '') {
                $snapshot[$extra] = $value;
            }
        }

        return $snapshot;
    }

    /** @param array<string, mixed> $invoice
     *  @return array<string, mixed>
     */
    private static function invoiceSnapshot(array $invoice): array
    {
        return [
            'kind' => (string) ($invoice['kind'] ?? ''),
            'title' => trim((string) ($invoice['title'] ?? '')),
            'tax_office' => trim((string) ($invoice['tax_office'] ?? '')),
            'tax_no' => trim((string) ($invoice['tax_no'] ?? '')),
            'same' => !empty($invoice['same']),
            'city' => trim((string) ($invoice['city'] ?? '')),
            'district' => trim((string) ($invoice['district'] ?? '')),
            'neighborhood' => trim((string) ($invoice['neighborhood'] ?? '')),
            'line' => trim((string) ($invoice['line'] ?? '')),
            'postal' => trim((string) ($invoice['postal'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private static function present(array $row): array
    {
        $unit = number_format((float) $row['unit_price'], 2, '.', '');
        $cargo = number_format((float) $row['cargo_price'], 2, '.', '');
        $total = number_format((float) $row['total'], 2, '.', '');
        $shipping = decode_value((string) ($row['shipping_json'] ?? ''));
        $invoice = decode_value((string) ($row['invoice_json'] ?? ''));
        if (!is_array($shipping)) {
            $shipping = [];
        }
        if (!is_array($invoice)) {
            $invoice = [];
        }
        $access = (string) ($shipping['access'] ?? '');
        unset($shipping['access']);
        $first = (string) ($row['first_name'] ?? '');
        $last = (string) ($row['last_name'] ?? '');
        if ($first === '' && $last === '') {
            $parts = preg_split('/\s+/u', trim((string) ($shipping['full_name'] ?? '')), 2) ?: [];
            $first = (string) ($parts[0] ?? '');
            $last = (string) ($parts[1] ?? '');
        }
        $email = (string) ($row['email'] ?? '');
        if ($email === '') {
            $email = (string) ($shipping['email'] ?? '');
        }
        $phone = (string) ($row['phone'] ?? '');
        if ($phone === '') {
            $phone = (string) ($shipping['phone'] ?? '');
        }

        return [
            'order_no' => (string) $row['order_no'],
            'member_id' => (int) $row['member_id'],
            'status' => (string) $row['status'],
            'payment_method' => (string) $row['payment_method'],
            'product_key' => (string) $row['product_key'],
            'product_title' => (string) $row['product_title'],
            'product_image' => (string) $row['product_image'],
            'unit_price' => $unit,
            'qty' => (int) $row['qty'],
            'cargo_name' => (string) $row['cargo_name'],
            'cargo_price' => $cargo,
            'total' => $total,
            'shipping' => $shipping,
            'invoice' => $invoice,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone,
            'access' => $access,
            'paytr_note' => (string) ($row['paytr_note'] ?? ''),
            'paytr_collected' => number_format((float) ($row['paytr_collected'] ?? 0), 2, '.', ''),
            'refunded' => number_format((float) ($row['refunded'] ?? 0), 2, '.', ''),
            'refunds' => self::refundList($row['refund_json'] ?? ''),
            'geliver' => self::geliverView($row['geliver_json'] ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private static function emptyGeliver(): array
    {
        return [
            'shipment_id' => '',
            'test' => false,
            'status' => '',
            'tracking_status' => '',
            'offers' => [],
            'offer_id' => '',
            'barcode' => '',
            'tracking_number' => '',
            'tracking_url' => '',
            'label_url' => '',
            'provider' => '',
            'amount' => '',
            'currency' => '',
            'note' => '',
            'iade' => Geliver::emptyReturn(),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function returnOrder(string $orderNo, int $memberId): ?array
    {
        if (!self::validNo($orderNo)) {
            return null;
        }
        if ($memberId > 0) {
            return self::findForMember($orderNo, $memberId);
        }

        return self::find($orderNo);
    }

    /** @return array<string, string> */
    private static function iadeView(mixed $value): array
    {
        $iade = Geliver::emptyReturn();
        if (!is_array($value)) {
            return $iade;
        }
        foreach ($iade as $key => $default) {
            if (isset($value[$key]) && is_string($value[$key])) {
                $iade[$key] = $value[$key];
            }
        }

        return $iade;
    }

    /** @param array<string, string> $current
     *  @param array<string, string> $fresh
     *  @return array<string, string>
     */
    private static function mergeReturn(array $current, array $fresh): array
    {
        foreach ($fresh as $key => $value) {
            if ($value !== '') {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    /** @return array<string, mixed> */
    private static function geliverView(mixed $json): array
    {
        $state = self::emptyGeliver();
        $decoded = decode_value(is_string($json) ? $json : '');
        if (!is_array($decoded) || array_is_list($decoded)) {
            return $state;
        }
        foreach ($state as $key => $default) {
            if ($key === 'offers') {
                $state['offers'] = is_array($decoded['offers'] ?? null) ? array_values($decoded['offers']) : [];
                continue;
            }
            if ($key === 'test') {
                $state['test'] = !empty($decoded['test']);
                continue;
            }
            if ($key === 'iade') {
                $state['iade'] = self::iadeView($decoded['iade'] ?? null);
                continue;
            }
            if (isset($decoded[$key]) && is_string($decoded[$key])) {
                $state[$key] = $decoded[$key];
            }
        }

        return $state;
    }

    /** @param array<string, mixed> $current
     *  @param array<string, mixed> $fresh
     *  @return array<string, mixed>
     */
    private static function mergeGeliver(array $current, array $fresh): array
    {
        $merged = $current;
        foreach ($fresh as $key => $value) {
            if ($key === 'offers' && $value === []) {
                continue;
            }
            if ($key === 'note') {
                continue;
            }
            if (is_string($value) && $value === '' && (string) ($merged[$key] ?? '') !== '') {
                continue;
            }
            $merged[$key] = $value;
        }

        return $merged;
    }

    /** @param array<string, mixed> $state
     *  @return list<string>
     */
    private static function storeGeliver(string $orderNo, array $state, ?string $next): array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return ['Kargo kaydı güncellenemedi.'];
        }
        $clean = self::geliverView(encode_value($state));
        $previous = '';
        $previousTracking = '';
        $saved = false;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT status, geliver_json FROM orders WHERE order_no = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$orderNo]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                $pdo->rollBack();

                return ['Sipariş bulunamadı.'];
            }
            $current = (string) $row['status'];
            $previous = $current;
            $previousTracking = (string) (self::geliverView($row['geliver_json'] ?? '')['tracking_number'] ?? '');
            $status = $next !== null && self::geliverCanAdvance($current, $next) ? $next : $current;
            $update = $pdo->prepare('UPDATE orders SET status = ?, geliver_json = ? WHERE order_no = ?');
            $update->execute([$status, encode_value($clean), $orderNo]);
            $saved = true;
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['Kargo kaydı güncellenemedi.'];
        }
        $tracking = (string) ($clean['tracking_number'] ?? '');
        if ($saved && ($status !== $previous || ($tracking !== '' && $tracking !== $previousTracking))) {
            $fresh = self::find($orderNo);
            if (is_array($fresh)) {
                Notices::customerMoved($fresh, $previous, $previousTracking);
            }
        }

        return [];
    }

    /** @param array<string, mixed> $state */
    private static function geliverNextStatus(array $state): ?string
    {
        $codes = [
            strtoupper((string) ($state['status'] ?? '')),
            strtoupper((string) ($state['tracking_status'] ?? '')),
        ];
        foreach ($codes as $code) {
            if ($code === 'DELIVERED') {
                return 'teslim_edildi';
            }
        }
        foreach ($codes as $code) {
            if (in_array($code, ['SHIPPED', 'TRANSIT', 'IN_TRANSIT', 'OUT_FOR_DELIVERY'], true)) {
                return 'kargoda';
            }
        }

        return null;
    }

    private static function geliverCanAdvance(string $current, string $next): bool
    {
        $rank = [
            'odeme_bekliyor' => 0,
            'teslimde_odenecek' => 1,
            'odendi' => 2,
            'hazirlaniyor' => 3,
            'kargoda' => 4,
            'teslim_edildi' => 5,
        ];
        if (!isset($rank[$current], $rank[$next])) {
            return false;
        }

        return $rank[$next] > $rank[$current];
    }

    /** @return list<array{amount:string,reference:string,test:bool,at:string}> */
    private static function refundList(mixed $json): array
    {
        $decoded = decode_value(is_string($json) ? $json : '');
        if (!is_array($decoded)) {
            return [];
        }
        $rows = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $amount = number_format((float) ($item['amount'] ?? 0), 2, '.', '');
            if (ProductEditor::moneyCents($amount) < 1) {
                continue;
            }
            $rows[] = [
                'amount' => $amount,
                'reference' => (string) ($item['reference'] ?? ''),
                'test' => !empty($item['test']),
                'at' => (string) ($item['at'] ?? ''),
            ];
        }

        return $rows;
    }

    private static function recordRefund(string $orderNo, string $amount, string $reference, bool $test): string
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return 'İade PayTR tarafında oluştu ama sipariş kaydı güncellenemedi.';
        }
        $previous = '';
        $changed = false;
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT status, total, paytr_collected, refunded, refund_json, product_key, qty, stock_held
                 FROM orders WHERE order_no = ? LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([$orderNo]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                $pdo->rollBack();

                return 'İade PayTR tarafında oluştu ama sipariş bulunamadı.';
            }
            $cents = ProductEditor::moneyCents($amount);
            $refunded = ProductEditor::moneyCents(number_format((float) $row['refunded'], 2, '.', '')) + $cents;
            $history = self::refundList($row['refund_json'] ?? '');
            $history[] = [
                'amount' => $amount,
                'reference' => $reference,
                'test' => $test,
                'at' => date('Y-m-d H:i:s'),
            ];
            $encoded = encode_value($history);
            $cap = max(
                ProductEditor::moneyCents(number_format((float) $row['total'], 2, '.', '')),
                ProductEditor::moneyCents(number_format((float) $row['paytr_collected'], 2, '.', ''))
            );
            $status = (string) $row['status'];
            $previous = $status;
            $held = (int) $row['stock_held'] === 1;
            if ($refunded >= $cap && $status !== 'iptal') {
                if ($held) {
                    ProductEditor::releaseStock($pdo, (string) $row['product_key'], (int) $row['qty']);
                }
                $held = false;
                $status = 'iade';
            }
            $left = max(0, $cap - $refunded);
            $note = ($test ? 'Test. ' : '') . 'İade ' . ProductEditor::moneyLabel($amount) . '. Kalan ' . ProductEditor::moneyLabel(self::fromCents($left)) . '.';
            $update = $pdo->prepare(
                'UPDATE orders SET status = ?, stock_held = ?, refunded = ?, refund_json = ?, paytr_note = ? WHERE order_no = ?'
            );
            $update->execute([
                $status,
                $held ? 1 : 0,
                self::fromCents($refunded),
                $encoded,
                mb_substr($note, 0, 180),
                $orderNo,
            ]);
            $changed = $status !== $previous;
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return 'İade PayTR tarafında oluştu ama sipariş kaydı güncellenemedi.';
        }
        if ($changed) {
            $fresh = self::find($orderNo);
            if (is_array($fresh)) {
                Notices::customerMoved($fresh, $previous, (string) ($fresh['geliver']['tracking_number'] ?? ''));
            }
        }

        return '';
    }

    private static function fromCents(int $cents): string
    {
        if ($cents < 0) {
            $cents = 0;
        }

        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function note(string $orderNo, string $note): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return;
        }
        $stmt = $pdo->prepare('UPDATE orders SET paytr_note = ? WHERE order_no = ?');
        $stmt->execute([mb_substr($note, 0, 180), $orderNo]);
    }

    public static function validNo(string $orderNo): bool
    {
        return preg_match('/^TA\d{6}[A-F0-9]{6}$/', $orderNo) === 1;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function clip(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }

        return substr($value, 0, $limit);
    }
}
