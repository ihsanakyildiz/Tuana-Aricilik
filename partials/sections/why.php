<?php
$motion = motion_config($data);
$whyIndex = 0;
$align = ($data['align'] ?? 'right') === 'left' ? 'left' : 'right';
?>
<section class="why why-align-<?= e($align) ?>" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <div class="why-photo"<?= anim_bind($motion, 'photo') ?>>
        <?= media_tag((string) ($data['image'] ?? ''), (string) ($data['image_alt'] ?? ''), '', true) ?>
    </div>
    <div class="container">
        <div class="why-card"<?= anim_bind($motion, 'card') ?>>
            <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
            <h2><?= title_spans($data) ?></h2>
            <?php if (!empty($data['text'])): ?><p><?= e((string) $data['text']) ?></p><?php endif; ?>
            <ul class="why-list">
                <?php foreach (($data['items'] ?? []) as $item): ?>
                    <?php if (!is_array($item) || (array_key_exists('active', $item) && empty($item['active']))) { continue; } ?>
                    <li<?= anim_bind($motion, 'items', $whyIndex) ?>>
                        <span class="tick" aria-hidden="true"><?= icon('check') ?></span>
                        <div>
                            <h3><?= e((string) ($item['title'] ?? '')) ?></h3>
                            <p><?= e((string) ($item['text'] ?? '')) ?></p>
                        </div>
                    </li>
                    <?php $whyIndex++; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
