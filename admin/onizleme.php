<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/SectionCatalog.php';

Auth::allowEmbed();
Auth::requireLogin();

$type = isset($_GET['tip']) ? (string) $_GET['tip'] : '';
$section = SectionCatalog::preview($type);
if ($section === null) {
    http_response_code(404);
    echo 'Önizleme yok';
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=1200">
    <title><?= e(SectionCatalog::label($type)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= theme_file('css/main.css') ?>">
</head>
<body class="is-preview">
<?php render_section($section); ?>
</body>
</html>
