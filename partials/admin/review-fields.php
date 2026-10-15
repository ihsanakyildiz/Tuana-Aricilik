<?php
$reviewItem = is_array($reviewItem ?? null) ? $reviewItem : [];
$reviewActive = !array_key_exists('active', $reviewItem) || !empty($reviewItem['active']);
$reviewTone = (int) ($reviewItem['tone'] ?? 1);
if ($reviewTone < 1 || $reviewTone > 4) {
    $reviewTone = 1;
}
$reviewTones = [
    1 => 'Açık bal',
    2 => 'Krem',
    3 => 'Kehribar',
    4 => 'Fildişi',
];
?>
<article class="settings-card card-editor" data-review-item>
    <div class="card-editor-head">
        <strong><?= e((string) (($reviewItem['name'] ?? '') !== '' ? $reviewItem['name'] : 'Yeni yorum')) ?></strong>
        <div class="slide-actions">
            <button type="button" data-review-up aria-label="Yukarı taşı">↑</button>
            <button type="button" data-review-down aria-label="Aşağı taşı">↓</button>
            <button type="button" data-review-remove aria-label="Yorumu sil">×</button>
        </div>
    </div>
    <input type="hidden" name="item_active[]" value="<?= $reviewActive ? '1' : '0' ?>" data-active>
    <div class="fields-2">
        <label class="field">
            <span>Yorum</span>
            <textarea name="item_quote[]" maxlength="320" rows="3"><?= e((string) ($reviewItem['quote'] ?? '')) ?></textarea>
        </label>
        <label class="field">
            <span>Ad</span>
            <input name="item_name[]" type="text" maxlength="48" value="<?= e((string) ($reviewItem['name'] ?? '')) ?>">
        </label>
        <label class="field">
            <span>Ünvan</span>
            <input name="item_role[]" type="text" maxlength="40" value="<?= e((string) ($reviewItem['role'] ?? '')) ?>">
        </label>
        <label class="field">
            <span>Baş harfler</span>
            <input name="item_initials[]" type="text" maxlength="4" value="<?= e((string) ($reviewItem['initials'] ?? '')) ?>" placeholder="Boşsa addan yazılır">
        </label>
        <label class="field">
            <span>Avatar rengi</span>
            <select name="item_tone[]">
                <?php foreach ($reviewTones as $toneValue => $toneLabel): ?>
                    <option value="<?= (int) $toneValue ?>"<?= $reviewTone === (int) $toneValue ? ' selected' : '' ?>><?= e($toneLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="check service-live"><input type="checkbox" data-active-box<?= $reviewActive ? ' checked' : '' ?>> <span>Yayında</span></label>
    </div>
</article>
