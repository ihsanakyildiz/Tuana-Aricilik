<?php

declare(strict_types=1);

final class Auth
{
    private const IDLE_SECONDS = 1800;
    private const ABSOLUTE_SECONDS = 28800;
    private const REMEMBER_SECONDS = 1209600;
    private const LOCK_SECONDS = 900;
    private const LOCK_LIMIT = 5;
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=3,p=1$eC5kUkNHWjUyR3lHcDFqeA$xJP/uXBv5mYVgxrbtdAawSmwXJC+h/gEHj4amQimi+s';

    private static bool $booted = false;

    private static bool $embed = false;

    private static bool $adminLoaded = false;

    /** @var array{id:int,username:string,must_change:bool}|null */
    private static ?array $admin = null;

    public static function allowEmbed(): void
    {
        self::$embed = true;
    }

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::sendHeaders();
        self::startSession();
        self::expireStaleSession();
        if ((int) ($_SESSION['admin_id'] ?? 0) < 1) {
            self::restoreRemember();
        }
        if ((int) ($_SESSION['admin_id'] ?? 0) > 0) {
            $_SESSION['last_seen'] = time();
        }
    }

    public static function check(): bool
    {
        return self::admin() !== null;
    }

    public static function username(): string
    {
        return self::admin()['username'] ?? '';
    }

    public static function mustChange(): bool
    {
        $admin = self::admin();

        return $admin !== null && $admin['must_change'];
    }

    public static function requireLogin(bool $allowPasswordChange = false): void
    {
        self::boot();
        if (!self::check()) {
            self::redirect('admin/giris.php');
        }
        if (self::mustChange() && !$allowPasswordChange) {
            self::redirect('admin/hesap.php');
        }
    }

    public static function redirect(string $path): void
    {
        $target = root_path() . '/' . ltrim($path, '/');
        header('Location: ' . $target, true, 303);
        exit;
    }

    public static function csrfToken(): string
    {
        self::boot();
        $token = $_SESSION['csrf'] ?? '';
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf'] = $token;
        }

        return $token;
    }

    public static function checkCsrf(?string $token, bool $rotate = true): bool
    {
        self::boot();
        $known = $_SESSION['csrf'] ?? '';
        $valid = is_string($token)
            && is_string($known)
            && $known !== ''
            && hash_equals($known, $token);
        if ($rotate) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $valid;
    }

    public static function flash(string $type, string $text): void
    {
        self::boot();
        $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    }

    /** @return array{type:string,text:string}|null */
    public static function pullFlash(): ?array
    {
        self::boot();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        if (!is_array($flash) || !isset($flash['type'], $flash['text'])) {
            return null;
        }

        return [
            'type' => (string) $flash['type'],
            'text' => (string) $flash['text'],
        ];
    }

    public static function attempt(string $username, string $password, bool $remember): string
    {
        self::boot();
        $username = self::normalizeUsername($username);
        $pdo = db_pdo();
        if (!$pdo instanceof PDO || $username === '' || strlen($password) < 1 || strlen($password) > 128) {
            self::pause();

            return 'invalid';
        }

        $ipHash = self::ipHash();
        self::pruneAttempts($pdo);
        if (self::isLocked($pdo, $ipHash, $username)) {
            self::pause();

            return 'locked';
        }

        $stmt = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        $hash = is_array($row) ? (string) $row['password_hash'] : self::DUMMY_HASH;
        $valid = password_verify($password, $hash);
        if (!is_array($row) || !$valid) {
            self::recordAttempt($pdo, $ipHash, $username);
            self::pause();

            return 'invalid';
        }

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID, self::hashOptions())) {
            $updated = password_hash($password, PASSWORD_ARGON2ID, self::hashOptions());
            if (is_string($updated)) {
                $save = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
                $save->execute([$updated, (int) $row['id']]);
            }
        }

        self::clearAttempts($pdo, $ipHash, $username);
        self::establish((int) $row['id']);
        if ($remember) {
            self::storeRemember((int) $row['id']);
        } else {
            self::forgetRememberCookie();
        }

        return 'ok';
    }

    /** @param array<string, mixed> $input */
    public static function updateCredentials(array $input): true|array
    {
        self::boot();
        $admin = self::admin();
        $pdo = db_pdo();
        if ($admin === null || !$pdo instanceof PDO) {
            return ['Oturum bulunamadı. Yeniden giriş yap.'];
        }

        $current = (string) ($input['current_password'] ?? '');
        $nextName = self::normalizeUsername((string) ($input['username'] ?? ''));
        $nextPassword = (string) ($input['new_password'] ?? '');
        $confirm = (string) ($input['new_password_confirm'] ?? '');
        $errors = [];

        if ($nextName === null) {
            $errors[] = 'Kullanıcı adı 3-32 karakter olmalı ve harf, rakam, nokta, alt çizgi veya kısa çizgi içerebilir.';
        }
        if (strlen($current) < 1 || strlen($current) > 128) {
            $errors[] = 'Mevcut şifreyi yaz.';
        }
        if ($nextPassword !== '' || $confirm !== '' || self::mustChange()) {
            $passwordError = self::passwordError($nextPassword, $nextName ?? '');
            if ($passwordError !== null) {
                $errors[] = $passwordError;
            } elseif ($nextPassword !== $confirm) {
                $errors[] = 'Yeni şifreler eşleşmiyor.';
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $stmt->execute([$admin['id']]);
        $hash = $stmt->fetchColumn();
        if (!is_string($hash) || !password_verify($current, $hash)) {
            self::pause();

            return ['Mevcut şifre hatalı.'];
        }

        $username = $nextName ?? $admin['username'];
        $changingPassword = $nextPassword !== '';
        if (!$changingPassword && $username === $admin['username']) {
            return ['Değişen bir bilgi yok.'];
        }
        if ($changingPassword && password_verify($nextPassword, $hash)) {
            return ['Yeni şifre mevcut şifreden farklı olmalı.'];
        }

        $newHash = $changingPassword
            ? password_hash($nextPassword, PASSWORD_ARGON2ID, self::hashOptions())
            : $hash;
        if (!is_string($newHash)) {
            return ['Şifre kaydedilemedi. Yeniden dene.'];
        }

        $pdo->beginTransaction();
        $update = $pdo->prepare(
            'UPDATE admins SET username = ?, password_hash = ?, must_change = 0 WHERE id = ?'
        );
        $update->execute([$username, $newHash, $admin['id']]);
        $forget = $pdo->prepare('DELETE FROM admin_remember WHERE admin_id = ?');
        $forget->execute([$admin['id']]);
        $pdo->commit();

        self::forgetRememberCookie();
        self::$adminLoaded = false;
        self::$admin = null;
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['issued_at'] = time();
        $_SESSION['last_seen'] = time();
        $_SESSION['ua'] = self::uaHash();

        return true;
    }

    public static function logout(): void
    {
        self::boot();
        $adminId = (int) (self::admin()['id'] ?? 0);
        $pdo = db_pdo();
        if ($adminId > 0 && $pdo instanceof PDO) {
            $forget = $pdo->prepare('DELETE FROM admin_remember WHERE admin_id = ?');
            $forget->execute([$adminId]);
        }
        self::forgetRememberCookie();
        self::$admin = null;
        self::$adminLoaded = true;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', self::cookieOptions($params['path'], time() - 42000));
        }
        session_destroy();
        self::$booted = false;
    }

    /** @return array{id:int,username:string,must_change:bool}|null */
    private static function admin(): ?array
    {
        if (self::$adminLoaded) {
            return self::$admin;
        }
        self::$adminLoaded = true;
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        $pdo = db_pdo();
        if ($id < 1 || !$pdo instanceof PDO) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id, username, must_change FROM admins WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            unset($_SESSION['admin_id']);

            return null;
        }

        self::$admin = [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'must_change' => (int) $row['must_change'] === 1,
        ];

        return self::$admin;
    }

    private static function establish(int $adminId): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $adminId;
        $_SESSION['issued_at'] = time();
        $_SESSION['last_seen'] = time();
        $_SESSION['ua'] = self::uaHash();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        self::$adminLoaded = false;
        self::$admin = null;
    }

    private static function expireStaleSession(): void
    {
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        if ($id < 1) {
            return;
        }
        $issued = (int) ($_SESSION['issued_at'] ?? 0);
        $seen = (int) ($_SESSION['last_seen'] ?? 0);
        $ua = (string) ($_SESSION['ua'] ?? '');
        $now = time();
        $expired = $issued < 1
            || $seen < 1
            || ($now - $seen) > self::IDLE_SECONDS
            || ($now - $issued) > self::ABSOLUTE_SECONDS
            || !hash_equals($ua, self::uaHash());
        if (!$expired) {
            return;
        }
        unset($_SESSION['admin_id'], $_SESSION['issued_at'], $_SESSION['last_seen'], $_SESSION['ua']);
    }

    private static function restoreRemember(): void
    {
        $cookie = (string) ($_COOKIE['tuana_admin_remember'] ?? '');
        $parts = explode(':', $cookie, 2);
        if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{24}$/', $parts[0])) {
            return;
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return;
        }

        $selector = $parts[0];
        $validator = $parts[1];
        $stmt = $pdo->prepare(
            'SELECT admin_id, validator_hash, ua_hash, expires_at FROM admin_remember WHERE selector = ?'
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            self::forgetRememberCookie();

            return;
        }

        $expired = strtotime((string) $row['expires_at']) < time();
        $uaOk = hash_equals((string) $row['ua_hash'], self::uaHash());
        $secretOk = hash_equals((string) $row['validator_hash'], hash('sha256', $validator));
        if ($expired || !$uaOk || !$secretOk) {
            if (!$secretOk && !$expired) {
                $revoke = $pdo->prepare('DELETE FROM admin_remember WHERE admin_id = ?');
                $revoke->execute([(int) $row['admin_id']]);
            } else {
                $drop = $pdo->prepare('DELETE FROM admin_remember WHERE selector = ?');
                $drop->execute([$selector]);
            }
            self::forgetRememberCookie();

            return;
        }

        $nextValidator = bin2hex(random_bytes(32));
        $update = $pdo->prepare(
            'UPDATE admin_remember SET validator_hash = ?, expires_at = ? WHERE selector = ?'
        );
        $update->execute([
            hash('sha256', $nextValidator),
            date('Y-m-d H:i:s', time() + self::REMEMBER_SECONDS),
            $selector,
        ]);
        self::rememberCookie($selector, $nextValidator);
        self::establish((int) $row['admin_id']);
    }

    private static function storeRemember(int $adminId): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return;
        }
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $insert = $pdo->prepare(
            'INSERT INTO admin_remember (selector, admin_id, validator_hash, ua_hash, expires_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $selector,
            $adminId,
            hash('sha256', $validator),
            self::uaHash(),
            date('Y-m-d H:i:s', time() + self::REMEMBER_SECONDS),
        ]);
        self::rememberCookie($selector, $validator);
    }

    private static function rememberCookie(string $selector, string $validator): void
    {
        setcookie(
            'tuana_admin_remember',
            $selector . ':' . $validator,
            self::cookieOptions(self::cookiePath(), time() + self::REMEMBER_SECONDS)
        );
    }

    private static function forgetRememberCookie(): void
    {
        setcookie('tuana_admin_remember', '', self::cookieOptions(self::cookiePath(), time() - 42000));
    }

    private static function isLocked(PDO $pdo, string $ipHash, string $username): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM admin_attempts
             WHERE created_at >= ? AND (ip_hash = ? OR username = ?)'
        );
        $stmt->execute([date('Y-m-d H:i:s', time() - self::LOCK_SECONDS), $ipHash, $username]);

        return (int) $stmt->fetchColumn() >= self::LOCK_LIMIT;
    }

    private static function recordAttempt(PDO $pdo, string $ipHash, string $username): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO admin_attempts (ip_hash, username, created_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$ipHash, $username, date('Y-m-d H:i:s')]);
    }

    private static function clearAttempts(PDO $pdo, string $ipHash, string $username): void
    {
        $stmt = $pdo->prepare('DELETE FROM admin_attempts WHERE ip_hash = ? OR username = ?');
        $stmt->execute([$ipHash, $username]);
    }

    private static function pruneAttempts(PDO $pdo): void
    {
        $stmt = $pdo->prepare('DELETE FROM admin_attempts WHERE created_at < ?');
        $stmt->execute([date('Y-m-d H:i:s', time() - 86400)]);
        $tokens = $pdo->prepare('DELETE FROM admin_remember WHERE expires_at < ?');
        $tokens->execute([date('Y-m-d H:i:s')]);
    }

    private static function passwordError(string $password, string $username): ?string
    {
        if (strlen($password) < 10 || strlen($password) > 128) {
            return 'Yeni şifre 10 ile 128 karakter arasında olmalı.';
        }
        if (str_contains($password, "\0")) {
            return 'Şifre geçersiz karakter içeriyor.';
        }
        if (!preg_match('/\p{L}/u', $password) || !preg_match('/\p{N}/u', $password)) {
            return 'Yeni şifre en az bir harf ve bir rakam içermeli.';
        }
        if ($username !== '' && strtolower($username) === strtolower($password)) {
            return 'Şifre kullanıcı adıyla aynı olamaz.';
        }
        $denied = ['password', '123456789', '1234567890', 'admin123', 'admin1234', 'tuana123', 'tuana1234', 'qwerty123'];
        if (in_array(strtolower($password), $denied, true)) {
            return 'Bu şifre çok yaygın. Daha kendine özgü bir şifre seç.';
        }

        return null;
    }

    private static function normalizeUsername(string $username): ?string
    {
        $username = trim($username);
        if (function_exists('mb_strtolower')) {
            $username = mb_strtolower($username, 'UTF-8');
        } else {
            $username = strtolower($username);
        }
        if (!preg_match('/^[\p{L}][\p{L}\p{N}._-]{2,31}$/u', $username)) {
            return null;
        }

        return $username;
    }

    /** @return array{memory_cost:int,time_cost:int,threads:int} */
    private static function hashOptions(): array
    {
        return [
            'memory_cost' => 65536,
            'time_cost' => 3,
            'threads' => 1,
        ];
    }

    private static function pause(): void
    {
        usleep(400000);
    }

    private static function ipHash(): string
    {
        $config = app_config();
        $key = (string) ($config['auth_key'] ?? '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return hash('sha256', $key . '|' . $ip);
    }

    private static function uaHash(): string
    {
        return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('tuana_admin');
        $options = self::cookieOptions(self::cookiePath(), 0);
        $options['lifetime'] = 0;
        unset($options['expires']);
        session_set_cookie_params($options);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        if (!self::requestUsesAdminCookie() && !isset($_COOKIE[session_name()])) {
            ini_set('session.use_cookies', '0');
        }
        session_start();
    }

    private static function requestUsesAdminCookie(): bool
    {
        $path = self::cookiePath();
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($path === '' || $path === '/') {
            return true;
        }

        return $uri === $path || str_starts_with($uri, $path . '/');
    }

    private static function cookiePath(): string
    {
        $root = root_path();

        return ($root === '' ? '' : $root) . '/admin';
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    /** @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string} */
    private static function cookieOptions(string $path, int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => $path === '' ? '/' : $path,
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }

    private static function sendHeaders(): void
    {
        header(self::$embed ? 'X-Frame-Options: SAMEORIGIN' : 'X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        $ancestors = self::$embed ? "'self'" : "'none'";
        header(
            "Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; "
            . "script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors {$ancestors}"
        );
    }
}
