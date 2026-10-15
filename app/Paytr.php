<?php

declare(strict_types=1);

final class Paytr
{
    private const TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';

    /** @return array{merchant_id:string,merchant_key:string,merchant_salt:string,test_mode:bool,no_installment:bool,max_installment:int,test_ip:string} */
    public static function config(): array
    {
        $stored = setting('paytr', []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $max = (int) ($stored['max_installment'] ?? 0);
        if (!in_array($max, self::installmentChoices(), true)) {
            $max = 0;
        }

        return [
            'merchant_id' => (string) ($stored['merchant_id'] ?? ''),
            'merchant_key' => (string) ($stored['merchant_key'] ?? ''),
            'merchant_salt' => (string) ($stored['merchant_salt'] ?? ''),
            'test_mode' => !array_key_exists('test_mode', $stored) || !empty($stored['test_mode']),
            'no_installment' => !empty($stored['no_installment']),
            'max_installment' => $max,
            'test_ip' => (string) ($stored['test_ip'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $member
     *  @param array<string, mixed> $shipping
     */
    public static function checkoutError(array $member, array $shipping): ?string
    {
        if (!self::ready()) {
            return 'Kredi kartı ödemesi henüz yapılandırılmadı.';
        }
        $email = (string) ($member['email'] ?? '');
        if (preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email) !== 1 || strlen($email) > 100) {
            return 'Ödeme için e-posta adresinde yalnızca İngilizce karakterler olmalı.';
        }
        $name = self::clip((string) ($shipping['full_name'] ?? ''), 60);
        $address = self::clip(self::address($shipping), 400);
        $phone = preg_replace('/\D+/', '', (string) ($shipping['phone'] ?? '')) ?? '';
        if ($name === '' || $address === '' || $phone === '') {
            return 'Ödeme için ad, telefon ve adres gerekli.';
        }
        $config = self::config();
        if (self::clientIp($config['test_mode'], $config['test_ip']) === null) {
            return 'Yerel denemede PayTR genel bir IP adresi ister. Ödeme ayarlarındaki test IP alanına dış IP adresini yaz.';
        }

        return null;
    }

    public static function ready(): bool
    {
        $config = self::config();

        return $config['merchant_id'] !== ''
            && $config['merchant_key'] !== ''
            && $config['merchant_salt'] !== '';
    }

    public static function callbackUrl(): string
    {
        return absolute_url('/paytr-bildirim');
    }

    /** @return list<int> */
    public static function installmentChoices(): array
    {
        return [0, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function save(array $input): array
    {
        $current = self::config();
        $merchantId = preg_replace('/\D+/', '', (string) ($input['merchant_id'] ?? '')) ?? '';
        if ($merchantId === '' || strlen($merchantId) > 12) {
            return ['Mağaza numarasını yaz.'];
        }
        $key = trim((string) ($input['merchant_key'] ?? ''));
        $salt = trim((string) ($input['merchant_salt'] ?? ''));
        if ($key === '') {
            $key = $current['merchant_key'];
        }
        if ($salt === '') {
            $salt = $current['merchant_salt'];
        }
        if ($key === '' || $salt === '' || strlen($key) > 128 || strlen($salt) > 128 || preg_match('/\s/', $key . $salt) === 1) {
            return ['Mağaza anahtarı ve salt değerini PayTR panelinden yaz.'];
        }
        $testIp = trim((string) ($input['test_ip'] ?? ''));
        if ($testIp !== '' && !self::isPublicIp($testIp)) {
            return ['Test IP adresi genel bir IP olmalı.'];
        }
        $max = (int) ($input['max_installment'] ?? 0);
        if (!in_array($max, self::installmentChoices(), true)) {
            $max = 0;
        }
        $noInstallment = !empty($input['no_installment']);
        save_setting('paytr', [
            'merchant_id' => $merchantId,
            'merchant_key' => $key,
            'merchant_salt' => $salt,
            'test_mode' => !empty($input['test_mode']),
            'no_installment' => $noInstallment,
            'max_installment' => $noInstallment ? 0 : $max,
            'test_ip' => $testIp,
        ]);

        return [];
    }

    /** @param array<string, mixed> $order
     *  @param array<string, mixed> $member
     *  @return array{token:?string,error:string}
     */
    public static function token(array $order, array $member): array
    {
        if (!self::ready()) {
            return ['token' => null, 'error' => 'Kredi kartı ödemesi henüz yapılandırılmadı.'];
        }
        $config = self::config();
        $orderNo = (string) ($order['order_no'] ?? '');
        if (!OrderBook::validNo($orderNo)) {
            return ['token' => null, 'error' => 'Sipariş numarası geçersiz.'];
        }
        $email = (string) ($member['email'] ?? '');
        if (preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email) !== 1 || strlen($email) > 100) {
            return ['token' => null, 'error' => 'Ödeme için e-posta adresinde yalnızca İngilizce karakterler olmalı.'];
        }
        $shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
        $name = self::clip((string) ($shipping['full_name'] ?? ''), 60);
        $address = self::clip(self::address($shipping), 400);
        $phone = preg_replace('/\D+/', '', (string) ($shipping['phone'] ?? '')) ?? '';
        $phone = substr($phone, 0, 20);
        if ($name === '' || $address === '' || $phone === '') {
            return ['token' => null, 'error' => 'Ödeme için ad, telefon ve adres gerekli.'];
        }
        $ip = self::clientIp($config['test_mode'], $config['test_ip']);
        if ($ip === null) {
            return ['token' => null, 'error' => 'Yerel denemede PayTR genel bir IP adresi ister. Ödeme ayarlarındaki test IP alanına dış IP adresini yaz.'];
        }
        $amount = (string) ProductEditor::moneyCents((string) ($order['total'] ?? ''));
        if ($amount === '0') {
            return ['token' => null, 'error' => 'Ödeme tutarı geçersiz.'];
        }
        $basket = self::basket($order);
        if ($basket === null) {
            return ['token' => null, 'error' => 'Ödeme sepeti hazırlanamadı.'];
        }
        $noInstallment = $config['no_installment'] ? '1' : '0';
        $maxInstallment = (string) ($config['no_installment'] ? 0 : $config['max_installment']);
        $testMode = $config['test_mode'] ? '1' : '0';
        $currency = 'TL';
        $hashStr = $config['merchant_id'] . $ip . $orderNo . $email . $amount . $basket . $noInstallment . $maxInstallment . $currency . $testMode;
        $token = base64_encode(hash_hmac('sha256', $hashStr . $config['merchant_salt'], $config['merchant_key'], true));
        $return = absolute_url('/odeme-sonuc') . '?no=' . rawurlencode($orderNo);
        $post = [
            'merchant_id' => $config['merchant_id'],
            'user_ip' => $ip,
            'merchant_oid' => $orderNo,
            'email' => $email,
            'payment_amount' => $amount,
            'paytr_token' => $token,
            'user_basket' => $basket,
            'debug_on' => $testMode,
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'user_name' => $name,
            'user_address' => $address,
            'user_phone' => $phone,
            'merchant_ok_url' => $return,
            'merchant_fail_url' => $return,
            'timeout_limit' => '30',
            'currency' => $currency,
            'test_mode' => $testMode,
        ];
        $result = self::request($post);
        if ($result === null) {
            return ['token' => null, 'error' => 'PayTR ödeme formu şu anda açılamadı. Bir süre sonra yeniden dene.'];
        }
        if (($result['status'] ?? '') === 'success') {
            $iframe = (string) ($result['token'] ?? '');
            if (preg_match('/^[A-Za-z0-9_-]{8,}$/', $iframe) !== 1) {
                return ['token' => null, 'error' => 'PayTR ödeme formu şu anda açılamadı. Bir süre sonra yeniden dene.'];
            }

            return ['token' => $iframe, 'error' => ''];
        }
        $reason = trim((string) ($result['reason'] ?? ''));
        if ($config['test_mode'] && $reason !== '') {
            return ['token' => null, 'error' => 'PayTR ödeme formunu açmadı: ' . self::clip($reason, 180)];
        }

        return ['token' => null, 'error' => 'PayTR ödeme formu şu anda açılamadı. Bir süre sonra yeniden dene.'];
    }

    /** @param array<string, mixed> $post
     *  @return array{accepted:bool,order_no:string,paid:bool,paid_cents:int,note:string,response:string}
     */
    public static function verify(array $post): array
    {
        $rejected = [
            'accepted' => false,
            'order_no' => '',
            'paid' => false,
            'paid_cents' => 0,
            'note' => '',
            'response' => 'PAYTR notification failed: bad hash',
        ];
        if (!self::ready()) {
            $rejected['response'] = 'PAYTR notification failed: not configured';

            return $rejected;
        }
        $orderNo = (string) ($post['merchant_oid'] ?? '');
        $status = (string) ($post['status'] ?? '');
        $total = (string) ($post['total_amount'] ?? '');
        $hash = (string) ($post['hash'] ?? '');
        if (!OrderBook::validNo($orderNo) || !in_array($status, ['success', 'failed'], true) || preg_match('/^\d+$/', $total) !== 1 || $hash === '') {
            return $rejected;
        }
        $config = self::config();
        $expected = base64_encode(hash_hmac('sha256', $orderNo . $config['merchant_salt'] . $status . $total, $config['merchant_key'], true));
        if (!hash_equals($expected, $hash)) {
            return $rejected;
        }
        $note = '';
        if ($status === 'failed') {
            $note = self::clip(trim(strip_tags((string) ($post['failed_reason_msg'] ?? ''))), 160);
            if ($note === '') {
                $note = 'Ödeme onaylanmadı.';
            }
        } elseif ((string) ($post['test_mode'] ?? '') === '1') {
            $note = 'Test işlemi';
        }

        return [
            'accepted' => true,
            'order_no' => $orderNo,
            'paid' => $status === 'success',
            'paid_cents' => (int) $total,
            'note' => $note,
            'response' => 'OK',
        ];
    }

    /** @return array{ok:bool,error:string,test:bool} */
    public static function refund(string $orderNo, string $amount, string $reference): array
    {
        $failed = ['ok' => false, 'error' => 'PayTR iadesi tamamlanamadı.', 'test' => false];
        if (!self::ready() || !OrderBook::validNo($orderNo) || preg_match('/^\d+\.\d{2}$/', $amount) !== 1 || $amount === '0.00') {
            return $failed;
        }
        if (preg_match('/^[A-Za-z0-9]{1,64}$/', $reference) !== 1) {
            return $failed;
        }
        $config = self::config();
        $token = base64_encode(hash_hmac(
            'sha256',
            $config['merchant_id'] . $orderNo . $amount . $config['merchant_salt'],
            $config['merchant_key'],
            true
        ));
        $result = self::request([
            'merchant_id' => $config['merchant_id'],
            'merchant_oid' => $orderNo,
            'return_amount' => $amount,
            'reference_no' => $reference,
            'paytr_token' => $token,
        ], 'https://www.paytr.com/odeme/iade', 90);
        if ($result === null) {
            return ['ok' => false, 'error' => 'PayTR iade servisine ulaşılamadı.', 'test' => false];
        }
        if ((string) ($result['status'] ?? '') !== 'success') {
            $message = self::clip(trim(strip_tags((string) ($result['err_msg'] ?? ''))), 160);
            $code = preg_replace('/\D+/', '', (string) ($result['err_no'] ?? '')) ?? '';
            if ($message === '') {
                $message = 'PayTR iadesi tamamlanamadı.';
            } elseif ($code !== '') {
                $message = $code . ': ' . $message;
            }

            return ['ok' => false, 'error' => $message, 'test' => false];
        }
        $oid = (string) ($result['merchant_oid'] ?? '');
        if ($oid !== '' && $oid !== $orderNo) {
            return ['ok' => false, 'error' => 'PayTR sipariş numarası uyuşmadı.', 'test' => false];
        }
        $test = (string) ($result['is_test'] ?? '') === '1' || (int) ($result['is_test'] ?? 0) === 1;

        return ['ok' => true, 'error' => '', 'test' => $test];
    }

    /** @param array<string, string> $post */
    private static function request(array $post, string $url = self::TOKEN_URL, int $timeout = 20): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $channel = curl_init($url);
        if ($channel === false) {
            return null;
        }
        curl_setopt_array($channel, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(20, $timeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($channel);
        $failed = curl_errno($channel) !== 0 || !is_string($body);
        curl_close($channel);
        if ($failed) {
            return null;
        }
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $shipping */
    private static function address(array $shipping): string
    {
        $parts = [];
        foreach (['line', 'neighborhood', 'district', 'city', 'postal'] as $key) {
            $value = trim((string) ($shipping[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(' ', $parts);
    }

    /** @param array<string, mixed> $order */
    private static function basket(array $order): ?string
    {
        $title = self::clip((string) ($order['product_title'] ?? ''), 100);
        $unit = (string) ($order['unit_price'] ?? '');
        $qty = (int) ($order['qty'] ?? 0);
        if ($title === '' || preg_match('/^\d+\.\d{2}$/', $unit) !== 1 || $qty < 1) {
            return null;
        }
        $lines = [[$title, $unit, $qty]];
        $cargo = (string) ($order['cargo_price'] ?? '0.00');
        if (preg_match('/^\d+\.\d{2}$/', $cargo) === 1 && $cargo !== '0.00') {
            $cargoName = self::clip((string) ($order['cargo_name'] ?? ''), 80);
            $lines[] = [$cargoName !== '' ? $cargoName : 'Kargo', $cargo, 1];
        }
        $json = json_encode($lines);

        return is_string($json) ? base64_encode($json) : null;
    }

    private static function clientIp(bool $testMode, string $testIp): ?string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (self::isPublicIp($remote)) {
            return substr($remote, 0, 39);
        }
        if ($testMode && self::isPublicIp($testIp)) {
            return substr($testIp, 0, 39);
        }

        return null;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && strlen($ip) <= 39;
    }

    private static function clip(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit);
    }
}
