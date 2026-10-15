<?php $motion = motion_config($data); $featureIndex = 0; ?>
<section class="features" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <div class="container feature-grid">
        <?php foreach (section_items($data, 'card') as $item): ?>
            <?php if (!is_array($item)) { continue; } ?>
            <article class="feature-card"<?= anim_bind($motion, 'items', $featureIndex) ?>>
                <span class="feature-icon" aria-hidden="true"><?= icon((string) ($item['icon'] ?? 'jar')) ?></span>
                <h2><?= e((string) ($item['title'] ?? '')) ?></h2>
                <p><?= e((string) ($item['text'] ?? '')) ?></p>
                <?php render_link($item['button'] ?? null, 'btn btn-gold btn-sm'); ?>
            </article>
            <?php $featureIndex++; ?>
        <?php endforeach; ?>
    </div>
</section>
