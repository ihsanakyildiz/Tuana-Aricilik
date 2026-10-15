<?php
$whyItem = is_array($whyItem ?? null) ? $whyItem : [];
$whyActive = !array_key_exists('active', $whyItem) || !empty($whyItem['active']);
?>
<article class="settings-card card-editor" data-why-item>
    <div class="card-editor-head">
        <strong><?= e((string) (($whyItem['title'] ?? '') !== '' ? $whyItem['title'] : 'Yeni madde')) ?></strong>
        <div class="slide-actions">
            <button type="button" data-why-up aria-label="Yukarı taşı">↑</button>
            <button type="button" data-why-down aria-label="Aşağı taşı">↓</button>
            <button type="button" data-why-remove aria-label="Maddeyi sil">×</button>
        </div>
    </div>
    <input type="hidden" name="item_active[]" value="<?= $whyActive ? '1' : '0' ?>" data-active>
    <div class="fields-2">
        <label class="field">
            <span>Madde adı</span>
            <input name="item_title[]" type="text" maxlength="48" value="<?= e((string) ($whyItem['title'] ?? '')) ?>">
        </label>
        <label class="check service-live"><input type="checkbox" data-active-box<?= $whyActive ? ' checked' : '' ?>> <span>Yayında</span></label>
        <label class="field">
            <span>Açıklama</span>
            <textarea name="item_text[]" maxlength="220" rows="2"><?= e((string) ($whyItem['text'] ?? '')) ?></textarea>
        </label>
    </div>
</article>
