<?php $motion = motion_config($data); ?>
<section class="about" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <div class="hex-deco" aria-hidden="true">
        <svg viewBox="0 0 220 180" fill="none">
            <path d="M0-30 26-15 26 15 0 30-26 15-26-15Z" transform="translate(148 42)" fill="#f6e2b0"/>
            <path d="M0-30 26-15 26 15 0 30-26 15-26-15Z" transform="translate(104 78) scale(0.72)" stroke="#e2a93b" stroke-width="2.4"/>
            <path d="M0-30 26-15 26 15 0 30-26 15-26-15Z" transform="translate(176 86) scale(0.78)" stroke="#f0c35a" stroke-width="2.4"/>
            <path d="M0-30 26-15 26 15 0 30-26 15-26-15Z" transform="translate(132 118)" fill="#f0c35a"/>
            <path d="M0-30 26-15 26 15 0 30-26 15-26-15Z" transform="translate(78 132) scale(0.7)" stroke="#f0c35a" stroke-width="2.4"/>
            <path d="M0-30 26-15 26 15 0 30-26 15-26-15Z" transform="translate(188 140) scale(0.66)" stroke="#e2a93b" stroke-width="2.4"/>
            <path d="M0-30 26-15 26 15 0 30-26 15-26-15Z" transform="translate(154 162) scale(0.48)" fill="#f8e7bc"/>
        </svg>
    </div>
    <div class="container about-grid">
        <div class="about-media"<?= anim_bind($motion, 'media') ?>>
            <?= media_tag((string) ($data['image'] ?? ''), (string) ($data['image_alt'] ?? '')) ?>
            <div class="year-badge">
                <strong><?= e((string) ($data['badge_value'] ?? '')) ?></strong>
                <span><?= e((string) ($data['badge_label'] ?? '')) ?></span>
            </div>
        </div>
        <div class="about-copy"<?= anim_bind($motion, 'copy') ?>>
            <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
            <h2><?= title_spans($data) ?></h2>
            <?php if (!empty($data['text'])): ?><p><?= e((string) $data['text']) ?></p><?php endif; ?>
            <?php if (!empty($data['text_extra'])): ?><p><?= e((string) $data['text_extra']) ?></p><?php endif; ?>
            <?php render_link($data['button'] ?? null, 'btn btn-gold'); ?>
        </div>
    </div>
</section>
