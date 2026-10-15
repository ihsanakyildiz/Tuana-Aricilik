<?php
$motion = motion_config($data);
$serviceIndex = 0;
$columns = (int) ($data['columns'] ?? 4);
if ($columns < 2 || $columns > 6) {
    $columns = 4;
}
$slider = is_array($data['slider'] ?? null) ? $data['slider'] : [];
$items = [];
foreach (($data['items'] ?? []) as $item) {
    if (!is_array($item)) {
        continue;
    }
    if (array_key_exists('active', $item) && empty($item['active'])) {
        continue;
    }
    $items[] = $item;
}
$sliderOn = !empty($slider['enabled']) && count($items) > $columns;
$effect = (string) ($slider['effect'] ?? 'slide');
if (!in_array($effect, ['slide', 'fade', 'none'], true)) {
    $effect = 'slide';
}
$step = ($slider['step'] ?? '1') === 'group' ? 'group' : '1';
$interval = max(3, min(12, (int) ($slider['interval'] ?? 5)));
?>
<section class="services" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <div class="container">
        <header class="section-head"<?= anim_bind($motion, 'head') ?>>
            <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
            <h2><?= title_spans($data) ?></h2>
            <?php if (!empty($data['text'])): ?><p><?= e((string) $data['text']) ?></p><?php endif; ?>
        </header>
        <?php if ($sliderOn): ?>
            <div class="service-slider" data-service-slider data-cols="<?= $columns ?>" data-effect="<?= e($effect) ?>" data-step="<?= e($step) ?>" data-autoplay="<?= !empty($slider['autoplay']) ? '1' : '0' ?>" data-interval="<?= $interval * 1000 ?>" data-loop="<?= !array_key_exists('loop', $slider) || !empty($slider['loop']) ? '1' : '0' ?>" data-pause="<?= !array_key_exists('pause', $slider) || !empty($slider['pause']) ? '1' : '0' ?>" style="--service-cols: <?= $columns ?>">
                <div class="service-viewport">
                    <div class="service-track">
                        <?php foreach ($items as $item): ?>
                            <?php require __DIR__ . '/service-card.php'; $serviceIndex++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (!isset($slider['arrows']) || !empty($slider['arrows']) || !isset($slider['dots']) || !empty($slider['dots'])): ?>
                    <div class="service-nav">
                        <?php if (!isset($slider['arrows']) || !empty($slider['arrows'])): ?>
                            <button class="service-arrow" type="button" data-service-prev aria-label="Önceki hizmet">‹</button>
                        <?php endif; ?>
                        <?php if (!isset($slider['dots']) || !empty($slider['dots'])): ?>
                            <div class="service-dots" data-service-dots></div>
                        <?php endif; ?>
                        <?php if (!isset($slider['arrows']) || !empty($slider['arrows'])): ?>
                            <button class="service-arrow" type="button" data-service-next aria-label="Sonraki hizmet">›</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="service-grid" style="--service-cols: <?= $columns ?>">
                <?php foreach ($items as $item): ?>
                    <?php require __DIR__ . '/service-card.php'; $serviceIndex++; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
