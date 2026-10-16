<?php

declare(strict_types=1);

require_once __DIR__ . '/Mailbox.php';
require_once __DIR__ . '/Notices.php';

final class ContactDesk
{
    /** @return array{title:string,lead:string,form_title:string,form_note:string,success:string,form_on:bool,map_url:string,map_label:string,show_email:bool,show_phone:bool,show_whatsapp:bool,show_address:bool,show_hours:bool,topics:list<string>,panel:bool,mail:bool} */
    public static function page(): array
    {
        $stored = setting('contact_page', []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $topics = [];
        $rawTopics = $stored['topics'] ?? [];
        if (is_array($rawTopics)) {
            foreach ($rawTopics as $topic) {
                $topic = self::line((string) $topic, 40);
                if ($topic !== '') {
                    $topics[] = $topic;
                }
            }
        }

        return [
            'title' => self::orDefault(self::line((string) ($stored['title'] ?? ''), 80), 'İletişim'),
            'lead' => self::line((string) ($stored['lead'] ?? ''), 400),
            'form_title' => self::orDefault(self::line((string) ($stored['form_title'] ?? ''), 80), 'Mesaj bırak'),
            'form_note' => self::line((string) ($stored['form_note'] ?? ''), 240),
            'success' => self::orDefault(self::line((string) ($stored['success'] ?? ''), 240), 'Mesajın alındı. En kısa sürede dönüş yapacağız.'),
            'form_on' => !array_key_exists('form_on', $stored) || !empty($stored['form_on']),
            'map_url' => self::mapUrl((string) ($stored['map_url'] ?? '')),
            'map_label' => self::orDefault(self::line((string) ($stored['map_label'] ?? ''), 40), 'Haritada aç'),
            'show_email' => !array_key_exists('show_email', $stored) || !empty($stored['show_email']),
            'show_phone' => !array_key_exists('show_phone', $stored) || !empty($stored['show_phone']),
            'show_whatsapp' => !array_key_exists('show_whatsapp', $stored) || !empty($stored['show_whatsapp']),
            'show_address' => !array_key_exists('show_address', $stored) || !empty($stored['show_address']),
            'show_hours' => !array_key_exists('show_hours', $stored) || !empty($stored['show_hours']),
            'topics' => array_slice($topics, 0, 8),
            'panel' => !array_key_exists('panel', $stored) || !empty($stored['panel']),
            'mail' => !array_key_exists('mail', $stored) || !empty($stored['mail']),
        ];
    }

    /** @return array{email:string,phone:string,whatsapp:string,address:string,hours:string} */
    public static function details(): array
    {
        return [
            'email' => self::line((string) setting('email', ''), 120),
            'phone' => self::line((string) setting('phone', ''), 40),
            'whatsapp' => self::line((string) setting('whatsapp', ''), 40),
            'address' => self::line((string) setting('address', ''), 180),
            'hours' => self::line((string) setting('hours', ''), 80),
        ];
    }

    /** @param array<string, mixed> $input
     *  @return list<string>
     */
    public static function save(array $input): array
    {
        $email = strtolower(self::line((string) ($input['email'] ?? ''), 120));
        $phone = self::phone((string) ($input['phone'] ?? ''));
        $whatsapp = self::phone((string) ($input['whatsapp'] ?? ''));
        $map = trim((string) ($input['map_url'] ?? ''));
        $errors = [];
        if ($email !== '' && preg_match('/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/', $email) !== 1) {
            $errors[] = 'E-posta adresini kontrol et.';
        }
        if ($phone === null || $whatsapp === null) {
            $errors[] = 'Telefon ve WhatsApp yalnızca rakam, boşluk ve + içerebilir.';
        }
        if ($map !== '' && self::mapUrl($map) === '') {
            $errors[] = 'Harita adresi https ile başlamalı.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $topics = [];
        $rawTopics = preg_split('/\r\n|\r|\n/', (string) ($input['topics'] ?? '')) ?: [];
        foreach ($rawTopics as $topic) {
            $topic = self::line($topic, 40);
            if ($topic !== '' && !in_array($topic, $topics, true)) {
                $topics[] = $topic;
            }
            if (count($topics) === 8) {
                break;
            }
        }

        save_setting('email', $email);
        save_setting('phone', $phone ?? '');
        save_setting('whatsapp', $whatsapp ?? '');
        save_setting('address', self::line((string) ($input['address'] ?? ''), 180));
        save_setting('hours', self::line((string) ($input['hours'] ?? ''), 80));
        save_setting('contact_page', [
            'title' => self::orDefault(self::line((string) ($input['title'] ?? ''), 80), 'İletişim'),
            'lead' => self::line((string) ($input['lead'] ?? ''), 400),
            'form_title' => self::orDefault(self::line((string) ($input['form_title'] ?? ''), 80), 'Mesaj bırak'),
            'form_note' => self::line((string) ($input['form_note'] ?? ''), 240),
            'success' => self::orDefault(self::line((string) ($input['success'] ?? ''), 240), 'Mesajın alındı. En kısa sürede dönüş yapacağız.'),
            'form_on' => isset($input['form_on']),
            'map_url' => self::mapUrl($map),
            'map_label' => self::orDefault(self::line((string) ($input['map_label'] ?? ''), 40), 'Haritada aç'),
            'show_email' => isset($input['show_email']),
            'show_phone' => isset($input['show_phone']),
            'show_whatsapp' => isset($input['show_whatsapp']),
            'show_address' => isset($input['show_address']),
            'show_hours' => isset($input['show_hours']),
            'topics' => $topics,
            'panel' => isset($input['panel']),
            'mail' => isset($input['mail']),
        ]);

        return [];
    }

    /** @param array<string, mixed> $input
     *  @return array{ok:bool,errors:list<string>}
     */
    public static function receive(array $input): array
    {
        $page = self::page();
        if (!$page['form_on']) {
            return ['ok' => false, 'errors' => ['İletişim formu şu anda kapalı.']];
        }
        if (trim((string) ($input['company'] ?? '')) !== '') {
            return ['ok' => true, 'errors' => []];
        }
        $name = self::line((string) ($input['name'] ?? ''), 80);
        $email = strtolower(self::line((string) ($input['email'] ?? ''), 120));
        $phone = self::phone((string) ($input['phone'] ?? ''));
        $subject = self::subject($page['topics'], (string) ($input['subject'] ?? ''));
        $body = self::body((string) ($input['body'] ?? ''));
        $errors = [];
        if (self::length($name) < 2) {
            $errors[] = 'Adını yaz.';
        }
        if (preg_match('/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/', $email) !== 1) {
            $errors[] = 'E-posta adresini kontrol et.';
        }
        if ($phone === null) {
            $errors[] = 'Telefon yalnızca rakam, boşluk ve + içerebilir.';
        }
        if ($subject === '') {
            $errors[] = 'Konuyu yaz.';
        }
        if (self::length($body) < 10) {
            $errors[] = 'Mesajını biraz daha açık yaz.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return ['ok' => false, 'errors' => ['Mesaj şu anda gönderilemiyor.']];
        }
        $hash = self::clientHash();
        $since = date('Y-m-d H:i:s', time() - 3600);
        $count = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE ip_hash = ? AND created_at > ?');
        $count->execute([$hash, $since]);
        if ((int) $count->fetchColumn() >= 5) {
            return ['ok' => false, 'errors' => ['Kısa süre içinde çok fazla mesaj gönderildi. Biraz sonra yeniden dene.']];
        }

        $insert = $pdo->prepare('INSERT INTO messages (name, email, phone, subject, body, ip_hash) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([$name, $email, $phone ?? '', $subject, $body, $hash]);
        $id = (int) $pdo->lastInsertId();
        self::prune($pdo);
        if ($page['panel']) {
            Notices::message($id, $name, $email, $subject);
        }
        if ($page['mail'] && Mailbox::ready()) {
            $text = "Yeni bir iletişim mesajı geldi.\n\nAd: " . $name
                . "\nE-posta: " . $email
                . "\nTelefon: " . ($phone ?? '')
                . "\nKonu: " . $subject
                . "\n\n" . $body
                . "\n\n" . absolute_url('/admin/iletisim.php?mesaj=' . $id);
            Mailbox::send(Mailbox::config()['to_email'], 'İletişim — ' . $subject, $text);
        }

        return ['ok' => true, 'errors' => []];
    }

    /** @return list<array{id:int,name:string,email:string,phone:string,subject:string,body:string,read:bool,created_at:string}> */
    public static function recent(int $limit): array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return [];
        }
        $limit = max(1, min(40, $limit));
        $stmt = $pdo->query(
            'SELECT id, name, email, phone, subject, body, is_read, created_at FROM messages ORDER BY id DESC LIMIT ' . $limit
        );
        if ($stmt === false) {
            return [];
        }
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = self::present($row);
        }

        return $rows;
    }

