<?php

declare(strict_types=1);

final class Geliver
{
    private const BASE = 'https://api.geliver.io/api/v1';

    /** @return array{token:string,test_mode:bool,sender_id:string,length:string,width:string,height:string,weight:string,sender:array{name:string,email:string,phone:string,address:string,city:string,district:string,zip:string}} */
    public static function config(): array
    {
        $stored = setting('geliver', []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $sender = is_array($stored['sender'] ?? null) ? $stored['sender'] : [];

        return [
            'token' => (string) ($stored['token'] ?? ''),
            'test_mode' => !array_key_exists('test_mode', $stored) || !empty($stored['test_mode']),
            'sender_id' => (string) ($stored['sender_id'] ?? ''),
            'length' => self::measure((string) ($stored['length'] ?? '10'), 1, 200) ?? '10.0',
            'width' => self::measure((string) ($stored['width'] ?? '10'), 1, 200) ?? '10.0',
            'height' => self::measure((string) ($stored['height'] ?? '10'), 1, 200) ?? '10.0',
            'weight' => self::measure((string) ($stored['weight'] ?? '1'), 0.1, 30) ?? '1.0',
            'sender' => [
                'name' => (string) ($sender['name'] ?? ''),
                'email' => (string) ($sender['email'] ?? ''),
                'phone' => (string) ($sender['phone'] ?? ''),
                'address' => (string) ($sender['address'] ?? ''),
                'city' => (string) ($sender['city'] ?? ''),
                'district' => (string) ($sender['district'] ?? ''),
                'zip' => (string) ($sender['zip'] ?? ''),
            ],
        ];
    }

    public static function ready(): bool
    {
        $config = self::config();

        return $config['token'] !== '' && $config['sender_id'] !== '';
    }

    public static function callbackUrl(): string
    {
        return absolute_url('/geliver-bildirim');
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function save(array $input): array
    {
        $current = self::config();
        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '') {
            $token = $current['token'];
        }
        if ($token === '' || strlen($token) > 200 || preg_match('/\s/', $token) === 1) {
            return ['Geliver API tokenını yaz.'];
        }
        $length = self::measure((string) ($input['length'] ?? ''), 1, 200);
        $width = self::measure((string) ($input['width'] ?? ''), 1, 200);
        $height = self::measure((string) ($input['height'] ?? ''), 1, 200);
        $weight = self::measure((string) ($input['weight'] ?? ''), 0.1, 30);
        if ($length === null || $width === null || $height === null || $weight === null) {
            return ['Paket ölçüsü santimetre, ağırlık kilogram olmalı.'];
        }
        $sender = [
            'name' => self::clip((string) ($input['sender_name'] ?? ''), 80),
            'email' => trim((string) ($input['sender_email'] ?? '')),
            'phone' => self::phone((string) ($input['sender_phone'] ?? '')),
            'address' => self::clip((string) ($input['sender_address'] ?? ''), 180),
            'city' => self::clip((string) ($input['sender_city'] ?? ''), 40),
            'district' => self::clip((string) ($input['sender_district'] ?? ''), 40),
            'zip' => preg_replace('/\D+/', '', (string) ($input['sender_zip'] ?? '')) ?? '',
        ];
        if ($sender['name'] === '' || $sender['address'] === '' || $sender['city'] === '' || $sender['district'] === '') {
            return ['Gönderici adı, adres, il ve ilçe gerekli.'];
        }
        if (!self::email($sender['email'])) {
            return ['Gönderici e-postasında yalnızca İngilizce karakterler olmalı.'];
        }
        if ($sender['phone'] === '' || strlen($sender['zip']) !== 5) {
            return ['Gönderici telefonu ve 5 haneli posta kodu gerekli.'];
        }
        $plate = self::plate($sender['city']);
        if ($plate === null) {
            return ['Gönderici ili Türkiye il listesindeki adıyla yaz.'];
        }
        $senderId = $current['sender_id'];
        $sameSender = $sender === $current['sender'] && $senderId !== '' && $token === $current['token'];
        if (!$sameSender) {
            $created = self::request('POST', '/addresses', [
                'name' => $sender['name'],
                'email' => $sender['email'],
                'phone' => $sender['phone'],
                'address1' => $sender['address'],
                'countryCode' => 'TR',
                'cityName' => $sender['city'],
                'cityCode' => $plate,
                'districtName' => $sender['district'],
                'zip' => $sender['zip'],
                'isRecipientAddress' => false,
            ], $token);
            if (!$created['ok'] || !is_array($created['data'])) {
                return [$created['error'] !== '' ? $created['error'] : 'Gönderici adresi Geliver tarafında kaydedilemedi.'];
            }
            $senderId = (string) ($created['data']['id'] ?? '');
            if (!self::id($senderId)) {
                return ['Geliver gönderici adres kimliği dönmedi.'];
            }
        }
        save_setting('geliver', [
            'token' => $token,
            'test_mode' => !empty($input['test_mode']),
            'sender_id' => $senderId,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'weight' => $weight,
            'sender' => $sender,
        ]);

        return [];
    }

    /** @param array<string, mixed> $order
     *  @return array{ok:bool,error:string,state:array<string, mixed>}
     */
    public static function create(array $order): array
    {
        $empty = ['ok' => false, 'error' => 'Geliver gönderisi açılamadı.', 'state' => []];
        if (!self::ready()) {
            $empty['error'] = 'Geliver tokenı ve gönderici adresi kayıtlı değil.';

            return $empty;
        }
        $config = self::config();
        $shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
        $city = trim((string) ($shipping['city'] ?? ''));
        $plate = self::plate($city);
        $phone = self::phone((string) ($shipping['phone'] ?? ''));
        $address = self::clip(trim((string) ($shipping['neighborhood'] ?? '') . ' ' . (string) ($shipping['line'] ?? '')), 180);
        if ($plate === null || $phone === '' || $address === '' || trim((string) ($shipping['district'] ?? '')) === '') {
            $empty['error'] = 'Alıcı il, ilçe, telefon ve açık adres Geliver için eksik.';

            return $empty;
        }
        $email = (string) ($order['email'] ?? '');
        if (!self::email($email)) {
            $email = $config['sender']['email'];
        }
        $body = [
            'senderAddressID' => $config['sender_id'],
            'length' => $config['length'],
            'width' => $config['width'],
            'height' => $config['height'],
            'distanceUnit' => 'cm',
            'weight' => $config['weight'],
            'massUnit' => 'kg',
            'productPaymentOnDelivery' => false,
            'recipientAddress' => [
                'name' => self::clip((string) ($shipping['full_name'] ?? ''), 80),
                'email' => $email,
                'phone' => $phone,
                'address1' => $address,
                'countryCode' => 'TR',
                'cityName' => $city,
                'cityCode' => $plate,
                'districtName' => self::clip((string) $shipping['district'], 40),
            ],
            'items' => [[
                'title' => self::clip((string) ($order['product_title'] ?? 'Sipariş'), 100),
                'quantity' => max(1, (int) ($order['qty'] ?? 1)),
            ]],
            'order' => [
                'sourceCode' => 'API',
                'sourceIdentifier' => absolute_url('/'),
                'orderNumber' => (string) ($order['order_no'] ?? ''),
                'totalAmount' => (string) ($order['total'] ?? '0.00'),
                'totalAmountCurrency' => 'TRY',
            ],
        ];
        $zip = preg_replace('/\D+/', '', (string) ($shipping['postal'] ?? '')) ?? '';
        if (strlen($zip) === 5) {
            $body['recipientAddress']['zip'] = $zip;
        }
        if ($config['test_mode']) {
            $body['test'] = true;
        }
        $result = self::request('POST', '/shipments', $body, $config['token']);
        if (!$result['ok'] || !is_array($result['data'])) {
            $empty['error'] = $result['error'] !== '' ? $result['error'] : 'Geliver gönderisi açılamadı.';

            return $empty;
        }

        return ['ok' => true, 'error' => '', 'state' => self::stateFromShipment($result['data'], $config['test_mode'])];
    }

    /** @return array{ok:bool,error:string,state:array<string, mixed>} */
    public static function shipment(string $shipmentId): array
    {
        $empty = ['ok' => false, 'error' => 'Geliver gönderisi alınamadı.', 'state' => []];
        if (!self::ready() || !self::id($shipmentId)) {
            return $empty;
        }
        $config = self::config();
        $result = self::request('GET', '/shipments/' . rawurlencode($shipmentId), null, $config['token']);
        if (!$result['ok'] || !is_array($result['data'])) {
            $empty['error'] = $result['error'] !== '' ? $result['error'] : 'Geliver gönderisi alınamadı.';

            return $empty;
        }

        return ['ok' => true, 'error' => '', 'state' => self::stateFromShipment($result['data'], $config['test_mode'])];
    }

    /** @param list<array<string, mixed>> $offers
     *  @return array{ok:bool,error:string,state:array<string, mixed>}
     */
    public static function accept(string $offerId, array $offers): array
    {
        $empty = ['ok' => false, 'error' => 'Kargo teklifi satın alınamadı.', 'state' => []];
        if (!self::ready() || !self::id($offerId)) {
            return $empty;
        }
        $known = false;
        foreach ($offers as $offer) {
            if ((string) ($offer['id'] ?? '') === $offerId) {
                $known = true;
                break;
            }
        }
        if (!$known) {
            $empty['error'] = 'Bu teklif bu gönderiye ait değil.';

            return $empty;
        }
        $config = self::config();
        $result = self::request('POST', '/transactions', ['offerID' => $offerId], $config['token']);
        if (!$result['ok'] || !is_array($result['data'])) {
            $empty['error'] = $result['error'] !== '' ? $result['error'] : 'Kargo teklifi satın alınamadı.';

            return $empty;
        }
        $shipment = is_array($result['data']['shipment'] ?? null) ? $result['data']['shipment'] : $result['data'];
        $state = self::stateFromShipment($shipment, $config['test_mode']);
        $state['offer_id'] = $offerId;

        return ['ok' => true, 'error' => '', 'state' => $state];
    }

    /** @return array{ok:bool,error:string,iade:array<string, string>} */
    public static function createReturn(string $shipmentId): array
    {
        $empty = ['ok' => false, 'error' => 'İade kodu oluşturulamadı.', 'iade' => self::emptyReturn()];
        if (!self::ready() || !self::id($shipmentId)) {
            return $empty;
        }
        $config = self::config();
        $result = self::request('POST', '/shipments/' . rawurlencode($shipmentId), [
            'isReturn' => true,
            'willAccept' => true,
            'count' => 1,
        ], $config['token']);
        if (!$result['ok'] || !is_array($result['data'])) {
            $empty['error'] = $result['error'] !== '' ? $result['error'] : 'İade kodu oluşturulamadı.';

            return $empty;
        }
        $shipment = is_array($result['data']['shipment'] ?? null) ? $result['data']['shipment'] : $result['data'];
        $state = self::stateFromShipment($shipment, $config['test_mode'] || !empty($shipment['test']));
        if (!self::id($state['shipment_id']) && $state['barcode'] === '') {
            $empty['error'] = 'Geliver iade kodu dönmedi.';

            return $empty;
        }

        return [
            'ok' => true,
            'error' => '',
            'iade' => [
                'shipment_id' => $state['shipment_id'],
                'barcode' => $state['barcode'],
                'tracking_number' => $state['tracking_number'],
                'tracking_url' => $state['tracking_url'],
                'label_url' => $state['label_url'],
                'provider' => $state['provider'],
                'status' => $state['status'],
            ],
        ];
    }

    /** @return array{ok:bool,error:string,iade:array<string, string>} */
    public static function returnShipment(string $shipmentId): array
    {
        $loaded = self::shipment($shipmentId);
        if (!$loaded['ok']) {
            return ['ok' => false, 'error' => $loaded['error'], 'iade' => self::emptyReturn()];
        }
        $state = $loaded['state'];

        return [
            'ok' => true,
            'error' => '',
            'iade' => [
                'shipment_id' => (string) ($state['shipment_id'] ?? ''),
                'barcode' => (string) ($state['barcode'] ?? ''),
                'tracking_number' => (string) ($state['tracking_number'] ?? ''),
                'tracking_url' => (string) ($state['tracking_url'] ?? ''),
                'label_url' => (string) ($state['label_url'] ?? ''),
                'provider' => (string) ($state['provider'] ?? ''),
                'status' => (string) ($state['status'] ?? ''),
            ],
        ];
    }

    /** @return array<string, string> */
    public static function emptyReturn(): array
    {
        return [
            'shipment_id' => '',
            'barcode' => '',
            'tracking_number' => '',
            'tracking_url' => '',
            'label_url' => '',
            'provider' => '',
            'status' => '',
        ];
    }

    /** @return array{ok:bool,error:string} */
    public static function cancel(string $shipmentId): array
    {
        if (!self::ready() || !self::id($shipmentId)) {
            return ['ok' => false, 'error' => 'Gönderi iptal edilemedi.'];
        }
        $result = self::request('DELETE', '/shipments/' . rawurlencode($shipmentId), null, self::config()['token']);
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'] !== '' ? $result['error'] : 'Gönderi iptal edilemedi.'];
        }

        return ['ok' => true, 'error' => ''];
    }

