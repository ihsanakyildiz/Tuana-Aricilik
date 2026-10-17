<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/MenuEditor.php';
require_once dirname(__DIR__) . '/app/MemberAccount.php';

MemberAccount::boot();

$socials = setting('socials', []);
$contactButton = setting('contact_button', []);
$tagline = trim((string) setting('meta_title', 'Doğal Bal ve Arı Ürünleri'));
$documentTitle = (string) ($pageTitle ?? '');
if ($tagline !== '' && $tagline !== $documentTitle) {
    $documentTitle .= ' | ' . $tagline;
}
$themeColor = (string) setting('theme_color', '#e2a93b');
if (preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor) !== 1) {
    $themeColor = '#e2a93b';
}
$favicon = favicon_file();
$canonicalPath = isset($canonicalPath) && is_string($canonicalPath) && str_starts_with($canonicalPath, '/')
    ? $canonicalPath
    : current_path();
$canonical = absolute_url($canonicalPath);
if (!isset($shareImage) || trim((string) $shareImage) === '') {
    $shareImage = trim((string) setting('logo', ''));
} else {
    $shareImage = trim((string) $shareImage);
}
$robots = isset($robots) && is_string($robots) && $robots !== '' ? $robots : 'index,follow';
$ogType = isset($ogType) && is_string($ogType) && preg_match('/^[a-z]+$/', $ogType) === 1 ? $ogType : 'website';
$articlePublished = isset($articlePublished) && is_string($articlePublished) ? $articlePublished : '';
$jsonLd = isset($jsonLd) && is_array($jsonLd) ? $jsonLd : [];
$preloadImage = isset($preloadImage) && is_string($preloadImage) ? ltrim(trim($preloadImage), '/') : '';
if ($preloadImage !== '' && (str_contains($preloadImage, '..') || preg_match('#^assets/(images|uploads)/[A-Za-z0-9_./-]+$#', $preloadImage) !== 1)) {
    $preloadImage = '';
}
$productOffer = isset($productOffer) && is_array($productOffer) ? $productOffer : [];
$offerPrice = (string) ($productOffer['price'] ?? '');
if (preg_match('/^\d+\.\d{2}$/', $offerPrice) !== 1) {
    $offerPrice = '';
}
if (!is_array($socials)) {
    $socials = [];
}
if (!is_array($contactButton)) {
    $contactButton = [];
}
$contactHref = trim((string) ($contactButton['url'] ?? ''));
if ($contactHref === '' || $contactHref === '#iletisim') {
    $contactButton['url'] = '/iletisim';
}
if (trim((string) ($contactButton['label'] ?? '')) === '') {
    $contactButton['label'] = 'İletişim';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <meta name="theme-color" content="<?= e($themeColor) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <link rel="sitemap" type="application/xml" title="Sitemap" href="<?= e(absolute_url('/sitemap.xml')) ?>">
    <?php if ($preloadImage !== ''): ?>
        <link rel="preload" as="image" href="<?= asset($preloadImage) ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:title" content="<?= e($documentTitle) ?>">
    <meta property="og:description" content="<?= e((string) $metaDescription) ?>">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <?php if ($ogType === 'article' && $articlePublished !== ''): ?>
        <meta property="article:published_time" content="<?= e($articlePublished) ?>">
    <?php endif; ?>
    <?php if ($ogType === 'product' && $offerPrice !== ''): ?>
        <meta property="product:price:amount" content="<?= e($offerPrice) ?>">
        <meta property="product:price:currency" content="TRY">
    <?php endif; ?>
    <?php if ($shareImage !== ''): ?>
        <meta property="og:image" content="<?= e(absolute_url('/' . ltrim($shareImage, '/'))) ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= e($documentTitle) ?>">
        <meta name="twitter:description" content="<?= e((string) $metaDescription) ?>">
        <meta name="twitter:image" content="<?= e(absolute_url('/' . ltrim($shareImage, '/'))) ?>">
    <?php endif; ?>
    <?php if ($jsonLd !== []): ?>
        <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <?php endif; ?>
    <link rel="icon" href="<?= asset($favicon) ?>" type="<?= e(favicon_type($favicon)) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= theme_file('css/main.css') ?>">
    <script>
        if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            document.documentElement.classList.add("motion-ok");
        }
    </script>
