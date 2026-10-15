<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/MenuEditor.php';

Auth::boot();
Auth::requireLogin();

$errors = [];
$creating = false;
$newName = '';
$menu = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
        $creating = $action === 'create';
        $newName = is_string($_POST['menu_name'] ?? null) ? $_POST['menu_name'] : '';
    } elseif ($action === 'create') {
        $newName = is_string($_POST['menu_name'] ?? null) ? $_POST['menu_name'] : '';
        $created = MenuEditor::create($newName);
        if ($created['errors'] === []) {
            Auth::flash('ok', 'Menü oluşturuldu. İçerik ekleyip kaydet.');
            Auth::redirect('admin/menuler.php?menu=' . $created['key']);
        }
        $errors = $created['errors'];
        $creating = true;
    } elseif ($action === 'save') {
        $saved = MenuEditor::save($_POST);
        if ($saved['errors'] === []) {
            Auth::flash('ok', 'Menü kaydedildi.');
            Auth::redirect('admin/menuler.php?menu=' . $saved['key']);
        }
        $errors = $saved['errors'];
        $menu = $saved['menu'];
    } elseif ($action === 'delete') {
        $key = is_string($_POST['menu_key'] ?? null) ? $_POST['menu_key'] : '';
        $errors = MenuEditor::remove($key);
        if ($errors === []) {
            Auth::flash('ok', 'Menü silindi.');
            Auth::redirect('admin/menuler.php');
        }
    }
}

$menus = MenuEditor::menus();
if (!$creating && $menu === null) {
    $requested = is_string($_GET['menu'] ?? null) ? $_GET['menu'] : '';
    if (isset($_GET['yeni'])) {
        $creating = true;
    } elseif ($requested !== '') {
        $menu = MenuEditor::find($requested);
        if ($menu === null) {
            $errors[] = 'Menü bulunamadı.';
        }
    } elseif ($menus !== []) {
        $openKey = '';
        foreach ($menus as $row) {
            if ($row['header']) {
                $openKey = $row['key'];
                break;
            }
        }
        if ($openKey === '') {
            $openKey = $menus[0]['key'];
        }
        $menu = MenuEditor::find($openKey);
    } else {
        $creating = true;
    }
}

$catalog = MenuEditor::catalog();
$pageTitle = 'Menüler';
$crumbCurrent = 'Menüler';
$activeNav = 'menuler';

