<?php
$motion = motion_config($data);
$postIndex = 0;
$columns = (int) ($data['columns'] ?? 2);
if ($columns < 2 || $columns > 6) {
    $columns = 2;
}
$slider = is_array($data['slider'] ?? null) ? $data['slider'] : [];
$effect = (string) ($slider['effect'] ?? 'slide');
if (!in_array($effect, ['slide', 'fade', 'none'], true)) {
    $effect = 'slide';
}
$step = ($slider['step'] ?? '1') === 'group' ? 'group' : '1';
$interval = max(3, min(12, (int) ($slider['interval'] ?? 5)));
$showBlogHead = trim((string) ($data['eyebrow'] ?? '')) !== ''
    || trim((string) ($data['title'] ?? '')) !== ''
    || trim((string) (($data['button']['label'] ?? ''))) !== '';
$postFilters = ['q' => '', 'category' => ''];
$blogPath = current_path();
$onBlog = (string) ($data['source'] ?? '') === 'posts'
    && ($blogPath === '/blog' || str_starts_with($blogPath, '/blog/kategori/'));
$postItems = section_posts($data, $onBlog);
if ($onBlog) {
    $postFilters = request_post_filters();
    if ($postFilters['q'] !== '' || $postFilters['category'] !== '') {
        $postItems = filter_posts($postItems, $postFilters);
    }
}
$blogCategories = $onBlog ? post_categories() : [];
$sliderOn = !empty($slider['enabled']) && count($postItems) > $columns;
?>
<section class="blog" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <div class="container">
        <?php if ($showBlogHead): ?>
        <header class="blog-head"<?= anim_bind($motion, 'head') ?>>
            <div>
                <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
                <h2><?= e((string) ($data['title'] ?? '')) ?></h2>
            </div>
            <?php render_link($data['button'] ?? null, 'btn btn-gold'); ?>
        </header>
        <?php endif; ?>
        <?php if ($blogCategories !== []): ?>
            <nav class="blog-cats" aria-label="Kategoriler">
                <?php foreach ($blogCategories as $catKey => $catLabel): ?>
                    <?php $catCurrent = (string) $catKey === (string) $postFilters['category']; ?>
                    <a href="<?= url(category_url((string) $catKey)) ?>"<?= $catCurrent ? ' aria-current="page"' : '' ?>><?= e($catLabel) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <?php if ($postFilters['q'] !== ''): ?>
            <p class="filter-note">Arama: <?= e($postFilters['q']) ?></p>
        <?php endif; ?>
        <?php if ($postItems === []): ?>
            <p class="blog-empty">Bu seçime uygun yazı bulunamadı.</p>
        <?php elseif ($sliderOn): ?>
            <div class="service-slider" data-service-slider data-cols="<?= $columns ?>" data-effect="<?= e($effect) ?>" data-step="<?= e($step) ?>" data-autoplay="<?= !empty($slider['autoplay']) ? '1' : '0' ?>" data-interval="<?= $interval * 1000 ?>" data-loop="<?= !array_key_exists('loop', $slider) || !empty($slider['loop']) ? '1' : '0' ?>" data-pause="<?= !array_key_exists('pause', $slider) || !empty($slider['pause']) ? '1' : '0' ?>" style="--service-cols: <?= $columns ?>">
                <div class="service-viewport">
                    <div class="service-track">
                        <?php foreach ($postItems as $item): ?>
                            <?php if (!is_array($item)) { continue; } ?>
                            <?php require __DIR__ . '/post-card.php'; ?>
                            <?php $postIndex++; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (!isset($slider['arrows']) || !empty($slider['arrows']) || !isset($slider['dots']) || !empty($slider['dots'])): ?>
                    <div class="service-nav">
                        <?php if (!isset($slider['arrows']) || !empty($slider['arrows'])): ?>
                            <button class="service-arrow" type="button" data-service-prev aria-label="Önceki yazılar">‹</button>
                        <?php endif; ?>
                        <?php if (!isset($slider['dots']) || !empty($slider['dots'])): ?>
                            <div class="service-dots" data-service-dots></div>
                        <?php endif; ?>
                        <?php if (!isset($slider['arrows']) || !empty($slider['arrows'])): ?>
                            <button class="service-arrow" type="button" data-service-next aria-label="Sonraki yazılar">›</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="blog-grid" style="--blog-cols: <?= $columns ?>">
                <?php foreach ($postItems as $item): ?>
                    <?php if (!is_array($item)) { continue; } ?>
                    <?php require __DIR__ . '/post-card.php'; ?>
                    <?php $postIndex++; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
