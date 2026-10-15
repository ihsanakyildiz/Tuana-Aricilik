<?php

declare(strict_types=1);

$html = trim((string) ($page['body_html'] ?? ''));
?>
<article class="classic-page">
    <div class="container">
        <div class="article-copy">
            <?php if ($html !== ''): ?>
                <?= post_body_html($html) ?>
            <?php endif; ?>
        </div>
    </div>
</article>
