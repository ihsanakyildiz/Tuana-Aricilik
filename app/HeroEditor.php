<?php

declare(strict_types=1);

final class HeroEditor
{
    private static string $boundPage = 'home';
    private static string $boundKey = 'hero';

    public static function bind(string $page, string $key): bool
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $page) !== 1 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1) {
            return false;
        }
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return false;
        }
        $stmt = $pdo->prepare(
            'SELECT id FROM sections WHERE page_slug = ? AND section_key = ? AND section_type = ? LIMIT 1'
        );
        $stmt->execute([$page, $key, 'hero']);
        if ($stmt->fetchColumn() === false) {
            return false;
        }
        self::$boundPage = $page;
        self::$boundKey = $key;

        return true;
    }
    private const IMAGE_MAX = 8_000_000;
    private const VIDEO_MAX = 24_000_000;

    public const FONTS = [
        '' => 'Temanın yazı tipi',
        'outfit' => 'Outfit',
        'manrope' => 'Manrope',
        'vibes' => 'El yazısı',
        'georgia' => 'Serif',
    ];

    public const EFFECTS = [
        'fade' => 'Belirme',
        'slide' => 'Kaydırma',
        'zoom' => 'Yakınlaşma',
        'none' => 'Anında geçiş',
    ];

    /** @return array{slider:array<string,mixed>,slides:list<array<string,mixed>>} */
    public static function collection(): array
    {
        $content = self::ensure();
        $slides = [];
        foreach ($content['slides'] as $slide) {
            if (is_array($slide)) {
                $slides[] = self::present($slide);
            }
        }

        return [
            'slider' => self::slider($content),
            'slides' => $slides,
        ];
    }

    /** @return array<string, mixed> */
    public static function blank(): array
    {
        return self::present([
            'id' => '',
            'active' => true,
            'name' => '',
            'title_lines' => [],
            'media' => ['layout' => 'side', 'align' => 'right'],
        ]);
    }

    /** @return array<string, mixed>|null */
    public static function open(string $id): ?array
    {
        if (!self::validId($id)) {
            return null;
        }
        foreach (self::collection()['slides'] as $slide) {
            if ($slide['id'] === $id) {
                return $slide;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $post
     * @return list<string>
     */
    public static function saveSlider(array $post): array
    {
        $effect = is_string($post['effect'] ?? null) ? $post['effect'] : 'fade';
        if (!isset(self::EFFECTS[$effect])) {
            return ['Geçiş seçimi geçersiz.'];
        }
        $content = self::ensure();
        $content['slider'] = [
            'effect' => $effect,
            'interval' => max(3, min(12, (int) ($post['interval'] ?? 6))),
            'autoplay' => isset($post['autoplay']),
        ];
        self::write($content);

        return [];
    }

    /** @return list<string> */
    public static function remove(string $id): array
    {
        $content = self::ensure();
        $slides = $content['slides'];
        if (count($slides) < 2) {
            return ['Son karşılama silinemez.'];
        }
        $keep = [];
        $gone = null;
        foreach ($slides as $slide) {
            if (is_array($slide) && (string) ($slide['id'] ?? '') === $id) {
                $gone = $slide;
                continue;
            }
            $keep[] = $slide;
        }
        if ($gone === null) {
            return ['Karşılama bulunamadı.'];
        }
        $content['slides'] = array_values($keep);
        self::mirror($content);
        self::write($content);
        ImageSet::deletePaths(self::managedPaths(self::present($gone)));
        $video = (string) (is_array($gone['media'] ?? null) ? ($gone['media']['video'] ?? '') : '');
        if (self::isManaged($video)) {
            ImageSet::deletePaths([$video]);
        }

        return [];
    }

    public static function setActive(string $id, bool $active): void
    {
        $content = self::ensure();
        foreach ($content['slides'] as &$slide) {
            if (is_array($slide) && (string) ($slide['id'] ?? '') === $id) {
                $slide['active'] = $active;
            }
        }
        unset($slide);
        self::mirror($content);
        self::write($content);
    }

    public static function move(string $id, int $direction): void
    {
        if ($direction !== -1 && $direction !== 1) {
            return;
        }
        $content = self::ensure();
        $slides = array_values($content['slides']);
        $index = null;
        foreach ($slides as $i => $slide) {
            if (is_array($slide) && (string) ($slide['id'] ?? '') === $id) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $target = $index + $direction;
        if (!isset($slides[$target])) {
            return;
        }
        $swap = $slides[$target];
        $slides[$target] = $slides[$index];
        $slides[$index] = $swap;
        $content['slides'] = $slides;
        self::mirror($content);
        self::write($content);
    }

    /** @return array<string, mixed> */
    public static function current(): array
    {
        $slides = self::collection()['slides'];

        return $slides[0] ?? self::blank();
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private static function present(array $content): array
    {
        $media = is_array($content['media'] ?? null) ? $content['media'] : [];
        $lines = $content['title_lines'] ?? [];
        if (!is_array($lines)) {
            $lines = [];
        }
        $lines = array_values(array_map(static fn (mixed $line): string => (string) $line, $lines));
        $lines = array_pad(array_slice($lines, 0, 3), 3, '');
        $primary = is_array($content['primary_button'] ?? null) ? $content['primary_button'] : [];
        $secondary = is_array($content['secondary_button'] ?? null) ? $content['secondary_button'] : [];
        $type = is_array($content['type'] ?? null) ? $content['type'] : [];
        $layout = (string) ($media['layout'] ?? 'side');
        if (!in_array($layout, ['side', 'cover', 'video'], true)) {
            $layout = 'side';
        }
        $sources = [];
        if (is_array($media['sources'] ?? null)) {
            foreach ($media['sources'] as $source) {
                if (!is_array($source)) {
                    continue;
                }
                $path = (string) ($source['path'] ?? '');
                $width = (int) ($source['w'] ?? 0);
                if ($path !== '' && $width > 0) {
                    $sources[] = ['w' => $width, 'path' => $path];
                }
            }
        }

        return [
            'title_lines' => $lines,
            'text' => (string) ($content['text'] ?? ''),
            'image' => (string) ($media['image'] ?? $content['image'] ?? ''),
            'image_alt' => (string) ($content['image_alt'] ?? ''),
            'width' => (int) ($media['width'] ?? 0),
            'height' => (int) ($media['height'] ?? 0),
            'sources' => $sources,
            'layout' => $layout,
            'align' => ($media['align'] ?? 'right') === 'left' ? 'left' : 'right',
            'focal_x' => self::percent($media['focal_x'] ?? 50),
            'focal_y' => self::percent($media['focal_y'] ?? 45),
            'overlay' => max(0, min(80, (int) ($media['overlay'] ?? 46))),
            'tone' => ($media['tone'] ?? 'light') === 'dark' ? 'dark' : 'light',
            'video' => (string) ($media['video'] ?? ''),
            'primary_label' => (string) ($primary['label'] ?? ''),
            'primary_url' => (string) ($primary['url'] ?? ''),
            'secondary_label' => (string) ($secondary['label'] ?? ''),
            'secondary_url' => (string) ($secondary['url'] ?? ''),
            'secondary_modal' => (string) ($secondary['modal'] ?? '') === 'intro',
            'id' => (string) ($content['id'] ?? ''),
            'name' => (string) ($content['name'] ?? ''),
            'active' => self::isOn($content['active'] ?? true),
            'title_font' => self::fontKey($type['title_font'] ?? ''),
            'title_color' => self::color($type['title_color'] ?? ''),
            'title_weight' => self::weight($type['title_weight'] ?? '700', '700'),
            'title_italic' => ($type['title_style'] ?? '') === 'italic',
            'title_underline' => ($type['title_decoration'] ?? '') === 'underline',
            'title_uppercase' => ($type['title_transform'] ?? '') === 'uppercase',
            'title_size' => self::size($type['title_size'] ?? 'normal'),
            'text_font' => self::fontKey($type['text_font'] ?? ''),
            'text_color' => self::color($type['text_color'] ?? ''),
            'text_weight' => self::weight($type['text_weight'] ?? '400', '400'),
            'text_italic' => ($type['text_style'] ?? '') === 'italic',
            'text_underline' => ($type['text_decoration'] ?? '') === 'underline',
            'text_uppercase' => ($type['text_transform'] ?? '') === 'uppercase',
            'text_size' => self::size($type['text_size'] ?? 'normal'),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return list<string>
     */
    public static function save(array $post, array $files): array
    {
        $errors = [];
        $lines = [];
        $postedLines = $post['title_lines'] ?? [];
        if (!is_array($postedLines)) {
            $postedLines = [];
        }
        foreach (array_slice(array_values($postedLines), 0, 3) as $line) {
            $lines[] = self::clip(is_string($line) ? $line : '', 60);
        }
        $lines = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
        if ($lines === []) {
            $errors[] = 'Başlık için en az bir satır yaz.';
        }

        $text = self::clip(is_string($post['text'] ?? null) ? $post['text'] : '', 420);
        $alt = self::clip(is_string($post['image_alt'] ?? null) ? $post['image_alt'] : '', 140);
        $layout = is_string($post['layout'] ?? null) ? $post['layout'] : 'side';
        if (!in_array($layout, ['side', 'cover', 'video'], true)) {
            $errors[] = 'Görünüm seçimi geçersiz.';
            $layout = 'side';
        }
        $align = ($post['align'] ?? '') === 'left' ? 'left' : 'right';
        $tone = ($post['tone'] ?? '') === 'dark' ? 'dark' : 'light';
        $overlay = max(0, min(80, (int) ($post['overlay'] ?? 46)));
        $focalX = self::percent($post['focal_x'] ?? 50);
        $focalY = self::percent($post['focal_y'] ?? 45);

        $primary = self::button(
            is_string($post['primary_label'] ?? null) ? $post['primary_label'] : '',
            is_string($post['primary_url'] ?? null) ? $post['primary_url'] : '',
            false
        );
        $secondary = self::button(
            is_string($post['secondary_label'] ?? null) ? $post['secondary_label'] : '',
            is_string($post['secondary_url'] ?? null) ? $post['secondary_url'] : '',
            isset($post['secondary_modal'])
        );
        if ($primary === false || $secondary === false) {
            $errors[] = 'Düğme adresi site içi bir yol, #bağlantı ya da http adresi olmalı.';
        }

        $content = self::ensure();
        $slides = $content['slides'];
        $postedId = is_string($post['slide_id'] ?? null) ? $post['slide_id'] : '';
        $index = -1;
        if (self::validId($postedId)) {
            foreach ($slides as $i => $slide) {
                if (is_array($slide) && (string) ($slide['id'] ?? '') === $postedId) {
                    $index = $i;
                    break;
                }
            }
        }
        $current = $index >= 0 && is_array($slides[$index]) ? self::present($slides[$index]) : self::blank();
        $imageFile = self::inspectImage($files['image'] ?? null);
        $videoFile = self::inspectVideo($files['video'] ?? null);
        if ($imageFile === false) {
            $errors[] = 'Görsel PNG, JPEG, WebP veya GIF olmalı ve 8 MB altında kalmalı.';
        }
        if ($videoFile === false) {
            $errors[] = 'Video MP4 veya WebM olmalı ve 24 MB altında kalmalı.';
        }
        if (is_array($imageFile) && !ImageSet::ready()) {
            $errors[] = 'WebP dönüştürme şu an kullanılamıyor.';
        }

        $image = (string) $current['image'];
        $sources = $current['sources'];
        $width = (int) $current['width'];
        $height = (int) $current['height'];
        $video = (string) $current['video'];
        $removeImage = isset($post['remove_image']) && self::isManaged($image);
        $removeVideo = isset($post['remove_video']) && self::isManaged($video);
        if ($layout === 'video' && $video === '' && !is_array($videoFile) && !$removeVideo) {
            $errors[] = 'Kapak videosu için bir video seç.';
        }
        if ($layout === 'video' && $removeVideo && !is_array($videoFile)) {
            $errors[] = 'Videoyu kaldırmadan önce yenisini seç ya da başka bir görünüm kullan.';
        }
        $nextImage = $removeImage ? '' : $image;
        if ($layout === 'cover' && $nextImage === '' && !is_array($imageFile)) {
            $errors[] = 'Kapak görünümü için bir görsel seç.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $freshImages = [];
        $freshVideo = '';
        try {
            if (is_array($imageFile)) {
                $stored = ImageSet::storeRaster($imageFile['tmp'], $imageFile['mime'], self::folder(), 'hero-' . bin2hex(random_bytes(4)));
                $freshImages = array_column($stored['sources'], 'path');
                $image = $stored['image'];
                $sources = $stored['sources'];
                $width = $stored['width'];
                $height = $stored['height'];
            } elseif ($removeImage) {
                $image = 'assets/images/about.jpg';
                $sources = [];
                $width = 0;
                $height = 0;
                $alt = $alt !== '' ? $alt : 'Kavanozda süzme bal ve tahta bal kaşığı';
            }

            if (is_array($videoFile)) {
                $name = 'hero-' . bin2hex(random_bytes(4)) . '.' . $videoFile['ext'];
                $target = self::inside(self::folder() . '/' . $name);
                if ($target === null || !move_uploaded_file($videoFile['tmp'], $target)) {
                    throw new RuntimeException('Video kaydedilemedi.');
                }
                $freshVideo = self::folder() . '/' . $name;
                $video = $freshVideo;
            } elseif ($removeVideo) {
                $video = '';
            }

            $name = self::clip(is_string($post['name'] ?? null) ? $post['name'] : '', 40);
            if ($name === '') {
                $name = $lines[0];
            }
            $stored = [
                'id' => $index >= 0 ? $postedId : bin2hex(random_bytes(4)),
                'active' => isset($post['active']),
                'name' => $name,
                'title_lines' => $lines,
                'text' => $text,
                'image_alt' => $alt,
                'primary_button' => $primary,
                'secondary_button' => $secondary,
                'type' => self::typeFromPost($post),
                'media' => [
                    'layout' => $layout,
                    'align' => $align,
                    'focal_x' => $focalX,
                    'focal_y' => $focalY,
                    'overlay' => $overlay,
                    'tone' => $tone,
                    'image' => $image,
                    'width' => $width,
                    'height' => $height,
                    'sources' => $sources,
                    'video' => $video,
                ],
            ];
            if ($index >= 0) {
                $slides[$index] = $stored;
            } else {
                $slides[] = $stored;
            }
            $content['slides'] = array_values($slides);
            self::mirror($content);
            self::write($content);
        } catch (Throwable $error) {
            ImageSet::deletePaths($freshImages);
            if ($freshVideo !== '') {
                ImageSet::deletePaths([$freshVideo]);
            }

            return [$error->getMessage() !== '' ? $error->getMessage() : 'Karşılama kaydedilemedi.'];
        }

        $retired = [];
        if (is_array($imageFile) || $removeImage) {
            $retired = array_merge($retired, self::managedPaths($current));
        }
        if ((is_array($videoFile) || $removeVideo) && self::isManaged((string) $current['video'])) {
            $retired[] = (string) $current['video'];
        }
        ImageSet::deletePaths($retired);

        return [];
    }

    /** @return array<string, mixed> */
    private static function ensure(): array
    {
        $content = self::content();
        $changed = false;
        $slides = is_array($content['slides'] ?? null) ? $content['slides'] : [];
        if ($slides === []) {
            $slides = [self::legacy($content)];
            $changed = true;
        }
        $clean = [];
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            if (!self::validId((string) ($slide['id'] ?? ''))) {
                $slide['id'] = bin2hex(random_bytes(4));
                $changed = true;
            }
            $clean[] = $slide;
        }
        if ($clean === []) {
            $clean[] = self::legacy($content);
            $changed = true;
        }
        $content['slides'] = $clean;
        if (!is_array($content['slider'] ?? null)) {
            $content['slider'] = ['effect' => 'fade', 'interval' => 6, 'autoplay' => true];
            $changed = true;
        }
        if ($changed) {
            self::write($content);
        }

        return $content;
    }

    /** @param array<string, mixed> $content @return array<string, mixed> */
    private static function legacy(array $content): array
    {
        $lines = $content['title_lines'] ?? [];
        $first = is_array($lines) && isset($lines[0]) ? self::clip((string) $lines[0], 40) : '';

        return [
            'id' => bin2hex(random_bytes(4)),
            'active' => true,
            'name' => $first !== '' ? $first : 'Karşılama',
            'title_lines' => is_array($lines) ? $lines : [],
            'text' => (string) ($content['text'] ?? ''),
            'image_alt' => (string) ($content['image_alt'] ?? ''),
            'primary_button' => $content['primary_button'] ?? null,
            'secondary_button' => $content['secondary_button'] ?? null,
            'type' => [],
            'media' => is_array($content['media'] ?? null) ? $content['media'] : [
                'layout' => 'side',
                'align' => 'right',
                'image' => (string) ($content['image'] ?? ''),
            ],
        ];
    }

    /** @param array<string, mixed> $content */
    private static function mirror(array &$content): void
    {
        $chosen = null;
        $slides = $content['slides'] ?? [];
        if (!is_array($slides)) {
            return;
        }
        foreach ($slides as $slide) {
            if (is_array($slide) && self::isOn($slide['active'] ?? true)) {
                $chosen = $slide;
                break;
            }
        }
        if ($chosen === null && isset($slides[0]) && is_array($slides[0])) {
            $chosen = $slides[0];
        }
        if ($chosen === null) {
            return;
        }
        $media = is_array($chosen['media'] ?? null) ? $chosen['media'] : [];
        $content['title_lines'] = $chosen['title_lines'] ?? [];
        $content['text'] = (string) ($chosen['text'] ?? '');
        $content['image'] = (string) ($media['image'] ?? '');
        $content['image_alt'] = (string) ($chosen['image_alt'] ?? '');
        $content['primary_button'] = $chosen['primary_button'] ?? null;
        $content['secondary_button'] = $chosen['secondary_button'] ?? null;
        $content['media'] = $media;
    }

    /** @param array<string, mixed> $content @return array{effect:string,interval:int,autoplay:bool} */
    private static function slider(array $content): array
    {
        $raw = is_array($content['slider'] ?? null) ? $content['slider'] : [];
        $effect = (string) ($raw['effect'] ?? 'fade');
        if (!isset(self::EFFECTS[$effect])) {
            $effect = 'fade';
        }

        return [
            'effect' => $effect,
            'interval' => max(3, min(12, (int) ($raw['interval'] ?? 6))),
            'autoplay' => self::isOn($raw['autoplay'] ?? true),
        ];
    }

    /** @param array<string, mixed> $post @return array<string, string> */
    private static function typeFromPost(array $post): array
    {
        return [
            'title_font' => self::fontKey($post['title_font'] ?? ''),
            'title_color' => isset($post['title_custom_color']) ? self::color($post['title_color'] ?? '') : '',
            'title_weight' => self::weight($post['title_weight'] ?? '700', '700'),
            'title_style' => isset($post['title_italic']) ? 'italic' : 'normal',
            'title_decoration' => isset($post['title_underline']) ? 'underline' : 'none',
            'title_transform' => isset($post['title_uppercase']) ? 'uppercase' : 'none',
            'title_size' => self::size($post['title_size'] ?? 'normal'),
            'text_font' => self::fontKey($post['text_font'] ?? ''),
            'text_color' => isset($post['text_custom_color']) ? self::color($post['text_color'] ?? '') : '',
            'text_weight' => self::weight($post['text_weight'] ?? '400', '400'),
            'text_style' => isset($post['text_italic']) ? 'italic' : 'normal',
            'text_decoration' => isset($post['text_underline']) ? 'underline' : 'none',
            'text_transform' => isset($post['text_uppercase']) ? 'uppercase' : 'none',
            'text_size' => self::size($post['text_size'] ?? 'normal'),
        ];
    }

    private static function validId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{8}$/', $id) === 1;
    }

    private static function isOn(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }

    private static function fontKey(mixed $value): string
    {
        $value = is_string($value) ? $value : '';

        return $value !== '' && isset(self::FONTS[$value]) ? $value : '';
    }

    private static function color(mixed $value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : '';
    }

    private static function weight(mixed $value, string $fallback): string
    {
        $value = (string) $value;

        return in_array($value, ['400', '500', '600', '700', '800'], true) ? $value : $fallback;
    }

    private static function size(mixed $value): string
    {
        $value = (string) $value;

        return in_array($value, ['small', 'normal', 'large'], true) ? $value : 'normal';
    }

    /** @return array<string, mixed> */
    private static function content(): array
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Veritabanına ulaşılamadı.');
        }
        $stmt = $pdo->prepare(
            'SELECT content FROM sections WHERE page_slug = ? AND section_key = ? AND section_type = ? LIMIT 1'
        );
        $stmt->execute([self::$boundPage, self::$boundKey, 'hero']);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            throw new RuntimeException('Karşılama bölümü bulunamadı.');
        }
        $content = decode_value((string) $row['content']);

        return is_array($content) ? $content : [];
    }

    /** @param array<string, mixed> $content */
    private static function write(array $content): void
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Veritabanına ulaşılamadı.');
        }
        $stmt = $pdo->prepare(
            'UPDATE sections SET content = ? WHERE page_slug = ? AND section_key = ? AND section_type = ?'
        );
        $stmt->execute([encode_value($content), self::$boundPage, self::$boundKey, 'hero']);
    }

    /** @return array{label:string,url:string,modal?:string,icon?:string}|null|false */
    private static function button(string $label, string $url, bool $modal): array|null|false
    {
        $label = self::clip($label, 32);
        $url = trim($url);
        if ($label === '' && $url === '') {
            return null;
        }
        if ($label === '' || $url === '' || preg_match('/^\s*javascript:/i', $url) === 1) {
            return false;
        }
        $allowed = str_starts_with($url, '/')
            || str_starts_with($url, '#')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
            || preg_match('#^https?://#i', $url) === 1;
        if (!$allowed) {
            return false;
        }
        $button = ['label' => $label, 'url' => $url];
        if ($modal) {
            $button['modal'] = 'intro';
            $button['icon'] = 'play';
        }

        return $button;
    }

    /** @return array{tmp:string,mime:string}|false|null */
    private static function inspectImage(mixed $file): array|false|null
    {
        $upload = self::upload($file, self::IMAGE_MAX);
        if ($upload === null || $upload === false) {
            return $upload;
        }
        $map = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
            'image/gif' => true,
        ];
        if (!isset($map[$upload['mime']])) {
            return false;
        }

        return $upload;
    }

    /** @return array{tmp:string,mime:string,ext:string}|false|null */
    private static function inspectVideo(mixed $file): array|false|null
    {
        $upload = self::upload($file, self::VIDEO_MAX);
        if ($upload === null || $upload === false) {
            return $upload;
        }
        $ext = match ($upload['mime']) {
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => '',
        };
        if ($ext === '') {
            return false;
        }
        $upload['ext'] = $ext;

        return $upload;
    }

    /** @return array{tmp:string,mime:string}|false|null */
    private static function upload(mixed $file, int $maxBytes): array|false|null
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return false;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || $size < 1 || $size > $maxBytes || !is_uploaded_file($tmp)) {
            return false;
        }
        $mime = '';
        if (class_exists(finfo::class)) {
            $info = new finfo(FILEINFO_MIME_TYPE);
            $detected = $info->file($tmp);
            $mime = is_string($detected) ? $detected : '';
        }
        if ($mime === '') {
            return false;
        }

        return ['tmp' => $tmp, 'mime' => $mime];
    }

    /** @param array<string, mixed> $current @return list<string> */
    private static function managedPaths(array $current): array
    {
        $paths = [];
        if (self::isManaged((string) $current['image'])) {
            $paths[] = (string) $current['image'];
        }
        $sources = $current['sources'] ?? [];
        if (is_array($sources)) {
            foreach ($sources as $source) {
                if (is_array($source) && self::isManaged((string) ($source['path'] ?? ''))) {
                    $paths[] = (string) $source['path'];
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private static function isManaged(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');

        return $path !== '' && $upload !== '' && str_starts_with($path, $upload . '/');
    }

    private static function folder(): string
    {
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');
        if ($upload === '' || str_contains($upload, '..')) {
            $upload = 'assets/uploads';
        }
        $relative = $upload . '/hero';
        $full = dirname(__DIR__) . '/' . $relative;
        if (!is_dir($full) && !mkdir($full, 0755, true) && !is_dir($full)) {
            throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
        }

        return $relative;
    }

    private static function inside(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        $root = realpath(dirname(__DIR__));
        $full = dirname(__DIR__) . '/' . $relative;
        $parent = realpath(dirname($full));
        if ($root === false || $parent === false || !str_starts_with($parent, $root)) {
            return null;
        }

        return $full;
    }

    private static function percent(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    private static function clip(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        if (mb_strlen($value) > $limit) {
            $value = mb_substr($value, 0, $limit);
        }

        return $value;
    }
}
