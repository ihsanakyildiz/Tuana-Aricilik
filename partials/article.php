<?php

declare(strict_types=1);

$paragraphs = [];
foreach (($post['body'] ?? []) as $paragraph) {
    $text = trim((string) $paragraph);
    if ($text !== '') {
        $paragraphs[] = $text;
    }
}
$figure = is_array($post['figure'] ?? null) ? $post['figure'] : [];
$tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];
$shareTarget = absolute_url('/yazi/' . (string) ($post['_key'] ?? ''));
$shareTitle = (string) ($post['title'] ?? '');
$shares = [
    ['icon' => 'facebook', 'label' => 'Facebook', 'url' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareTarget)],
    ['icon' => 'twitter', 'label' => 'X', 'url' => 'https://twitter.com/intent/tweet?url=' . rawurlencode($shareTarget) . '&text=' . rawurlencode($shareTitle)],
    ['icon' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($shareTarget)],
    ['icon' => 'pinterest', 'label' => 'Pinterest', 'url' => 'https://pinterest.com/pin/create/button/?url=' . rawurlencode($shareTarget) . '&description=' . rawurlencode($shareTitle)],
];
?>
<article class="article">
    <div class="container article-layout">
        <div class="article-main">
            <div class="article-photo">
                <?= media_tag((string) ($post['image'] ?? ''), (string) ($post['image_alt'] ?? ''), '', false) ?>
            </div>
            <p class="byline">Yazan : <?= e((string) ($post['author'] ?? 'Tuana Arıcılık')) ?></p>
            <div class="article-copy">
                <?php $bodyHtml = trim((string) ($post['body_html'] ?? '')); ?>
                <?php if ($bodyHtml !== ''): ?>
                    <?= post_body_html($bodyHtml) ?>
                    <?php $figurePath = trim((string) ($figure['image'] ?? '')); ?>
                    <?php if ($figurePath !== '' && !str_contains($bodyHtml, $figurePath)): ?>
                        <figure class="article-figure">
                            <?= media_tag($figurePath, (string) ($figure['image_alt'] ?? '')) ?>
                            <?php if (trim((string) ($figure['caption'] ?? '')) !== ''): ?>
                                <figcaption><?= e((string) $figure['caption']) ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endif; ?>
                <?php else: ?>
                    <?php foreach ($paragraphs as $index => $paragraph): ?>
                        <p><?= e($paragraph) ?></p>
                        <?php if ($index === 0 && trim((string) ($figure['image'] ?? '')) !== ''): ?>
                            <figure class="article-figure">
                                <?= media_tag((string) $figure['image'], (string) ($figure['image_alt'] ?? '')) ?>
                                <?php if (trim((string) ($figure['caption'] ?? '')) !== ''): ?>
                                    <figcaption><?= e((string) $figure['caption']) ?></figcaption>
                                <?php endif; ?>
                            </figure>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="article-foot">
                <p class="post-tags">
                    <span>Etiketler :</span>
                    <?php if ($tags === []): ?>
                        <em>Etiket yok</em>
                    <?php else: ?>
                        <?php
                        $tagLinks = [];
                        foreach ($tags as $tag) {
                            $tagLinks[] = '<a href="' . url('/blog?q=' . rawurlencode((string) $tag)) . '">' . e((string) $tag) . '</a>';
                        }
                        echo implode(', ', $tagLinks);
                        ?>
                    <?php endif; ?>
                </p>
                <p class="share">
                    <span>Paylaş :</span>
                    <?php foreach ($shares as $share): ?>
                        <a href="<?= e($share['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($share['label']) ?>"><?= icon($share['icon']) ?></a>
                    <?php endforeach; ?>
                </p>
            </div>
        </div>
        <?php require __DIR__ . '/sidebar.php'; ?>
    </div>
</article>
