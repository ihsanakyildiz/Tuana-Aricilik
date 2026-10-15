<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/SectionCatalog.php';
require dirname(__DIR__) . '/app/SectionEditor.php';
require dirname(__DIR__) . '/app/PageEditor.php';

Auth::boot();
Auth::requireLogin();

$requested = $_POST['page_slug'] ?? $_GET['sayfa'] ?? '';
$slug = is_string($requested) ? $requested : '';
$requestedKey = $_POST['section_key'] ?? $_GET['bolum'] ?? '';
$gridKey = is_string($requestedKey) ? $requestedKey : '';
if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $gridKey) !== 1) {
    Auth::redirect('admin/sayfalar.php');
}

$record = $slug === 'home' ? null : PageEditor::find($slug);
$allowed = $slug === 'home';
if (is_array($record) && (($record['kind'] ?? '') === 'advanced')) {
    $allowed = true;
}
if (!$allowed && $record === null) {
    foreach (PageEditor::designed() as $designed) {
        if ($designed['key'] === $slug) {
            $allowed = true;
            break;
        }
    }
}
if (!$allowed) {
    Auth::redirect('admin/sayfalar.php');
}

SectionEditor::usePage($slug);
$section = SectionEditor::open($gridKey);
if ($section === null || ($section['type'] ?? '') !== 'grid') {
    Auth::redirect('admin/gelismis.php?sayfa=' . rawurlencode($slug));
}

$back = 'admin/gelismis.php?sayfa=' . rawurlencode($slug);
$self = 'admin/grid.php?sayfa=' . rawurlencode($slug) . '&bolum=' . rawurlencode($gridKey);
$errors = [];
$columnIndex = (int) ($_POST['column'] ?? $_GET['kolon'] ?? 0);
if ($columnIndex < 0) {
    $columnIndex = 0;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } elseif ($action === 'head') {
        $errors = SectionEditor::saveGridHead($gridKey, $_POST);
        if ($errors === []) {
            Auth::flash('ok', 'Grid başlığı kaydedildi.');
            Auth::redirect($self);
        }
    } elseif ($action === 'layout') {
        $errors = SectionEditor::saveGridLayout($gridKey, $_POST);
        if ($errors === []) {
            Auth::flash('ok', 'Yerleşim kaydedildi.');
            Auth::redirect($self);
        }
    } elseif ($action === 'preset') {
        $preset = is_string($_POST['preset'] ?? null) ? $_POST['preset'] : '';
        $errors = SectionEditor::applyGridPreset($gridKey, $preset);
        if ($errors === []) {
            Auth::flash('ok', 'Kolonlar güncellendi.');
            Auth::redirect($self);
        }
    } elseif ($action === 'span') {
        $errors = SectionEditor::adjustGridSpan($gridKey, (int) ($_POST['index'] ?? -1), (int) ($_POST['delta'] ?? 0));
        if ($errors === []) {
            Auth::redirect($self);
        }
    } elseif ($action === 'column-add') {
        $errors = SectionEditor::addGridColumn($gridKey);
        if ($errors === []) {
            Auth::flash('ok', 'Kolon eklendi.');
            Auth::redirect($self);
        }
    } elseif ($action === 'column-remove') {
        $errors = SectionEditor::removeGridColumn($gridKey, (int) ($_POST['index'] ?? -1));
        if ($errors === []) {
            Auth::flash('ok', 'Kolon birleştirildi.');
            Auth::redirect($self);
        }
    } elseif ($action === 'add') {
        $type = is_string($_POST['type'] ?? null) ? $_POST['type'] : '';
        $created = SectionEditor::gridAdd($gridKey, $columnIndex, $type);
        $errors = $created['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Bölüm kolona eklendi.');
            Auth::redirect($self);
        }
    } elseif ($action === 'arrange') {
        $payload = is_string($_POST['columns_json'] ?? null) ? $_POST['columns_json'] : '';
        $errors = SectionEditor::gridArrange($gridKey, $payload);
        if ($errors === []) {
            Auth::redirect($self);
        }
    } elseif ($action === 'shift') {
        $childKey = is_string($_POST['child_key'] ?? null) ? $_POST['child_key'] : '';
        $errors = SectionEditor::gridShift($gridKey, $childKey, (int) ($_POST['direction'] ?? 0));
        if ($errors === []) {
            Auth::redirect($self);
        }
    } elseif ($action === 'drop') {
        $childKey = is_string($_POST['child_key'] ?? null) ? $_POST['child_key'] : '';
        $errors = SectionEditor::gridDrop($gridKey, $childKey);
        if ($errors === []) {
            Auth::flash('ok', 'Bölüm kolondan kaldırıldı.');
            Auth::redirect($self);
        }
    }
}

