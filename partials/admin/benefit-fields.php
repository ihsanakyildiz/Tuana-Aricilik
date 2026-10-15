<?php
$benefitBar = is_array($benefitBar ?? null) ? $benefitBar : [];
$benefitActive = !array_key_exists('active', $benefitBar) || !empty($benefitBar['active']);
$benefitValue = max(0, min(100, (int) ($benefitBar['value'] ?? 0)));
?>
<article class="settings-card card-editor" data-benefit-item>
    <div class="card-editor-head">
        <strong><?= e((string) (($benefitBar['label'] ?? '') !== '' ? $benefitBar['label'] : 'Yeni çubuk')) ?></strong>
        <div class="slide-actions">
            <button type="button" data-benefit-up aria-label="Yukarı taşı">↑</button>
            <button type="button" data-benefit-down aria-label="Aşağı taşı">↓</button>
            <button type="button" data-benefit-remove aria-label="Çubuğu sil">×</button>
        </div>
    </div>
    <input type="hidden" name="bar_active[]" value="<?= $benefitActive ? '1' : '0' ?>" data-active>
    <div class="fields-2">
        <label class="field">
            <span>Çubuk adı</span>
            <input name="bar_label[]" type="text" maxlength="40" value="<?= e((string) ($benefitBar['label'] ?? '')) ?>">
        </label>
        <label class="field">
            <span>Yüzde</span>
            <input name="bar_value[]" type="number" min="0" max="100" value="<?= $benefitValue ?>">
        </label>
        <label class="check service-live"><input type="checkbox" data-active-box<?= $benefitActive ? ' checked' : '' ?>> <span>Yayında</span></label>
    </div>
</article>
