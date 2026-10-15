<?php $motion = motion_config($data); ?>
<section class="promo" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>" data-promo<?= motion_vars($motion) ?>>
    <div class="promo-pin">
        <div class="promo-photo">
            <?= media_tag((string) ($data['image'] ?? ''), (string) ($data['image_alt'] ?? ''), 'promo-bg', true) ?>
        </div>
        <div class="promo-slide">
            <div class="container promo-copy"<?= anim_bind($motion, 'copy') ?>>
                <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
                <h2><?= title_spans($data) ?></h2>
                <?php if (!empty($data['text'])): ?><p><?= e((string) $data['text']) ?></p><?php endif; ?>
                <?php render_link($data['button'] ?? null, 'btn btn-gold'); ?>
            </div>
        </div>
    </div>
</section>