$section = SectionEditor::open($gridKey);
$state = $section === null ? null : SectionEditor::gridState($gridKey);
if ($section === null || $state === null) {
    Auth::redirect($back);
}
$columns = $state['columns'];
if ($columnIndex >= count($columns)) {
    $columnIndex = max(0, count($columns) - 1);
}
$adding = isset($_GET['ekle']);
$byKey = [];
foreach (SectionEditor::rows() as $row) {
    $byKey[$row['key']] = $row;
}
$spans = [];
foreach ($columns as $column) {
    $spans[] = $column['span'];
}
$canAddColumn = count($columns) < 12 && max($spans) > 1;
$activePreset = '';
foreach (SectionEditor::GRID_PRESETS as $presetId => $preset) {
    if ($preset['spans'] === $spans) {
        $activePreset = $presetId;
        break;
    }
}
$childEdit = static function (array $child) use ($slug, $gridKey): string {
    $key = (string) $child['key'];
    $query = 'sayfa=' . rawurlencode($slug) . '&bolum=' . rawurlencode($key) . '&donus=grid&grid=' . rawurlencode($gridKey);
    if (($child['type'] ?? '') === 'hero') {
        if ($slug === 'home' && $key === 'hero') {
            return 'admin/hero.php?donus=grid&grid=' . rawurlencode($gridKey);
        }

        return 'admin/hero.php?' . $query;
    }

    return 'admin/bolumler.php?' . $query;
};
$catalog = [
    'hero' => ['label' => 'Karşılama', 'text' => 'Tam ekran karşılama. Bu kolonda kendi slaytlarıyla durur.'],
    'pagehead' => ['label' => 'Sayfa bandı', 'text' => 'Başlık ve sayfa yolu.'],
];
foreach (SectionCatalog::DESIGNS as $type => $design) {
    $catalog[$type] = ['label' => $design['label'], 'text' => $design['text']];
}
$catalog['blog'] = ['label' => 'Yazı listesi', 'text' => 'Yayındaki blog yazılarını dizer.'];

