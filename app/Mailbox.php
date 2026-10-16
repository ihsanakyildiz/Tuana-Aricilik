<?php

declare(strict_types=1);

final class Mailbox
{
    /** @return array{host:string,port:int,encryption:string,username:string,password:string,from_email:string,from_name:string,to_email:string} */
    public static function config(): array
    {
        $stored = setting('smtp', []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $encryption = (string) ($stored['encryption'] ?? 'tls');
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }
        $port = (int) ($stored['port'] ?? ($encryption === 'ssl' ? 465 : 587));
        if ($port < 1 || $port > 65535) {
            $port = 587;
        }

        return [
            'host' => (string) ($stored['host'] ?? ''),
            'port' => $port,
            'encryption' => $encryption,
            'username' => (string) ($stored['username'] ?? ''),
            'password' => (string) ($stored['password'] ?? ''),
            'from_email' => (string) ($stored['from_email'] ?? ''),
            'from_name' => (string) ($stored['from_name'] ?? ''),
            'to_email' => (string) ($stored['to_email'] ?? ''),
        ];
    }

    public static function ready(): bool
    {
        $config = self::config();

        return self::host($config['host']) !== null
            && self::email($config['from_email']) !== null
            && self::email($config['to_email']) !== null;
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function save(array $input): array
    {
        $current = self::config();
        $encryption = (string) ($input['encryption'] ?? 'tls');
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }
        $port = (int) ($input['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            $port = $encryption === 'ssl' ? 465 : 587;
        }
        $host = self::host((string) ($input['host'] ?? ''));
        $from = self::email((string) ($input['from_email'] ?? ''));
        $to = self::email((string) ($input['to_email'] ?? ''));
        $username = self::plain((string) ($input['username'] ?? ''), 120);
        $fromName = self::plain((string) ($input['from_name'] ?? ''), 80);
        $password = (string) ($input['password'] ?? '');
        if (str_contains($password, "\0") || strlen($password) > 200) {
            return ['SMTP şifresi geçersiz.'];
        }
        $errors = [];
        if ($host === null) {
            $errors[] = 'SMTP sunucusunu yaz.';
        }
        if ($from === null) {
            $errors[] = 'Gönderen e-posta adresini yaz.';
        }
        if ($to === null) {
            $errors[] = 'Bildirimlerin gideceği e-posta adresini yaz.';
        }
        if ($errors !== []) {
            return $errors;
        }
        if ($password === '') {
            $password = $current['password'];
        }
        save_setting('smtp', [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'from_email' => $from,
            'from_name' => $fromName !== '' ? $fromName : 'Tuana Arıcılık',
            'to_email' => $to,
        ]);

        return [];
    }

    /** @param array<string, mixed> $input
     *  @return array{ok:bool,error:string,to:string}
     */
    public static function trial(array $input): array
    {
        $draft = self::draft($input);
        if ($draft['config'] === null) {
            return ['ok' => false, 'error' => $draft['error'], 'to' => ''];
        }
        $sent = self::deliver(
            $draft['config'],
            $draft['to'],
            'Deneme bildirimi',
            "Bu bir deneme e-postasıdır.\nSMTP ayarların çalışıyor."
        );

        return ['ok' => $sent['ok'], 'error' => $sent['error'], 'to' => $sent['ok'] ? $draft['to'] : ''];
    }

    /** @return array{ok:bool,error:string} */
    public static function send(string $to, string $subject, string $body): array
    {
        if (!self::ready()) {
            return ['ok' => false, 'error' => 'SMTP ayarları henüz tamamlanmadı.'];
        }

        return self::deliver(self::config(), $to, $subject, $body);
    }

    /** @param array{host:string,port:int,encryption:string,username:string,password:string,from_email:string,from_name:string,to_email:string} $config
     *  @return array{ok:bool,error:string}
     */
    private static function deliver(array $config, string $to, string $subject, string $body): array
    {
        $failed = ['ok' => false, 'error' => 'E-posta gönderilemedi.'];
        $recipient = self::email($to);
        $subject = self::plain($subject, 160);
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        if ($recipient === null || $subject === '' || trim($body) === '' || strlen($body) > 8000) {
            return $failed;
        }
        if (self::host($config['host']) === null || self::email($config['from_email']) === null) {
            return ['ok' => false, 'error' => 'SMTP ayarları henüz tamamlanmadı.'];
        }
        $remote = ($config['encryption'] === 'ssl' ? 'ssl://' : 'tcp://') . $config['host'] . ':' . $config['port'];
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);
        $errno = 0;
        $error = '';
        $socket = stream_socket_client($remote, $errno, $error, 15, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            return ['ok' => false, 'error' => 'SMTP sunucusuna bağlanılamadı.'];
        }
        stream_set_timeout($socket, 15);
        $hello = self::hello();
        $fail = self::expect($socket, [220]);
        if ($fail === null) {
            $fail = self::command($socket, 'EHLO ' . $hello, [250]);
        }
        if ($fail === null && $config['encryption'] === 'tls') {
            $fail = self::command($socket, 'STARTTLS', [220]);
            if ($fail === null) {
                $started = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($started !== true) {
                    $fail = 'Şifreli bağlantı kurulamadı.';
                } else {
                    $fail = self::command($socket, 'EHLO ' . $hello, [250]);
                }
            }
        }
        if ($fail === null && $config['username'] !== '') {
            $fail = self::command($socket, 'AUTH LOGIN', [334]);
            if ($fail === null) {
                $fail = self::command($socket, base64_encode($config['username']), [334]);
            }
            if ($fail === null) {
                $fail = self::command($socket, base64_encode($config['password']), [235]);
            }
        }
        if ($fail === null) {
            $fail = self::command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);
        }
        if ($fail === null) {
            $fail = self::command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        }
        if ($fail === null) {
            $fail = self::command($socket, 'DATA', [354]);
        }
        if ($fail === null) {
            $message = self::message($config['from_email'], $config['from_name'], $recipient, $subject, $body);
            $fail = self::command($socket, $message . "\r\n.", [250]);
        }
        self::command($socket, 'QUIT', [221]);
        fclose($socket);
        if ($fail !== null) {
            return ['ok' => false, 'error' => $fail];
        }

