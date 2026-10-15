<?php
$gutter = (string) ($data['gutter'] ?? 'g-4');
if (!in_array($gutter, ['g-0', 'g-2', 'g-3', 'g-4', 'g-5'], true)) {
    $gutter = 'g-4';
}
$align = (string) ($data['align'] ?? 'stretch');
if (!in_array($align, ['stretch', 'start', 'center', 'end'], true)) {
    $align = 'stretch';
}
$container = (($data['container'] ?? 'open') === 'full') ? 'full' : 'open';
$anchor = trim((string) ($data['anchor'] ?? ''));
if ($anchor === '') {
    $anchor = (string) $sectionKey;
}
$eyebrow = trim((string) ($data['eyebrow'] ?? ''));
$heading = trim((string) ($data['title'] ?? ''));
$subtitle = trim((string) ($data['subtitle'] ?? ''));
$columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
$wrap = $container === 'open' ? 'container' : 'layout-grid-bleed';
?>
<section class="layout-grid" id="<?= e($anchor) ?>">
    <div class="<?= e($wrap) ?>">
        <?php if ($eyebrow !== '' || $heading !== '' || $subtitle !== ''): ?>
            <header class="layout-grid-head">
                <?php if ($eyebrow !== ''): ?><p class="eyebrow"><?= e($eyebrow) ?></p><?php endif; ?>
                <?php if ($heading !== ''): ?><h2><?= e($heading) ?></h2><?php endif; ?>
                <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
            </header>
        <?php endif; ?>
        <div class="layout-row is-<?= e($align) ?> is-<?= e($gutter) ?>">
            <?php foreach ($columns as $column): ?>
                <?php
                if (!is_array($column)) {
                    continue;
                }
                $span = (int) ($column['span'] ?? 12);
                if ($span < 1 || $span > 12) {
                    $span = 12;
                }
                ?>
                <div class="layout-col" data-span="<?= $span ?>">
                    <?php foreach ($column['keys'] ?? [] as $childKey): ?>
                        <?php
                        if (!is_string($childKey)) {
                            continue;
                        }
                        $child = page_section($childKey);
                        if ($child === null || section_is_placed($child) === false) {
                            continue;
                        }
                        render_section($child);
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