$levels = ['Üst', 'Alt', 'İç'];
$renderNode = static function (array $item) use ($levels): void {
    $depth = (int) ($item['depth'] ?? 0);
    if ($depth < 0 || $depth > 2) {
        $depth = 0;
    }
    $kind = (string) ($item['kind'] ?? 'link');
    $locked = !empty($item['url_locked']);
    $status = (string) ($item['status'] ?? '');
    ?>
    <li class="menu-node" data-depth="<?= $depth ?>" data-kind="<?= e($kind) ?>" style="--menu-depth: <?= $depth ?>">
        <span class="menu-handle" data-menu-drag draggable="true" title="Sürükle">⋮⋮</span>
        <div class="menu-node-body">
            <input class="menu-label" name="item_label[]" type="text" maxlength="80" value="<?= e((string) ($item['label'] ?? '')) ?>" aria-label="Menüde görünen ad" autocomplete="off">
            <input class="menu-url" name="item_url[]" type="text" maxlength="180" value="<?= e((string) ($item['url'] ?? '')) ?>" aria-label="Adres" autocomplete="off" spellcheck="false"<?= $locked ? ' readonly' : '' ?>>
            <div class="menu-node-tools">
                <span class="menu-level"><?= e($levels[$depth]) ?></span>
                <span class="menu-kind"><?= e((string) ($item['kind_label'] ?? 'Bağlantı')) ?></span>
                <button class="menu-mini" type="button" data-menu-outdent title="Bir üst seviyeye çıkar">Üste al</button>
                <button class="menu-mini" type="button" data-menu-indent title="Bir alt seviyeye indir">Alta al</button>
                <button class="menu-mini" type="button" data-menu-up title="Aynı seviyede yukarı taşı">Yukarı</button>
                <button class="menu-mini" type="button" data-menu-down title="Aynı seviyede aşağı taşı">Aşağı</button>
                <label class="check menu-mega"><input data-menu-mega type="checkbox"<?= !empty($item['mega']) ? ' checked' : '' ?>> <span>Mega</span></label>
                <label class="check"><input data-menu-blank type="checkbox"<?= !empty($item['blank']) ? ' checked' : '' ?>> <span>Yeni sekme</span></label>
                <button class="menu-mini is-remove" type="button" data-menu-remove>Kaldır</button>
            </div>
            <?php if ($status !== ''): ?>
                <p class="menu-status"><?= e($status) ?></p>
            <?php else: ?>
                <p class="menu-status" hidden></p>
            <?php endif; ?>
        </div>
        <input type="hidden" name="item_id[]" value="<?= e((string) ($item['id'] ?? '')) ?>">
        <input type="hidden" name="item_kind[]" value="<?= e($kind) ?>">
        <input type="hidden" name="item_ref[]" value="<?= e((string) ($item['ref'] ?? '')) ?>">
        <input class="menu-depth" type="hidden" name="item_depth[]" value="<?= $depth ?>">
        <input class="menu-mega-value" type="hidden" name="item_mega[]" value="<?= !empty($item['mega']) ? '1' : '0' ?>">
        <input class="menu-blank-value" type="hidden" name="item_blank[]" value="<?= !empty($item['blank']) ? '1' : '0' ?>">
    </li>
    <?php
};

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="menu-admin">
    <aside class="settings-card menu-switch-card">
        <h2>Menüler</h2>
        <p class="settings-lead">İstediğin kadar menü oluştur. Üst menü olarak işaretlenen, sitedeki gezintide görünür.</p>
        <?php if ($menus === []): ?>
            <p class="field-hint">Henüz menü yok.</p>
        <?php else: ?>
            <ul class="menu-switch">
                <?php foreach ($menus as $row): ?>
                    <li>
                        <a class="<?= is_array($menu) && $menu['key'] === $row['key'] ? 'is-on' : '' ?>" href="<?= root_url('admin/menuler.php?menu=' . rawurlencode($row['key'])) ?>">
                            <strong><?= e($row['name']) ?></strong>
                            <span><?= $row['header'] ? 'Üst menü' : 'Atanmamış' ?> · <?= (int) $row['count'] ?> öğe</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a class="login-submit menu-switch-new" href="<?= root_url('admin/menuler.php?yeni=1') ?>">Yeni menü</a>
        <?php if (is_array($menu)): ?>
            <form method="post" action="<?= root_url('admin/menuler.php?menu=' . rawurlencode((string) $menu['key'])) ?>" data-confirm="Bu menü silinsin mi? Üst menüye bağlıysa siteden de kalkar.">
                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="menu_key" value="<?= e((string) $menu['key']) ?>">
                <button class="menu-delete" type="submit">Menüyü sil</button>
            </form>
        <?php endif; ?>
    </aside>

    <div class="menu-main">
        <?php if ($creating): ?>
            <form class="settings-form" method="post" action="<?= root_url('admin/menuler.php') ?>">
                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="create">
                <section class="settings-card">
                    <h2>Yeni menü</h2>
                    <p class="settings-lead">Ad yalnızca panelde görünür. Öğeleri bir sonraki adımda eklersin.</p>
                    <label class="field" for="new-menu-name">
                        <span>Menü adı</span>
                        <input id="new-menu-name" name="menu_name" type="text" maxlength="60" value="<?= e($newName) ?>" required>
                    </label>
                    <div class="settings-save">
                        <a class="login-submit slide-add" href="<?= root_url('admin/menuler.php') ?>">Vazgeç</a>
                        <button class="login-submit" type="submit">Menü oluştur</button>
                    </div>
                </section>
            </form>
        <?php elseif (is_array($menu)): ?>
            <form class="settings-form menu-editor" method="post" action="<?= root_url('admin/menuler.php?menu=' . rawurlencode((string) $menu['key'])) ?>" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="menu_key" value="<?= e((string) $menu['key']) ?>">
                <section class="settings-card">
                    <h2>Menü ayarı</h2>
                    <div class="fields-2">
                        <label class="field" for="menu-name">
                            <span>Menü adı</span>
                            <input id="menu-name" name="menu_name" type="text" maxlength="60" value="<?= e((string) $menu['name']) ?>" required>
                        </label>
                        <div class="field">
                            <span>Konum</span>
                            <label class="check" for="use-header"><input id="use-header" name="use_header" type="checkbox"<?= !empty($menu['header']) ? ' checked' : '' ?>> <span>Üst menüde göster</span></label>
                        </div>
                    </div>
                    <p class="field-hint">İşaretlenince bu menü sitedeki ana gezintinin yerini alır. Diğer menüler durur, istediğinde onu seçersin.</p>
                </section>

                <div class="menu-work">
                    <section class="settings-card">
                        <h2>Menüye ekle</h2>
                        <p class="settings-lead">Sayfa, yazı, ürün veya düz bağlantı seç. Eklenenler sağdaki listeye düşer; siteye geçmesi için kaydet.</p>
                        <div class="menu-tabs" role="tablist">
                            <?php foreach ($catalog as $groupIndex => $group): ?>
                                <button class="menu-tab<?= $groupIndex === 0 ? ' is-on' : '' ?>" type="button" data-menu-tab="<?= e((string) $group['id']) ?>"><?= e((string) $group['label']) ?></button>
                            <?php endforeach; ?>
                            <button class="menu-tab" type="button" data-menu-tab="baglanti">Bağlantı</button>
                        </div>
                        <?php foreach ($catalog as $groupIndex => $group): ?>
                            <div class="menu-source-panel<?= $groupIndex === 0 ? ' is-on' : '' ?>" data-menu-source-panel="<?= e((string) $group['id']) ?>">
                                <input class="menu-filter" type="search" data-menu-filter placeholder="Listede ara" aria-label="<?= e((string) $group['label']) ?> içinde ara">
                                <?php if ($group['items'] === []): ?>
                                    <p class="field-hint">Henüz kayıt yok.</p>
                                <?php else: ?>
                                    <div class="menu-source-list">
                                        <?php foreach ($group['items'] as $choice): ?>
                                            <?php
                                            $search = (string) $choice['title'] . ' ' . (string) $choice['url'];
                                            ?>
                                            <label class="menu-choice" data-menu-choice data-search="<?= e($search) ?>">
                                                <input type="checkbox" data-kind="<?= e((string) $choice['kind']) ?>" data-ref="<?= e((string) $choice['ref']) ?>" data-label="<?= e((string) $choice['title']) ?>" data-url="<?= e((string) $choice['url']) ?>">
                                                <span>
                                                    <?= e((string) $choice['title']) ?>
                                                    <?php if ((string) $choice['note'] !== ''): ?><em><?= e((string) $choice['note']) ?></em><?php endif; ?>
                                                    <small><?= e((string) $choice['url']) ?></small>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="menu-mini" type="button" data-menu-add>Seçilenleri ekle</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <div class="menu-source-panel" data-menu-source-panel="baglanti" data-menu-custom>
                            <label class="field">
                                <span>Görünen ad</span>
                                <input data-custom-label type="text" maxlength="80" autocomplete="off">
                            </label>
                            <label class="field">
                                <span>Adres</span>
                                <input data-custom-url type="text" maxlength="180" placeholder="/sayfa, #iletisim veya https://" autocomplete="off" spellcheck="false">
                            </label>
                            <button class="menu-mini" type="button" data-menu-custom-add>Bağlantıyı ekle</button>
                            <p class="menu-status" data-custom-error hidden></p>
                        </div>
                    </section>

                    <section class="settings-card">
                        <h2>Menü yapısı</h2>
                        <p class="settings-lead">Sürükleyerek sırayı değiştir. Alta al ile öğeyi bir üsttekinin altına indir. Mega işaretli üst öğede 1. seviye sütun, 2. seviye o sütunun bağlantısı olur.</p>
                        <p class="menu-empty" data-menu-empty<?= $menu['items'] !== [] ? ' hidden' : '' ?>>Henüz öğe yok. Soldan sayfa, yazı, ürün veya bağlantı ekle.</p>
                        <ol class="menu-tree" data-menu-tree>
                            <?php foreach ($menu['items'] as $item): ?>
                                <?php $renderNode($item); ?>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                </div>
                <div class="settings-save">
                    <a class="login-submit slide-add" href="<?= root_url('/') ?>" target="_blank" rel="noopener">Sitede gör</a>
                    <button class="login-submit" type="submit">Menüyü kaydet</button>
                </div>
            </form>
            <template id="menu-node-template">
                <?php $renderNode([
                    'id' => '',
                    'label' => '',
                    'kind' => 'link',
                    'ref' => '',
                    'url' => '',
                    'blank' => false,
                    'depth' => 0,
                    'mega' => false,
                    'kind_label' => 'Bağlantı',
                    'status' => '',
                    'url_locked' => false,
                ]); ?>
            </template>
        <?php endif; ?>
    </div>
</div>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