        return ['ok' => true, 'error' => ''];
    }

    /** @param array<string, mixed> $input
     *  @return array{config:array{host:string,port:int,encryption:string,username:string,password:string,from_email:string,from_name:string,to_email:string}|null,to:string,error:string}
     */
    private static function draft(array $input): array
    {
        $empty = ['config' => null, 'to' => '', 'error' => ''];
        $current = self::config();
        $encryption = (string) ($input['encryption'] ?? 'tls');
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }
        $port = (int) ($input['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            $port = $encryption === 'ssl' ? 465 : 587;
        }
        $host = self::host((string) ($input['host'] ?? ''));
        $from = self::email((string) ($input['from_email'] ?? ''));
        $inbox = self::email((string) ($input['to_email'] ?? ''));
        $username = self::plain((string) ($input['username'] ?? ''), 120);
        $fromName = self::plain((string) ($input['from_name'] ?? ''), 80);
        $password = (string) ($input['password'] ?? '');
        if (str_contains($password, "\0") || strlen($password) > 200) {
            $empty['error'] = 'SMTP şifresi geçersiz.';

            return $empty;
        }
        if ($password === '') {
            $password = $current['password'];
        }
        $probe = trim((string) ($input['test_to'] ?? ''));
        $to = $probe === '' ? $inbox : self::email($probe);
        if ($host === null) {
            $empty['error'] = 'SMTP sunucusunu yaz.';

            return $empty;
        }
        if ($from === null) {
            $empty['error'] = 'Gönderen e-posta adresini yaz.';

            return $empty;
        }
        if ($inbox === null) {
            $empty['error'] = 'Bildirimlerin gideceği e-posta adresini yaz.';

            return $empty;
        }
        if ($to === null) {
            $empty['error'] = 'Deneme adresini kontrol et.';

            return $empty;
        }
        if ($username !== '' && $password === '') {
            $empty['error'] = 'SMTP şifresini yaz.';

            return $empty;
        }

        return [
            'config' => [
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => $password,
                'from_email' => $from,
                'from_name' => $fromName !== '' ? $fromName : 'Tuana Arıcılık',
                'to_email' => $inbox,
            ],
            'to' => $to,
            'error' => '',
        ];
    }

    private static function hello(): string
    {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $host = strtolower(trim($host));
        if (preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            return 'localhost';
        }

        return $host;
    }

    /** @param resource $socket
     *  @param list<int> $ok
     */
    private static function command($socket, string $command, array $ok): ?string
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            return 'SMTP sunucusu yanıt vermedi.';
        }

        return self::expect($socket, $ok);
    }

    /** @param resource $socket
     *  @param list<int> $ok
     */
    private static function expect($socket, array $ok): ?string
    {
        $text = '';
        while (($line = fgets($socket, 515)) !== false) {
            $text .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($text, 0, 3);
        if (in_array($code, $ok, true)) {
            return null;
        }
        $reason = trim(preg_replace('/^\d{3}[ -]?/m', '', $text) ?? '');
        if ($reason === '') {
            return 'SMTP sunucusu isteği kabul etmedi.';
        }

        return self::plain($reason, 180);
    }

    /** @param array{from_email:string,from_name:string} $from */
    private static function message(string $fromEmail, string $fromName, string $to, string $subject, string $body): string
    {
        $encodedName = '=?UTF-8?B?' . base64_encode($fromName !== '' ? $fromName : 'Tuana Arıcılık') . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $lines = explode("\n", $body);
        $safe = [];
        foreach ($lines as $line) {
            $safe[] = str_starts_with($line, '.') ? '.' . $line : $line;
        }
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedName . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $safe);
    }

    private static function host(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === 'localhost') {
            return $value;
        }
        if ($value === '' || strlen($value) > 120 || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function email(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) > 120 || preg_match('/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function plain(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', str_replace(["\r", "\n", "\0"], ' ', $value)) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }
}
