<?php
$cardIcon = (string) ($card['icon'] ?? 'jar');
$shared = is_array($card['shared'] ?? null) ? $card['shared'] : [];
?>
<input type="hidden" name="card_key[]" value="<?= e((string) ($card['key'] ?? '')) ?>">
<div class="card-editor-head">
    <strong><?= e((string) (($card['title'] ?? '') !== '' ? $card['title'] : 'Yeni kart')) ?></strong>
    <div class="slide-actions">
        <button type="button" data-card-up aria-label="Yukarı taşı">↑</button>
        <button type="button" data-card-down aria-label="Aşağı taşı">↓</button>
        <button type="button" data-card-remove>Çıkar</button>
    </div>
</div>
<div class="fields-2">
    <label class="field">
        <span>Simge</span>
        <select name="card_icon[]">
            <?php foreach (SectionEditor::ICONS as $iconKey => $iconLabel): ?>
                <option value="<?= e($iconKey) ?>"<?= $cardIcon === $iconKey ? ' selected' : '' ?>><?= e($iconLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="field">
        <span>Başlık</span>
        <input name="card_title[]" type="text" maxlength="80" value="<?= e((string) ($card['title'] ?? '')) ?>">
    </label>
    <label class="field">
        <span>Açıklama</span>
        <input name="card_text[]" type="text" maxlength="220" value="<?= e((string) ($card['text'] ?? '')) ?>">
    </label>
    <label class="field">
        <span>Düğme</span>
        <input name="card_button_label[]" type="text" maxlength="32" value="<?= e((string) ($card['button_label'] ?? '')) ?>">
    </label>
    <label class="field">
        <span>Düğme adresi</span>
        <input name="card_button_url[]" type="text" maxlength="180" value="<?= e((string) ($card['button_url'] ?? '')) ?>">
    </label>
</div>
<?php if ($shared !== []): ?>
    <p class="field-hint">Bu kart başka yerde de duruyor: <?= e(implode(', ', $shared)) ?>. Buradaki değişiklik oraya da yansır.</p>
<?php endif; ?>
