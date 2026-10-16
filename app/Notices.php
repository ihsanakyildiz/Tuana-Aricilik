<?php

declare(strict_types=1);

require_once __DIR__ . '/Mailbox.php';
require_once __DIR__ . '/MemberAccount.php';
require_once __DIR__ . '/OrderBook.php';

final class Notices
{
    /** @return array{member_panel:bool,member_mail:bool,order_panel:bool,order_mail:bool,customer_order:bool,customer_status:bool} */
    public static function config(): array
    {
        $stored = setting('notify', []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return [
            'member_panel' => !array_key_exists('member_panel', $stored) || !empty($stored['member_panel']),
            'member_mail' => !array_key_exists('member_mail', $stored) || !empty($stored['member_mail']),
            'order_panel' => !array_key_exists('order_panel', $stored) || !empty($stored['order_panel']),
            'order_mail' => !array_key_exists('order_mail', $stored) || !empty($stored['order_mail']),
            'customer_order' => !array_key_exists('customer_order', $stored) || !empty($stored['customer_order']),
            'customer_status' => !array_key_exists('customer_status', $stored) || !empty($stored['customer_status']),
        ];
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function save(array $input): array
    {
        save_setting('notify', [
            'member_panel' => isset($input['member_panel']),
            'member_mail' => isset($input['member_mail']),
            'order_panel' => isset($input['order_panel']),
            'order_mail' => isset($input['order_mail']),
            'customer_order' => isset($input['customer_order']),
            'customer_status' => isset($input['customer_status']),
        ]);

        return [];
    }

    public static function member(int $id, string $name, string $email, string $phone): void
    {
        try {
            self::memberNotice($id, $name, $email, $phone);
        } catch (Throwable $exception) {
            return;
        }
    }

    /** @param array<string, mixed> $order */
    public static function order(array $order): void
    {
        try {
            self::orderNotice($order);
        } catch (Throwable $exception) {
            return;
        }
    }

    /** @param array<string, mixed> $order */
    public static function customerPlaced(array $order): void
    {
        try {
            self::customerOrderMail($order);
        } catch (Throwable $exception) {
            return;
        }
    }

    /** @param array<string, mixed> $order */
    public static function customerMoved(array $order, string $previousStatus, string $previousTracking): void
    {
        try {
            self::customerStatusMail($order, $previousStatus, $previousTracking);
        } catch (Throwable $exception) {
            return;
        }
    }

    private static function memberNotice(int $id, string $name, string $email, string $phone): void
    {
        if ($id < 1) {
            return;
        }
        $flags = self::config();
        $name = self::clip($name, 80);
        $email = self::clip($email, 120);
        $title = 'Yeni üyelik';
        $body = trim($name . ($email !== '' ? ' · ' . $email : ''));
        $href = 'admin/musteriler.php?uye=' . $id;
        if ($flags['member_panel']) {
            self::push('uye', $title, $body, $href);
        }
        if ($flags['member_mail'] && Mailbox::ready()) {
            $text = "Yeni bir üyelik açıldı.\n\nAd: " . $name . "\nE-posta: " . $email . "\nTelefon: " . self::clip($phone, 20) . "\n\n" . absolute_url('/' . $href);
            self::mail($title . ($name !== '' ? ' — ' . $name : ''), $text);
        }
    }

    public static function message(int $id, string $name, string $email, string $subject): void
    {
        try {
            self::messageNotice($id, $name, $email, $subject);
        } catch (Throwable $exception) {
            return;
        }
    }

    private static function messageNotice(int $id, string $name, string $email, string $subject): void
    {
        if ($id < 1) {
            return;
        }
        $name = self::clip($name, 80);
        $subject = self::clip($subject, 80);
        $title = 'Yeni iletişim mesajı';
        $body = $subject !== '' ? $subject : self::clip($email, 120);
        if ($name !== '') {
            $body = $name . ($body !== '' ? ' · ' . $body : '');
        }
        self::push('iletisim', $title, $body, 'admin/iletisim.php?mesaj=' . $id);
    }

    /** @param array<string, mixed> $order */
    private static function orderNotice(array $order): void
    {
        $no = (string) ($order['order_no'] ?? '');
        if (!preg_match('/^TA\d{6}[A-F0-9]{6}$/', $no)) {
            return;
        }
        $flags = self::config();
        $name = self::clip(trim((string) ($order['first_name'] ?? '') . ' ' . (string) ($order['last_name'] ?? '')), 80);
        $title = 'Yeni sipariş';
        $body = $no . ($name !== '' ? ' · ' . $name : '');
        $href = 'admin/siparisler.php?no=' . rawurlencode($no);
        if ($flags['order_panel']) {
            self::push('siparis', $title, $body, $href);
        }
        if ($flags['order_mail'] && Mailbox::ready()) {
            $payments = ['kart' => 'Kredi kartı', 'havale' => 'Havale', 'elden' => 'Elden teslim'];
            $payment = $payments[(string) ($order['payment_method'] ?? '')] ?? 'Ödeme';
            $text = "Yeni bir sipariş alındı.\n\nSipariş: " . $no
                . "\nÜrün: " . self::clip((string) ($order['product_title'] ?? ''), 120)
                . "\nAdet: " . (string) (int) ($order['qty'] ?? 0)
                . "\nTutar: " . self::clip((string) ($order['total'] ?? ''), 16) . " TL"
                . "\nÖdeme: " . $payment
                . "\nMüşteri: " . $name
                . "\nE-posta: " . self::clip((string) ($order['email'] ?? ''), 120)
                . "\n\n" . absolute_url('/' . $href);
            self::mail($title . ' — ' . $no, $text);
        }
    }

    /** @return list<array{id:int,kind:string,title:string,body:string,href:string,read:bool,created_at:string}> */
    public static function recent(int $limit): array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return [];
        }
        $limit = max(1, min(30, $limit));
        $stmt = $pdo->query(
            'SELECT id, kind, title, body, href, is_read, created_at FROM notices ORDER BY id DESC LIMIT ' . $limit
        );
        if ($stmt === false) {
            return [];
        }
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'id' => (int) $row['id'],
                'kind' => (string) $row['kind'],
                'title' => (string) $row['title'],
                'body' => (string) $row['body'],
                'href' => (string) $row['href'],
                'read' => (int) $row['is_read'] === 1,
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $rows;
    }