</head>
<body<?= !empty($bodyClass) ? ' class="' . e((string) $bodyClass) . '"' : '' ?>>
<a class="skip" href="#icerik">İçeriğe geç</a>
<header class="site-header" id="site-header">
    <div class="container header-bar">
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">
            <span></span><span></span><span></span>
            <span class="sr-only">Menü</span>
        </button>
        <nav class="nav" id="site-nav" aria-label="Ana menü">
            <?php
            $currentPath = current_path();
            $menuTree = MenuEditor::place('header');
            $menuCurrent = static function (array $item) use ($currentPath, &$menuCurrent): bool {
                if (nav_is_current((string) ($item['url'] ?? ''), $currentPath)) {
                    return true;
                }
                $children = $item['children'] ?? [];
                if (!is_array($children)) {
                    return false;
                }
                foreach ($children as $child) {
                    if (is_array($child) && $menuCurrent($child)) {
                        return true;
                    }
                }

                return false;
            };
            ?>
            <ul class="menu">
                <?php foreach ($menuTree as $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }
                    $children = is_array($item['children'] ?? null) ? $item['children'] : [];
                    $selfCurrent = nav_is_current((string) ($item['url'] ?? ''), $currentPath);
                    $isActive = $menuCurrent($item);
                    $mega = !empty($item['mega']) && $children !== [];
                    $blank = !empty($item['blank']);
                    $itemClass = 'menu-item' . ($children !== [] ? ' has-sub' : '') . ($mega ? ' is-mega' : '');
                    ?>
                    <li class="<?= e($itemClass) ?>">
                        <a class="menu-link<?= $isActive ? ' is-active' : '' ?>" href="<?= url((string) ($item['url'] ?? '#')) ?>"<?= $selfCurrent ? ' aria-current="page"' : '' ?><?= $children !== [] ? ' aria-haspopup="true"' : '' ?><?= $blank ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <?= e((string) ($item['label'] ?? '')) ?>
                            <?php if ($children !== []): ?><span class="caret" aria-hidden="true"></span><?php endif; ?>
                        </a>
                        <?php if ($mega): ?>
                            <div class="mega-panel">
                                <?php foreach ($children as $child): ?>
                                    <?php
                                    if (!is_array($child)) {
                                        continue;
                                    }
                                    $grand = is_array($child['children'] ?? null) ? $child['children'] : [];
                                    $childSelf = nav_is_current((string) ($child['url'] ?? ''), $currentPath);
                                    ?>
                                    <div class="mega-col">
                                        <a class="mega-title<?= $menuCurrent($child) ? ' is-active' : '' ?>" href="<?= url((string) ($child['url'] ?? '#')) ?>"<?= $childSelf ? ' aria-current="page"' : '' ?><?= !empty($child['blank']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e((string) ($child['label'] ?? '')) ?></a>
                                        <?php if ($grand !== []): ?>
                                            <ul class="mega-links">
                                                <?php foreach ($grand as $link): ?>
                                                    <?php
                                                    if (!is_array($link)) {
                                                        continue;
                                                    }
                                                    $linkCurrent = nav_is_current((string) ($link['url'] ?? ''), $currentPath);
                                                    ?>
                                                    <li><a class="<?= $linkCurrent ? 'is-active' : '' ?>" href="<?= url((string) ($link['url'] ?? '#')) ?>"<?= $linkCurrent ? ' aria-current="page"' : '' ?><?= !empty($link['blank']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e((string) ($link['label'] ?? '')) ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($children !== []): ?>
                            <ul class="submenu">
                                <?php foreach ($children as $child): ?>
                                    <?php
                                    if (!is_array($child)) {
                                        continue;
                                    }
                                    $grand = is_array($child['children'] ?? null) ? $child['children'] : [];
                                    $childSelf = nav_is_current((string) ($child['url'] ?? ''), $currentPath);
                                    ?>
                                    <li>
                                        <a class="<?= $menuCurrent($child) ? 'is-active' : '' ?>" href="<?= url((string) ($child['url'] ?? '#')) ?>"<?= $childSelf ? ' aria-current="page"' : '' ?><?= !empty($child['blank']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e((string) ($child['label'] ?? '')) ?></a>
                                        <?php if ($grand !== []): ?>
                                            <ul class="submenu">
                                                <?php foreach ($grand as $link): ?>
                                                    <?php
                                                    if (!is_array($link)) {
                                                        continue;
                                                    }
                                                    $linkCurrent = nav_is_current((string) ($link['url'] ?? ''), $currentPath);
                                                    ?>
                                                    <li><a class="<?= $linkCurrent ? 'is-active' : '' ?>" href="<?= url((string) ($link['url'] ?? '#')) ?>"<?= $linkCurrent ? ' aria-current="page"' : '' ?><?= !empty($link['blank']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e((string) ($link['label'] ?? '')) ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php $logoClass = 'brand-header'; require __DIR__ . '/logo.php'; ?>
        <div class="header-tools">
            <ul class="socials">
                <?php foreach ($socials as $social): ?>
                    <?php
                    if (!is_array($social) || trim((string) ($social['url'] ?? '')) === '') {
                        continue;
                    }
                    $socialUrl = (string) $social['url'];
                    $socialExternal = preg_match('#^https?://#i', $socialUrl) === 1;
                    ?>
                    <li>
                        <a href="<?= url($socialUrl) ?>" aria-label="<?= e((string) ($social['label'] ?? 'Sosyal')) ?>"<?= $socialExternal ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <?= icon((string) ($social['network'] ?? '')) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="account-link" href="<?= url(MemberAccount::check() ? '/hesabim' : '/uye-giris') ?>" aria-label="<?= MemberAccount::check() ? 'Hesabım' : 'Giriş' ?>"><?= icon('user') ?></a>
            <?php render_link($contactButton, 'btn btn-gold'); ?>
        </div>
    </div>
</header>
<main id="icerik">
