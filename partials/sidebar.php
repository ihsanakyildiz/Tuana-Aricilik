<?php

declare(strict_types=1);

$latestPosts = [];
foreach (blocks_of_type('post') as $item) {
    if (($item['_key'] ?? '') === ($post['_key'] ?? '')) {
        continue;
    }
    $latestPosts[] = $item;
    if (count($latestPosts) === 4) {
        break;
    }
}
$categories = post_categories();
$promo = setting('sidebar_promo', []);
if (!is_array($promo)) {
    $promo = [];
}
$filters = request_post_filters();
?>
<aside class="sidebar" aria-label="Yazı kenar çubuğu">
    <form class="side-search" method="get" action="<?= url('/blog') ?>">
        <label class="sr-only" for="blog-search">Yazılarda ara</label>
        <input id="blog-search" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Ara...">
        <button type="submit" aria-label="Ara"><?= icon('search') ?></button>
    </form>
    <?php if ($latestPosts !== []): ?>
        <section class="side-block">
            <h2>Son Yazılar</h2>
            <ul class="latest-list">
                <?php foreach ($latestPosts as $item): ?>
                    <li>
                        <a class="latest-item" href="<?= url((string) ($item['url'] ?? '/blog')) ?>">
                            <?= media_tag((string) ($item['image'] ?? ''), (string) ($item['image_alt'] ?? '')) ?>
                            <span>
                                <strong><?= e((string) ($item['title'] ?? '')) ?></strong>
                                <em><?= e((string) ($item['date'] ?? '')) ?> / <?= e((string) ($item['comments'] ?? '')) ?></em>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
    <?php if ($categories !== []): ?>
        <section class="side-block">
            <h2>Kategoriler</h2>
            <ul class="cat-list">
                <?php foreach ($categories as $key => $label): ?>
                    <li>
                        <a href="<?= url(category_url((string) $key)) ?>">
                            <?= icon('chevron') ?><span><?= e($label) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
    <?php if (trim((string) ($promo['image'] ?? '')) !== ''): ?>
        <a class="side-promo" href="<?= url((string) ($promo['url'] ?? '/')) ?>">
            <?= media_tag((string) $promo['image'], (string) ($promo['image_alt'] ?? ''), '', true) ?>
            <strong><?= e((string) ($promo['title'] ?? '')) ?></strong>
        </a>
    <?php endif; ?>
</aside>