    /** @param array<string, mixed> $payload
     *  @return array{shipment_id:string,status:string,tracking_status:string,tracking_number:string,tracking_url:string,label_url:string,barcode:string}
     */
    public static function webhook(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $tracking = is_array($data['trackingStatus'] ?? null) ? $data['trackingStatus'] : [];

        return [
            'shipment_id' => (string) ($data['id'] ?? ''),
            'status' => strtoupper((string) ($data['statusCode'] ?? '')),
            'tracking_status' => strtoupper((string) ($tracking['trackingStatusCode'] ?? '')),
            'tracking_number' => (string) ($data['trackingNumber'] ?? ''),
            'tracking_url' => self::https((string) ($data['trackingUrl'] ?? '')),
            'label_url' => self::https((string) ($data['labelURL'] ?? '')),
            'barcode' => (string) ($data['barcode'] ?? ''),
        ];
    }

    /** @param array<string, mixed>|null $body
     *  @return array{ok:bool,error:string,data:array<string, mixed>|null}
     */
    private static function request(string $method, string $path, ?array $body, string $token): array
    {
        $failed = ['ok' => false, 'error' => 'Geliver servisine ulaşılamadı.', 'data' => null];
        if (!function_exists('curl_init') || $token === '') {
            return $failed;
        }
        $channel = curl_init(self::BASE . $path);
        if ($channel === false) {
            return $failed;
        }
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                return $failed;
            }
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }
        curl_setopt_array($channel, $options);
        $raw = curl_exec($channel);
        $status = (int) curl_getinfo($channel, CURLINFO_RESPONSE_CODE);
        $transportFailed = curl_errno($channel) !== 0 || !is_string($raw);
        curl_close($channel);
        if ($transportFailed) {
            return $failed;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'Geliver yanıtı okunamadı.', 'data' => null];
        }
        if ($status >= 400 || (array_key_exists('result', $decoded) && $decoded['result'] === false)) {
            $message = self::clip(trim(strip_tags((string) ($decoded['message'] ?? ''))), 160);
            $extra = self::clip(trim(strip_tags((string) ($decoded['additionalMessage'] ?? ''))), 120);
            if ($extra !== '' && $extra !== $message) {
                $message = trim($message . ' ' . $extra);
            }

            return ['ok' => false, 'error' => $message !== '' ? $message : 'Geliver isteği tamamlanamadı.', 'data' => null];
        }
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;

        return ['ok' => true, 'error' => '', 'data' => $data];
    }

    /** @param array<string, mixed> $shipment
     *  @return array<string, mixed>
     */
    private static function stateFromShipment(array $shipment, bool $test): array
    {
        $tracking = is_array($shipment['trackingStatus'] ?? null) ? $shipment['trackingStatus'] : [];
        $offers = self::offers(is_array($shipment['offers'] ?? null) ? $shipment['offers'] : []);
        $provider = (string) ($shipment['providerServiceCode'] ?? $shipment['providerCode'] ?? '');

        return [
            'shipment_id' => (string) ($shipment['id'] ?? ''),
            'test' => $test || !empty($shipment['test']),
            'status' => strtoupper((string) ($shipment['statusCode'] ?? '')),
            'tracking_status' => strtoupper((string) ($tracking['trackingStatusCode'] ?? '')),
            'offers' => $offers,
            'offer_id' => (string) ($shipment['acceptedOfferID'] ?? ''),
            'barcode' => (string) ($shipment['barcode'] ?? ''),
            'tracking_number' => (string) ($shipment['trackingNumber'] ?? ''),
            'tracking_url' => self::https((string) ($shipment['trackingUrl'] ?? '')),
            'label_url' => self::https((string) ($shipment['labelURL'] ?? '')),
            'provider' => $provider,
            'amount' => (string) ($shipment['totalAmount'] ?? $shipment['amount'] ?? ''),
            'currency' => (string) ($shipment['currency'] ?? 'TRY'),
            'note' => '',
        ];
    }

    /** @param array<string, mixed> $offers
     *  @return list<array{id:string,provider:string,service:string,amount:string,currency:string,eta:string,cheapest:bool}>
     */
    private static function offers(array $offers): array
    {
        $list = is_array($offers['list'] ?? null) ? $offers['list'] : [];
        $cheapest = is_array($offers['cheapest'] ?? null) ? (string) ($offers['cheapest']['id'] ?? '') : '';
        if ($list === [] && is_array($offers['cheapest'] ?? null)) {
            $list[] = $offers['cheapest'];
        }
        $rows = [];
        $seen = [];
        foreach ($list as $offer) {
            if (!is_array($offer)) {
                continue;
            }
            $id = (string) ($offer['id'] ?? '');
            if (!self::id($id) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $amount = (string) ($offer['totalAmount'] ?? $offer['amount'] ?? '');
            $rows[] = [
                'id' => $id,
                'provider' => (string) ($offer['providerCode'] ?? ''),
                'service' => (string) ($offer['providerServiceCode'] ?? ''),
                'amount' => $amount,
                'currency' => (string) ($offer['currency'] ?? 'TRY'),
                'eta' => self::clip((string) ($offer['averageEstimatedTimeHumanReadible'] ?? ''), 80),
                'cheapest' => $id === $cheapest,
            ];
        }

        return $rows;
    }

    /** @return array<string, string> */
    private static function plates(): array
    {
        $cities = [
            'Adana' => '01', 'Adıyaman' => '02', 'Afyonkarahisar' => '03', 'Ağrı' => '04', 'Amasya' => '05',
            'Ankara' => '06', 'Antalya' => '07', 'Artvin' => '08', 'Aydın' => '09', 'Balıkesir' => '10',
            'Bilecik' => '11', 'Bingöl' => '12', 'Bitlis' => '13', 'Bolu' => '14', 'Burdur' => '15',
            'Bursa' => '16', 'Çanakkale' => '17', 'Çankırı' => '18', 'Çorum' => '19', 'Denizli' => '20',
            'Diyarbakır' => '21', 'Edirne' => '22', 'Elazığ' => '23', 'Erzincan' => '24', 'Erzurum' => '25',
            'Eskişehir' => '26', 'Gaziantep' => '27', 'Giresun' => '28', 'Gümüşhane' => '29', 'Hakkari' => '30',
            'Hatay' => '31', 'Isparta' => '32', 'Mersin' => '33', 'İstanbul' => '34', 'İzmir' => '35',
            'Kars' => '36', 'Kastamonu' => '37', 'Kayseri' => '38', 'Kırklareli' => '39', 'Kırşehir' => '40',
            'Kocaeli' => '41', 'Konya' => '42', 'Kütahya' => '43', 'Malatya' => '44', 'Manisa' => '45',
            'Kahramanmaraş' => '46', 'Mardin' => '47', 'Muğla' => '48', 'Muş' => '49', 'Nevşehir' => '50',
            'Niğde' => '51', 'Ordu' => '52', 'Rize' => '53', 'Sakarya' => '54', 'Samsun' => '55',
            'Siirt' => '56', 'Sinop' => '57', 'Sivas' => '58', 'Tekirdağ' => '59', 'Tokat' => '60',
            'Trabzon' => '61', 'Tunceli' => '62', 'Şanlıurfa' => '63', 'Uşak' => '64', 'Van' => '65',
            'Yozgat' => '66', 'Zonguldak' => '67', 'Aksaray' => '68', 'Bayburt' => '69', 'Karaman' => '70',
            'Kırıkkale' => '71', 'Batman' => '72', 'Şırnak' => '73', 'Bartın' => '74', 'Ardahan' => '75',
            'Iğdır' => '76', 'Yalova' => '77', 'Karabük' => '78', 'Kilis' => '79', 'Osmaniye' => '80',
            'Düzce' => '81',
        ];
        $plates = [];
        foreach ($cities as $name => $code) {
            $plates[self::fold($name)] = $code;
        }

        return $plates;
    }

    private static function plate(string $city): ?string
    {
        return self::plates()[self::fold($city)] ?? null;
    }

    private static function fold(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, ['ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u']);

        return str_replace(['i̇', ' '], ['i', ''], $value);
    }

    private static function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '90' . substr($digits, 1);
        } elseif ($digits !== '' && !str_starts_with($digits, '90')) {
            $digits = '90' . $digits;
        }
        if (preg_match('/^90\d{10}$/', $digits) !== 1) {
            return '';
        }

        return '+' . $digits;
    }

    private static function email(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $value) === 1 && strlen($value) <= 100;
    }

    private static function id(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{8,80}$/', $value) === 1;
    }

    private static function https(string $value): string
    {
        return preg_match('#^https://#', $value) === 1 && strlen($value) <= 400 ? $value : '';
    }

    private static function measure(string $value, float $min, float $max): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        if ($number < $min || $number > $max) {
            return null;
        }

        return number_format($number, 1, '.', '');
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