$pageTitle = 'Grid satırı';
$crumbCurrent = 'Grid';
$activeNav = 'sayfalar';

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e((string) $error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<p class="slide-back"><a href="<?= root_url($back) ?>">Sayfaya dön</a></p>
<?php if ($adding): ?>
    <section class="settings-card">
        <h2>Kolona bölüm ekle</h2>
        <p class="settings-lead">Seçtiğin tasarım bu kolonun sonuna eklenir. Grid içine başka grid konmaz.</p>
    </section>
    <div class="design-grid">
        <?php foreach ($catalog as $type => $design): ?>
            <article class="design-card">
                <?php if (SectionCatalog::has($type)): ?>
                    <div class="design-stage" data-preview>
                        <iframe src="<?= root_url('admin/onizleme.php?tip=' . rawurlencode($type)) ?>" title="<?= e($design['label']) ?> önizlemesi" loading="lazy" tabindex="-1"></iframe>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= root_url($self . '&ekle=1&kolon=' . $columnIndex) ?>">
                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                    <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                    <input type="hidden" name="column" value="<?= $columnIndex ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <h2><?= e($design['label']) ?></h2>
                    <p><?= e($design['text']) ?></p>
                    <button class="login-submit" type="submit">Bu kolona ekle</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <form class="settings-form" method="post" action="<?= root_url($self) ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
        <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
        <input type="hidden" name="action" value="head">
        <section class="settings-card">
            <h2>Grid başlığı</h2>
            <p class="grid-note">Boş bırakılan alanlar sitede görünmez.</p>
            <div class="fields-2">
                <label class="field" for="grid-label">
                    <span>Admin etiketi</span>
                    <input id="grid-label" name="title" type="text" maxlength="80" value="<?= e((string) $section['title']) ?>" required>
                </label>
                <label class="field" for="grid-anchor">
                    <span>Anchor / id (opsiyonel)</span>
                    <input id="grid-anchor" name="anchor" type="text" maxlength="64" value="<?= e((string) ($section['content']['anchor'] ?? '')) ?>" placeholder="örnek: iletisim">
                </label>
            </div>
            <label class="field" for="grid-eyebrow">
                <span>Üst etiket / rozet</span>
                <input id="grid-eyebrow" name="eyebrow" type="text" maxlength="80" value="<?= e((string) ($section['content']['eyebrow'] ?? '')) ?>">
            </label>
            <div class="fields-2">
                <label class="field" for="grid-heading">
                    <span>Bölüm başlığı</span>
                    <input id="grid-heading" name="heading" type="text" maxlength="140" value="<?= e((string) ($section['content']['title'] ?? '')) ?>">
                </label>
                <label class="field" for="grid-subtitle">
                    <span>Alt başlık</span>
                    <input id="grid-subtitle" name="subtitle" type="text" maxlength="220" value="<?= e((string) ($section['content']['subtitle'] ?? '')) ?>">
                </label>
            </div>
            <div class="settings-save">
                <button class="login-submit" type="submit">Başlığı kaydet</button>
            </div>
        </section>
    </form>

    <section class="settings-card bs-builder">
        <div class="bs-head">
            <div>
                <h2>Bootstrap satırı</h2>
                <p class="settings-lead">12 birim. Kolon genişliğini + ve − ile değiştir; toplam her zaman 12 kalır. İçine istediğin bölümü ekle. Telefonda kolonlar alt alta iner.</p>
            </div>
            <form method="post" action="<?= root_url($self) ?>">
                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                <input type="hidden" name="action" value="column-add">
                <button class="bs-tool" type="submit"<?= $canAddColumn ? '' : ' disabled' ?>>Kolon ekle</button>
            </form>
        </div>
        <form method="post" action="<?= root_url($self) ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
            <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
            <input type="hidden" name="action" value="preset">
            <div class="bs-presets">
                <?php foreach (SectionEditor::GRID_PRESETS as $presetId => $preset): ?>
                    <button class="bs-preset<?= $activePreset === $presetId ? ' is-on' : '' ?>" type="submit" name="preset" value="<?= e((string) $presetId) ?>">
                        <span class="bs-mini" aria-hidden="true">
                            <?php foreach ($preset['spans'] as $miniSpan): ?>
                                <i data-span="<?= (int) $miniSpan ?>"></i>
                            <?php endforeach; ?>
                        </span>
                        <span><?= e($preset['label']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </form>
        <form method="post" action="<?= root_url($self) ?>" data-grid-layout>
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
            <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
            <input type="hidden" name="action" value="layout">
            <div class="bs-toolbar">
                <label class="field" for="grid-gutter">
                    <span>Boşluk</span>
                    <select id="grid-gutter" name="gutter">
                        <?php foreach (SectionEditor::GRID_GUTTERS as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $state['gutter'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field" for="grid-align">
                    <span>Dikey hizalama</span>
                    <select id="grid-align" name="align">
                        <?php foreach (SectionEditor::GRID_ALIGNS as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $state['align'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field" for="grid-container">
                    <span>Satır genişliği</span>
                    <select id="grid-container" name="container">
                        <?php foreach (SectionEditor::GRID_CONTAINERS as $value => $label): ?>
                            <option value="<?= e($value) ?>"<?= $state['container'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="settings-save">
                    <button class="login-submit" type="submit">Uygula</button>
                </div>
            </div>
        </form>
        <div class="bs-scroll" data-grid-board>
            <form method="post" action="<?= root_url($self) ?>" data-grid-arrange hidden>
                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                <input type="hidden" name="action" value="arrange">
                <input type="hidden" name="columns_json" value="">
            </form>
            <div class="bs-ruler" aria-hidden="true">
                <?php for ($unit = 1; $unit <= 12; $unit++): ?>
                    <span><?= $unit ?></span>
                <?php endfor; ?>
            </div>
            <div class="bs-row">
                <?php foreach ($columns as $index => $column): ?>
                    <?php
                    $span = (int) $column['span'];
                    $canGrow = false;
                    $count = count($spans);
                    for ($step = 1; $step < $count; $step++) {
                        if ($spans[($index + $step) % $count] > 1) {
                            $canGrow = true;
                            break;
                        }
                    }
                    $canShrink = $span > 1 && $count > 1;
                    $mergeConfirm = $column['keys'] === []
                        ? 'Bu kolon kaldırılsın mı?'
                        : 'Bu kolondaki bölümler yan kolona taşınsın mı?';
                    ?>
                    <div class="bs-col" data-grid-column data-span="<?= $span ?>">
                        <div class="bs-col-bar">
                            <strong>col-md-<?= $span ?></strong>
                            <div class="bs-steps">
                                <form method="post" action="<?= root_url($self) ?>">
                                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                                    <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                                    <input type="hidden" name="action" value="span">
                                    <input type="hidden" name="index" value="<?= (int) $index ?>">
                                    <input type="hidden" name="delta" value="-1">
                                    <button type="submit"<?= $canShrink ? '' : ' disabled' ?> aria-label="Kolonu daralt">−</button>
                                </form>
                                <form method="post" action="<?= root_url($self) ?>">
                                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                                    <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                                    <input type="hidden" name="action" value="span">
                                    <input type="hidden" name="index" value="<?= (int) $index ?>">
                                    <input type="hidden" name="delta" value="1">
                                    <button type="submit"<?= $canGrow ? '' : ' disabled' ?> aria-label="Kolonu genişlet">+</button>
                                </form>
                                <?php if (count($columns) > 1): ?>
                                    <form method="post" action="<?= root_url($self) ?>" data-confirm="<?= e($mergeConfirm) ?>">
                                        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                        <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                                        <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                                        <input type="hidden" name="action" value="column-remove">
                                        <input type="hidden" name="index" value="<?= (int) $index ?>">
                                        <button type="submit" aria-label="Kolonu birleştir">×</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bs-col-list" data-grid-list>
                            <?php foreach ($column['keys'] as $position => $childKey): ?>
                                <?php
                                $child = $byKey[$childKey] ?? null;
                                if ($child === null) {
                                    continue;
                                }
                                $childTitle = (string) ($child['title'] !== '' ? $child['title'] : SectionCatalog::label((string) $child['type']));
                                ?>
                                <article class="grid-chip" draggable="true" data-grid-chip data-key="<?= e($childKey) ?>">
                                    <span class="grid-handle" aria-hidden="true">⋮⋮</span>
                                    <div>
                                        <strong><?= e($childTitle) ?></strong>
                                        <small><?= e(SectionCatalog::label((string) $child['type'])) ?><?= empty($child['active']) ? ' · Gizli' : '' ?></small>
                                    </div>
                                    <div class="grid-chip-actions">
                                        <form method="post" action="<?= root_url($self) ?>">
                                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                                            <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                                            <input type="hidden" name="action" value="shift">
                                            <input type="hidden" name="child_key" value="<?= e($childKey) ?>">
                                            <input type="hidden" name="direction" value="-1">
                                            <button type="submit"<?= $position === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                                        </form>
                                        <form method="post" action="<?= root_url($self) ?>">
                                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                                            <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                                            <input type="hidden" name="action" value="shift">
                                            <input type="hidden" name="child_key" value="<?= e($childKey) ?>">
                                            <input type="hidden" name="direction" value="1">
                                            <button type="submit"<?= $position === count($column['keys']) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                                        </form>
                                        <a href="<?= root_url($childEdit($child)) ?>">Düzenle</a>
                                        <form method="post" action="<?= root_url($self) ?>" data-confirm="Bu bölüm kolondan kaldırılsın mı?">
                                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                                            <input type="hidden" name="section_key" value="<?= e($gridKey) ?>">
                                            <input type="hidden" name="action" value="drop">
                                            <input type="hidden" name="child_key" value="<?= e($childKey) ?>">
                                            <button type="submit" aria-label="Kaldır">Çıkar</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <p class="grid-empty">Bu kolon boş.</p>
                        <a class="bs-add" href="<?= root_url($self . '&ekle=1&kolon=' . (int) $index) ?>">Bölüm ekle</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <p class="field-hint">Tutamacı sürükleyerek bölümü kolon içinde sıralayın veya başka kolona taşıyın. Çıkar, bölümü satırdan siler. Anasayfadaki ilk karşılama silinmez.</p>
    </section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