    public static function unreadCount(): int
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return 0;
        }
        $count = $pdo->query('SELECT COUNT(*) FROM notices WHERE is_read = 0');

        return $count === false ? 0 : (int) $count->fetchColumn();
    }

    public static function markRead(int $id): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO || $id < 1) {
            return;
        }
        $stmt = $pdo->prepare('UPDATE notices SET is_read = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function markAllRead(): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return;
        }
        $pdo->exec('UPDATE notices SET is_read = 1 WHERE is_read = 0');
    }

    private static function push(string $kind, string $title, string $body, string $href): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return;
        }
        $stmt = $pdo->prepare('INSERT INTO notices (kind, title, body, href) VALUES (?, ?, ?, ?)');
        $stmt->execute([$kind, self::clip($title, 160), self::clip($body, 240), self::clip($href, 180)]);
        $cut = $pdo->query('SELECT id FROM notices ORDER BY id DESC LIMIT 1 OFFSET 199');
        $oldest = $cut === false ? false : $cut->fetchColumn();
        if ($oldest !== false) {
            $drop = $pdo->prepare('DELETE FROM notices WHERE id < ?');
            $drop->execute([(int) $oldest]);
        }
    }

    private static function mail(string $subject, string $body): void
    {
        $to = Mailbox::config()['to_email'];
        Mailbox::send($to, $subject, $body);
    }

    /** @param array<string, mixed> $order */
    private static function customerOrderMail(array $order): void
    {
        $flags = self::config();
        $to = self::customerAddress($order);
        $no = self::orderNo($order);
        if (!$flags['customer_order'] || !Mailbox::ready() || $to === null || $no === null) {
            return;
        }
        $text = self::placedText($order);
        if ($text === '') {
            return;
        }
        Mailbox::send($to, 'Siparişin alındı — ' . $no, $text);
    }

    /** @param array<string, mixed> $order */
    private static function customerStatusMail(array $order, string $previousStatus, string $previousTracking): void
    {
        $flags = self::config();
        $to = self::customerAddress($order);
        $no = self::orderNo($order);
        if (!$flags['customer_status'] || !Mailbox::ready() || $to === null || $no === null) {
            return;
        }
        $status = (string) ($order['status'] ?? '');
        $tracking = self::trackingNumber($order);
        $statusChanged = $previousStatus !== '' && $previousStatus !== $status;
        $trackingNew = $tracking !== '' && $tracking !== $previousTracking;
        if (!$statusChanged && !$trackingNew) {
            return;
        }
        $headline = self::statusHeadline($status, $statusChanged);
        $text = self::movedText($order, $headline, $status === 'kargoda' || $tracking !== '');
        if ($text === '') {
            return;
        }
        Mailbox::send($to, $headline . ' — ' . $no, $text);
    }

    /** @param array<string, mixed> $order */
    private static function placedText(array $order): string
    {
        $no = self::orderNo($order);
        if ($no === null) {
            return '';
        }
        $lines = [self::greeting($order), '', 'Siparişin alındı.', ''];
        foreach (self::detailLines($order) as $line) {
            $lines[] = $line;
        }
        $delivery = self::deliveryLines($order);
        if ($delivery !== []) {
            $lines[] = '';
            $lines[] = 'Teslimat';
            foreach ($delivery as $line) {
                $lines[] = $line;
            }
        }
        $payment = (string) ($order['payment_method'] ?? '');
        if ($payment === 'havale') {
            $banks = self::bankLines();
            $lines[] = '';
            $lines[] = 'Havale';
            if ($banks === []) {
                $lines[] = 'Hesap bilgisi ayrıca iletilecek.';
            } else {
                foreach ($banks as $line) {
                    $lines[] = $line;
                }
                $lines[] = 'Açıklamaya ' . $no . ' yaz.';
            }
        } elseif ($payment === 'elden') {
            $lines[] = '';
            $lines[] = 'Ödemeyi ürünü teslim alırken yaparsın.';
        } elseif ($payment === 'kart') {
            $lines[] = '';
            $lines[] = 'Kart ödemesinin sonucu ayrıca bildirilir.';
        }
        $link = self::orderLink($order);
        if ($link !== '') {
            $lines[] = '';
            $lines[] = 'Sipariş sayfan:';
            $lines[] = $link;
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $order */
    private static function movedText(array $order, string $headline, bool $cargo): string
    {
        $no = self::orderNo($order);
        if ($no === null) {
            return '';
        }
        $lines = [self::greeting($order), '', $headline . '.', ''];
        foreach (self::detailLines($order) as $line) {
            $lines[] = $line;
        }
        if ($cargo) {
            $lines[] = '';
            $lines[] = 'Kargo';
            foreach (self::cargoLines($order) as $line) {
                $lines[] = $line;
            }
        }
        $link = self::orderLink($order);
        if ($link !== '') {
            $lines[] = '';
            $lines[] = 'Sipariş sayfan:';
            $lines[] = $link;
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $order
     *  @return list<string>
     */
    private static function detailLines(array $order): array
    {
        $cargoName = self::clip((string) ($order['cargo_name'] ?? ''), 80);
        $cargoPrice = OrderBook::money((string) ($order['cargo_price'] ?? ''));
        $cargo = $cargoName !== '' ? $cargoName . ($cargoPrice !== '' ? ' · ' . $cargoPrice : '') : 'Kargo tutarı yok';

        return [
            'Sipariş: ' . (string) self::orderNo($order),
            'Durum: ' . OrderBook::statusLabel((string) ($order['status'] ?? '')),
            'Ürün: ' . self::clip((string) ($order['product_title'] ?? ''), 120),
            'Adet: ' . (string) (int) ($order['qty'] ?? 0),
            'Birim fiyat: ' . OrderBook::money((string) ($order['unit_price'] ?? '')),
            'Kargo: ' . $cargo,
            'Ödeme: ' . OrderBook::paymentLabel((string) ($order['payment_method'] ?? '')),
            'Toplam: ' . OrderBook::money((string) ($order['total'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $order
     *  @return list<string>
     */
    private static function deliveryLines(array $order): array
    {
        $shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
        $lines = [];
        $name = self::clip((string) ($shipping['full_name'] ?? ''), 80);
        $phone = MemberAccount::phoneLabel(self::clip((string) ($shipping['phone'] ?? ''), 20));
        $street = self::clip((string) ($shipping['line'] ?? ''), 160);
        $area = self::clip(MemberAccount::addressLine($shipping), 160);
        $postal = self::clip((string) ($shipping['postal'] ?? ''), 12);
        if ($name !== '') {
            $lines[] = $name;
        }
        if ($phone !== '') {
            $lines[] = $phone;
        }
        if ($street !== '') {
            $lines[] = $street;
        }
        if ($area !== '') {
            $lines[] = $area . ($postal !== '' ? ' ' . $postal : '');
        }

        return $lines;
    }

    /** @param array<string, mixed> $order
     *  @return list<string>
     */
    private static function cargoLines(array $order): array
    {
        $geliver = is_array($order['geliver'] ?? null) ? $order['geliver'] : [];
        $provider = self::clip((string) ($geliver['provider'] ?? ''), 80);
        if ($provider === '') {
            $provider = self::clip((string) ($order['cargo_name'] ?? ''), 80);
        }
        $tracking = self::trackingNumber($order);
        $url = (string) ($geliver['tracking_url'] ?? '');
        if (preg_match('#^https://#', $url) !== 1) {
            $url = '';
        }
        $lines = [];
        if ($provider !== '') {
            $lines[] = 'Firma: ' . $provider;
        }
        if ($tracking !== '') {
            $lines[] = 'Takip numarası: ' . $tracking;
        } elseif ((string) ($order['status'] ?? '') === 'kargoda') {
            $lines[] = 'Takip numarası oluşunca ayrıca yazacağız.';
        }
        if ($url !== '') {
            $lines[] = 'Takip sayfası: ' . $url;
        }
        if ($lines === []) {
            $lines[] = 'Kargo bilgisi hazırlanıyor.';
        }

        return $lines;
    }

    /** @return list<string> */
    private static function bankLines(): array
    {
        $lines = [];
        foreach (OrderBook::banks() as $bank) {
            $iban = trim(chunk_split($bank['iban'], 4, ' '));
            $lines[] = self::clip($bank['bank'], 80);
            $lines[] = self::clip($bank['holder'], 80);
            $lines[] = $iban;
        }

        return $lines;
    }

    private static function statusHeadline(string $status, bool $statusChanged): string
    {
        if (!$statusChanged) {
            return 'Kargo takip bilgin hazır';
        }

        return match ($status) {
            'odendi' => 'Ödemen alındı',
            'teslimde_odenecek' => 'Siparişin teslimde ödenecek',
            'hazirlaniyor' => 'Siparişin hazırlanıyor',
            'kargoda' => 'Siparişin kargoya verildi',
            'teslim_edildi' => 'Siparişin teslim edildi',
            'iade' => 'Siparişin iade edildi',
            'iptal' => 'Siparişin iptal edildi',
            'odeme_bekliyor' => 'Siparişin ödeme bekliyor',
            default => 'Sipariş durumun güncellendi',
        };
    }

    /** @param array<string, mixed> $order */
    private static function greeting(array $order): string
    {
        $name = self::clip(trim((string) ($order['first_name'] ?? '') . ' ' . (string) ($order['last_name'] ?? '')), 80);

        return $name !== '' ? 'Merhaba ' . $name . ',' : 'Merhaba,';
    }

    /** @param array<string, mixed> $order */
    private static function customerAddress(array $order): ?string
    {
        $email = strtolower(trim((string) ($order['email'] ?? '')));
        if ($email === '' || strlen($email) > 120 || preg_match('/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/', $email) !== 1) {
            return null;
        }

        return $email;
    }

    /** @param array<string, mixed> $order */
    private static function orderNo(array $order): ?string
    {
        $no = (string) ($order['order_no'] ?? '');

        return preg_match('/^TA\d{6}[A-F0-9]{6}$/', $no) === 1 ? $no : null;
    }

    /** @param array<string, mixed> $order */
    private static function trackingNumber(array $order): string
    {
        $geliver = is_array($order['geliver'] ?? null) ? $order['geliver'] : [];

        return self::clip((string) ($geliver['tracking_number'] ?? ''), 40);
    }

    /** @param array<string, mixed> $order */
    private static function orderLink(array $order): string
    {
        $no = self::orderNo($order);
        if ($no === null) {
            return '';
        }
        if ((int) ($order['member_id'] ?? 0) > 0) {
            return absolute_url('/siparislerim?no=' . rawurlencode($no));
        }

        return absolute_url('/tesekkur?' . OrderBook::resumeQuery($order));
    }

    private static function clip(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n", "\0"], ' ', $value)) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }
}
