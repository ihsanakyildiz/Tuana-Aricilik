<?php

declare(strict_types=1);

require_once __DIR__ . '/icons.php';
require_once dirname(__DIR__, 2) . '/app/Auth.php';

Auth::boot();
Auth::requireLogin(!empty($allowPasswordChange));
$adminFlash = Auth::pullFlash();
$adminName = Auth::username();
if ($adminName === '') {
    $adminInitial = 'Y';
} elseif (function_exists('mb_substr')) {
    $adminInitial = mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'), 'UTF-8');
} else {
    $adminInitial = strtoupper(substr($adminName, 0, 1));
}

$pageTitle = $pageTitle ?? 'Panel';
$crumbCurrent = $crumbCurrent ?? $pageTitle;
$activeNav = $activeNav ?? '';
$siteName = (string) setting('site_name', 'Tuana Arıcılık');

$adminNav = [
    [
        'label' => 'Genel',
        'items' => [
            ['id' => 'panel', 'label' => 'Panel', 'icon' => 'grid', 'href' => root_url('admin/')],
        ],
    ],
    [
        'label' => 'Mağaza',
        'items' => [
            ['id' => 'urunler', 'label' => 'Ürünler', 'icon' => 'bag', 'href' => root_url('admin/urunler.php')],
            ['id' => 'urun-kategoriler', 'label' => 'Kategoriler', 'icon' => 'folder', 'href' => root_url('admin/urun-kategorileri.php')],
            ['id' => 'siparisler', 'label' => 'Siparişler', 'icon' => 'receipt', 'href' => root_url('admin/siparisler.php')],
            ['id' => 'musteriler', 'label' => 'Müşteriler', 'icon' => 'users', 'href' => root_url('admin/musteriler.php')],
        ],
    ],
    [
        'label' => 'İçerik',
        'items' => [
            ['id' => 'hero', 'label' => 'Karşılama', 'icon' => 'image', 'href' => root_url('admin/hero.php')],
            ['id' => 'sayfalar', 'label' => 'Sayfalar', 'icon' => 'pages', 'href' => root_url('admin/sayfalar.php')],
            ['id' => 'menuler', 'label' => 'Menüler', 'icon' => 'menu', 'href' => root_url('admin/menuler.php')],
            ['id' => 'bolumler', 'label' => 'Bölümler', 'icon' => 'layers', 'href' => root_url('admin/bolumler.php')],
            ['id' => 'kartlar', 'label' => 'Kartlar', 'icon' => 'cards', 'href' => root_url('admin/bolumler.php?kartlar=1')],
            [
                'id' => 'yazilar',
                'label' => 'Yazılar',
                'icon' => 'article',
                'open' => $activeNav === 'yazi-yazilar' || $activeNav === 'yazi-kategoriler',
                'children' => [
                    ['id' => 'yazi-yazilar', 'label' => 'Tüm yazılar', 'href' => root_url('admin/yazilar.php')],
                    ['id' => 'yazi-kategoriler', 'label' => 'Kategoriler', 'href' => root_url('admin/kategoriler.php')],
                ],
            ],
        ],
    ],
    [
        'label' => 'Ayarlar',
        'items' => [
            ['id' => 'ayarlar', 'label' => 'Genel', 'icon' => 'settings', 'href' => root_url('admin/ayarlar.php')],
            ['id' => 'konumlar', 'label' => 'Adres listesi', 'icon' => 'globe', 'href' => root_url('admin/konumlar.php')],
            ['id' => 'kargo', 'label' => 'Kargo', 'icon' => 'archive', 'href' => root_url('admin/kargo.php')],
            ['id' => 'odeme-yontem', 'label' => 'Ödeme', 'icon' => 'card', 'href' => root_url('admin/odeme.php')],
            ['id' => 'hesap', 'label' => 'Hesap', 'icon' => 'lock', 'href' => root_url('admin/hesap.php')],
        ],
    ],
];

