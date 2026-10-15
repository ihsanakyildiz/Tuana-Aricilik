<?php

declare(strict_types=1);

final class ImageSet
{
    /** @var list<int> */
    public const WIDTHS = [640, 960, 1280, 1920];

    private const QUALITY = 78;
    private const MAX_PIXELS = 24_000_000;

    public static function ready(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatetruecolor');
    }

    /**
     * @return array{image:string,width:int,height:int,sources:list<array{w:int,path:string}>}
     */
    public static function storeRaster(string $tmp, string $mime, string $relativeDir, string $token): array
    {
        if (!self::ready()) {
            throw new RuntimeException('WebP dönüştürme şu an kullanılamıyor.');
        }

        $image = self::load($tmp, $mime);
        $image = self::orient($image, $tmp, $mime);
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        if ($sourceWidth < 32 || $sourceHeight < 32 || ($sourceWidth * $sourceHeight) > self::MAX_PIXELS) {
            imagedestroy($image);
            throw new RuntimeException('Görsel çok küçük ya da çok büyük.');
        }

        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        $directory = dirname(__DIR__) . '/' . $relativeDir;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            imagedestroy($image);
            throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
        }

        $widths = [];
        foreach (self::WIDTHS as $width) {
            $use = min($width, $sourceWidth);
            if (!in_array($use, $widths, true)) {
                $widths[] = $use;
            }
            if ($use === $sourceWidth) {
                break;
            }
        }

        $sources = [];
        $fallback = '';
        $fallbackWidth = 0;
        $fallbackHeight = 0;
        try {
            foreach ($widths as $width) {
                $height = (int) max(1, round($sourceHeight * ($width / $sourceWidth)));
                $frame = self::frame($image, $width, $height);
                $name = $token . '-' . $width . '.webp';
                $full = $directory . '/' . $name;
                if (!imagewebp($frame, $full, self::QUALITY)) {
                    imagedestroy($frame);
                    throw new RuntimeException('WebP kaydedilemedi.');
                }
                imagedestroy($frame);
                $path = $relativeDir . '/' . $name;
                $sources[] = ['w' => $width, 'path' => $path];
                if ($width <= 1280 || $fallback === '') {
                    $fallback = $path;
                    $fallbackWidth = $width;
                    $fallbackHeight = $height;
                }
            }
        } catch (Throwable $error) {
            imagedestroy($image);
            self::deletePaths(array_column($sources, 'path'));
            throw $error;
        }

        imagedestroy($image);

        return [
            'image' => $fallback,
            'width' => $fallbackWidth,
            'height' => $fallbackHeight,
            'sources' => $sources,
        ];
    }

    /** @return list<string> */
    public static function family(string $path): array
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (preg_match('#^(assets/uploads/(?:pages|posts)/(?:sayfa|kapak|yazi|govde)-[a-f0-9]+)-\d+\.webp$#', $path, $match) !== 1) {
            return [$path];
        }
        $directory = dirname(__DIR__) . '/' . dirname($match[1]);
        $prefix = basename($match[1]);
        $paths = [];
        $files = glob($directory . '/' . $prefix . '-*.webp');
        if (is_array($files)) {
            foreach ($files as $file) {
                $name = basename($file);
                if (preg_match('/^' . preg_quote($prefix, '/') . '-\d+\.webp$/', $name) !== 1) {
                    continue;
                }
                $paths[] = dirname($match[1]) . '/' . $name;
            }
        }

        return $paths === [] ? [$path] : $paths;
    }

    public static function deleteUnreferenced(string $relativeDir, string $namePattern): void
    {
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');
        if ($relativeDir === '' || $upload === '' || !str_starts_with($relativeDir, $upload . '/')) {
            return;
        }
        $directory = dirname(__DIR__) . '/' . $relativeDir;
        if (!is_dir($directory)) {
            return;
        }
        $names = scandir($directory);
        if (!is_array($names)) {
            return;
        }
        $blob = self::storedReferences();
        $unused = [];
        foreach ($names as $name) {
            if (preg_match($namePattern, $name) !== 1) {
                continue;
            }
            $relative = $relativeDir . '/' . $name;
            if (!str_contains($blob, $relative)) {
                $unused[] = $relative;
            }
        }
        self::deletePaths($unused);
    }

    /** @param list<string> $paths */
    public static function deletePaths(array $paths): void
    {
        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            return;
        }
        $upload = trim(str_replace('\\', '/', (string) setting('upload_dir', 'assets/uploads')), '/');
        $blob = null;
        foreach ($paths as $path) {
            $relative = ltrim(str_replace('\\', '/', (string) $path), '/');
            if ($relative === '' || str_contains($relative, '..') || !str_starts_with($relative, $upload . '/')) {
                continue;
            }
            $full = dirname(__DIR__) . '/' . $relative;
            $dir = realpath(dirname($full));
            if ($dir === false || !str_starts_with($dir, $root) || !is_file($full)) {
                continue;
            }
            if ($blob === null) {
                $blob = self::storedReferences();
            }
            if (str_contains($blob, $relative)) {
                continue;
            }
            unlink($full);
        }
    }

    private static function storedReferences(): string
    {
        $pdo = db_pdo();
        if (!$pdo instanceof PDO) {
            return '';
        }
        $parts = [];
        foreach (['blocks', 'sections'] as $table) {
            $rows = $pdo->query('SELECT content FROM ' . $table)->fetchAll();
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $parts[] = (string) ($row['content'] ?? '');
                }
            }
        }
        $settings = $pdo->query('SELECT setting_value FROM settings')->fetchAll();
        foreach ($settings as $row) {
            if (is_array($row)) {
                $parts[] = (string) ($row['setting_value'] ?? '');
            }
        }

        return implode("\n", $parts);
    }

    private static function load(string $tmp, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($tmp),
            'image/png' => imagecreatefrompng($tmp),
            'image/webp' => imagecreatefromwebp($tmp),
            'image/gif' => imagecreatefromgif($tmp),
            default => false,
        };
        if (!$image instanceof GdImage) {
            throw new RuntimeException('Görsel okunamadı.');
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private static function orient(GdImage $image, string $tmp, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($tmp);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated instanceof GdImage) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    private static function frame(GdImage $source, int $width, int $height): GdImage
    {
        $frame = imagecreatetruecolor($width, $height);
        if (!$frame instanceof GdImage) {
            throw new RuntimeException('Görsel küçültülemedi.');
        }
        imagealphablending($frame, false);
        imagesavealpha($frame, true);
        $clear = imagecolorallocatealpha($frame, 0, 0, 0, 127);
        if ($clear !== false) {
            imagefilledrectangle($frame, 0, 0, $width, $height, $clear);
        }
        imagecopyresampled(
            $frame,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source)
        );

        return $frame;
    }
}
