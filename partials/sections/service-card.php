<article class="service-card"<?= anim_bind($motion, 'items', $serviceIndex) ?>>
    <div class="service-media">
        <?= media_tag((string) ($item['image'] ?? ''), (string) ($item['image_alt'] ?? '')) ?>
        <h3><?= e((string) ($item['title'] ?? '')) ?></h3>
    </div>
    <div class="service-body">
        <p><?= e((string) ($item['text'] ?? '')) ?></p>
        <?php render_link($item['button'] ?? null, 'btn btn-gold btn-sm'); ?>
    </div>
</article>