    /** @return array{id:int,name:string,email:string,phone:string,subject:string,body:string,read:bool,created_at:string}|null */
    public static function find(int $id): ?array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO || $id < 1) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id, name, email, phone, subject, body, is_read, created_at FROM messages WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? self::present($row) : null;
    }

    public static function markRead(int $id): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO || $id < 1) {
            return;
        }
        $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function markAllRead(): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return;
        }
        $pdo->exec('UPDATE messages SET is_read = 1 WHERE is_read = 0');
    }

    public static function delete(int $id): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO || $id < 1) {
            return;
        }
        $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function unreadCount(): int
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return 0;
        }
        $count = $pdo->query('SELECT COUNT(*) FROM messages WHERE is_read = 0');

        return $count === false ? 0 : (int) $count->fetchColumn();
    }

    /** @param array<string, mixed> $row
     *  @return array{id:int,name:string,email:string,phone:string,subject:string,body:string,read:bool,created_at:string}
     */
    private static function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'phone' => (string) $row['phone'],
            'subject' => (string) $row['subject'],
            'body' => (string) $row['body'],
            'read' => (int) $row['is_read'] === 1,
            'created_at' => (string) $row['created_at'],
        ];
    }

    private static function prune(PDO $pdo): void
    {
        $cut = $pdo->query('SELECT id FROM messages ORDER BY id DESC LIMIT 1 OFFSET 299');
        $oldest = $cut === false ? false : $cut->fetchColumn();
        if ($oldest !== false) {
            $drop = $pdo->prepare('DELETE FROM messages WHERE id < ?');
            $drop->execute([(int) $oldest]);
        }
    }

    /** @param list<string> $topics */
    private static function subject(array $topics, string $value): string
    {
        $value = self::line($value, 80);
        if ($topics === []) {
            return $value;
        }

        return in_array($value, $topics, true) ? $value : '';
    }

    private static function body(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", strip_tags($value));
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 2000, 'UTF-8');
        }

        return substr($value, 0, 2000);
    }

    private static function clientHash(): string
    {
        $config = app_config();
        $key = is_array($config) ? (string) ($config['auth_key'] ?? '') : '';

        return hash('sha256', $key . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    private static function mapUrl(string $value): string
    {
        $value = self::line($value, 300);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https://#i', $value) !== 1 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $value;
    }

    private static function phone(string $value): ?string
    {
        $value = self::line($value, 40);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^[0-9+\s().-]{7,40}$/', $value)) {
            return null;
        }

        return $value;
    }

    private static function line(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(["\r", "\n", "\0"], ' ', $value))) ?? '');
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }

    private static function orDefault(string $value, string $fallback): string
    {
        return $value !== '' ? $value : $fallback;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
