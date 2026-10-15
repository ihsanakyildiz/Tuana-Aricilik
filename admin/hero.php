<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/ImageSet.php';
require dirname(__DIR__) . '/app/HeroEditor.php';

Auth::boot();
Auth::requireLogin();

$heroPage = 'home';
$heroKey = 'hero';
$requestedPage = $_POST['page_slug'] ?? $_GET['sayfa'] ?? 'home';
$requestedKey = $_POST['section_key'] ?? $_GET['bolum'] ?? 'hero';
if (is_string($requestedPage) && is_string($requestedKey) && ($requestedPage !== 'home' || $requestedKey !== 'hero')) {
    if (!HeroEditor::bind($requestedPage, $requestedKey)) {
        Auth::redirect('admin/hero.php');
    }
    $heroPage = $requestedPage;
    $heroKey = $requestedKey;
}
$heroDonus = $_POST['donus'] ?? $_GET['donus'] ?? '';
$heroDonus = ($heroDonus === 'gelismis' || $heroDonus === 'grid') ? $heroDonus : '';
$heroGrid = '';
$heroGridCandidate = $_POST['grid'] ?? $_GET['grid'] ?? '';
if ($heroDonus === 'grid' && is_string($heroGridCandidate) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $heroGridCandidate) === 1) {
    $heroGrid = $heroGridCandidate;
} elseif ($heroDonus === 'grid') {
    $heroDonus = '';
}
$heroReturnBuilder = $heroDonus !== '';
$heroQuery = ($heroPage === 'home' && $heroKey === 'hero') ? '' : ('sayfa=' . rawurlencode($heroPage) . '&bolum=' . rawurlencode($heroKey));
if ($heroDonus !== '') {
    $heroQuery = ($heroQuery === '' ? '' : $heroQuery . '&') . 'donus=' . $heroDonus;
    if ($heroGrid !== '') {
        $heroQuery .= '&grid=' . rawurlencode($heroGrid);
    }
}
$heroReturn = 'admin/hero.php' . ($heroQuery === '' ? '' : '?' . $heroQuery);
$heroUrl = static function (string $extra = '') use ($heroQuery): string {
    $parts = [];
    if ($heroQuery !== '') {
        $parts[] = $heroQuery;
    }
    if ($extra !== '') {
        $parts[] = $extra;
    }

    return 'admin/hero.php' . ($parts === [] ? '' : '?' . implode('&', $parts));
};

