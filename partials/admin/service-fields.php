<?php
$service = is_array($service ?? null) ? $service : [];
$button = is_array($service['button'] ?? null) ? $service['button'] : [];
$image = (string) ($service['image'] ?? '');
$managed = $image !== '' && !str_starts_with($image, 'assets/images/');
$active = !array_key_exists('active', $service) || !empty($service['active']);
$buttonLabel = array_key_exists('button_label', $service) ? (string) $service['button_label'] : (string) ($button['label'] ?? '');
$buttonUrl = array_key_exists('button_url', $service) ? (string) $service['button_url'] : (string) ($button['url'] ?? '');
?>
<article class="settings-card card-editor" data-service-item>
    <div class="card-editor-head">
        <strong><?= e((string) (($service['title'] ?? '') !== '' ? $service['title'] : 'Yeni hizmet')) ?></strong>
        <div class="slide-actions">
            <button type="button" data-service-up aria-label="Yukarı taşı">↑</button>
            <button type="button" data-service-down aria-label="Aşağı taşı">↓</button>
            <button type="button" data-service-remove aria-label="Hizmeti sil">×</button>
        </div>
    </div>
    <input type="hidden" name="item_id[]" value="<?= e((string) ($service['id'] ?? '')) ?>">
    <input type="hidden" name="item_image_keep[]" value="<?= e($image) ?>">
    <input type="hidden" name="item_active[]" value="<?= $active ? '1' : '0' ?>" data-active>
    <input type="hidden" name="item_clear[]" value="0" data-clear>
    <div class="fields-2">
        <label class="field">
            <span>Hizmet adı</span>
            <input name="item_title[]" type="text" maxlength="48" value="<?= e((string) ($service['title'] ?? '')) ?>">
        </label>
        <label class="check service-live"><input type="checkbox" data-active-box<?= $active ? ' checked' : '' ?>> <span>Yayında</span></label>
        <label class="field">
            <span>Açıklama</span>
            <textarea name="item_text[]" maxlength="280" rows="3"><?= e((string) ($service['text'] ?? '')) ?></textarea>
        </label>
        <label class="field">
            <span>Görsel açıklaması</span>
            <input name="item_alt[]" type="text" maxlength="140" value="<?= e((string) ($service['image_alt'] ?? '')) ?>">
        </label>
        <label class="field">
            <span>Düğme</span>
            <input name="item_button[]" type="text" maxlength="32" value="<?= e($buttonLabel) ?>">
        </label>
        <label class="field">
            <span>Düğme adresi</span>
            <input name="item_url[]" type="text" maxlength="180" value="<?= e($buttonUrl) ?>">
        </label>
    </div>
    <?php if ($image !== ''): ?>
        <img class="about-preview" src="<?= root_url($image) ?>" alt="">
    <?php endif; ?>
    <div class="field">
        <span>Görsel</span>
        <label class="file-pick">
            <input name="item_image[]" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
            <span class="file-btn">Dosya seç</span>
            <span class="file-name">Dosya seçilmedi</span>
        </label>
    </div>
    <?php if ($managed): ?>
        <label class="check"><input type="checkbox" data-clear-box> <span>Yüklenen görseli kaldır</span></label>
    <?php endif; ?>
</article>
