<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/SectionCatalog.php';
require dirname(__DIR__) . '/app/SectionEditor.php';

Auth::boot();
Auth::requireLogin();

$builderPage = 'home';
$requestedPage = $_POST['page_slug'] ?? $_GET['sayfa'] ?? 'home';
if (is_string($requestedPage) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $requestedPage) === 1) {
    $builderPage = $requestedPage;
}
SectionEditor::usePage($builderPage);
$returnTarget = $_POST['donus'] ?? $_GET['donus'] ?? '';
$returnBuilder = $returnTarget === 'gelismis' || $returnTarget === 'grid';
$returnGrid = '';
$gridCandidate = $_POST['grid'] ?? $_GET['grid'] ?? '';
if ($returnTarget === 'grid' && is_string($gridCandidate) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $gridCandidate) === 1) {
    $returnGrid = $gridCandidate;
}
$listUrl = ($builderPage === 'home' && !$returnBuilder) ? 'admin/bolumler.php' : 'admin/gelismis.php?sayfa=' . rawurlencode($builderPage);
if ($returnGrid !== '') {
    $listUrl = 'admin/grid.php?sayfa=' . rawurlencode($builderPage) . '&bolum=' . rawurlencode($returnGrid);
}
$editorUrl = static function (string $key, string $extra = '') use ($builderPage, $returnBuilder, $returnGrid): string {
    $params = ['bolum' => $key];
    if ($builderPage !== 'home' || $returnBuilder) {
        $params = ['sayfa' => $builderPage, 'bolum' => $key];
    }
    if ($returnGrid !== '') {
        $params['donus'] = 'grid';
        $params['grid'] = $returnGrid;
    } elseif ($returnBuilder) {
        $params['donus'] = 'gelismis';
    }
    if ($extra !== '') {
        parse_str($extra, $more);
        foreach ($more as $name => $value) {
            if (is_string($name)) {
                $params[$name] = is_scalar($value) ? (string) $value : '';
            }
        }
    }

    return 'admin/bolumler.php?' . http_build_query($params);
};

$errors = [];
$mode = 'list';
$section = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
        $mode = in_array($action, ['save', 'meta', 'about', 'banner', 'services', 'why', 'benefits', 'promo', 'testimonials', 'blog', 'products'], true) ? 'editor' : 'list';
    } elseif ($action === 'create') {
        $type = is_string($_POST['type'] ?? null) ? $_POST['type'] : '';
        $created = SectionEditor::create($type);
        $errors = $created['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Bölüm eklendi.');
            if ($type === 'hero') {
                $heroTarget = 'admin/hero.php?sayfa=' . rawurlencode($builderPage) . '&bolum=' . rawurlencode((string) $created['key']);
                if ($builderPage === 'home' && $created['key'] === 'hero') {
                    $heroTarget = 'admin/hero.php';
                }
                $target = $heroTarget;
            } elseif (in_array($type, ['features', 'about', 'banner', 'services', 'products', 'why', 'benefits', 'promo', 'testimonials', 'blog'], true)) {
                $target = $editorUrl((string) $created['key']);
            } else {
                $target = $listUrl;
            }
            Auth::redirect($target);
        }
        $mode = 'picker';
    } elseif ($action === 'delete') {
        $errors = SectionEditor::remove((int) ($_POST['section_id'] ?? 0));
        if ($errors === []) {
            Auth::flash('ok', 'Bölüm silindi.');
            Auth::redirect($listUrl);
        }
    } elseif ($action === 'toggle') {
        SectionEditor::setActive((int) ($_POST['section_id'] ?? 0), isset($_POST['active']));
        Auth::flash('ok', isset($_POST['active']) ? 'Bölüm yayında.' : 'Bölüm gizlendi.');
        Auth::redirect($listUrl);
    } elseif ($action === 'move') {
        SectionEditor::move((int) ($_POST['section_id'] ?? 0), (int) ($_POST['direction'] ?? 0));
        Auth::redirect($listUrl);
    } elseif ($action === 'meta') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $errors = SectionEditor::saveMeta($key, $_POST);
        if ($errors === []) {
            Auth::flash('ok', 'Bölüm kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
    } elseif ($action === 'save') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveFeatures($key, $_POST);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Kartlar kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $section['title'] = $saved['title'];
            $section['active'] = $saved['active'];
            $section['content']['anchor'] = $saved['anchor'];
            $section['cards'] = $saved['cards'];
        }
    } elseif ($action === 'about') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveAbout($key, $_POST, $_FILES);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Hakkımızda kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['badge_value'] = $values['badge_value'];
            $section['content']['badge_label'] = $values['badge_label'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title_lines'] = $values['line_fields'];
            $section['content']['text'] = $values['text'];
            $section['content']['text_extra'] = $values['text_extra'];
            $section['content']['image_alt'] = $values['image_alt'];
            $section['content']['button'] = [
                'label' => $values['button_label'],
                'url' => $values['button_url'],
            ];
        }
    } elseif ($action === 'banner') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveBanner($key, $_POST, $_FILES);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Vurgu bandı kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title_lines'] = $values['line_fields'];
            $section['content']['text'] = $values['text'];
            $section['content']['image_alt'] = $values['image_alt'];
            $section['content']['button'] = [
                'label' => $values['button_label'],
                'url' => $values['button_url'],
            ];
        }
    } elseif ($action === 'promo') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::savePromo($key, $_POST, $_FILES);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Promosyon kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title_lines'] = $values['line_fields'];
            $section['content']['text'] = $values['text'];
            $section['content']['image_alt'] = $values['image_alt'];
            $section['content']['button'] = [
                'label' => $values['button_label'],
                'url' => $values['button_url'],
            ];
        }
    } elseif ($action === 'testimonials') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveTestimonials($key, $_POST);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Yorumlar kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title_lines'] = $values['line_fields'];
            $section['content']['text'] = $values['text'];
            $section['content']['items'] = $values['items'];
        }
    } elseif ($action === 'blog') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveBlog($key, $_POST);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Yazı listesi kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title'] = $values['heading'];
            $section['content']['category'] = $values['category'];
            $section['content']['order'] = $values['order'];
            $section['content']['columns'] = $values['columns'];
            $section['content']['limit'] = $values['limit'];
            $section['content']['slider'] = $values['slider'];
            $section['content']['button'] = [
                'label' => $values['button_label'],
                'url' => $values['button_url'],
            ];
        }
    } elseif ($action === 'products') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveProducts($key, $_POST, $_FILES);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Ürün vitrini kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title'] = $values['heading'];
            $section['content']['title_lines'] = $values['heading'] === '' ? [] : [$values['heading']];
            $section['content']['text'] = $values['text'];
            $section['content']['category'] = $values['category'];
            $section['content']['order'] = $values['order'];
            $section['content']['columns'] = $values['columns'];
            $section['content']['limit'] = $values['limit'];
            $section['content']['slider'] = $values['slider'];
            $section['content']['button'] = [
                'label' => $values['button_label'],
                'url' => $values['button_url'],
            ];
            $section['content']['pledge_enabled'] = $values['pledge_enabled'];
            $section['content']['pledge_side'] = $values['pledge_side'];
            $section['content']['pledge_title'] = $values['pledge_line_fields'];
            $section['content']['pledge_text'] = $values['pledge_text'];
            $section['content']['pledge_alt'] = $values['pledge_alt'];
            $section['content']['pledge_button'] = [
                'label' => $values['pledge_button_label'],
                'url' => $values['pledge_button_url'],
            ];
        }
    } elseif ($action === 'services') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveServices($key, $_POST, $_FILES);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Hizmetler kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title_lines'] = $values['line_fields'];
            $section['content']['text'] = $values['text'];
            $section['content']['columns'] = $values['columns'];
            $section['content']['slider'] = $values['slider'];
            $section['content']['items'] = $values['items'];
        }
    } elseif ($action === 'why') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveWhy($key, $_POST, $_FILES);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Neden biz kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title_lines'] = $values['line_fields'];
            $section['content']['text'] = $values['text'];
            $section['content']['image_alt'] = $values['image_alt'];
            $section['content']['align'] = $values['align'];
            $section['content']['items'] = $values['items'];
        }
    } elseif ($action === 'benefits') {
        $key = is_string($_POST['section_key'] ?? null) ? $_POST['section_key'] : '';
        $saved = SectionEditor::saveBenefits($key, $_POST, $_FILES);
        $errors = $saved['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Balın profili kaydedildi.');
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
        $section = SectionEditor::open($key);
        if (is_array($section)) {
            $values = $saved['values'];
            $section['title'] = $values['title'];
            $section['active'] = $values['active'];
            $section['content']['anchor'] = (string) $values['anchor_input'];
            $section['content']['eyebrow'] = $values['eyebrow'];
            $section['content']['title_lines'] = $values['line_fields'];
            $section['content']['text'] = $values['text'];
            $section['content']['image_alt'] = $values['image_alt'];
            $section['content']['bars'] = $values['bars'];
        }
    }
}

