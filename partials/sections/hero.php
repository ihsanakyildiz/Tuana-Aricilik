<?php
$deck = hero_normalize($data);
$slider = $deck['slider'];
$slides = [];
foreach ($deck['slides'] as $slide) {
    if (!is_array($slide)) {
        continue;
    }
    $flag = $slide['active'] ?? true;
    if ($flag === true || $flag === 1 || $flag === '1') {
        $slides[] = $slide;
    }
}
if ($slides === []) {
    return;
}
$many = count($slides) > 1;
$effect = (string) $slider['effect'];
?>
<section class="hero-deck" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= $many ? ' data-hero-slider data-effect="' . e($effect) . '" data-interval="' . ((int) $slider['interval'] * 1000) . '" data-autoplay="' . (!empty($slider['autoplay']) ? '1' : '0') . '"' : '' ?>>
    <div class="hero-track">
        <?php foreach ($slides as $index => $slide): ?>
            <?php
            $motion = $index === 0 ? motion_config($data) : ['slots' => []];
            $media = is_array($slide['media'] ?? null) ? $slide['media'] : [];
            $layout = (string) ($media['layout'] ?? 'side');
            if (!in_array($layout, ['side', 'cover', 'video'], true)) {
                $layout = 'side';
            }
            $imagePath = trim((string) ($media['image'] ?? $slide['image'] ?? ''));
            $video = trim((string) ($media['video'] ?? ''));
            if ($layout === 'video' && $video === '') {
                $layout = $imagePath !== '' ? 'cover' : 'side';
            }
            if ($layout === 'cover' && $imagePath === '') {
                $layout = 'side';
            }
            $align = ($media['align'] ?? 'right') === 'left' ? 'left' : 'right';
            $tone = ($media['tone'] ?? 'light') === 'dark' ? 'dark' : 'light';
            $overlay = max(0, min(80, (int) ($media['overlay'] ?? 46))) / 100;
            $focalX = max(0, min(100, (int) ($media['focal_x'] ?? 50)));
            $focalY = max(0, min(100, (int) ($media['focal_y'] ?? 45)));
            $classes = 'hero hero-slide' . ($index === 0 ? ' is-on' : '');
            if ($layout === 'side' && $align === 'left') {
                $classes .= ' hero-align-left';
            }
            if ($layout === 'cover' || $layout === 'video') {
                $classes .= ' hero-cover hero-tone-' . $tone;
            }
            $sizes = $layout === 'side' ? '(max-width: 860px) 92vw, 46vw' : '100vw';
            $videoType = strtolower(pathinfo($video, PATHINFO_EXTENSION)) === 'webm' ? 'video/webm' : 'video/mp4';
            $type = is_array($slide['type'] ?? null) ? $slide['type'] : [];
            $css = hero_type_attr($type);
            if ($motion['slots'] !== []) {
                $css .= ($css !== '' ? ';' : '')
                    . '--anim-distance:' . (int) $motion['distance']
                    . 'px;--anim-duration:' . (int) $motion['duration']
                    . 'ms;--anim-step:' . (int) $motion['stagger'] . 'ms';
            }
            $pictureData = $slide;
            $pictureData['media'] = $media;
            $pictureData['image_alt'] = (string) ($slide['image_alt'] ?? '');
            ?>
            <article class="<?= e($classes) ?>" data-slide<?= $css !== '' ? ' style="' . e($css) . '"' : '' ?>>
                <?php if ($layout === 'cover' || $layout === 'video'): ?>
                    <div class="hero-bg" aria-hidden="true">
                        <?php if ($layout === 'video'): ?>
                            <video class="hero-video"<?= $index === 0 ? ' autoplay' : '' ?> muted loop playsinline preload="metadata"<?= $imagePath !== '' ? ' poster="' . asset($imagePath) . '"' : '' ?> style="object-position:<?= $focalX ?>% <?= $focalY ?>%">
                                <source src="<?= asset($video) ?>" type="<?= e($videoType) ?>">
                            </video>
                        <?php else: ?>
                            <?= hero_picture($pictureData, $sizes) ?>
                        <?php endif; ?>
                        <span class="hero-scrim" style="opacity:<?= e((string) $overlay) ?>"></span>
                    </div>
                <?php endif; ?>
                <div class="container hero-grid">
                    <div class="hero-copy"<?= $index === 0 ? anim_bind($motion, 'copy') : '' ?>>
                        <h1><?= title_spans($slide) ?></h1>
                        <?php if (!empty($slide['text'])): ?><p><?= e((string) $slide['text']) ?></p><?php endif; ?>
                        <div class="hero-actions">
                            <?php render_link(is_array($slide['primary_button'] ?? null) ? $slide['primary_button'] : null, 'btn btn-gold'); ?>
                            <?php render_link(is_array($slide['secondary_button'] ?? null) ? $slide['secondary_button'] : null, 'btn btn-line'); ?>
                        </div>
                    </div>
                    <?php if ($layout === 'side'): ?>
                        <div class="hero-media"<?= $index === 0 ? anim_bind($motion, 'media') : '' ?>>
                            <?= hero_picture($pictureData, $sizes) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($many): ?>
        <div class="hero-controls">
            <button class="hero-arrow" type="button" data-hero-prev aria-label="Önceki karşılama"></button>
            <div class="hero-dots">
                <?php foreach ($slides as $index => $slide): ?>
                    <button class="hero-dot" type="button" data-hero-dot="<?= (int) $index ?>" aria-label="Karşılama <?= (int) $index + 1 ?>"<?= $index === 0 ? ' aria-current="true"' : '' ?>></button>
                <?php endforeach; ?>
            </div>
            <button class="hero-arrow hero-arrow-next" type="button" data-hero-next aria-label="Sonraki karşılama"></button>
        </div>
    <?php endif; ?>
</section>
