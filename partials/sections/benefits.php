<?php $motion = motion_config($data); $barIndex = 0; ?>
<section class="benefits" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <div class="container benefits-grid">
        <div class="benefits-copy"<?= anim_bind($motion, 'copy') ?>>
            <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
            <h2><?= title_spans($data) ?></h2>
            <?php if (!empty($data['text'])): ?><p><?= e((string) $data['text']) ?></p><?php endif; ?>
        </div>
        <div class="benefits-photo">
            <?= media_tag((string) ($data['image'] ?? ''), (string) ($data['image_alt'] ?? ''), '', true, anim_bind($motion, 'photo')) ?>
        </div>
        <div class="bars" data-bars>
            <?php foreach (($data['bars'] ?? []) as $bar): ?>
                <?php if (!is_array($bar) || (array_key_exists('active', $bar) && empty($bar['active']))) { continue; } ?>
                <?php $value = max(0, min(100, (int) ($bar['value'] ?? 0))); ?>
                <div class="bar"<?= anim_bind($motion, 'bars', $barIndex) ?>>
                    <div class="bar-meta">
                        <span><?= e((string) ($bar['label'] ?? '')) ?></span>
                        <strong><?= $value ?>%</strong>
                    </div>
                    <div class="bar-track"><span style="--w: <?= $value ?>%"></span></div>
                </div>
                <?php $barIndex++; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
