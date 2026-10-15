<?php
if (!is_array($item)) {
    return;
}
$selling = (string) ($item['discount_price'] ?? '') !== '' ? (string) $item['discount_price'] : (string) ($item['price'] ?? '');
$was = (string) ($item['discount_price'] ?? '') !== '' ? (string) ($item['price'] ?? '') : '';
$tracked = !empty($item['stock_track']);
$available = !$tracked || (int) ($item['stock_qty'] ?? 0) > 0;
$categoryLabel = (string) ($productCategoryLabels[(string) ($item['category_key'] ?? '')] ?? '');
$productUrl = (string) ($item['url'] ?? '#urunler');
?>
<article class="product-card"<?= anim_bind($motion, 'items', $productIndex) ?>>
    <a href="<?= url($productUrl) ?>">
        <?php if ((string) ($item['image'] ?? '') !== ''): ?>
            <?= media_tag((string) $item['image'], (string) ($item['image_alt'] ?? $item['title'] ?? '')) ?>
        <?php else: ?>
            <span class="product-photo-empty" aria-hidden="true"></span>
        <?php endif; ?>
        <div class="product-body">
            <?php if ($categoryLabel !== ''): ?><p class="eyebrow"><?= e($categoryLabel) ?></p><?php endif; ?>
            <h3><?= e((string) ($item['title'] ?? '')) ?></h3>
            <?php if ($selling !== ''): ?>
                <p class="product-price">
                    <?= e(ProductEditor::moneyLabel($selling)) ?>
                    <?php if ($was !== ''): ?><s><?= e(ProductEditor::moneyLabel($was)) ?></s><?php endif; ?>
                </p>
            <?php endif; ?>
            <p class="shop-stock<?= $available ? '' : ' is-out' ?>"><?= $available ? 'Stokta' : 'Tükendi' ?></p>
        </div>
    </a>
</article>
