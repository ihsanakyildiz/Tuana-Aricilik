<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$admin = rtrim(base_path(), '/') . '/admin/';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo "User-agent: *\n";
echo "Allow: /\n";
echo 'Disallow: ' . $admin . "\n";
$memberRoot = rtrim(base_path(), '/');
foreach (['/hesabim', '/uye-giris', '/uye-ol', '/uye-cikis', '/siparis', '/tesekkur', '/siparislerim', '/odeme-kart', '/odeme-sonuc', '/paytr-bildirim', '/geliver-bildirim', '/konum.php'] as $memberPath) {
    echo 'Disallow: ' . $memberRoot . $memberPath . "\n";
}
echo 'Sitemap: ' . absolute_url('/sitemap.xml') . "\n";
