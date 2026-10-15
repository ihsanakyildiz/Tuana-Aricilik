<?php

declare(strict_types=1);

/** @var array<string, mixed> $card */
$selling = (string) ($card['discount_price'] ?? '') !== '' ? (string) $card['discount_price'] : (string) ($card['price'] ?? '');
$was = (string) ($card['discount_price'] ?? '') !== '' ? (string) ($card['price'] ?? '') : '';
$off = 0;
if ($was !== '' && (float) $was > 0 && (float) $selling > 0 && (float) $selling < (float) $was) {
    $off = (int) round((1 - ((float) $selling / (float) $was)) * 100);
}
$available = empty($card['stock_track']) || (int) ($card['stock_qty'] ?? 0) > 0;
$productPath = '/urun/' . rawurlencode((string) ($card['key'] ?? ''));
$orderPath = '/siparis?urun=' . rawurlencode((string) ($card['key'] ?? ''));
$buyPath = MemberAccount::check() ? $orderPath : '/uye-giris?sonraki=' . rawurlencode($orderPath);
$title = (string) ($card['title'] ?? '');
$cardTag = (isset($cardTag) && $cardTag === 'h3') ? 'h3' : 'h2';
?>
<article class="shop-card">
    <?php if ($off >= 1): ?>
        <span class="shop-off<?= $off >= 20 ? ' is-hot' : '' ?>">%<?= e((string) $off) ?> indirim</span>
    <?php endif; ?>
    <a class="shop-photo" href="<?= url($productPath) ?>">
        <?php if ((string) ($card['image'] ?? '') !== ''): ?>
            <?= media_tag((string) $card['image'], $title) ?>
        <?php else: ?>
            <span class="shop-photo-empty" aria-hidden="true"></span>
        <?php endif; ?>
    </a>
    <div class="shop-card-body">
        <<?= $cardTag ?>><a href="<?= url($productPath) ?>"><?= e($title) ?></a></<?= $cardTag ?>>
        <div class="shop-row">
            <p class="shop-price">
                <?php if ($was !== ''): ?><s><?= e(ProductEditor::moneyLabel($was)) ?></s><?php endif; ?>
                <b><?= e(ProductEditor::moneyLabel($selling)) ?></b>
            </p>
            <?php if ($available): ?>
                <a class="shop-order" href="<?= url($buyPath) ?>" aria-label="<?= e('Satın al: ' . $title) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12l-.8 11.2a1.5 1.5 0 0 1-1.5 1.3H8.3a1.5 1.5 0 0 1-1.5-1.3L6 8z"/><path d="M9 8V6.8A3 3 0 0 1 12 3.8 3 3 0 0 1 15 6.8V8"/></svg>
                </a>
            <?php else: ?>
                <span class="shop-order is-off" aria-label="Tükendi">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12l-.8 11.2a1.5 1.5 0 0 1-1.5 1.3H8.3a1.5 1.5 0 0 1-1.5-1.3L6 8z"/><path d="M9 8V6.8A3 3 0 0 1 12 3.8 3 3 0 0 1 15 6.8V8"/></svg>
                </span>
            <?php endif; ?>
        </div>
    </div>
</article>
