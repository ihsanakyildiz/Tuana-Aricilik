<?php $motion = motion_config($data); $reviewIndex = 0; ?>
<section class="testimonials" id="<?= e((string) ($data['anchor'] ?? $sectionKey)) ?>"<?= motion_vars($motion) ?>>
    <div class="container">
        <header class="section-head reviews-head"<?= anim_bind($motion, 'head') ?>>
            <?php if (!empty($data['eyebrow'])): ?><p class="eyebrow"><?= e((string) $data['eyebrow']) ?></p><?php endif; ?>
            <h2><?= title_spans($data) ?></h2>
            <?php if (!empty($data['text'])): ?><p><?= e((string) $data['text']) ?></p><?php endif; ?>
            <span class="quote-mark" aria-hidden="true">”</span>
        </header>
    </div>
    <div class="review-slider" data-slider>
        <button class="slider-btn prev" type="button" aria-label="Önceki yorum"><?= icon('arrow') ?></button>
        <div class="review-viewport">
            <div class="review-track">
                <?php foreach (($data['items'] ?? []) as $item): ?>
                    <?php if (!is_array($item) || (array_key_exists('active', $item) && empty($item['active']))) { continue; } ?>
                    <article class="review-card"<?= anim_bind($motion, 'items', $reviewIndex) ?>>
                        <p>“<?= e((string) ($item['quote'] ?? '')) ?>”</p>
                        <div class="reviewer">
                            <span class="avatar tone-<?= (int) ($item['tone'] ?? 1) ?>"><?= e((string) ($item['initials'] ?? '')) ?></span>
                            <div>
                                <strong><?= e((string) ($item['name'] ?? '')) ?></strong>
                                <span><?= e((string) ($item['role'] ?? '')) ?></span>
                            </div>
                        </div>
                    </article>
                    <?php $reviewIndex++; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="slider-btn next" type="button" aria-label="Sonraki yorum"><?= icon('arrow') ?></button>
    </div>
</section>
