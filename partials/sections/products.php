<?php
require_once dirname(__DIR__, 2) . '/app/ProductEditor.php';

$motion = motion_config($data);
$productIndex = 0;
$useBlocks = (string) ($data['source'] ?? '') === 'products';
$columns = (int) ($data['columns'] ?? 3);
if ($columns < 2 || $columns > 6) {
    $columns = 3;
}
$slider = is_array($data['slider'] ?? null) ? $data['slider'] : [];
$effect = (string) ($slider['effect'] ?? 'slide');
if (!in_array($effect, ['slide', 'fade', 'none'], true)) {
    $effect = 'slide';
}
$step = ($slider['step'] ?? '1') === 'group' ? 'group' : '1';
$interval = max(3, min(12, (int) ($slider['interval'] ?? 5)));
$productItems = $useBlocks ? section_products($data) : [];
$productCategoryLabels = $useBlocks ? product_categories() : [];
$sliderOn = $useBlocks && !empty($slider['enabled']) && count($productItems) > $columns;
$pledgeOn = !array_key_exists('pledge_enabled', $data) || !empty($data['pledge_enabled']);
$pledgeSide = (string) ($data['pledge_side'] ?? 'left') === 'right' ? 'right' : 'left';
$productGridClass = 'products-grid' . ($pledgeOn ? ' has-pledge' : ' is-full');
if ($pledgeOn && $pledgeSide === 'right') {
    $productGridClass .= ' products-side-right';
}
?>
<section class="products" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <div class="container <?= e($productGridClass) ?>">
        <?php if ($pledgeOn): ?>
        <article class="pledge"<?= anim_bind($motion, 'pledge') ?>>
            <div class="pledge-media">
                <?= media_tag((string) ($data['pledge_image'] ?? ''), (string) ($data['pledge_alt'] ?? '')) ?>
            </div>
            <div class="pledge-body">
                <h2>
                    <?php foreach (($data['pledge_title'] ?? []) as $line): ?>
                        <span><?= e((string) $line) ?></span>
                    <?php endforeach; ?>
                </h2>
                <p><?= e((string) ($data['pledge_text'] ?? '')) ?></p>
                <?php render_link($data['pledge_button'] ?? null, 'btn btn-gold btn-sm'); ?>
            </div>
        </article>
        <?php endif; ?>
        <div class="products-main">
            <div class="products-intro"<?= anim_bind($motion, 'intro') ?>>
                <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
                <h2><?= title_spans($data) ?></h2>
                <?php if (!empty($data['text'])): ?><p class="lead"><?= e((string) $data['text']) ?></p><?php endif; ?>
                <?php render_link($data['button'] ?? null, 'btn btn-gold btn-sm'); ?>
            </div>
            <?php if ($useBlocks && $productItems === []): ?>
                <p class="blog-empty">Bu seçime uygun ürün bulunamadı.</p>
            <?php elseif ($sliderOn): ?>
                <div class="service-slider" data-service-slider data-cols="<?= $columns ?>" data-effect="<?= e($effect) ?>" data-step="<?= e($step) ?>" data-autoplay="<?= !empty($slider['autoplay']) ? '1' : '0' ?>" data-interval="<?= $interval * 1000 ?>" data-loop="<?= !array_key_exists('loop', $slider) || !empty($slider['loop']) ? '1' : '0' ?>" data-pause="<?= !array_key_exists('pause', $slider) || !empty($slider['pause']) ? '1' : '0' ?>" style="--service-cols: <?= $columns ?>">
                    <div class="service-viewport">
                        <div class="service-track">
                            <?php foreach ($productItems as $item): ?>
                                <?php if (!is_array($item)) { continue; } ?>
                                <?php require __DIR__ . '/product-card.php'; ?>
                                <?php $productIndex++; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (!isset($slider['arrows']) || !empty($slider['arrows']) || !isset($slider['dots']) || !empty($slider['dots'])): ?>
                        <div class="service-nav">
                            <?php if (!isset($slider['arrows']) || !empty($slider['arrows'])): ?>
                                <button class="service-arrow" type="button" data-service-prev aria-label="Önceki ürünler">‹</button>
                            <?php endif; ?>
                            <?php if (!isset($slider['dots']) || !empty($slider['dots'])): ?>
                                <div class="service-dots" data-service-dots></div>
                            <?php endif; ?>
                            <?php if (!isset($slider['arrows']) || !empty($slider['arrows'])): ?>
                                <button class="service-arrow" type="button" data-service-next aria-label="Sonraki ürünler">›</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="product-grid"<?= $useBlocks ? ' style="--product-cols: ' . $columns . '"' : '' ?>>
                    <?php if ($useBlocks): ?>
                        <?php foreach ($productItems as $item): ?>
                            <?php if (!is_array($item)) { continue; } ?>
                            <?php require __DIR__ . '/product-card.php'; ?>
                            <?php $productIndex++; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach (($data['items'] ?? []) as $item): ?>
                            <?php if (!is_array($item)) { continue; } ?>
                            <article class="product-card"<?= anim_bind($motion, 'items', $productIndex) ?>>
                                <?= media_tag((string) ($item['image'] ?? ''), (string) ($item['image_alt'] ?? '')) ?>
                                <div class="product-body">
                                    <h3><?= e((string) ($item['title'] ?? '')) ?></h3>
                                    <ul>
                                        <?php foreach (($item['points'] ?? []) as $point): ?>
                                            <li><span aria-hidden="true"><?= icon('check') ?></span><?= e((string) $point) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </article>
                            <?php $productIndex++; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
