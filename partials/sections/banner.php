<?php $motion = motion_config($data); ?>
<section class="banner" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>" data-banner<?= motion_vars($motion) ?>>
    <div class="banner-pin">
        <div class="banner-photo">
            <?= media_tag((string) ($data['image'] ?? ''), (string) ($data['image_alt'] ?? ''), 'banner-bg', true) ?>
            <div class="banner-shade"></div>
        </div>
        <div class="banner-slide">
            <div class="container banner-copy"<?= anim_bind($motion, 'copy') ?>>
                <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow light"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
                <h2><?= title_spans($data) ?></h2>
                <?php if (!empty($data['text'])): ?><p><?= e((string) $data['text']) ?></p><?php endif; ?>
                <?php render_link($data['button'] ?? null, 'btn btn-gold'); ?>
            </div>
        </div>
    </div>
</section>
