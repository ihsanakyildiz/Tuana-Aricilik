<?php

declare(strict_types=1);

require_once __DIR__ . '/LocationCatalog.php';

final class MemberAccount
{
    private const NAME = 'tuana_uye';

    private const IDLE_SECONDS = 7200;

    private const ABSOLUTE_SECONDS = 28800;

    private const LOCK_LIMIT = 5;

    private const LOCK_SECONDS = 900;

    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=3,p=1$eC5kUkNHWjUyR3lHcDFqeA$xJP/uXBv5mYVgxrbtdAawSmwXJC+h/gEHj4amQimi+s';

    private static bool $booted = false;

    private static bool $loaded = false;

    /** @var array<string, mixed>|null */
    private static ?array $member = null;

    public static function boot(bool $persist = false): void
    {
        if (self::$booted) {
            if ($persist && session_status() !== PHP_SESSION_ACTIVE) {
                self::start();
            }

            return;
        }
        self::$booted = true;
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!$persist && !isset($_COOKIE[self::NAME])) {
            return;
        }
        self::start();
        self::expire();
    }

    public static function check(): bool
    {
        return self::current() !== null;
    }

    /** @return array<string, mixed>|null */
    public static function current(): ?array
    {
        self::boot();
        if (self::$loaded) {
            return self::$member;
        }
        self::$loaded = true;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        $id = (int) ($_SESSION['member_id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $row = self::find($id);
        if ($row === null) {
            unset($_SESSION['member_id']);

            return null;
        }
        self::$member = $row;
        $_SESSION['member_seen'] = time();

        return self::$member;
    }

    public static function requireLogin(): void
    {
        self::boot(true);
        if (self::check()) {
            return;
        }
        $next = current_path();
        $query = $_SERVER['QUERY_STRING'] ?? '';
        if (is_string($query) && $query !== '') {
            $next .= '?' . $query;
        }
        self::redirect('uye-giris?sonraki=' . rawurlencode($next));
    }

    public static function redirect(string $path): void
    {
        header('Location: ' . root_path() . '/' . ltrim($path, '/'), true, 303);
        exit;
    }

    public static function csrfToken(): string
    {
        self::boot(true);
        $token = $_SESSION['member_csrf'] ?? '';
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['member_csrf'] = $token;
        }

        return $token;
    }

    public static function checkCsrf(?string $token, bool $rotate = true): bool
    {
        self::boot(true);
        $known = $_SESSION['member_csrf'] ?? '';
        $valid = is_string($token) && is_string($known) && $known !== '' && hash_equals($known, $token);
        if ($rotate) {
            $_SESSION['member_csrf'] = bin2hex(random_bytes(32));
        }

        return $valid;
    }

    public static function flash(string $type, string $text): void
    {
        self::boot(true);
        $_SESSION['member_flash'] = ['type' => $type, 'text' => $text];
    }

    /** @return array{type:string,text:string}|null */
    public static function pullFlash(): ?array
    {
        self::boot(true);
        $flash = $_SESSION['member_flash'] ?? null;
        unset($_SESSION['member_flash']);
        if (!is_array($flash) || !isset($flash['type'], $flash['text'])) {
            return null;
        }

        return ['type' => (string) $flash['type'], 'text' => (string) $flash['text']];
    }

    public static function safeNext(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || !str_starts_with($raw, '/') || str_starts_with($raw, '//')) {
            return '';
        }
        $parts = parse_url($raw);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])) {
            return '';
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || str_contains($path, '\\') || str_contains($path, '..') || preg_match('#^/[a-z0-9\\-/]*$#', $path) !== 1) {
            return '';
        }
        if ($path === '/admin' || str_starts_with($path, '/admin/') || in_array($path, ['/uye-giris', '/uye-ol', '/uye-cikis'], true)) {
            return '';
        }
        $query = (string) ($parts['query'] ?? '');
        if ($query !== '' && preg_match('/^[a-z0-9=&%_.\\-]+$/i', $query) !== 1) {
            return $path;
        }

        return $path . ($query === '' ? '' : '?' . $query);
    }

    public static function resume(string $next): void
    {
        $next = self::safeNext($next);
        $path = (string) parse_url($next === '' ? '/' : $next, PHP_URL_PATH);
        if (preg_match('#^/urun/([a-z0-9\\-]+)$#', $path, $match) === 1) {
            self::redirect('siparis?urun=' . $match[1]);
        }
        if ($next !== '') {
            self::redirect(ltrim($next, '/'));
        }
        self::redirect('hesabim');
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function register(array $input): array
    {
        self::boot(true);
        if (self::registerLimited()) {
            return ['Çok fazla kayıt denemesi. Bir süre sonra yeniden dene.'];
        }
        $errors = [];
        $first = self::personName((string) ($input['first_name'] ?? ''));
        $last = self::personName((string) ($input['last_name'] ?? ''));
        $email = self::email((string) ($input['email'] ?? ''));
        $phone = self::phone((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirm = (string) ($input['password_confirm'] ?? '');
        if ($first === null) {
            $errors[] = 'Ad en az 2 harf olmalı.';
        }
        if ($last === null) {
            $errors[] = 'Soyad en az 2 harf olmalı.';
        }
        if ($email === null) {
            $errors[] = 'Geçerli bir e-posta yaz.';
        }
        if ($phone === null) {
            $errors[] = 'Telefon 05xx xxx xx xx biçiminde olmalı.';
        }
        $passwordError = self::passwordError($password, $email ?? '');
        if ($passwordError !== null) {
            $errors[] = $passwordError;
        } elseif ($password !== $confirm) {
            $errors[] = 'Şifreler eşleşmiyor.';
        }
        if ($errors !== []) {
            return $errors;
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO || $first === null || $last === null || $email === null || $phone === null) {
            return ['Kayıt şu anda tamamlanamıyor.'];
        }
        $exists = $pdo->prepare('SELECT id FROM members WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn() !== false) {
            return ['Bu e-posta ile bir üyelik var. Giriş yapabilirsin.'];
        }
        $hash = password_hash($password, PASSWORD_ARGON2ID, self::hashOptions());
        if (!is_string($hash)) {
            return ['Şifre kaydedilemedi. Yeniden dene.'];
        }
        $insert = $pdo->prepare(
            'INSERT INTO members (email, password_hash, first_name, last_name, phone, profile) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$email, $hash, $first, $last, $phone, encode_value(self::blankProfile())]);
        self::noteRegister();
        self::establish((int) $pdo->lastInsertId());

        return [];
    }

    public static function login(string $email, string $password): string
    {
        self::boot(true);
        $email = self::email($email) ?? '';
        $pdo = db_pdo();
        if (self::locked()) {
            self::pause();

            return 'locked';
        }
        if (!$pdo instanceof PDO || $email === '' || strlen($password) < 1 || strlen($password) > 128) {
            self::fail();
            self::pause();

            return 'invalid';
        }
        $stmt = $pdo->prepare('SELECT id, password_hash FROM members WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        $hash = is_array($row) ? (string) $row['password_hash'] : self::DUMMY_HASH;
        $valid = password_verify($password, $hash);
        if (!is_array($row) || !$valid) {
            self::fail();
            self::pause();

            return 'invalid';
        }
        if (password_needs_rehash($hash, PASSWORD_ARGON2ID, self::hashOptions())) {
            $updated = password_hash($password, PASSWORD_ARGON2ID, self::hashOptions());
            if (is_string($updated)) {
                $save = $pdo->prepare('UPDATE members SET password_hash = ? WHERE id = ?');
                $save->execute([$updated, (int) $row['id']]);
            }
        }
        self::clearFailures();
        self::establish((int) $row['id']);

        return 'ok';
    }

    public static function logout(): void
    {
        self::boot(true);
        self::$member = null;
        self::$loaded = true;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string) ($params['path'] ?? '/'),
                'secure' => !empty($params['secure']),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        session_destroy();
        self::$booted = false;
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function save(string $action, array $input): array
    {
        $member = self::current();
        $pdo = db_pdo();
        if ($member === null || !$pdo instanceof PDO) {
            return ['Oturum bulunamadı. Yeniden giriş yap.'];
        }

        return match ($action) {
            'identity' => self::saveIdentity($pdo, $member, $input),
            'password' => self::savePassword($pdo, $member, $input),
            'shipping' => self::saveShipping($pdo, $member, $input),
            'invoice' => self::saveInvoice($pdo, $member, $input),
            default => ['Bu kayıt türü tanınmıyor.'],
        };
    }

    /** @param array<string, mixed> $input
     *  @return array{errors:list<string>,guest:?array<string,mixed>}
     */
    public static function guestProfile(array $input): array
    {
        self::boot(true);
        $email = self::email((string) ($input['guest_email'] ?? ''));
        $errors = [];
        if ($email === null) {
            $errors[] = 'E-posta adresini yaz.';
        } elseif (preg_match('/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/', $email) !== 1 || strlen($email) > 100) {
            $errors[] = 'E-posta adresinde yalnızca İngilizce karakterler olmalı.';
        }
        $shipping = self::shippingFromInput($input);
        $invoice = self::invoiceFromInput($input, $shipping['errors'] === [], true);
        $errors = array_merge($errors, $shipping['errors'], $invoice['errors']);
        if ($errors !== [] || $email === null) {
            return ['errors' => $errors, 'guest' => null];
        }
        $address = $shipping['shipping'];
        $address['email'] = $email;

        return [
            'errors' => [],
            'guest' => [
                'id' => 0,
                'email' => $email,
                'first_name' => '',
                'last_name' => '',
                'phone' => $address['phone'],
                'profile' => [
                    'shipping' => $address,
                    'invoice' => $invoice['invoice'],
                ],
                'shipping_ready' => true,
                'invoice_ready' => true,
            ],
        ];
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function saveFor(int $id, string $action, array $input): array
    {
        $member = self::find($id);
        $pdo = db_pdo();
        if ($member === null || !$pdo instanceof PDO) {
            return ['Üye bulunamadı.'];
        }

        return match ($action) {
            'identity' => self::saveIdentity($pdo, $member, $input, true),
            'shipping' => self::saveShipping($pdo, $member, $input),
            'invoice' => self::saveInvoice($pdo, $member, $input),
            default => ['Bu kayıt türü tanınmıyor.'],
        };
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return [];
        }
        $rows = $pdo->query(
            'SELECT id, email, first_name, last_name, phone, profile, created_at
             FROM members ORDER BY id DESC'
        )->fetchAll();
        $list = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $list[] = self::present($row);
            }
        }

        return $list;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT id, email, first_name, last_name, phone, profile, created_at FROM members WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? self::present($row) : null;
    }

    public static function phoneLabel(string $digits): string
    {
        if (preg_match('/^0\d{10}$/', $digits) !== 1) {
            return $digits;
        }

        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 2) . ' ' . substr($digits, 9, 2);
    }

    /** @param array<string, mixed> $address */
    public static function addressLine(array $address): string
    {
        $place = trim((string) ($address['neighborhood'] ?? ''));
        $district = trim((string) ($address['district'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));
        $area = trim($district . ($district !== '' && $city !== '' ? ' / ' : '') . $city);
        if ($place !== '' && $area !== '') {
            return $place . ', ' . $area;
        }

        return $place !== '' ? $place : $area;
    }

    /** @param array<string, mixed> $member */
    public static function ready(array $member): bool
    {
        return self::shippingReady($member) && self::invoiceReady($member);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private static function present(array $row): array
    {
        $profile = self::profile($row['profile'] ?? '');
        $member = [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'first_name' => (string) $row['first_name'],
            'last_name' => (string) $row['last_name'],
            'phone' => (string) $row['phone'],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'profile' => $profile,
        ];
        $member['shipping_ready'] = self::shippingReady($member);
        $member['invoice_ready'] = self::invoiceReady($member);

        return $member;
    }

    /** @param array<string, mixed> $member */
    private static function shippingReady(array $member): bool
    {
        $shipping = is_array($member['profile']['shipping'] ?? null) ? $member['profile']['shipping'] : [];

        return self::filled($shipping, ['full_name', 'phone', 'city', 'district', 'neighborhood', 'line']);
    }

    /** @param array<string, mixed> $member */
    private static function invoiceReady(array $member): bool
    {
        $invoice = is_array($member['profile']['invoice'] ?? null) ? $member['profile']['invoice'] : [];
        $kind = (string) ($invoice['kind'] ?? '');
        if (!in_array($kind, ['bireysel', 'kurumsal'], true) || trim((string) ($invoice['title'] ?? '')) === '' || trim((string) ($invoice['tax_no'] ?? '')) === '') {
            return false;
        }
        if ($kind === 'kurumsal' && trim((string) ($invoice['tax_office'] ?? '')) === '') {
            return false;
        }
        if (!empty($invoice['same']) && $kind !== 'kurumsal') {
            return self::shippingReady($member);
        }

        return self::filled($invoice, ['city', 'district', 'neighborhood', 'line']);
    }

    /** @param array<string, mixed> $bag
     *  @param list<string> $keys
     */
    private static function filled(array $bag, array $keys): bool
    {
        foreach ($keys as $key) {
            if (trim((string) ($bag[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $member
     *  @param array<string, mixed> $input
     *  @return list<string>
     */
    private static function saveIdentity(PDO $pdo, array $member, array $input, bool $trusted = false): array
    {
        $first = self::personName((string) ($input['first_name'] ?? ''));
        $last = self::personName((string) ($input['last_name'] ?? ''));
        $email = self::email((string) ($input['email'] ?? ''));
        $phone = self::phone((string) ($input['phone'] ?? ''));
        $errors = [];
        if ($first === null) {
            $errors[] = 'Ad en az 2 harf olmalı.';
        }
        if ($last === null) {
            $errors[] = 'Soyad en az 2 harf olmalı.';
        }
        if ($email === null) {
            $errors[] = 'Geçerli bir e-posta yaz.';
        }
        if ($phone === null) {
            $errors[] = 'Telefon 05xx xxx xx xx biçiminde olmalı.';
        }
        if ($errors !== [] || $first === null || $last === null || $email === null || $phone === null) {
            return $errors;
        }
        if ($email !== $member['email']) {
            if (!$trusted) {
                $passwordError = self::confirmPassword($pdo, $member, (string) ($input['current_password'] ?? ''));
                if ($passwordError !== null) {
                    return [$passwordError];
                }
            }
            $exists = $pdo->prepare('SELECT id FROM members WHERE email = ? AND id <> ? LIMIT 1');
            $exists->execute([$email, (int) $member['id']]);
            if ($exists->fetchColumn() !== false) {
                return ['Bu e-posta başka bir üyelikte kayıtlı.'];
            }
        }
        $update = $pdo->prepare('UPDATE members SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?');
        $update->execute([$first, $last, $email, $phone, (int) $member['id']]);
        self::refresh((int) $member['id']);

        return [];
    }

    /** @param array<string, mixed> $member
     *  @param array<string, mixed> $input
     *  @return list<string>
     */
    private static function savePassword(PDO $pdo, array $member, array $input): array
    {
        $passwordError = self::confirmPassword($pdo, $member, (string) ($input['current_password'] ?? ''));
        if ($passwordError !== null) {
            return [$passwordError];
        }
        $next = (string) ($input['new_password'] ?? '');
        $confirm = (string) ($input['new_password_confirm'] ?? '');
        $error = self::passwordError($next, (string) $member['email']);
        if ($error !== null) {
            return [$error];
        }
        if ($next !== $confirm) {
            return ['Yeni şifreler eşleşmiyor.'];
        }
        $stmt = $pdo->prepare('SELECT password_hash FROM members WHERE id = ?');
        $stmt->execute([(int) $member['id']]);
        $hash = $stmt->fetchColumn();
        if (is_string($hash) && password_verify($next, $hash)) {
            return ['Yeni şifre mevcut şifreden farklı olmalı.'];
        }
        $updated = password_hash($next, PASSWORD_ARGON2ID, self::hashOptions());
        if (!is_string($updated)) {
            return ['Şifre kaydedilemedi. Yeniden dene.'];
        }
        $save = $pdo->prepare('UPDATE members SET password_hash = ? WHERE id = ?');
        $save->execute([$updated, (int) $member['id']]);

        return [];
    }

    /** @param array<string, mixed> $member
     *  @param array<string, mixed> $input
     *  @return list<string>
     */
    private static function saveShipping(PDO $pdo, array $member, array $input): array
    {
        $built = self::shippingFromInput($input);
        if ($built['errors'] !== []) {
            return $built['errors'];
        }
        $profile = $member['profile'];
        $profile['shipping'] = $built['shipping'];

        return self::storeProfile($pdo, (int) $member['id'], $profile);
    }

    /** @param array<string, mixed> $input
     *  @return array{errors:list<string>,shipping:array<string, string>}
     */
    private static function shippingFromInput(array $input): array
    {
        $chain = LocationCatalog::chain(
            (int) ($input['ship_city'] ?? 0),
            (int) ($input['ship_district'] ?? 0),
            (int) ($input['ship_place'] ?? 0)
        );
        $shipping = [
            'full_name' => self::personName((string) ($input['ship_name'] ?? '')) ?? '',
            'phone' => self::phone((string) ($input['ship_phone'] ?? '')) ?? '',
            'city_id' => (string) ($chain['city_id'] ?? ''),
            'district_id' => (string) ($chain['district_id'] ?? ''),
            'place_id' => (string) ($chain['place_id'] ?? ''),
            'place_kind' => (string) ($chain['place_kind'] ?? ''),
            'city' => (string) ($chain['city'] ?? ''),
            'district' => (string) ($chain['district'] ?? ''),
            'neighborhood' => (string) ($chain['neighborhood'] ?? ''),
            'line' => self::line((string) ($input['ship_line'] ?? ''), 10, 180) ?? '',
            'postal' => self::postal((string) ($input['ship_postal'] ?? '')) ?? '',
            'note' => self::clip((string) ($input['ship_note'] ?? ''), 180),
        ];
        $errors = [];
        if ($shipping['full_name'] === '') {
            $errors[] = 'Kargo alıcısının adını yaz.';
        }
        if ($shipping['phone'] === '') {
            $errors[] = 'Kargo telefonu 05xx xxx xx xx biçiminde olmalı.';
        }
        if ($chain === null) {
            $errors[] = 'İl, ilçe ve mahalle veya köy seç.';
        }
        if ($shipping['line'] === '') {
            $errors[] = 'Açık adresi yaz.';
        }
        if (self::postal((string) ($input['ship_postal'] ?? '')) === null) {
            $errors[] = 'Posta kodu 5 rakam olmalı.';
            $shipping['postal'] = '';
        }

        return ['errors' => $errors, 'shipping' => $shipping];
    }

    /** @param array<string, mixed> $member
     *  @param array<string, mixed> $input
     *  @return list<string>
     */
    private static function saveInvoice(PDO $pdo, array $member, array $input): array
    {
        $built = self::invoiceFromInput($input, self::shippingReady($member));
        if ($built['errors'] !== []) {
            return $built['errors'];
        }
        $profile = $member['profile'];
        $profile['invoice'] = $built['invoice'];

        return self::storeProfile($pdo, (int) $member['id'], $profile);
    }

    /** @param array<string, mixed> $input
     *  @return array{errors:list<string>,invoice:array<string, mixed>}
     */
    private static function invoiceFromInput(array $input, bool $shippingReady, bool $combined = false): array
    {
        $kind = (string) ($input['invoice_kind'] ?? '');
        $blank = [
            'kind' => 'bireysel',
            'title' => '',
            'tax_office' => '',
            'tax_no' => '',
            'same' => false,
            'city_id' => '',
            'district_id' => '',
            'place_id' => '',
            'place_kind' => '',
            'city' => '',
            'district' => '',
            'neighborhood' => '',
            'line' => '',
            'postal' => '',
        ];
        if (!in_array($kind, ['bireysel', 'kurumsal'], true)) {
            return ['errors' => ['Fatura türünü seç.'], 'invoice' => $blank];
        }
        $same = $kind === 'bireysel' && isset($input['invoice_same']);
        $title = self::line((string) ($input['invoice_title'] ?? ''), 2, 120) ?? '';
        $office = self::placeName((string) ($input['invoice_tax_office'] ?? '')) ?? '';
        $tax = preg_replace('/\D+/', '', (string) ($input['invoice_tax_no'] ?? '')) ?? '';
        $errors = [];
        if ($title === '') {
            $errors[] = $kind === 'kurumsal' ? 'Firma unvanını yaz.' : 'Fatura adını yaz.';
        }
        if ($kind === 'bireysel' && !self::tckn($tax)) {
            $errors[] = 'T.C. kimlik numarası 11 rakam olmalı ve doğrulanmalı.';
        }
        if ($kind === 'kurumsal') {
            if ($office === '') {
                $errors[] = 'Vergi dairesini yaz.';
            }
            if (preg_match('/^\d{10}$/', $tax) !== 1 || $tax === '0000000000') {
                $errors[] = 'Vergi numarası 10 rakam olmalı.';
            }
        }
        $address = [
            'city_id' => '',
            'district_id' => '',
            'place_id' => '',
            'place_kind' => '',
            'city' => '',
            'district' => '',
            'neighborhood' => '',
            'line' => '',
            'postal' => '',
        ];
        if (!$same) {
            $chain = LocationCatalog::chain(
                (int) ($input['invoice_city'] ?? 0),
                (int) ($input['invoice_district'] ?? 0),
                (int) ($input['invoice_place'] ?? 0)
            );
            if ($chain !== null) {
                $address['city_id'] = (string) $chain['city_id'];
                $address['district_id'] = (string) $chain['district_id'];
                $address['place_id'] = (string) $chain['place_id'];
                $address['place_kind'] = (string) $chain['place_kind'];
                $address['city'] = (string) $chain['city'];
                $address['district'] = (string) $chain['district'];
                $address['neighborhood'] = (string) $chain['neighborhood'];
            }
            $address['line'] = self::line((string) ($input['invoice_line'] ?? ''), 10, 180) ?? '';
            $postal = self::postal((string) ($input['invoice_postal'] ?? ''));
            if ($chain === null || $address['line'] === '') {
                $errors[] = 'Fatura adresinin il, ilçe, mahalle veya köy ve açık adresini seç.';
            }
            if ($postal === null) {
                $errors[] = 'Fatura posta kodu 5 rakam olmalı.';
            } else {
                $address['postal'] = $postal;
            }
        } elseif (!$shippingReady && !$combined) {
            $errors[] = 'Fatura adresi kargo adresiyle aynı olacaksa önce kargo adresini kaydet.';
        }
        $blank['kind'] = $kind;
        $blank['title'] = $title;
        $blank['tax_office'] = $kind === 'kurumsal' ? $office : '';
        $blank['tax_no'] = $tax;
        $blank['same'] = $same;
        foreach ($address as $key => $value) {
            $blank[$key] = $value;
        }

        return ['errors' => $errors, 'invoice' => $blank];
    }

    /** @param array<string, mixed> $profile
     *  @return list<string>
     */
    private static function storeProfile(PDO $pdo, int $id, array $profile): array
    {
        $update = $pdo->prepare('UPDATE members SET profile = ? WHERE id = ?');
        $update->execute([encode_value($profile), $id]);
        self::refresh($id);

        return [];
    }

    private static function refresh(int $id): void
    {
        self::$loaded = false;
        self::$member = null;
        self::find($id);
        self::$member = self::find($id);
        self::$loaded = true;
    }

    /** @param array<string, mixed> $member */
    private static function confirmPassword(PDO $pdo, array $member, string $password): ?string
    {
        if (strlen($password) < 1 || strlen($password) > 128) {
            return 'Bu değişiklik için mevcut şifreni yaz.';
        }
        $stmt = $pdo->prepare('SELECT password_hash FROM members WHERE id = ?');
        $stmt->execute([(int) $member['id']]);
        $hash = $stmt->fetchColumn();
        if (!is_string($hash) || !password_verify($password, $hash)) {
            self::pause();

            return 'Mevcut şifre hatalı.';
        }

        return null;
    }

    private static function establish(int $id): void
    {
        self::boot(true);
        session_regenerate_id(true);
        $_SESSION['member_id'] = $id;
        $_SESSION['member_issued'] = time();
        $_SESSION['member_seen'] = time();
        $_SESSION['member_ua'] = self::uaHash();
        $_SESSION['member_csrf'] = bin2hex(random_bytes(32));
        self::$loaded = false;
        self::$member = null;
    }

    private static function expire(): void
    {
        $id = (int) ($_SESSION['member_id'] ?? 0);
        if ($id < 1) {
            return;
        }
        $issued = (int) ($_SESSION['member_issued'] ?? 0);
        $seen = (int) ($_SESSION['member_seen'] ?? 0);
        $ua = (string) ($_SESSION['member_ua'] ?? '');
        $now = time();
        $expired = $issued < 1
            || $seen < 1
            || ($now - $seen) > self::IDLE_SECONDS
            || ($now - $issued) > self::ABSOLUTE_SECONDS
            || !hash_equals($ua, self::uaHash());
        if ($expired) {
            unset($_SESSION['member_id'], $_SESSION['member_issued'], $_SESSION['member_seen'], $_SESSION['member_ua']);
        }
    }

    private static function locked(): bool
    {
        $until = (int) ($_SESSION['member_lock_until'] ?? 0);

        return $until > time();
    }

    private static function fail(): void
    {
        $count = (int) ($_SESSION['member_fail'] ?? 0) + 1;
        $_SESSION['member_fail'] = $count;
        if ($count >= self::LOCK_LIMIT) {
            $_SESSION['member_lock_until'] = time() + self::LOCK_SECONDS;
            $_SESSION['member_fail'] = 0;
        }
    }

    private static function clearFailures(): void
    {
        unset($_SESSION['member_fail'], $_SESSION['member_lock_until']);
    }

    private static function registerLimited(): bool
    {
        $count = (int) ($_SESSION['member_registers'] ?? 0);

        return $count >= 5;
    }

    private static function noteRegister(): void
    {
        $_SESSION['member_registers'] = (int) ($_SESSION['member_registers'] ?? 0) + 1;
    }

    private static function personName(string $value): ?string
    {
        $value = self::squash($value);
        $length = self::length($value);
        if ($length < 2 || $length > 60 || preg_match('/^[\p{L}][\p{L}\'’. -]{1,59}$/u', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function placeName(string $value): ?string
    {
        $value = self::squash($value);
        if ($value === '') {
            return '';
        }
        $length = self::length($value);
        if ($length < 2 || $length > 40 || preg_match('/^[\p{L}][\p{L} .\'-]{1,39}$/u', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function line(string $value, int $min, int $max): ?string
    {
        $value = self::squash($value);
        $length = self::length($value);
        if ($length < $min || $length > $max) {
            return null;
        }

        return $value;
    }

    private static function postal(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{5}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function email(string $value): ?string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        if ($value === '' || strlen($value) > 190 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $value;
    }

    private static function phone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '5')) {
            $digits = '0' . $digits;
        }
        if (preg_match('/^0\d{10}$/', $digits) !== 1) {
            return null;
        }

        return $digits;
    }

    private static function tckn(string $value): bool
    {
        if (preg_match('/^[1-9]\d{10}$/', $value) !== 1) {
            return false;
        }
        $digits = array_map(intval(...), str_split($value));
        $odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $even = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        $tenth = (($odd * 7) - $even) % 10;
        if ($tenth < 0) {
            $tenth += 10;
        }
        if ($digits[9] !== $tenth) {
            return false;
        }
        $sum = 0;
        for ($index = 0; $index < 10; $index++) {
            $sum += $digits[$index];
        }

        return $digits[10] === ($sum % 10);
    }

    private static function passwordError(string $password, string $email): ?string
    {
        if (strlen($password) < 8 || strlen($password) > 128) {
            return 'Şifre 8 ile 128 karakter arasında olmalı.';
        }
        if (str_contains($password, "\0")) {
            return 'Şifre geçersiz karakter içeriyor.';
        }
        if (!preg_match('/\p{L}/u', $password) || !preg_match('/\p{N}/u', $password)) {
            return 'Şifre en az bir harf ve bir rakam içermeli.';
        }
        if ($email !== '' && strtolower($email) === strtolower($password)) {
            return 'Şifre e-posta adresinle aynı olamaz.';
        }

        return null;
    }

    /** @return array{memory_cost:int,time_cost:int,threads:int} */
    private static function hashOptions(): array
    {
        return ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1];
    }

    /** @return array{shipping:array<string, string>, invoice:array<string, mixed>} */
    private static function blankProfile(): array
    {
        return [
            'shipping' => [
                'full_name' => '',
                'phone' => '',
                'city_id' => '',
                'district_id' => '',
                'place_id' => '',
                'place_kind' => '',
                'city' => '',
                'district' => '',
                'neighborhood' => '',
                'line' => '',
                'postal' => '',
                'note' => '',
            ],
            'invoice' => [
                'kind' => 'bireysel',
                'title' => '',
                'tax_office' => '',
                'tax_no' => '',
                'same' => true,
                'city_id' => '',
                'district_id' => '',
                'place_id' => '',
                'place_kind' => '',
                'city' => '',
                'district' => '',
                'neighborhood' => '',
                'line' => '',
                'postal' => '',
            ],
        ];
    }

    /** @return array{shipping:array<string, string>, invoice:array<string, mixed>} */
    private static function profile(mixed $raw): array
    {
        $blank = self::blankProfile();
        if (is_string($raw)) {
            $raw = decode_value($raw);
        }
        if (!is_array($raw)) {
            return $blank;
        }
        foreach (['shipping', 'invoice'] as $group) {
            $source = is_array($raw[$group] ?? null) ? $raw[$group] : [];
            foreach ($blank[$group] as $key => $value) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }
                if ($key === 'same') {
                    $blank[$group][$key] = !empty($source[$key]);
                    continue;
                }
                $blank[$group][$key] = self::clip((string) $source[$key], 180);
            }
        }
        $kind = (string) $blank['invoice']['kind'];
        $blank['invoice']['kind'] = in_array($kind, ['bireysel', 'kurumsal'], true) ? $kind : 'bireysel';

        return $blank;
    }

    private static function squash(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return str_replace(["\r", "\n", '<', '>'], '', $value);
    }

    private static function clip(string $value, int $max): string
    {
        $value = self::squash($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function pause(): void
    {
        usleep(400000);
    }

    private static function uaHash(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    private static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(self::NAME);
        $path = root_path();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $path === '' ? '/' : $path,
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }
}