$deck = HeroEditor::collection();
$errors = [];
$editing = false;
$values = HeroEditor::blank();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'save';
    if ($_POST === [] && $length > 0) {
        $errors[] = 'Gönderilen dosya çok büyük. Görseli 8 MB, videoyu 24 MB altında tut.';
        $editing = $action === 'save';
    } elseif (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } elseif ($action === 'slider') {
        $errors = HeroEditor::saveSlider($_POST);
        if ($errors === []) {
            Auth::flash('ok', 'Slider ayarı kaydedildi.');
            Auth::redirect($heroReturn);
        }
    } elseif ($action === 'delete') {
        $errors = HeroEditor::remove(is_string($_POST['slide_id'] ?? null) ? $_POST['slide_id'] : '');
        if ($errors === []) {
            Auth::flash('ok', 'Karşılama silindi.');
            Auth::redirect($heroReturn);
        }
    } elseif ($action === 'toggle') {
        $id = is_string($_POST['slide_id'] ?? null) ? $_POST['slide_id'] : '';
        HeroEditor::setActive($id, isset($_POST['active']));
        Auth::flash('ok', isset($_POST['active']) ? 'Karşılama yayında.' : 'Karşılama gizlendi.');
        Auth::redirect($heroReturn);
    } elseif ($action === 'move') {
        $id = is_string($_POST['slide_id'] ?? null) ? $_POST['slide_id'] : '';
        HeroEditor::move($id, (int) ($_POST['direction'] ?? 0));
        Auth::redirect($heroReturn);
    } else {
        $errors = HeroEditor::save($_POST, $_FILES);
        if ($errors === []) {
            Auth::flash('ok', 'Karşılama kaydedildi.');
            Auth::redirect($heroReturn);
        }
        $editing = true;
        $posted = HeroEditor::blank();
        $postedLines = $_POST['title_lines'] ?? [];
        if (is_array($postedLines)) {
            $lines = [];
            foreach (array_slice(array_values($postedLines), 0, 3) as $line) {
                $lines[] = is_string($line) ? $line : '';
            }
            $posted['title_lines'] = array_pad($lines, 3, '');
        }
        foreach (['text', 'image_alt', 'primary_label', 'primary_url', 'secondary_label', 'secondary_url'] as $key) {
            if (isset($_POST[$key]) && is_string($_POST[$key])) {
                $posted[$key] = $_POST[$key];
            }
        }
        $layout = is_string($_POST['layout'] ?? null) ? $_POST['layout'] : 'side';
        $posted['layout'] = in_array($layout, ['side', 'cover', 'video'], true) ? $layout : 'side';
        $posted['align'] = ($_POST['align'] ?? '') === 'left' ? 'left' : 'right';
        $posted['tone'] = ($_POST['tone'] ?? '') === 'dark' ? 'dark' : 'light';
        $posted['overlay'] = max(0, min(80, (int) ($_POST['overlay'] ?? 46)));
        $posted['focal_x'] = max(0, min(100, (int) ($_POST['focal_x'] ?? 50)));
        $posted['focal_y'] = max(0, min(100, (int) ($_POST['focal_y'] ?? 45)));
        $posted['secondary_modal'] = isset($_POST['secondary_modal']);
        $posted['id'] = is_string($_POST['slide_id'] ?? null) ? $_POST['slide_id'] : '';
        $posted['name'] = is_string($_POST['name'] ?? null) ? $_POST['name'] : '';
        $posted['active'] = isset($_POST['active']);
        foreach (['title', 'text'] as $prefix) {
            $posted[$prefix . '_font'] = is_string($_POST[$prefix . '_font'] ?? null) ? $_POST[$prefix . '_font'] : '';
            $posted[$prefix . '_color'] = is_string($_POST[$prefix . '_color'] ?? null) ? $_POST[$prefix . '_color'] : '';
            $posted[$prefix . '_weight'] = is_string($_POST[$prefix . '_weight'] ?? null) ? $_POST[$prefix . '_weight'] : ($prefix === 'title' ? '700' : '400');
            $posted[$prefix . '_size'] = is_string($_POST[$prefix . '_size'] ?? null) ? $_POST[$prefix . '_size'] : 'normal';
            $posted[$prefix . '_italic'] = isset($_POST[$prefix . '_italic']);
            $posted[$prefix . '_underline'] = isset($_POST[$prefix . '_underline']);
            $posted[$prefix . '_uppercase'] = isset($_POST[$prefix . '_uppercase']);
            if (!isset($_POST[$prefix . '_custom_color'])) {
                $posted[$prefix . '_color'] = '';
            }
        }
        $values = $posted;
    }
}
if (!$editing) {
    $requested = isset($_GET['slide']) ? (string) $_GET['slide'] : '';
    if (isset($_GET['yeni'])) {
        $editing = true;
        $values = HeroEditor::blank();
    } elseif ($requested !== '') {
        $found = HeroEditor::open($requested);
        if ($found === null) {
            Auth::redirect($heroReturn);
        }
        $editing = true;
        $values = $found;
    }
}
$deck = HeroEditor::collection();

$pageTitle = 'Karşılama';
$crumbCurrent = 'Karşılama';
$activeNav = 'hero';
$lines = is_array($values['title_lines'] ?? null) ? $values['title_lines'] : ['', '', ''];
$image = (string) ($values['image'] ?? '');
$video = (string) ($values['video'] ?? '');
$managedImage = $image !== '' && !str_starts_with($image, 'assets/images/');

