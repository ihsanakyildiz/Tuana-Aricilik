<?php

declare(strict_types=1);

$shopCrumbs = is_array($shopCrumbs ?? null) ? $shopCrumbs : [];
?>
<section class="page-banner">
    <img class="page-banner-photo" src="<?= asset('assets/images/banner.jpg') ?>" alt="" loading="eager" decoding="async">
    <div class="page-banner-shade"></div>
    <div class="container page-banner-copy">
        <?php if ($shopCrumbs !== []): ?>
            <p class="crumbs">
                <?php foreach ($shopCrumbs as $index => $crumb): ?>
                    <?php if (!is_array($crumb)) { continue; } ?>
                    <?php if ($index > 0): ?><span class="sep" aria-hidden="true">›</span><?php endif; ?>
                    <?php $crumbUrl = trim((string) ($crumb['url'] ?? '')); ?>
                    <?php if ($crumbUrl !== ''): ?>
                        <a href="<?= url($crumbUrl) ?>"><?= e((string) ($crumb['label'] ?? '')) ?></a>
                    <?php else: ?>
                        <span aria-current="page"><?= e((string) ($crumb['label'] ?? '')) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <h1><?= e((string) ($shopTitle ?? '')) ?></h1>
    </div>
</section>
