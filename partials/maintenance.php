<?php

declare(strict_types=1);

$siteName = (string) setting('site_name', 'Tuana Arıcılık');
$message = (string) setting('maintenance_message', 'Sitemiz kısa bir bakımda. Birazdan yeniden buradayız.');
$logo = trim((string) setting('logo', ''));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bakımda · <?= e($siteName) ?></title>
    <meta name="robots" content="noindex">
    <link rel="icon" href="<?= asset(favicon_file()) ?>" type="<?= e(favicon_type(favicon_file())) ?>">
    <link rel="stylesheet" href="<?= theme_file('css/main.css') ?>">
</head>
<body class="maintenance-body">
    <main class="maintenance-card">
        <?php if ($logo !== ''): ?>
            <img src="<?= asset($logo) ?>" alt="<?= e($siteName) ?>">
        <?php endif; ?>
        <h1><?= e($siteName) ?></h1>
        <p><?= e($message) ?></p>
    </main>
</body>
</html>