require dirname(__DIR__) . '/partials/admin/start.php';
if ($heroGrid !== '') {
    echo '<p class="slide-back"><a href="' . root_url('admin/grid.php?sayfa=' . rawurlencode($heroPage) . '&bolum=' . rawurlencode($heroGrid)) . '">Satıra dön</a></p>';
} elseif ($heroReturnBuilder || $heroPage !== 'home' || $heroKey !== 'hero') {
    echo '<p class="slide-back"><a href="' . root_url('admin/gelismis.php?sayfa=' . rawurlencode($heroPage)) . '">Sayfaya dön</a></p>';
}
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e((string) $error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if (!ImageSet::ready()): ?>
    <div class="form-alert" role="alert">
        <p>WebP dönüştürme kapalı. Yeni görsel kaydedilemez; mevcut karşılama görünmeye devam eder.</p>
    </div>
<?php endif; ?>

<?php if (!$editing): ?>
    <?php $slider = $deck['slider']; ?>
    <form class="settings-form" method="post" action="<?= root_url($heroReturn) ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($heroPage) ?>">
            <input type="hidden" name="section_key" value="<?= e($heroKey) ?>">
        <input type="hidden" name="action" value="slider">
        <section class="settings-card">
            <h2>Slider</h2>
            <p class="settings-lead">Birden fazla aktif karşılama varsa anasayfada sırayla gösterilir.</p>
            <div class="fields-2">
                <label class="field" for="effect">
                    <span>Geçiş</span>
                    <select id="effect" name="effect">
                        <?php foreach (HeroEditor::EFFECTS as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $slider['effect'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field" for="interval">
                    <span>Bekleme</span>
                    <select id="interval" name="interval">
                        <?php for ($second = 3; $second <= 12; $second++): ?>
                            <option value="<?= $second ?>"<?= (int) $slider['interval'] === $second ? ' selected' : '' ?>><?= $second ?> saniye</option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>
            <label class="check" for="autoplay"><input id="autoplay" name="autoplay" type="checkbox"<?= !empty($slider['autoplay']) ? ' checked' : '' ?>> <span>Kendiliğinden ilerlesin</span></label>
            <div class="settings-save">
                <button class="login-submit" type="submit">Slider ayarını kaydet</button>
            </div>
        </section>
    </form>

    <div class="slide-head">
        <h2>Karşılamalar</h2>
        <a class="login-submit slide-add" href="<?= root_url($heroUrl('yeni=1')) ?>">Yeni karşılama</a>
    </div>
    <div class="slide-list">
        <?php foreach ($deck['slides'] as $index => $slide): ?>
            <?php
            $layoutName = match ((string) $slide['layout']) {
                'cover' => 'Kapak görseli',
                'video' => 'Kapak videosu',
                'side' => ((string) $slide['align'] === 'left' ? 'Solda görsel' : 'Sağda görsel'),
                default => 'Karşılama',
            };
            ?>
            <article class="slide-row">
                <div class="slide-thumb">
                    <?php if ((string) $slide['image'] !== ''): ?>
                        <img src="<?= root_url((string) $slide['image']) ?>" alt="">
                    <?php else: ?>
                        <span>Görsel yok</span>
                    <?php endif; ?>
                </div>
                <div>
                    <strong><?= e((string) ($slide['name'] !== '' ? $slide['name'] : 'Karşılama')) ?></strong>
                    <p><?= e($layoutName) ?> · <?= !empty($slide['active']) ? 'Yayında' : 'Gizli' ?></p>
                </div>
                <div class="slide-actions">
                    <form method="post" action="<?= root_url($heroReturn) ?>">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($heroPage) ?>">
            <input type="hidden" name="section_key" value="<?= e($heroKey) ?>">
                        <input type="hidden" name="action" value="move">
                        <input type="hidden" name="slide_id" value="<?= e((string) $slide['id']) ?>">
                        <input type="hidden" name="direction" value="-1">
                        <button type="submit"<?= $index === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                    </form>
                    <form method="post" action="<?= root_url($heroReturn) ?>">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($heroPage) ?>">
            <input type="hidden" name="section_key" value="<?= e($heroKey) ?>">
                        <input type="hidden" name="action" value="move">
                        <input type="hidden" name="slide_id" value="<?= e((string) $slide['id']) ?>">
                        <input type="hidden" name="direction" value="1">
                        <button type="submit"<?= $index === count($deck['slides']) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                    </form>
                    <form method="post" action="<?= root_url($heroReturn) ?>">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($heroPage) ?>">
            <input type="hidden" name="section_key" value="<?= e($heroKey) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="slide_id" value="<?= e((string) $slide['id']) ?>">
                        <?php if (empty($slide['active'])): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                        <button type="submit"><?= !empty($slide['active']) ? 'Gizle' : 'Yayınla' ?></button>
                    </form>
                    <a href="<?= root_url($heroUrl('slide=' . rawurlencode((string) $slide['id']))) ?>">Düzenle</a>
                    <form method="post" action="<?= root_url($heroReturn) ?>" data-confirm="Bu karşılamayı silmek istiyor musun?">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($heroPage) ?>">
            <input type="hidden" name="section_key" value="<?= e($heroKey) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slide_id" value="<?= e((string) $slide['id']) ?>">
                        <button type="submit"<?= count($deck['slides']) < 2 ? ' disabled' : '' ?>>Sil</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="field-hint">Gizli karşılama anasayfada görünmez. Hepsi gizliyse karşılama alanı kapanır.</p>
<?php else: ?>
<p class="slide-back"><a href="<?= root_url($heroReturn) ?>">Karşılamalara dön</a></p>
<form class="settings-form hero-form" method="post" action="<?= root_url($heroReturn) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($heroPage) ?>">
            <input type="hidden" name="section_key" value="<?= e($heroKey) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="slide_id" value="<?= e((string) $values['id']) ?>">

    <div class="settings-grid">
        <section class="settings-card">
            <h2>Kayıt</h2>
            <div class="fields-2">
                <label class="field" for="hero-name">
                    <span>Liste adı</span>
                    <input id="hero-name" name="name" type="text" maxlength="40" value="<?= e((string) $values['name']) ?>" placeholder="Örneğin: İlkbahar">
                </label>
            </div>
            <label class="check" for="hero-active"><input id="hero-active" name="active" type="checkbox"<?= !empty($values['active']) ? ' checked' : '' ?>> <span>Anasayfada göster</span></label>
        </section>
        <section class="settings-card">
            <h2>Görünüm</h2>
            <p class="settings-lead">Görsel metnin yanında durabilir, alanı tamamen kaplayabilir ya da yerini bir video alabilir.</p>
            <div class="layout-choices">
                <label class="layout-choice">
                    <input type="radio" name="layout" value="side"<?= $values['layout'] === 'side' ? ' checked' : '' ?>>
                    <span><strong>Yan görsel</strong><small>Metnin sağında ya da solunda.</small></span>
                </label>
                <label class="layout-choice">
                    <input type="radio" name="layout" value="cover"<?= $values['layout'] === 'cover' ? ' checked' : '' ?>>
                    <span><strong>Kapak görseli</strong><small>Alanı kenardan kenara kaplar.</small></span>
                </label>
                <label class="layout-choice">
                    <input type="radio" name="layout" value="video"<?= $values['layout'] === 'video' ? ' checked' : '' ?>>
                    <span><strong>Kapak videosu</strong><small>Sessiz video alanı kaplar.</small></span>
                </label>
            </div>

            <div class="only-side">
                <p class="choice-label">Görselin yeri</p>
                <div class="choice-row">
                    <label class="choice-pill"><input type="radio" name="align" value="left"<?= $values['align'] === 'left' ? ' checked' : '' ?>> <span>Solda</span></label>
                    <label class="choice-pill"><input type="radio" name="align" value="right"<?= $values['align'] === 'right' ? ' checked' : '' ?>> <span>Sağda</span></label>
                </div>
            </div>

            <div class="only-cover">
                <p class="choice-label">Yazının rengi</p>
                <div class="choice-row">
                    <label class="choice-pill"><input type="radio" name="tone" value="light"<?= $values['tone'] === 'light' ? ' checked' : '' ?>> <span>Açık</span></label>
                    <label class="choice-pill"><input type="radio" name="tone" value="dark"<?= $values['tone'] === 'dark' ? ' checked' : '' ?>> <span>Koyu</span></label>
                </div>
                <label class="field" for="overlay">
                    <span>Karartma <output id="overlay-value"><?= (int) $values['overlay'] ?></output></span>
                    <input id="overlay" name="overlay" type="range" min="0" max="80" value="<?= (int) $values['overlay'] ?>">
                </label>
                <p class="field-hint">Kapak görseli ve videoda yazının okunması için arkaya ton bindirilir.</p>
            </div>
        </section>

        <section class="settings-card">
            <h2>Görsel</h2>
            <p class="settings-lead">Yüklenen görsel WebP’ye çevrilir ve ekran genişliğine göre 640, 960, 1280 ve 1920 piksel olarak saklanır. Telefon geniş dosyayı indirmez.</p>
            <div class="focal-layout">
                <button class="focal-stage" type="button" data-focal aria-label="Odak noktasını görselin üzerine tıklayarak seç">
                    <?php if ($image !== ''): ?>
                        <img src="<?= root_url($image) ?>" alt="" style="object-position:<?= (int) $values['focal_x'] ?>% <?= (int) $values['focal_y'] ?>%">
                    <?php else: ?>
                        <img alt="" hidden>
                        <span class="focal-empty">Görsel yok</span>
                    <?php endif; ?>
                    <span class="focal-pin" style="left:<?= (int) $values['focal_x'] ?>%;top:<?= (int) $values['focal_y'] ?>%"></span>
                </button>
                <div>
                    <div class="field">
                        <span>Yeni görsel</span>
                        <label class="file-pick">
                            <input id="hero-image" name="image" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                            <span class="file-btn">Dosya seç</span>
                            <span class="file-name">Dosya seçilmedi</span>
                        </label>
                    </div>
                    <div class="fields-2">
                        <label class="field" for="focal-x">
                            <span>Yatay odak</span>
                            <input id="focal-x" name="focal_x" type="range" min="0" max="100" value="<?= (int) $values['focal_x'] ?>">
                        </label>
                        <label class="field" for="focal-y">
                            <span>Dikey odak</span>
                            <input id="focal-y" name="focal_y" type="range" min="0" max="100" value="<?= (int) $values['focal_y'] ?>">
                        </label>
                    </div>
                    <p class="field-hint">Odak, görsel kırpıldığında konunun kadrajda kalmasını sağlar. Özellikle telefonda kapak görünümünde işe yarar.</p>
                    <?php if ($managedImage): ?>
                        <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                    <?php endif; ?>
                </div>
            </div>
            <label class="field" for="image-alt">
                <span>Görsel açıklaması</span>
                <input id="image-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) $values['image_alt']) ?>">
            </label>
        </section>

        <section class="settings-card only-video">
            <h2>Video</h2>
            <p class="settings-lead">MP4 veya WebM, en fazla 24 MB. Video sessiz, otomatik ve döngüde oynar. Görsel, video yüklenene kadar kapak karesi olur.</p>
            <?php if ($video !== ''): ?>
                <video class="video-preview" controls preload="metadata" src="<?= root_url($video) ?>"></video>
                <label class="check" for="remove-video"><input id="remove-video" name="remove_video" type="checkbox"> <span>Videoyu kaldır</span></label>
            <?php endif; ?>
            <div class="field">
                <span>Yeni video</span>
                <label class="file-pick">
                    <input id="hero-video" name="video" type="file" accept=".mp4,.webm,video/mp4,video/webm">
                    <span class="file-btn">Dosya seç</span>
                    <span class="file-name">Dosya seçilmedi</span>
                </label>
            </div>
        </section>

        <section class="settings-card">
            <h2>Metin</h2>
            <p class="settings-lead">Başlık en fazla üç satır. Boş satırlar gösterilmez.</p>
            <div class="fields-2">
                <?php foreach ($lines as $index => $line): ?>
                    <label class="field" for="title-<?= (int) $index ?>">
                        <span>Başlık satırı <?= (int) $index + 1 ?></span>
                        <input id="title-<?= (int) $index ?>" name="title_lines[]" type="text" maxlength="60" value="<?= e((string) $line) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
            <label class="field" for="hero-text">
                <span>Açıklama</span>
                <textarea id="hero-text" name="text" maxlength="420" rows="3"><?= e((string) $values['text']) ?></textarea>
            </label>
            <div class="fields-2">
                <label class="field" for="primary-label">
                    <span>Birinci düğme</span>
                    <input id="primary-label" name="primary_label" type="text" maxlength="32" value="<?= e((string) $values['primary_label']) ?>">
                </label>
                <label class="field" for="primary-url">
                    <span>Birinci düğme adresi</span>
                    <input id="primary-url" name="primary_url" type="text" maxlength="180" value="<?= e((string) $values['primary_url']) ?>">
                </label>
                <label class="field" for="secondary-label">
                    <span>İkinci düğme</span>
                    <input id="secondary-label" name="secondary_label" type="text" maxlength="32" value="<?= e((string) $values['secondary_label']) ?>">
                </label>
                <label class="field" for="secondary-url">
                    <span>İkinci düğme adresi</span>
                    <input id="secondary-url" name="secondary_url" type="text" maxlength="180" value="<?= e((string) $values['secondary_url']) ?>">
                </label>
            </div>
            <label class="check" for="secondary-modal"><input id="secondary-modal" name="secondary_modal" type="checkbox"<?= !empty($values['secondary_modal']) ? ' checked' : '' ?>> <span>İkinci düğme tanıtım penceresini açsın</span></label>
        </section>

        <section class="settings-card">
            <h2>Yazı</h2>
            <p class="settings-lead">Renk boş bırakılırsa kapak görünümündeki açık veya koyu seçim geçerlidir.</p>
            <?php
            $typeGroups = [
                'title' => ['Başlık', '700'],
                'text' => ['Açıklama', '400'],
            ];
            ?>
            <?php foreach ($typeGroups as $prefix => [$groupLabel, $defaultWeight]): ?>
                <h3 class="type-label"><?= e($groupLabel) ?></h3>
                <div class="fields-2">
                    <label class="field" for="<?= e($prefix) ?>-font">
                        <span>Yazı tipi</span>
                        <select id="<?= e($prefix) ?>-font" name="<?= e($prefix) ?>_font">
                            <?php foreach (HeroEditor::FONTS as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= (string) $values[$prefix . '_font'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="<?= e($prefix) ?>-weight">
                        <span>Kalınlık</span>
                        <select id="<?= e($prefix) ?>-weight" name="<?= e($prefix) ?>_weight">
                            <?php foreach ([400 => 'Normal', 500 => 'Orta', 600 => 'Yarı kalın', 700 => 'Kalın', 800 => 'Çok kalın'] as $weight => $label): ?>
                                <?php $weight = (string) $weight; ?>
                                <option value="<?= e($weight) ?>"<?= (string) $values[$prefix . '_weight'] === $weight ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="<?= e($prefix) ?>-size">
                        <span>Boyut</span>
                        <select id="<?= e($prefix) ?>-size" name="<?= e($prefix) ?>_size">
                            <?php foreach (['small' => 'Küçük', 'normal' => 'Normal', 'large' => 'Büyük'] as $size => $label): ?>
                                <option value="<?= e($size) ?>"<?= (string) $values[$prefix . '_size'] === $size ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="<?= e($prefix) ?>-color">
                        <span>Renk</span>
                        <input id="<?= e($prefix) ?>-color" name="<?= e($prefix) ?>_color" type="color" value="<?= e((string) ($values[$prefix . '_color'] !== '' ? $values[$prefix . '_color'] : '#24180f')) ?>">
                    </label>
                </div>
                <div class="choice-row">
                    <label class="check" for="<?= e($prefix) ?>-custom"><input id="<?= e($prefix) ?>-custom" name="<?= e($prefix) ?>_custom_color" type="checkbox"<?= (string) $values[$prefix . '_color'] !== '' ? ' checked' : '' ?>> <span>Özel renk</span></label>
                    <label class="check" for="<?= e($prefix) ?>-italic"><input id="<?= e($prefix) ?>-italic" name="<?= e($prefix) ?>_italic" type="checkbox"<?= !empty($values[$prefix . '_italic']) ? ' checked' : '' ?>> <span>İtalik</span></label>
                    <label class="check" for="<?= e($prefix) ?>-underline"><input id="<?= e($prefix) ?>-underline" name="<?= e($prefix) ?>_underline" type="checkbox"<?= !empty($values[$prefix . '_underline']) ? ' checked' : '' ?>> <span>Altı çizili</span></label>
                    <label class="check" for="<?= e($prefix) ?>-upper"><input id="<?= e($prefix) ?>-upper" name="<?= e($prefix) ?>_uppercase" type="checkbox"<?= !empty($values[$prefix . '_uppercase']) ? ' checked' : '' ?>> <span>Büyük harf</span></label>
                </div>
            <?php endforeach; ?>
        </section>
    </div>

    <div class="settings-save">
        <button class="login-submit" type="submit">Karşılamayı kaydet</button>
    </div>
</form>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
