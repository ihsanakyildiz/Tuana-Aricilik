<?php
if (!is_array($item)) {
    return;
}
?>
<article class="post">
    <a class="post-media" href="<?= url((string) ($item['url'] ?? '#blog')) ?>"<?= anim_bind($motion, 'media', $postIndex) ?>>
        <?= media_tag((string) ($item['image'] ?? ''), (string) ($item['image_alt'] ?? '')) ?>
    </a>
    <div class="post-body"<?= anim_bind($motion, 'body', $postIndex) ?>>
        <h3><a href="<?= url((string) ($item['url'] ?? '#blog')) ?>"><?= e((string) ($item['title'] ?? '')) ?></a></h3>
        <p class="meta"><?= e((string) ($item['date'] ?? '')) ?> / <?= e((string) ($item['comments'] ?? '')) ?></p>
        <p><?= e((string) ($item['excerpt'] ?? '')) ?></p>
    </div>
</article>
