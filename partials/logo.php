<?php

declare(strict_types=1);

$logoClass = $logoClass ?? '';
$siteName = (string) setting('site_name', 'Tuana Arıcılık');
$logoFile = trim((string) setting('logo', ''));
?>
<a class="brand <?= e($logoClass) ?>" href="<?= url('/') ?>" aria-label="<?= e($siteName) ?>">
    <?php if ($logoFile !== ''): ?>
        <img class="brand-image" src="<?= asset($logoFile) ?>" alt="<?= e($siteName) ?>">
    <?php else: ?>
    <span class="brand-badge">
        <span class="brand-script">Tuana</span>
        <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 64 24">
                <path d="M6 16c6-9 12-9 16 0" fill="none" stroke="currentColor" stroke-width="1.4"/>
                <path d="M42 16c6-9 12-9 16 0" fill="none" stroke="currentColor" stroke-width="1.4"/>
                <ellipse cx="32" cy="13" rx="7" ry="5" fill="none" stroke="currentColor" stroke-width="1.4"/>
                <path d="M29 13h6M32 10v6" stroke="currentColor" stroke-width="1.2"/>
            </svg>
        </span>
        <span class="brand-ribbon">Arıcılık</span>
    </span>
    <?php endif; ?>
</a>
