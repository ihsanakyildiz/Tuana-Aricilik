<?php

declare(strict_types=1);

final class Installer
{
    public static function ensure(PDO $pdo): void
    {
        self::schema($pdo);
        self::ensureAdmin($pdo);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM sections')->fetchColumn();
        if ($count === 0) {
            self::seed($pdo);
        } else {
            self::migrateLibrary($pdo);
            self::migrateArticleFields($pdo);
            self::migrateHomeAnchors($pdo);
        }

        self::migratePostCategories($pdo);
    }

    private static function schema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(80) NOT NULL,
                setting_value LONGTEXT NOT NULL,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sections (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                page_slug VARCHAR(64) NOT NULL,
                section_key VARCHAR(64) NOT NULL,
                section_type VARCHAR(64) NOT NULL,
                title VARCHAR(180) NOT NULL DEFAULT "",
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                content LONGTEXT NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_page_section (page_slug, section_key),
                KEY idx_page_order (page_slug, is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS blocks (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                block_key VARCHAR(64) NOT NULL,
                block_type VARCHAR(32) NOT NULL,
                title VARCHAR(180) NOT NULL DEFAULT "",
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                content LONGTEXT NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_block_key (block_key),
                KEY idx_block_type (block_type, is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admins (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(64) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                must_change TINYINT(1) NOT NULL DEFAULT 1,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_admin_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_hash CHAR(64) NOT NULL,
                username VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_attempt_ip (ip_hash, created_at),
                KEY idx_attempt_user (username, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS admin_remember (
                selector CHAR(32) NOT NULL,
                admin_id INT UNSIGNED NOT NULL,
                validator_hash CHAR(64) NOT NULL,
                ua_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                PRIMARY KEY (selector),
                KEY idx_remember_admin (admin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS members (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                first_name VARCHAR(80) NOT NULL,
                last_name VARCHAR(80) NOT NULL,
                phone VARCHAR(20) NOT NULL DEFAULT "",
                profile LONGTEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_member_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS locations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                parent_id INT UNSIGNED NOT NULL DEFAULT 0,
                kind VARCHAR(16) NOT NULL,
                name VARCHAR(80) NOT NULL,
                postal_code CHAR(5) NOT NULL DEFAULT "",
                source_key VARCHAR(40) NULL DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uq_location_source (source_key),
                KEY idx_location_children (parent_id, is_active, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS orders (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                order_no VARCHAR(24) NOT NULL,
                member_id INT UNSIGNED NOT NULL,
                status VARCHAR(24) NOT NULL,
                payment_method VARCHAR(16) NOT NULL,
                product_key VARCHAR(80) NOT NULL,
                product_title VARCHAR(160) NOT NULL,
                product_image VARCHAR(255) NOT NULL DEFAULT "",
                unit_price DECIMAL(12,2) NOT NULL,
                qty INT UNSIGNED NOT NULL,
                cargo_name VARCHAR(80) NOT NULL DEFAULT "",
                cargo_price DECIMAL(12,2) NOT NULL DEFAULT 0,
                total DECIMAL(12,2) NOT NULL,
                stock_held TINYINT(1) NOT NULL DEFAULT 0,
                shipping_json LONGTEXT NOT NULL,
                invoice_json LONGTEXT NOT NULL,
                paytr_note VARCHAR(180) NOT NULL DEFAULT \'\',
                paytr_collected DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                refunded DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                refund_json LONGTEXT NULL,
                geliver_json LONGTEXT NULL,
                paytr_json LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_order_no (order_no),
                KEY idx_order_member (member_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::ensureColumn($pdo, 'orders', 'paytr_note', 'VARCHAR(180) NOT NULL DEFAULT \'\'');
        self::ensureColumn($pdo, 'orders', 'paytr_collected', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
        self::ensureColumn($pdo, 'orders', 'refunded', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
        self::ensureColumn($pdo, 'orders', 'refund_json', 'LONGTEXT NULL');
        self::ensureColumn($pdo, 'orders', 'geliver_json', 'LONGTEXT NULL');
        self::ensureColumn($pdo, 'orders', 'paytr_json', 'LONGTEXT NULL');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS notices (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                kind VARCHAR(16) NOT NULL,
                title VARCHAR(160) NOT NULL,
                body VARCHAR(240) NOT NULL,
                href VARCHAR(180) NOT NULL DEFAULT "",
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_notice_new (is_read, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS messages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(80) NOT NULL,
                email VARCHAR(120) NOT NULL,
                phone VARCHAR(40) NOT NULL DEFAULT "",
                subject VARCHAR(80) NOT NULL,
                body VARCHAR(2000) NOT NULL,
                ip_hash CHAR(64) NOT NULL DEFAULT "",
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_message_new (is_read, id),
                KEY idx_message_ip (ip_hash, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $columns = ['paytr_note', 'paytr_collected', 'refunded', 'refund_json', 'geliver_json', 'paytr_json'];
        if (!in_array($table, ['orders'], true) || !in_array($column, $columns, true)) {
            return;
        }
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column));
        if ($stmt !== false && $stmt->fetch() !== false) {
            return;
        }
        $pdo->exec('ALTER TABLE `' . $table . '` ADD `' . $column . '` ' . $definition);
    }

    private static function ensureAdmin(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $password = self::initialPassword();
        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 3,
            'threads' => 1,
        ]);
        if (!is_string($hash)) {
            throw new RuntimeException('Yönetici şifresi oluşturulamadı.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO admins (username, password_hash, must_change) VALUES (?, ?, 1)'
        );
        $insert->execute(['admin', $hash]);

        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Kurulum klasörü oluşturulamadı.');
        }
        $written = file_put_contents($dir . '/initial-admin.txt', $password, LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('İlk yönetici şifresi kaydedilemedi.');
        }
    }

    private static function initialPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $length = strlen($alphabet);
        $parts = [];
        for ($group = 0; $group < 4; $group++) {
            $chunk = '';
            for ($index = 0; $index < 4; $index++) {
                $chunk .= $alphabet[random_int(0, $length - 1)];
            }
            $parts[] = $chunk;
        }
        $parts[0] = 'K7' . substr($parts[0], 2);

        return implode('-', $parts);
    }

    private static function seed(PDO $pdo): void
    {
        $data = load_seed();
        $pdo->beginTransaction();

        $setting = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)'
        );
        foreach ($data['settings'] as $key => $value) {
            $setting->execute([(string) $key, encode_value($value)]);
        }

        self::insertSections($pdo, $data['sections'] ?? []);
        self::insertBlocks($pdo, $data['blocks'] ?? []);
        $setting->execute(['library_ready', encode_value(true)]);

        $pdo->commit();
    }

    private static function migrateLibrary(PDO $pdo): void
    {
        $ready = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key = ?');
        $ready->execute(['library_ready']);
        if ($ready->fetchColumn() !== false) {
            return;
        }

        $data = load_seed();
        $pdo->beginTransaction();
        self::insertBlocks($pdo, $data['blocks'] ?? []);
        self::insertMissingSections($pdo, $data['sections'] ?? []);
        self::mergeContent($pdo, 'home', 'features', static function (array $content): array {
            $content['block_keys'] = ['arici-bali', 'ari-bahcesi', 'ari-urunleri'];
            unset($content['items']);

            return $content;
        });
        self::mergeContent($pdo, 'home', 'blog', static function (array $content): array {
            $content['source'] = 'posts';
            $content['limit'] = 4;
            $content['button'] = ['label' => 'Tüm Yazılar', 'url' => '/blog'];
            unset($content['items']);

            return $content;
        });
        self::putSetting($pdo, 'navigation', $data['settings']['navigation'] ?? []);
        self::putSetting($pdo, 'footer_columns', $data['settings']['footer_columns'] ?? []);
        self::putSetting($pdo, 'library_ready', true);
        $pdo->commit();
    }

    private static function migrateArticleFields(PDO $pdo): void
    {
        $ready = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key = ?');
        $ready->execute(['article_fields']);
        if ($ready->fetchColumn() !== false) {
            return;
        }

        $data = load_seed();
        $seedBlocks = [];
        foreach ($data['blocks'] ?? [] as $block) {
            if (is_array($block)) {
                $seedBlocks[(string) $block['block_key']] = $block;
            }
        }

        $select = $pdo->prepare('SELECT content FROM blocks WHERE block_key = ?');
        $update = $pdo->prepare('UPDATE blocks SET content = ? WHERE block_key = ?');
        $fields = ['author', 'time', 'category', 'category_key', 'tags', 'figure'];
        foreach ($seedBlocks as $key => $block) {
            if (($block['block_type'] ?? '') !== 'post') {
                continue;
            }
            $select->execute([$key]);
            $raw = $select->fetchColumn();
            if ($raw === false) {
                continue;
            }
            $content = json_decode((string) $raw, true);
            if (!is_array($content)) {
                $content = [];
            }
            $seedContent = is_array($block['content'] ?? null) ? $block['content'] : [];
            foreach ($fields as $field) {
                if (!array_key_exists($field, $content) && array_key_exists($field, $seedContent)) {
                    $content[$field] = $seedContent[$field];
                }
            }
            $update->execute([encode_value($content), $key]);
        }

        if (isset($data['settings']['sidebar_promo'])) {
            $promo = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key = ?');
            $promo->execute(['sidebar_promo']);
            if ($promo->fetchColumn() === false) {
                self::putSetting($pdo, 'sidebar_promo', $data['settings']['sidebar_promo']);
            }
        }
        self::putSetting($pdo, 'article_fields', true);
    }

    private static function migrateHomeAnchors(PDO $pdo): void
    {
        $ready = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key = ?');
        $ready->execute(['home_anchors']);
        if ($ready->fetchColumn() !== false) {
            return;
        }

        $data = load_seed();
        if (isset($data['settings']['navigation'])) {
            self::putSetting($pdo, 'navigation', $data['settings']['navigation']);
        }
        self::putSetting($pdo, 'home_anchors', true);
    }

    private static function migratePostCategories(PDO $pdo): void
    {
        $ready = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key = ?');
        $ready->execute(['post_categories_ready']);
        if ($ready->fetchColumn() !== false) {
            return;
        }

        $rows = $pdo->query(
            "SELECT content FROM blocks WHERE block_type = 'post' ORDER BY sort_order ASC, id ASC"
        )->fetchAll();
        $exists = $pdo->prepare('SELECT id FROM blocks WHERE block_key = ?');
        $insert = $pdo->prepare(
            'INSERT INTO blocks (block_key, block_type, title, sort_order, is_active, content) VALUES (?, ?, ?, ?, 1, ?)'
        );
        $order = 10;
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $content = decode_value((string) ($row['content'] ?? ''));
            if (!is_array($content)) {
                continue;
            }
            $key = strtolower(trim((string) ($content['category_key'] ?? '')));
            $label = trim((string) ($content['category'] ?? ''));
            if ($key === '' || $label === '' || isset($seen[$key]) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1) {
                continue;
            }
            $seen[$key] = true;
            $exists->execute([$key]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }
            $insert->execute([$key, 'category', $label, $order, encode_value([])]);
            $order += 10;
        }

        self::putSetting($pdo, 'post_categories_ready', true);
    }

    private static function insertSections(PDO $pdo, array $sections): void
    {
        $section = $pdo->prepare(
            'INSERT INTO sections (page_slug, section_key, section_type, title, sort_order, is_active, content)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($sections as $item) {
            if (!is_array($item)) {
                continue;
            }
            $section->execute([
                (string) $item['page_slug'],
                (string) $item['section_key'],
                (string) $item['section_type'],
                (string) ($item['title'] ?? ''),
                (int) ($item['sort_order'] ?? 0),
                (int) ($item['is_active'] ?? 1),
                encode_value($item['content'] ?? []),
            ]);
        }
    }

    private static function insertMissingSections(PDO $pdo, array $sections): void
    {
        $exists = $pdo->prepare('SELECT id FROM sections WHERE page_slug = ? AND section_key = ?');
        $insert = $pdo->prepare(
            'INSERT INTO sections (page_slug, section_key, section_type, title, sort_order, is_active, content)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($sections as $item) {
            if (!is_array($item)) {
                continue;
            }
            $exists->execute([(string) $item['page_slug'], (string) $item['section_key']]);
            if ($exists->fetchColumn() !== false) {
                continue;
            }
            $insert->execute([
                (string) $item['page_slug'],
                (string) $item['section_key'],
                (string) $item['section_type'],
                (string) ($item['title'] ?? ''),
                (int) ($item['sort_order'] ?? 0),
                (int) ($item['is_active'] ?? 1),
                encode_value($item['content'] ?? []),
            ]);
        }
    }

    private static function insertBlocks(PDO $pdo, array $blocks): void
    {
        $insert = $pdo->prepare(
            'INSERT INTO blocks (block_key, block_type, title, sort_order, is_active, content)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE block_key = block_key'
        );
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $insert->execute([
                (string) $block['block_key'],
                (string) $block['block_type'],
                (string) ($block['title'] ?? ''),
                (int) ($block['sort_order'] ?? 0),
                (int) ($block['is_active'] ?? 1),
                encode_value($block['content'] ?? []),
            ]);
        }
    }

    private static function mergeContent(PDO $pdo, string $page, string $key, callable $change): void
    {
        $select = $pdo->prepare('SELECT content FROM sections WHERE page_slug = ? AND section_key = ?');
        $select->execute([$page, $key]);
        $raw = $select->fetchColumn();
        if ($raw === false) {
            return;
        }
        $content = json_decode((string) $raw, true);
        if (!is_array($content)) {
            $content = [];
        }
        $updated = $change($content);
        if (!is_array($updated)) {
            return;
        }
        $update = $pdo->prepare('UPDATE sections SET content = ? WHERE page_slug = ? AND section_key = ?');
        $update->execute([encode_value($updated), $page, $key]);
    }

    private static function putSetting(PDO $pdo, string $key, mixed $value): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, encode_value($value)]);
    }
}