$renderNavLink = static function (array $item, string $activeNav): void {
    $id = (string) ($item['id'] ?? '');
    $label = (string) ($item['label'] ?? '');
    $active = $id !== '' && $id === $activeNav;
    $soon = !isset($item['href']);
    $class = 'nav-link' . ($active ? ' is-active' : '');
    $href = $soon ? '#' : (string) $item['href'];
    ?>
    <a class="<?= e($class) ?>" href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?><?= $soon ? ' data-soon="' . e($label) . '"' : '' ?>>
        <?php if (isset($item['icon'])): ?>
            <span class="nav-ico"><?= admin_icon((string) $item['icon']) ?></span>
        <?php endif; ?>
        <span><?= e($label) ?></span>
    </a>
    <?php
};
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
    <link rel="icon" href="<?= root_url(favicon_file()) ?>" type="<?= e(favicon_type(favicon_file())) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= root_url('assets/css/admin.css?v=' . filemtime(dirname(__DIR__, 2) . '/assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= root_url('admin/') ?>">
            <span class="brand-mark"><?= admin_icon('drop') ?></span>
            <span class="brand-name">Tuana</span>
        </a>

        <nav class="side-nav" aria-label="Yönetim menüsü">
            <?php foreach ($adminNav as $group): ?>
                <p class="nav-label"><?= e((string) $group['label']) ?></p>
                <ul class="nav-list">
                    <?php foreach ($group['items'] as $item): ?>
                        <?php $children = $item['children'] ?? null; ?>
                        <li class="nav-item<?= !empty($item['open']) ? ' is-open' : '' ?>">
                            <?php if (is_array($children)): ?>
                                <button class="nav-link" type="button" data-submenu aria-expanded="<?= !empty($item['open']) ? 'true' : 'false' ?>">
                                    <span class="nav-ico"><?= admin_icon((string) ($item['icon'] ?? 'bag')) ?></span>
                                    <span><?= e((string) $item['label']) ?></span>
                                    <span class="chevron"><?= admin_icon('chevron') ?></span>
                                </button>
                                <ul class="nav-sub">
                                    <?php foreach ($children as $child): ?>
                                        <li><?php $renderNavLink($child, $activeNav); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <?php $renderNavLink($item, $activeNav); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="shell">
        <header class="topbar">
            <button class="icon-btn" type="button" data-sidebar-toggle aria-label="Menüyü aç veya kapat">
                <?= admin_icon('menu') ?>
            </button>

            <form class="search" data-search>
                <span class="search-ico"><?= admin_icon('search') ?></span>
                <input type="search" name="q" placeholder="Hızlı ara..." autocomplete="off" aria-label="Hızlı ara">
            </form>

            <div class="menu" data-menu>
                <button class="text-btn" type="button" aria-expanded="false">
                    Mega menü
                    <span class="chevron"><?= admin_icon('chevron') ?></span>
                </button>
                <div class="menu-panel menu-panel-wide" hidden>
                    <div class="mega">
                        <section>
                            <h3>İçerik</h3>
                            <a href="<?= root_url('admin/hero.php') ?>">Karşılama</a>
                            <a href="<?= root_url('admin/sayfalar.php') ?>">Sayfalar</a>
                            <a href="<?= root_url('admin/menuler.php') ?>">Menüler</a>
                            <a href="<?= root_url('admin/bolumler.php') ?>">Bölümler</a>
                            <a href="<?= root_url('admin/bolumler.php?kartlar=1') ?>">Kartlar</a>
                            <a href="<?= root_url('admin/yazilar.php') ?>">Yazılar</a>
                            <a href="<?= root_url('admin/kategoriler.php') ?>">Kategoriler</a>
                        </section>
                        <section>
                            <h3>Mağaza</h3>
                            <a href="<?= root_url('admin/urunler.php') ?>">Ürünler</a>
                            <a href="<?= root_url('admin/urun-kategorileri.php') ?>">Kategoriler</a>
                            <a href="<?= root_url('admin/siparisler.php') ?>">Siparişler</a>
                            <a href="<?= root_url('admin/musteriler.php') ?>">Müşteriler</a>
                        </section>
                        <section>
                            <h3>Site</h3>
                            <a href="<?= root_url('admin/ayarlar.php') ?>">Genel ayarlar</a>
                            <a href="<?= root_url('admin/konumlar.php') ?>">Adres listesi</a>
                            <a href="<?= root_url('admin/kargo.php') ?>">Kargo seçenekleri</a>
                            <a href="<?= root_url('admin/odeme.php') ?>">Ödeme yöntemleri</a>
                            <a href="<?= root_url('admin/hesap.php') ?>">Hesap</a>
                            <a href="<?= root_url('admin/') ?>">Panele dön</a>
                            <a href="<?= root_url('/') ?>" target="_blank" rel="noopener">Siteyi görüntüle</a>
                        </section>
                    </div>
                </div>
            </div>

            <div class="menu" data-menu>
                <button class="text-btn" type="button" aria-expanded="false">
                    Uygulamalar
                    <span class="chevron"><?= admin_icon('chevron') ?></span>
                </button>
                <div class="menu-panel" hidden>
                    <div class="app-grid">
                        <a href="<?= root_url('admin/') ?>"><span><?= admin_icon('grid') ?></span>Panel</a>
                        <a href="<?= root_url('admin/sayfalar.php') ?>"><span><?= admin_icon('pages') ?></span>Sayfalar</a>
                        <a href="<?= root_url('admin/yazilar.php') ?>"><span><?= admin_icon('article') ?></span>Yazılar</a>
                        <a href="<?= root_url('admin/urunler.php') ?>"><span><?= admin_icon('bag') ?></span>Ürünler</a>
                        <a href="<?= root_url('admin/siparisler.php') ?>"><span><?= admin_icon('receipt') ?></span>Siparişler</a>
                        <a href="<?= root_url('admin/musteriler.php') ?>"><span><?= admin_icon('users') ?></span>Müşteriler</a>
                        <a href="<?= root_url('admin/ayarlar.php') ?>"><span><?= admin_icon('settings') ?></span>Ayarlar</a>
                    </div>
                </div>
            </div>

            <div class="topbar-spacer"></div>

            <button class="icon-btn" type="button" data-soon="Görünüm" aria-label="Görünüm">
                <?= admin_icon('sun') ?>
            </button>

            <div class="menu" data-menu>
                <button class="icon-btn" type="button" aria-expanded="false" aria-label="Bildirimler">
                    <?= admin_icon('bell') ?>
                    <span class="dot"></span>
                </button>
                <div class="menu-panel menu-panel-right notes" hidden>
                    <h3>Bildirimler</h3>
                    <a href="#" data-soon="Yorumlar">
                        <strong>Yeni yorum bekliyor</strong>
                        <span>Bal yazısına bir yorum bırakıldı.</span>
                    </a>
                    <a href="#" data-soon="Stok">
                        <strong>Stok azaldı</strong>
                        <span>Çiçek balı için stok uyarısı var.</span>
                    </a>
                    <a href="#" data-soon="Mesajlar">
                        <strong>İletişim formu</strong>
                        <span>Yeni bir mesaj gelen kutusunda.</span>
                    </a>
                </div>
            </div>

            <button class="icon-btn" type="button" data-refresh aria-label="Yenile">
                <?= admin_icon('refresh') ?>
            </button>
            <a class="icon-btn" href="<?= root_url('admin/ayarlar.php') ?>" aria-label="Ayarlar">
                <?= admin_icon('settings') ?>
            </a>

            <button class="lang-btn" type="button" data-soon="Dil">
                <svg class="flag" viewBox="0 0 18 12" aria-hidden="true">
                    <rect width="18" height="12" rx="1.5" fill="#e30a17"/>
                    <circle cx="7" cy="6" r="3.1" fill="#fff"/>
                    <circle cx="8" cy="6" r="2.45" fill="#e30a17"/>
                    <path d="M11.2 6.1 10.3 6.4 10.6 5.5 9.8 5l.9-.1.4-.8.3.8.9.1-.7.5.3.9z" fill="#fff"/>
                </svg>
                TR
            </button>

            <div class="menu" data-menu>
                <button class="user-btn" type="button" aria-expanded="false">
                    <span class="avatar"><?= e($adminInitial) ?></span>
                    <span class="user-meta">
                        <strong><?= e($adminName) ?></strong>
                        <small>Site yöneticisi</small>
                    </span>
                    <span class="chevron"><?= admin_icon('chevron') ?></span>
                </button>
                <div class="menu-panel menu-panel-right user-menu" hidden>
                    <a href="<?= root_url('admin/hesap.php') ?>">Hesap</a>
                    <a href="<?= root_url('/') ?>" target="_blank" rel="noopener">Siteyi görüntüle</a>
                    <form method="post" action="<?= root_url('admin/cikis.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                        <button type="submit">Çıkış</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="page-head">
            <h1><?= e($pageTitle) ?></h1>
            <nav class="crumbs" aria-label="Konum">
                <a href="<?= root_url('/') ?>">Tuana</a>
                <span aria-hidden="true">›</span>
                <a href="<?= root_url('admin/') ?>">Panel</a>
                <span aria-hidden="true">›</span>
                <span class="is-current"><?= e($crumbCurrent) ?></span>
            </nav>
        </div>

        <div class="admin-content">
            <?php if (is_array($adminFlash)): ?>
                <p class="flash flash-<?= e($adminFlash['type']) ?>" role="status"><?= e($adminFlash['text']) ?></p>
            <?php endif; ?>
