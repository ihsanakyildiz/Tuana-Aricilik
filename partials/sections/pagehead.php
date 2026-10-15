<?php

declare(strict_types=1);

$motion = motion_config($data);
$crumbs = is_array($data['crumbs'] ?? null) ? $data['crumbs'] : [];
?>
<section class="page-banner" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <?= media_tag((string) ($data['image'] ?? ''), (string) ($data['image_alt'] ?? ''), 'page-banner-photo', false, anim_bind($motion, 'media')) ?>
    <div class="page-banner-shade"></div>
    <div class="container page-banner-copy"<?= anim_bind($motion, 'copy') ?>>
        <?php if ($crumbs !== []): ?>
            <p class="crumbs">
                <?php foreach ($crumbs as $index => $crumb): ?>
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
        <h1><?= e((string) ($data['title'] ?? '')) ?></h1>
        <?php $meta = is_array($data['meta'] ?? null) ? $data['meta'] : []; ?>
        <?php if ($meta !== []): ?>
            <ul class="banner-meta">
                <?php foreach ($meta as $item): ?>
                    <?php if (!is_array($item) || trim((string) ($item['text'] ?? '')) === '') { continue; } ?>
                    <li><?= icon((string) ($item['icon'] ?? '')) ?><span><?= e((string) $item['text']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