if ($mode === 'list' && $errors === []) {
    if (isset($_GET['yeni'])) {
        $mode = 'picker';
    } elseif (isset($_GET['kartlar']) && !isset($_GET['bolum'])) {
        $featuresKey = SectionEditor::featuresKey();
        Auth::redirect($featuresKey !== '' ? $editorUrl($featuresKey, 'kartlar=1') : ($builderPage === 'home' ? 'admin/bolumler.php?yeni=1' : $listUrl));
    } elseif (isset($_GET['bolum'])) {
        $section = SectionEditor::open((string) $_GET['bolum']);
        if ($section === null) {
            Auth::redirect($listUrl);
        }
        $mode = 'editor';
    }
}

if (($builderPage !== 'home' || $returnBuilder) && $mode === 'list' && $errors === []) {
    Auth::redirect($listUrl);
}

$rows = SectionEditor::rows();
$pageTitle = 'Bölümler';
$crumbCurrent = 'Bölümler';
$activeNav = 'bolumler';
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'features') {
    $pageTitle = 'Kartlar';
    $crumbCurrent = 'Kartlar';
    if (isset($_GET['kartlar'])) {
        $activeNav = 'kartlar';
    }
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'about') {
    $pageTitle = 'Hakkımızda';
    $crumbCurrent = 'Hakkımızda';
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'banner') {
    $pageTitle = 'Vurgu bandı';
    $crumbCurrent = 'Vurgu bandı';
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'blog') {
    $pageTitle = 'Yazı listesi';
    $crumbCurrent = 'Yazı listesi';
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'products') {
    $pageTitle = 'Ürün vitrini';
    $crumbCurrent = 'Ürün vitrini';
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'services') {
    $pageTitle = 'Hizmetler';
    $crumbCurrent = 'Hizmetler';
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'why') {
    $pageTitle = 'Neden biz';
    $crumbCurrent = 'Neden biz';
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'benefits') {
    $pageTitle = 'Balın profili';
    $crumbCurrent = 'Balın profili';
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'promo') {
    $pageTitle = 'Promosyon';
    $crumbCurrent = 'Promosyon';
}
if ($mode === 'editor' && is_array($section) && ($section['type'] ?? '') === 'testimonials') {
    $pageTitle = 'Yorumlar';
    $crumbCurrent = 'Yorumlar';
}

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e((string) $error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($mode === 'picker'): ?>
    <p class="slide-back"><a href="<?= root_url($listUrl) ?>"><?= $builderPage === 'home' ? 'Bölümlere dön' : 'Sayfaya dön' ?></a></p>
    <section class="settings-card">
        <h2>Hangi bölümü eklemek istiyorsun?</h2>
        <p class="settings-lead">Anasayfadaki dokuz tasarım. Seçtiğin tasarım listenin sonuna eklenir; aynı tasarımdan istediğin kadar kullanabilirsin.</p>
    </section>
    <div class="design-grid">
        <?php foreach (SectionCatalog::DESIGNS as $type => $design): ?>
            <article class="design-card" id="tasarim-<?= e($type) ?>">
                <div class="design-stage" data-preview>
                    <iframe src="<?= root_url('admin/onizleme.php?tip=' . rawurlencode($type)) ?>" title="<?= e($design['label']) ?> önizlemesi" loading="lazy" tabindex="-1"></iframe>
                </div>
                <form method="post" action="<?= root_url('admin/bolumler.php') ?>">
                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <h2><?= e($design['label']) ?></h2>
                    <p><?= e($design['text']) ?></p>
                    <button class="login-submit" type="submit">Bu tasarımı ekle</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php elseif ($mode === 'editor' && is_array($section)): ?>
    <p class="slide-back"><a href="<?= root_url($listUrl) ?>"><?= $builderPage === 'home' ? 'Bölümlere dön' : 'Sayfaya dön' ?></a></p>
    <?php if (($section['type'] ?? '') === 'features'): ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Kartlı bölüm</h2>
                <p class="settings-lead">Bu bölüm anasayfada bilgi kartlarını yan yana dizer. Kartın metnini, simgesini ve düğmesini buradan değiştirirsin.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>" placeholder="one-cikanlar">
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <div class="card-stack" data-card-stack>
                <?php foreach ($section['cards'] as $card): ?>
                    <article class="settings-card card-editor">
                        <?php require dirname(__DIR__) . '/partials/admin/card-fields.php'; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="settings-save">
                <button class="login-submit" type="button" data-card-add>Kart ekle</button>
                <button class="login-submit" type="submit">Kartları kaydet</button>
            </div>
        </form>
        <template id="card-template">
            <article class="settings-card card-editor">
                <?php
                $card = [
                    'key' => '',
                    'icon' => 'jar',
                    'title' => '',
                    'text' => '',
                    'button_label' => '',
                    'button_url' => '',
                    'shared' => [],
                ];
                require dirname(__DIR__) . '/partials/admin/card-fields.php';
                ?>
            </article>
        </template>
    <?php elseif (($section['type'] ?? '') === 'about'): ?>
        <?php
        $aboutLines = is_array($section['content']['title_lines'] ?? null) ? array_values($section['content']['title_lines']) : [];
        $aboutLines = array_pad(array_slice($aboutLines, 0, 3), 3, '');
        $aboutButton = is_array($section['content']['button'] ?? null) ? $section['content']['button'] : [];
        $aboutImage = (string) ($section['content']['image'] ?? '');
        $aboutManaged = $aboutImage !== '' && !str_starts_with($aboutImage, 'assets/images/');
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="about">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Hakkımızda</h2>
                <p class="settings-lead">Görsel solda, metin sağda durur. Beyaz zemin üstteki kartlarla birlikte devam eder; sağ altta petek simgesi görünür.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="badge-value">
                        <span>Rozet</span>
                        <input id="badge-value" name="badge_value" type="text" maxlength="8" value="<?= e((string) ($section['content']['badge_value'] ?? '')) ?>" placeholder="24+">
                    </label>
                    <label class="field" for="badge-label">
                        <span>Rozet yazısı</span>
                        <input id="badge-label" name="badge_label" type="text" maxlength="32" value="<?= e((string) ($section['content']['badge_label'] ?? '')) ?>" placeholder="Yıllık deneyim">
                    </label>
                    <label class="field" for="about-eyebrow">
                        <span>Üst etiket</span>
                        <input id="about-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Metin</h2>
                <div class="fields-2">
                    <?php foreach ($aboutLines as $index => $line): ?>
                        <label class="field" for="about-line-<?= (int) $index ?>">
                            <span>Başlık satırı <?= (int) $index + 1 ?></span>
                            <input id="about-line-<?= (int) $index ?>" name="title_lines[]" type="text" maxlength="60" value="<?= e((string) $line) ?>">
                        </label>
                    <?php endforeach; ?>
                    <label class="field" for="about-text">
                        <span>Açıklama</span>
                        <textarea id="about-text" name="text" maxlength="420" rows="3"><?= e((string) ($section['content']['text'] ?? '')) ?></textarea>
                    </label>
                    <label class="field" for="about-extra">
                        <span>İkinci paragraf</span>
                        <textarea id="about-extra" name="text_extra" maxlength="420" rows="3"><?= e((string) ($section['content']['text_extra'] ?? '')) ?></textarea>
                    </label>
                    <label class="field" for="about-button">
                        <span>Düğme</span>
                        <input id="about-button" name="button_label" type="text" maxlength="32" value="<?= e((string) ($aboutButton['label'] ?? '')) ?>">
                    </label>
                    <label class="field" for="about-url">
                        <span>Düğme adresi</span>
                        <input id="about-url" name="button_url" type="text" maxlength="180" value="<?= e((string) ($aboutButton['url'] ?? '')) ?>">
                    </label>
                </div>
            </section>
            <section class="settings-card">
                <h2>Görsel</h2>
                <p class="settings-lead">Yüklenen görsel WebP’ye çevrilir. Rozet görselin sol üstünde durur.</p>
                <?php if ($aboutImage !== ''): ?>
                    <img class="about-preview" src="<?= root_url($aboutImage) ?>" alt="">
                <?php endif; ?>
                <div class="field">
                    <span>Yeni görsel</span>
                    <label class="file-pick">
                        <input id="about-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
                <label class="field" for="about-alt">
                    <span>Görsel açıklaması</span>
                    <input id="about-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) ($section['content']['image_alt'] ?? '')) ?>">
                </label>
                <?php if ($aboutManaged): ?>
                    <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                <?php endif; ?>
                <div class="settings-save">
                    <button class="login-submit" type="submit">Hakkımızdayı kaydet</button>
                </div>
            </section>
        </form>
    <?php elseif (($section['type'] ?? '') === 'banner'): ?>
        <?php
        $bannerLines = is_array($section['content']['title_lines'] ?? null) ? array_values($section['content']['title_lines']) : [];
        $bannerLines = array_pad(array_slice($bannerLines, 0, 3), 3, '');
        $bannerButton = is_array($section['content']['button'] ?? null) ? $section['content']['button'] : [];
        $bannerImage = (string) ($section['content']['image'] ?? '');
        $bannerManaged = $bannerImage !== '' && !str_starts_with($bannerImage, 'assets/images/');
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="banner">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Vurgu bandı</h2>
                <p class="settings-lead">Yazı, petek görselinin üzerinde ortalanır. Sayfa aşağı kaydıkça fotoğraf yerinde kalır, yazı görselin üzerinde yukarı kayar.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="banner-eyebrow">
                        <span>Üst etiket</span>
                        <input id="banner-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Metin</h2>
                <div class="fields-2">
                    <?php foreach ($bannerLines as $index => $line): ?>
                        <label class="field" for="banner-line-<?= (int) $index ?>">
                            <span>Başlık satırı <?= (int) $index + 1 ?></span>
                            <input id="banner-line-<?= (int) $index ?>" name="title_lines[]" type="text" maxlength="70" value="<?= e((string) $line) ?>">
                        </label>
                    <?php endforeach; ?>
                    <label class="field" for="banner-text">
                        <span>Açıklama</span>
                        <textarea id="banner-text" name="text" maxlength="420" rows="3"><?= e((string) ($section['content']['text'] ?? '')) ?></textarea>
                    </label>
                    <label class="field" for="banner-button">
                        <span>Düğme</span>
                        <input id="banner-button" name="button_label" type="text" maxlength="32" value="<?= e((string) ($bannerButton['label'] ?? '')) ?>">
                    </label>
                    <label class="field" for="banner-url">
                        <span>Düğme adresi</span>
                        <input id="banner-url" name="button_url" type="text" maxlength="180" value="<?= e((string) ($bannerButton['url'] ?? '')) ?>">
                    </label>
                </div>
            </section>
            <section class="settings-card">
                <h2>Arka plan</h2>
                <p class="settings-lead">Yüklenen görsel WebP’ye çevrilir ve bandın tamamını kaplar.</p>
                <?php if ($bannerImage !== ''): ?>
                    <img class="about-preview" src="<?= root_url($bannerImage) ?>" alt="">
                <?php endif; ?>
                <div class="field">
                    <span>Yeni görsel</span>
                    <label class="file-pick">
                        <input id="banner-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
                <label class="field" for="banner-alt">
                    <span>Görsel açıklaması</span>
                    <input id="banner-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) ($section['content']['image_alt'] ?? '')) ?>">
                </label>
                <?php if ($bannerManaged): ?>
                    <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                <?php endif; ?>
                <div class="settings-save">
                    <button class="login-submit" type="submit">Vurgu bandını kaydet</button>
                </div>
            </section>
        </form>
    <?php elseif (($section['type'] ?? '') === 'promo'): ?>
        <?php
        $promoLines = is_array($section['content']['title_lines'] ?? null) ? array_values($section['content']['title_lines']) : [];
        $promoLines = array_pad(array_slice($promoLines, 0, 3), 3, '');
        $promoButton = is_array($section['content']['button'] ?? null) ? $section['content']['button'] : [];
        $promoImage = (string) ($section['content']['image'] ?? '');
        $promoManaged = $promoImage !== '' && !str_starts_with($promoImage, 'assets/images/');
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="promo">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Promosyon</h2>
                <p class="settings-lead">Yazı, bal görselinin alt kenarına oturur. Sayfa aşağı kaydıkça fotoğraf yerinde kalır, yazı görselin üzerinde yukarı kayar.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="promo-eyebrow">
                        <span>Üst etiket</span>
                        <input id="promo-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Metin</h2>
                <div class="fields-2">
                    <?php foreach ($promoLines as $index => $line): ?>
                        <label class="field" for="promo-line-<?= (int) $index ?>">
                            <span>Başlık satırı <?= (int) $index + 1 ?></span>
                            <input id="promo-line-<?= (int) $index ?>" name="title_lines[]" type="text" maxlength="70" value="<?= e((string) $line) ?>">
                        </label>
                    <?php endforeach; ?>
                    <label class="field" for="promo-text">
                        <span>Açıklama</span>
                        <textarea id="promo-text" name="text" maxlength="420" rows="3"><?= e((string) ($section['content']['text'] ?? '')) ?></textarea>
                    </label>
                    <label class="field" for="promo-button">
                        <span>Düğme</span>
                        <input id="promo-button" name="button_label" type="text" maxlength="32" value="<?= e((string) ($promoButton['label'] ?? '')) ?>">
                    </label>
                    <label class="field" for="promo-url">
                        <span>Düğme adresi</span>
                        <input id="promo-url" name="button_url" type="text" maxlength="180" value="<?= e((string) ($promoButton['url'] ?? '')) ?>">
                    </label>
                </div>
            </section>
            <section class="settings-card">
                <h2>Arka plan</h2>
                <p class="settings-lead">Yüklenen görsel WebP’ye çevrilir ve yazının arkasında üstte durur.</p>
                <?php if ($promoImage !== ''): ?>
                    <img class="about-preview" src="<?= root_url($promoImage) ?>" alt="">
                <?php endif; ?>
                <div class="field">
                    <span>Yeni görsel</span>
                    <label class="file-pick">
                        <input id="promo-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
                <label class="field" for="promo-alt">
                    <span>Görsel açıklaması</span>
                    <input id="promo-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) ($section['content']['image_alt'] ?? '')) ?>">
                </label>
                <?php if ($promoManaged): ?>
                    <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                <?php endif; ?>
                <div class="settings-save">
                    <button class="login-submit" type="submit">Promosyonu kaydet</button>
                </div>
            </section>
        </form>
    <?php elseif (($section['type'] ?? '') === 'services'): ?>
        <?php
        $serviceLines = is_array($section['content']['title_lines'] ?? null) ? array_values($section['content']['title_lines']) : [];
        $serviceLines = array_pad(array_slice($serviceLines, 0, 3), 3, '');
        $serviceItems = is_array($section['content']['items'] ?? null) ? array_values($section['content']['items']) : [];
        if ($serviceItems === []) {
            $serviceItems = [['title' => '', 'text' => '', 'image' => '', 'image_alt' => '', 'active' => true]];
        }
        $serviceColumns = (int) ($section['content']['columns'] ?? 4);
        if (!in_array($serviceColumns, [2, 3, 4, 5, 6], true)) {
            $serviceColumns = 4;
        }
        $serviceSlider = is_array($section['content']['slider'] ?? null) ? $section['content']['slider'] : [];
        $sliderOn = !empty($serviceSlider['enabled']);
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>" enctype="multipart/form-data" data-service-form>
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="services">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Hizmetler</h2>
                <p class="settings-lead">İstediğin kadar hizmet kartı ekleyebilirsin. Kaydırma kapalıyken seçilen adetten sonrakiler alt satıra iner. Kaydırma açıkken kartlar sağa sola kayar.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="service-eyebrow">
                        <span>Üst etiket</span>
                        <input id="service-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                    <label class="field" for="service-columns">
                        <span>Yan yana kart</span>
                        <select id="service-columns" name="columns">
                            <?php foreach ([2, 3, 4, 5, 6] as $count): ?>
                                <option value="<?= $count ?>"<?= $serviceColumns === $count ? ' selected' : '' ?>><?= $count ?> kart</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Başlık</h2>
                <div class="fields-2">
                    <?php foreach ($serviceLines as $index => $line): ?>
                        <label class="field" for="service-line-<?= (int) $index ?>">
                            <span>Başlık satırı <?= (int) $index + 1 ?></span>
                            <input id="service-line-<?= (int) $index ?>" name="title_lines[]" type="text" maxlength="70" value="<?= e((string) $line) ?>">
                        </label>
                    <?php endforeach; ?>
                    <label class="field" for="service-text">
                        <span>Açıklama</span>
                        <textarea id="service-text" name="text" maxlength="420" rows="3"><?= e((string) ($section['content']['text'] ?? '')) ?></textarea>
                    </label>
                </div>
            </section>
            <section class="settings-card">
                <h2>Kaydırma</h2>
                <label class="check" for="slider-enabled"><input id="slider-enabled" name="slider_enabled" type="checkbox"<?= $sliderOn ? ' checked' : '' ?>> <span>Sağa sola kayan slider kullan</span></label>
                <div class="service-slider-fields">
                    <div class="fields-2">
                        <label class="field" for="slider-effect">
                            <span>Geçiş</span>
                            <select id="slider-effect" name="slider_effect">
                                <?php foreach (['slide' => 'Kaydırma', 'fade' => 'Belirme', 'none' => 'Anında geçiş'] as $effect => $label): ?>
                                    <option value="<?= e($effect) ?>"<?= (string) ($serviceSlider['effect'] ?? 'slide') === $effect ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field" for="slider-step">
                            <span>Adım</span>
                            <select id="slider-step" name="slider_step">
                                <option value="1"<?= (string) ($serviceSlider['step'] ?? '1') === '1' ? ' selected' : '' ?>>Bir kart</option>
                                <option value="group"<?= (string) ($serviceSlider['step'] ?? '') === 'group' ? ' selected' : '' ?>>Görünen grup</option>
                            </select>
                        </label>
                        <label class="field" for="slider-interval">
                            <span>Bekleme (saniye)</span>
                            <input id="slider-interval" name="slider_interval" type="number" min="3" max="12" value="<?= (int) ($serviceSlider['interval'] ?? 5) ?>">
                        </label>
                    </div>
                    <label class="check" for="slider-autoplay"><input id="slider-autoplay" name="slider_autoplay" type="checkbox"<?= !isset($serviceSlider['autoplay']) || !empty($serviceSlider['autoplay']) ? ' checked' : '' ?>> <span>Otomatik kaydır</span></label>
                    <label class="check" for="slider-loop"><input id="slider-loop" name="slider_loop" type="checkbox"<?= !isset($serviceSlider['loop']) || !empty($serviceSlider['loop']) ? ' checked' : '' ?>> <span>Başa dön</span></label>
                    <label class="check" for="slider-arrows"><input id="slider-arrows" name="slider_arrows" type="checkbox"<?= !isset($serviceSlider['arrows']) || !empty($serviceSlider['arrows']) ? ' checked' : '' ?>> <span>Oklar</span></label>
                    <label class="check" for="slider-dots"><input id="slider-dots" name="slider_dots" type="checkbox"<?= !isset($serviceSlider['dots']) || !empty($serviceSlider['dots']) ? ' checked' : '' ?>> <span>Noktalar</span></label>
                    <label class="check" for="slider-pause"><input id="slider-pause" name="slider_pause" type="checkbox"<?= !isset($serviceSlider['pause']) || !empty($serviceSlider['pause']) ? ' checked' : '' ?>> <span>Üzerine gelince dur</span></label>
                </div>
            </section>
            <div data-service-stack>
                <?php foreach ($serviceItems as $service): ?>
                    <?php require dirname(__DIR__) . '/partials/admin/service-fields.php'; ?>
                <?php endforeach; ?>
            </div>
            <div class="settings-save">
                <button class="login-submit" type="button" data-service-add>Hizmet ekle</button>
                <button class="login-submit" type="submit">Hizmetleri kaydet</button>
            </div>
        </form>
        <template id="service-template">
            <?php
            $service = ['title' => '', 'text' => '', 'image' => '', 'image_alt' => '', 'active' => true, 'button_label' => '', 'button_url' => ''];
            require dirname(__DIR__) . '/partials/admin/service-fields.php';
            ?>
        </template>
    <?php elseif (($section['type'] ?? '') === 'why'): ?>
        <?php
        $whyLines = is_array($section['content']['title_lines'] ?? null) ? array_values($section['content']['title_lines']) : [];
        $whyLines = array_pad(array_slice($whyLines, 0, 3), 3, '');
        $whyItems = is_array($section['content']['items'] ?? null) ? array_values($section['content']['items']) : [];
        if ($whyItems === []) {
            $whyItems = [['title' => '', 'text' => '', 'active' => true]];
        }
        $whyAlign = (string) ($section['content']['align'] ?? 'right');
        if ($whyAlign !== 'left') {
            $whyAlign = 'right';
        }
        $whyImage = (string) ($section['content']['image'] ?? '');
        $whyManaged = $whyImage !== '' && !str_starts_with($whyImage, 'assets/images/');
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>" enctype="multipart/form-data" data-why-form>
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="why">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Neden biz</h2>
                <p class="settings-lead">Madde listesi bir kartta durur. Görsel kartın sağına ya da soluna alınabilir.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="why-eyebrow">
                        <span>Üst etiket</span>
                        <input id="why-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Görselin yeri</h2>
                <div class="layout-choices">
                    <label class="layout-choice">
                        <input type="radio" name="align" value="left"<?= $whyAlign === 'left' ? ' checked' : '' ?>>
                        <span><strong>Solda</strong><small>Fotoğraf solda, kart sağda.</small></span>
                    </label>
                    <label class="layout-choice">
                        <input type="radio" name="align" value="right"<?= $whyAlign === 'right' ? ' checked' : '' ?>>
                        <span><strong>Sağda</strong><small>Fotoğraf sağda, kart solda.</small></span>
                    </label>
                </div>
                <?php if ($whyImage !== ''): ?>
                    <img class="about-preview" src="<?= root_url($whyImage) ?>" alt="">
                <?php endif; ?>
                <div class="field">
                    <span>Yeni görsel</span>
                    <label class="file-pick">
                        <input id="why-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
                <label class="field" for="why-alt">
                    <span>Görsel açıklaması</span>
                    <input id="why-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) ($section['content']['image_alt'] ?? '')) ?>">
                </label>
                <?php if ($whyManaged): ?>
                    <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                <?php endif; ?>
            </section>
            <section class="settings-card">
                <h2>Metin</h2>
                <div class="fields-2">
                    <?php foreach ($whyLines as $index => $line): ?>
                        <label class="field" for="why-line-<?= (int) $index ?>">
                            <span>Başlık satırı <?= (int) $index + 1 ?></span>
                            <input id="why-line-<?= (int) $index ?>" name="title_lines[]" type="text" maxlength="70" value="<?= e((string) $line) ?>">
                        </label>
                    <?php endforeach; ?>
                    <label class="field" for="why-text">
                        <span>Açıklama</span>
                        <textarea id="why-text" name="text" maxlength="420" rows="3"><?= e((string) ($section['content']['text'] ?? '')) ?></textarea>
                    </label>
                </div>
            </section>
            <div data-why-stack>
                <?php foreach ($whyItems as $whyItem): ?>
                    <?php require dirname(__DIR__) . '/partials/admin/why-fields.php'; ?>
                <?php endforeach; ?>
            </div>
            <div class="settings-save">
                <button class="login-submit" type="button" data-why-add>Madde ekle</button>
                <button class="login-submit" type="submit">Neden bizi kaydet</button>
            </div>
        </form>
        <template id="why-template">
            <?php
            $whyItem = ['title' => '', 'text' => '', 'active' => true];
            require dirname(__DIR__) . '/partials/admin/why-fields.php';
            ?>
        </template>
    <?php elseif (($section['type'] ?? '') === 'benefits'): ?>
        <?php
        $benefitLines = is_array($section['content']['title_lines'] ?? null) ? array_values($section['content']['title_lines']) : [];
        $benefitLines = array_pad(array_slice($benefitLines, 0, 3), 3, '');
        $benefitBars = is_array($section['content']['bars'] ?? null) ? array_values($section['content']['bars']) : [];
        if ($benefitBars === []) {
            $benefitBars = [['label' => '', 'value' => 50, 'active' => true]];
        }
        $benefitImage = (string) ($section['content']['image'] ?? '');
        $benefitManaged = $benefitImage !== '' && !str_starts_with($benefitImage, 'assets/images/');
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>" enctype="multipart/form-data" data-benefit-form>
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="benefits">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Balın profili</h2>
                <p class="settings-lead">Metin solda, görsel ortada, çubuklar sağda durur. Yüzdeler tanıtım amaçlıdır; bir tedavi vaadi olarak yazma.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="benefit-eyebrow">
                        <span>Üst etiket</span>
                        <input id="benefit-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Metin</h2>
                <div class="fields-2">
                    <?php foreach ($benefitLines as $index => $line): ?>
                        <label class="field" for="benefit-line-<?= (int) $index ?>">
                            <span>Başlık satırı <?= (int) $index + 1 ?></span>
                            <input id="benefit-line-<?= (int) $index ?>" name="title_lines[]" type="text" maxlength="70" value="<?= e((string) $line) ?>">
                        </label>
                    <?php endforeach; ?>
                    <label class="field" for="benefit-text">
                        <span>Açıklama</span>
                        <textarea id="benefit-text" name="text" maxlength="420" rows="3"><?= e((string) ($section['content']['text'] ?? '')) ?></textarea>
                    </label>
                </div>
            </section>
            <section class="settings-card">
                <h2>Görsel</h2>
                <p class="settings-lead">Yüklenen görsel WebP’ye çevrilir ve metinle çubukların arasında durur.</p>
                <?php if ($benefitImage !== ''): ?>
                    <img class="about-preview" src="<?= root_url($benefitImage) ?>" alt="">
                <?php endif; ?>
                <div class="field">
                    <span>Yeni görsel</span>
                    <label class="file-pick">
                        <input id="benefit-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
                <label class="field" for="benefit-alt">
                    <span>Görsel açıklaması</span>
                    <input id="benefit-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) ($section['content']['image_alt'] ?? '')) ?>">
                </label>
                <?php if ($benefitManaged): ?>
                    <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                <?php endif; ?>
            </section>
            <div data-benefit-stack>
                <?php foreach ($benefitBars as $benefitBar): ?>
                    <?php require dirname(__DIR__) . '/partials/admin/benefit-fields.php'; ?>
                <?php endforeach; ?>
            </div>
            <div class="settings-save">
                <button class="login-submit" type="button" data-benefit-add>Çubuk ekle</button>
                <button class="login-submit" type="submit">Profili kaydet</button>
            </div>
        </form>
        <template id="benefit-template">
            <?php
            $benefitBar = ['label' => '', 'value' => 50, 'active' => true];
            require dirname(__DIR__) . '/partials/admin/benefit-fields.php';
            ?>
        </template>
    <?php elseif (($section['type'] ?? '') === 'testimonials'): ?>
        <?php
        $reviewLines = is_array($section['content']['title_lines'] ?? null) ? array_values($section['content']['title_lines']) : [];
        $reviewLines = array_pad(array_slice($reviewLines, 0, 3), 3, '');
        $reviewItems = is_array($section['content']['items'] ?? null) ? array_values($section['content']['items']) : [];
        if ($reviewItems === []) {
            $reviewItems = [['quote' => '', 'name' => '', 'role' => '', 'initials' => '', 'tone' => 1, 'active' => true]];
        }
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>" data-review-form>
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="testimonials">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Yorumlar</h2>
                <p class="settings-lead">Başlık ortada durur, yorum kartları altta kayar. İsimler örnek kişi olabilir.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="review-eyebrow">
                        <span>Üst etiket</span>
                        <input id="review-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Başlık</h2>
                <div class="fields-2">
                    <?php foreach ($reviewLines as $index => $line): ?>
                        <label class="field" for="review-line-<?= (int) $index ?>">
                            <span>Başlık satırı <?= (int) $index + 1 ?></span>
                            <input id="review-line-<?= (int) $index ?>" name="title_lines[]" type="text" maxlength="70" value="<?= e((string) $line) ?>">
                        </label>
                    <?php endforeach; ?>
                    <label class="field" for="review-text">
                        <span>Açıklama</span>
                        <textarea id="review-text" name="text" maxlength="280" rows="3"><?= e((string) ($section['content']['text'] ?? '')) ?></textarea>
                    </label>
                </div>
            </section>
            <div data-review-stack>
                <?php foreach ($reviewItems as $reviewItem): ?>
                    <?php require dirname(__DIR__) . '/partials/admin/review-fields.php'; ?>
                <?php endforeach; ?>
            </div>
            <div class="settings-save">
                <button class="login-submit" type="button" data-review-add>Yorum ekle</button>
                <button class="login-submit" type="submit">Yorumları kaydet</button>
            </div>
        </form>
        <template id="review-template">
            <?php
            $reviewItem = ['quote' => '', 'name' => '', 'role' => '', 'initials' => '', 'tone' => 1, 'active' => true];
            require dirname(__DIR__) . '/partials/admin/review-fields.php';
            ?>
        </template>
    <?php elseif (($section['type'] ?? '') === 'blog'): ?>
        <?php
        $blogColumns = (int) ($section['content']['columns'] ?? 2);
        if (!in_array($blogColumns, [2, 3, 4, 5, 6], true)) {
            $blogColumns = 2;
        }
        $blogLimit = (int) ($section['content']['limit'] ?? 0);
        if ($blogLimit < 0 || $blogLimit > 24) {
            $blogLimit = 0;
        }
        $blogOrder = (string) ($section['content']['order'] ?? 'new') === 'old' ? 'old' : 'new';
        $blogCategory = (string) ($section['content']['category'] ?? '');
        $blogCategories = post_categories();
        if (!isset($blogCategories[$blogCategory])) {
            $blogCategory = '';
        }
        $blogSlider = is_array($section['content']['slider'] ?? null) ? $section['content']['slider'] : [];
        $blogSliderOn = !empty($blogSlider['enabled']);
        $blogButton = is_array($section['content']['button'] ?? null) ? $section['content']['button'] : [];
        $blogPreview = section_posts($section['content'], false);
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
            <?php if ($returnGrid !== ''): ?>
                <input type="hidden" name="donus" value="grid">
                <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
            <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="blog">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Yazı listesi</h2>
                <p class="settings-lead">Yayındaki yazılar buradan seçtiğin kategoriye ve tarihe göre dizilir. Kaydırma kapalıyken seçilen adetten sonrakiler alt satıra iner. Kaydırma açıkken kartlar sağa sola kayar.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>" required>
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="blog-eyebrow">
                        <span>Üst etiket</span>
                        <input id="blog-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                    <label class="field" for="blog-heading">
                        <span>Bölüm başlığı</span>
                        <input id="blog-heading" name="heading" type="text" maxlength="120" value="<?= e((string) ($section['content']['title'] ?? '')) ?>">
                    </label>
                    <label class="field" for="blog-category">
                        <span>Kategori</span>
                        <select id="blog-category" name="category">
                            <option value=""<?= $blogCategory === '' ? ' selected' : '' ?>>Tüm kategoriler</option>
                            <?php foreach ($blogCategories as $catKey => $catLabel): ?>
                                <option value="<?= e((string) $catKey) ?>"<?= $blogCategory === (string) $catKey ? ' selected' : '' ?>><?= e($catLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="blog-order">
                        <span>Sıralama</span>
                        <select id="blog-order" name="order">
                            <option value="new"<?= $blogOrder === 'new' ? ' selected' : '' ?>>Yeniden eskiye</option>
                            <option value="old"<?= $blogOrder === 'old' ? ' selected' : '' ?>>Eskiden yeniye</option>
                        </select>
                    </label>
                    <label class="field" for="blog-columns">
                        <span>Yan yana kart</span>
                        <select id="blog-columns" name="columns">
                            <?php foreach ([2, 3, 4, 5, 6] as $count): ?>
                                <option value="<?= $count ?>"<?= $blogColumns === $count ? ' selected' : '' ?>><?= $count ?> kart</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="blog-limit">
                        <span>Yazı sayısı</span>
                        <input id="blog-limit" name="limit" type="number" min="0" max="24" value="<?= $blogLimit ?>">
                    </label>
                </div>
                <p class="field-hint">0, seçilen kategorideki bütün yazıları gösterir. Bu ayarla <?= count($blogPreview) ?> yazı listelenir.</p>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Düğme</h2>
                <div class="fields-2">
                    <label class="field" for="blog-button">
                        <span>Düğme yazısı</span>
                        <input id="blog-button" name="button_label" type="text" maxlength="40" value="<?= e((string) ($blogButton['label'] ?? '')) ?>">
                    </label>
                    <label class="field" for="blog-button-url">
                        <span>Düğme adresi</span>
                        <input id="blog-button-url" name="button_url" type="text" maxlength="180" value="<?= e((string) ($blogButton['url'] ?? '')) ?>" placeholder="/blog">
                    </label>
                </div>
            </section>
            <section class="settings-card">
                <h2>Kaydırma</h2>
                <label class="check" for="slider-enabled"><input id="slider-enabled" name="slider_enabled" type="checkbox"<?= $blogSliderOn ? ' checked' : '' ?>> <span>Sağa sola kayan slider kullan</span></label>
                <div class="service-slider-fields">
                    <div class="fields-2">
                        <label class="field" for="slider-effect">
                            <span>Geçiş</span>
                            <select id="slider-effect" name="slider_effect">
                                <?php foreach (['slide' => 'Kaydırma', 'fade' => 'Belirme', 'none' => 'Anında geçiş'] as $effect => $label): ?>
                                    <option value="<?= e($effect) ?>"<?= (string) ($blogSlider['effect'] ?? 'slide') === $effect ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field" for="slider-step">
                            <span>Adım</span>
                            <select id="slider-step" name="slider_step">
                                <option value="1"<?= (string) ($blogSlider['step'] ?? '1') === '1' ? ' selected' : '' ?>>Bir kart</option>
                                <option value="group"<?= (string) ($blogSlider['step'] ?? '') === 'group' ? ' selected' : '' ?>>Görünen grup</option>
                            </select>
                        </label>
                        <label class="field" for="slider-interval">
                            <span>Bekleme (saniye)</span>
                            <input id="slider-interval" name="slider_interval" type="number" min="3" max="12" value="<?= (int) ($blogSlider['interval'] ?? 5) ?>">
                        </label>
                    </div>
                    <label class="check" for="slider-autoplay"><input id="slider-autoplay" name="slider_autoplay" type="checkbox"<?= !isset($blogSlider['autoplay']) || !empty($blogSlider['autoplay']) ? ' checked' : '' ?>> <span>Otomatik kaydır</span></label>
                    <label class="check" for="slider-loop"><input id="slider-loop" name="slider_loop" type="checkbox"<?= !isset($blogSlider['loop']) || !empty($blogSlider['loop']) ? ' checked' : '' ?>> <span>Başa dön</span></label>
                    <label class="check" for="slider-arrows"><input id="slider-arrows" name="slider_arrows" type="checkbox"<?= !isset($blogSlider['arrows']) || !empty($blogSlider['arrows']) ? ' checked' : '' ?>> <span>Oklar</span></label>
                    <label class="check" for="slider-dots"><input id="slider-dots" name="slider_dots" type="checkbox"<?= !isset($blogSlider['dots']) || !empty($blogSlider['dots']) ? ' checked' : '' ?>> <span>Noktalar</span></label>
                    <label class="check" for="slider-pause"><input id="slider-pause" name="slider_pause" type="checkbox"<?= !isset($blogSlider['pause']) || !empty($blogSlider['pause']) ? ' checked' : '' ?>> <span>Üzerine gelince dur</span></label>
                </div>
            </section>
            <div class="settings-save">
                <button class="login-submit" type="submit">Yazı listesini kaydet</button>
            </div>
        </form>
    <?php elseif (($section['type'] ?? '') === 'products'): ?>
        <?php
        $productColumns = (int) ($section['content']['columns'] ?? 3);
        if (!in_array($productColumns, [2, 3, 4, 5, 6], true)) {
            $productColumns = 3;
        }
        $productLimit = (int) ($section['content']['limit'] ?? 0);
        if ($productLimit < 0 || $productLimit > 24) {
            $productLimit = 0;
        }
        $productOrder = (string) ($section['content']['order'] ?? 'new') === 'old' ? 'old' : 'new';
        $productCategory = (string) ($section['content']['category'] ?? '');
        $productCategories = product_categories();
        if (!isset($productCategories[$productCategory])) {
            $productCategory = '';
        }
        $productSlider = is_array($section['content']['slider'] ?? null) ? $section['content']['slider'] : [];
        $productSliderOn = !empty($productSlider['enabled']);
        $productButton = is_array($section['content']['button'] ?? null) ? $section['content']['button'] : [];
        $productHeading = trim((string) ($section['content']['title'] ?? ''));
        if ($productHeading === '') {
            $headingLines = $section['content']['title_lines'] ?? [];
            $productHeading = is_array($headingLines) ? trim(implode(' ', array_map(static fn (mixed $line): string => (string) $line, $headingLines))) : '';
        }
        $productPreview = section_products([
            'category' => $productCategory,
            'order' => $productOrder,
            'limit' => $productLimit,
        ]);
        $pledgeOn = !array_key_exists('pledge_enabled', $section['content']) || !empty($section['content']['pledge_enabled']);
        $pledgeSide = (string) ($section['content']['pledge_side'] ?? 'left') === 'right' ? 'right' : 'left';
        $pledgeLines = is_array($section['content']['pledge_title'] ?? null) ? array_values($section['content']['pledge_title']) : [];
        $pledgeLines = array_pad(array_slice($pledgeLines, 0, 3), 3, '');
        $pledgeButton = is_array($section['content']['pledge_button'] ?? null) ? $section['content']['pledge_button'] : [];
        $pledgeImage = (string) ($section['content']['pledge_image'] ?? '');
        $pledgeManaged = $pledgeImage !== '' && !str_starts_with($pledgeImage, 'assets/images/');
        ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
            <?php if ($returnGrid !== ''): ?>
                <input type="hidden" name="donus" value="grid">
                <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
            <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="products">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2>Ürün vitrini</h2>
                <p class="settings-lead">Yayındaki ürünler buradan seçtiğin kategoriye ve eklenme sırasına göre dizilir. Kaydırma kapalıyken seçilen adetten sonrakiler alt satıra iner. Kaydırma açıkken kartlar sağa sola kayar.</p>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>" required>
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                    <label class="field" for="product-eyebrow">
                        <span>Üst etiket</span>
                        <input id="product-eyebrow" name="eyebrow" type="text" maxlength="40" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
                    </label>
                    <label class="field" for="product-heading">
                        <span>Bölüm başlığı</span>
                        <input id="product-heading" name="heading" type="text" maxlength="120" value="<?= e($productHeading) ?>">
                    </label>
                    <label class="field" for="product-category">
                        <span>Kategori</span>
                        <select id="product-category" name="category">
                            <option value=""<?= $productCategory === '' ? ' selected' : '' ?>>Tüm kategoriler</option>
                            <?php foreach ($productCategories as $catKey => $catLabel): ?>
                                <option value="<?= e((string) $catKey) ?>"<?= $productCategory === (string) $catKey ? ' selected' : '' ?>><?= e($catLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="product-order">
                        <span>Sıralama</span>
                        <select id="product-order" name="order">
                            <option value="new"<?= $productOrder === 'new' ? ' selected' : '' ?>>Yeniden eskiye</option>
                            <option value="old"<?= $productOrder === 'old' ? ' selected' : '' ?>>Eskiden yeniye</option>
                        </select>
                    </label>
                    <label class="field" for="product-columns">
                        <span>Yan yana kart</span>
                        <select id="product-columns" name="columns">
                            <?php foreach ([2, 3, 4, 5, 6] as $count): ?>
                                <option value="<?= $count ?>"<?= $productColumns === $count ? ' selected' : '' ?>><?= $count ?> kart</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="product-limit">
                        <span>Ürün sayısı</span>
                        <input id="product-limit" name="limit" type="number" min="0" max="24" value="<?= $productLimit ?>">
                    </label>
                </div>
                <label class="field" for="product-text">
                    <span>Kısa metin</span>
                    <input id="product-text" name="text" type="text" maxlength="280" value="<?= e((string) ($section['content']['text'] ?? '')) ?>">
                </label>
                <p class="field-hint">0, seçilen kategorideki bütün ürünleri gösterir. Bu ayarla <?= count($productPreview) ?> ürün listelenir.<?php if ($productCategories === []): ?> Önce <a href="<?= root_url('admin/urun-kategorileri.php?yeni=1') ?>">ürün kategorisi</a> ekle.<?php endif; ?></p>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
            </section>
            <section class="settings-card">
                <h2>Söz alanı</h2>
                <p class="settings-lead">Ürünlerin yanındaki kutu. Kapalıyken ürünler bu alanı da kullanır.</p>
                <label class="check" for="pledge-enabled"><input id="pledge-enabled" name="pledge_enabled" type="checkbox"<?= $pledgeOn ? ' checked' : '' ?>> <span>Söz alanını göster</span></label>
                <div class="pledge-fields">
                    <div class="layout-choices">
                        <label class="layout-choice">
                            <input type="radio" name="pledge_side" value="left"<?= $pledgeSide === 'left' ? ' checked' : '' ?>>
                            <span><strong>Solda</strong><small>Söz solda, ürünler sağda.</small></span>
                        </label>
                        <label class="layout-choice">
                            <input type="radio" name="pledge_side" value="right"<?= $pledgeSide === 'right' ? ' checked' : '' ?>>
                            <span><strong>Sağda</strong><small>Ürünler solda, söz sağda.</small></span>
                        </label>
                    </div>
                    <div class="fields-2">
                        <?php foreach ($pledgeLines as $lineIndex => $pledgeLine): ?>
                            <label class="field" for="pledge-line-<?= (int) $lineIndex ?>">
                                <span>Başlık satırı <?= (int) $lineIndex + 1 ?></span>
                                <input id="pledge-line-<?= (int) $lineIndex ?>" name="pledge_title[]" type="text" maxlength="40" value="<?= e((string) $pledgeLine) ?>">
                            </label>
                        <?php endforeach; ?>
                        <label class="field" for="pledge-text">
                            <span>Metin</span>
                            <input id="pledge-text" name="pledge_text" type="text" maxlength="280" value="<?= e((string) ($section['content']['pledge_text'] ?? '')) ?>">
                        </label>
                        <label class="field" for="pledge-button">
                            <span>Düğme yazısı</span>
                            <input id="pledge-button" name="pledge_button_label" type="text" maxlength="40" value="<?= e((string) ($pledgeButton['label'] ?? '')) ?>">
                        </label>
                        <label class="field" for="pledge-button-url">
                            <span>Düğme adresi</span>
                            <input id="pledge-button-url" name="pledge_button_url" type="text" maxlength="180" value="<?= e((string) ($pledgeButton['url'] ?? '')) ?>" placeholder="#iletisim">
                        </label>
                    </div>
                    <?php if ($pledgeImage !== ''): ?>
                        <img class="about-preview" src="<?= root_url($pledgeImage) ?>" alt="">
                    <?php endif; ?>
                    <div class="field">
                        <span>Görsel</span>
                        <label class="file-pick">
                            <input id="pledge-image" name="pledge_image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                            <span class="file-btn">Dosya seç</span>
                            <span class="file-name">Dosya seçilmedi</span>
                        </label>
                    </div>
                    <label class="field" for="pledge-alt">
                        <span>Görsel açıklaması</span>
                        <input id="pledge-alt" name="pledge_alt" type="text" maxlength="140" value="<?= e((string) ($section['content']['pledge_alt'] ?? '')) ?>">
                    </label>
                    <?php if ($pledgeManaged): ?>
                        <label class="check" for="remove-pledge-image"><input id="remove-pledge-image" name="remove_pledge_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                    <?php endif; ?>
                </div>
            </section>
            <section class="settings-card">
                <h2>Düğme</h2>
                <div class="fields-2">
                    <label class="field" for="product-button">
                        <span>Düğme yazısı</span>
                        <input id="product-button" name="button_label" type="text" maxlength="40" value="<?= e((string) ($productButton['label'] ?? '')) ?>">
                    </label>
                    <label class="field" for="product-button-url">
                        <span>Düğme adresi</span>
                        <input id="product-button-url" name="button_url" type="text" maxlength="180" value="<?= e((string) ($productButton['url'] ?? '')) ?>" placeholder="/urunler">
                    </label>
                </div>
            </section>
            <section class="settings-card">
                <h2>Kaydırma</h2>
                <label class="check" for="slider-enabled"><input id="slider-enabled" name="slider_enabled" type="checkbox"<?= $productSliderOn ? ' checked' : '' ?>> <span>Sağa sola kayan slider kullan</span></label>
                <div class="service-slider-fields">
                    <div class="fields-2">
                        <label class="field" for="slider-effect">
                            <span>Geçiş</span>
                            <select id="slider-effect" name="slider_effect">
                                <?php foreach (['slide' => 'Kaydırma', 'fade' => 'Belirme', 'none' => 'Anında geçiş'] as $effect => $label): ?>
                                    <option value="<?= e($effect) ?>"<?= (string) ($productSlider['effect'] ?? 'slide') === $effect ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field" for="slider-step">
                            <span>Adım</span>
                            <select id="slider-step" name="slider_step">
                                <option value="1"<?= (string) ($productSlider['step'] ?? '1') === '1' ? ' selected' : '' ?>>Bir kart</option>
                                <option value="group"<?= (string) ($productSlider['step'] ?? '') === 'group' ? ' selected' : '' ?>>Görünen grup</option>
                            </select>
                        </label>
                        <label class="field" for="slider-interval">
                            <span>Bekleme (saniye)</span>
                            <input id="slider-interval" name="slider_interval" type="number" min="3" max="12" value="<?= (int) ($productSlider['interval'] ?? 5) ?>">
                        </label>
                    </div>
                    <label class="check" for="slider-autoplay"><input id="slider-autoplay" name="slider_autoplay" type="checkbox"<?= !isset($productSlider['autoplay']) || !empty($productSlider['autoplay']) ? ' checked' : '' ?>> <span>Otomatik kaydır</span></label>
                    <label class="check" for="slider-loop"><input id="slider-loop" name="slider_loop" type="checkbox"<?= !isset($productSlider['loop']) || !empty($productSlider['loop']) ? ' checked' : '' ?>> <span>Başa dön</span></label>
                    <label class="check" for="slider-arrows"><input id="slider-arrows" name="slider_arrows" type="checkbox"<?= !isset($productSlider['arrows']) || !empty($productSlider['arrows']) ? ' checked' : '' ?>> <span>Oklar</span></label>
                    <label class="check" for="slider-dots"><input id="slider-dots" name="slider_dots" type="checkbox"<?= !isset($productSlider['dots']) || !empty($productSlider['dots']) ? ' checked' : '' ?>> <span>Noktalar</span></label>
                    <label class="check" for="slider-pause"><input id="slider-pause" name="slider_pause" type="checkbox"<?= !isset($productSlider['pause']) || !empty($productSlider['pause']) ? ' checked' : '' ?>> <span>Üzerine gelince dur</span></label>
                </div>
            </section>
            <div class="settings-save">
                <button class="login-submit" type="submit">Ürün vitrinini kaydet</button>
            </div>
        </form>
    <?php else: ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/bolumler.php?bolum=' . (string) $section['key']) ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
            <input type="hidden" name="action" value="meta">
            <input type="hidden" name="section_key" value="<?= e((string) $section['key']) ?>">
            <section class="settings-card">
                <h2><?= e(SectionCatalog::label((string) $section['type'])) ?></h2>
                <?php if (($section['type'] ?? '') === 'hero'): ?>
                    <p class="settings-lead">Slaytların metni, görseli ve videosu Karşılama düzenleyicisinde duruyor. Buradan adını, adresini ve görünürlüğünü değiştirirsin.</p>
                    <p><a href="<?= root_url(($builderPage === 'home' && ($section['key'] ?? '') === 'hero') ? 'admin/hero.php' : ('admin/hero.php?sayfa=' . rawurlencode($builderPage) . '&bolum=' . rawurlencode((string) ($section['key'] ?? '')))) ?>">Karşılamayı düzenle</a></p>
                <?php else: ?>
                    <p class="settings-lead">Adı, bağlantı adresi ve görünürlüğü buradan kaydedilir. Bölüm eklenirken mevcut içerik kopyalanır.</p>
                <?php endif; ?>
                <div class="fields-2">
                    <label class="field" for="section-title">
                        <span>Liste adı</span>
                        <input id="section-title" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>">
                    </label>
                    <label class="field" for="section-anchor">
                        <span>Bağlantı adresi</span>
                        <input id="section-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>">
                    </label>
                </div>
                <label class="check" for="section-active"><input id="section-active" name="active" type="checkbox"<?= !empty($section['active']) ? ' checked' : '' ?>> <span><?= $builderPage === 'home' ? 'Anasayfada göster' : 'Sayfada göster' ?></span></label>
                <div class="settings-save">
                    <button class="login-submit" type="submit">Bölümü kaydet</button>
                </div>
            </section>
        </form>
    <?php endif; ?>
<?php else: ?>
    <div class="slide-head">
        <h2>Anasayfa bölümleri</h2>
        <a class="login-submit slide-add" href="<?= root_url('admin/bolumler.php?yeni=1') ?>">Yeni bölüm ekle</a>
    </div>
    <div class="slide-list">
        <?php foreach ($rows as $index => $row): ?>
            <article class="slide-row">
                <div class="slide-thumb"><span><?= (int) $index + 1 ?></span></div>
                <div>
                    <strong><?= e((string) ($row['title'] !== '' ? $row['title'] : SectionCatalog::label((string) $row['type']))) ?></strong>
                    <p><?= e(SectionCatalog::label((string) $row['type'])) ?> · <?= !empty($row['active']) ? 'Yayında' : 'Gizli' ?></p>
                </div>
                <div class="slide-actions">
                    <form method="post" action="<?= root_url('admin/bolumler.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
                        <input type="hidden" name="action" value="move">
                        <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                        <input type="hidden" name="direction" value="-1">
                        <button type="submit"<?= $index === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                    </form>
                    <form method="post" action="<?= root_url('admin/bolumler.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
                        <input type="hidden" name="action" value="move">
                        <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                        <input type="hidden" name="direction" value="1">
                        <button type="submit"<?= $index === count($rows) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                    </form>
                    <form method="post" action="<?= root_url('admin/bolumler.php') ?>">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                        <?php if (empty($row['active'])): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                        <button type="submit"><?= !empty($row['active']) ? 'Gizle' : 'Yayınla' ?></button>
                    </form>
                    <?php if (($row['type'] ?? '') === 'hero'): ?>
                        <a href="<?= root_url(($builderPage === 'home' && $row['key'] === 'hero') ? 'admin/hero.php' : ('admin/hero.php?sayfa=' . rawurlencode($builderPage) . '&bolum=' . rawurlencode((string) $row['key']))) ?>">Düzenle</a>
                    <?php else: ?>
                        <a href="<?= root_url($editorUrl((string) $row['key'])) ?>">Düzenle</a>
                    <?php endif; ?>
                    <form method="post" action="<?= root_url('admin/bolumler.php') ?>" data-confirm="Bu bölüm anasayfadan kaldırılsın mı?">
                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($builderPage) ?>">
                    <?php if ($returnGrid !== ''): ?>
                    <input type="hidden" name="donus" value="grid">
                    <input type="hidden" name="grid" value="<?= e($returnGrid) ?>">
                    <?php elseif ($returnBuilder): ?><input type="hidden" name="donus" value="gelismis"><?php endif; ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                        <button type="submit"<?= ($builderPage === 'home' && ($row['type'] ?? '') === 'hero' && $row['key'] === 'hero') ? ' disabled' : '' ?>>Sil</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="field-hint">Sıra, anasayfadaki sıradır. Aynı bölümleri gelişmiş sayfa düzeninden de dizebilirsin.</p>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
