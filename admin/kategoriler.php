<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/CategoryEditor.php';

Auth::boot();
Auth::requireLogin();

$errors = [];
$mode = 'list';
$category = null;
$posted = [
    'title' => '',
    'slug' => '',
    'active' => true,
    'key' => '',
    'seo_title' => '',
    'seo_description' => '',
    'image_alt' => '',
    'index' => true,
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
        $mode = $action === 'save' ? 'editor' : 'list';
    } elseif ($action === 'save') {
        $saved = CategoryEditor::save($_POST, $_FILES);
        $errors = $saved['errors'];
        $posted = $saved['values'];
        if ($errors === []) {
            Auth::flash('ok', $posted['key'] === '' ? 'Kategori eklendi.' : 'Kategori kaydedildi.');
            Auth::redirect('admin/kategoriler.php');
        }
        $mode = 'editor';
        if ($posted['key'] !== '') {
            $category = CategoryEditor::open($posted['key']);
        }
    } elseif ($action === 'delete') {
        $key = is_string($_POST['category_key'] ?? null) ? $_POST['category_key'] : '';
        $errors = CategoryEditor::remove($key);
        if ($errors === []) {
            Auth::flash('ok', 'Kategori silindi.');
            Auth::redirect('admin/kategoriler.php');
        }
    } elseif ($action === 'toggle') {
        $key = is_string($_POST['category_key'] ?? null) ? $_POST['category_key'] : '';
        CategoryEditor::setActive($key, isset($_POST['active']));
        Auth::redirect('admin/kategoriler.php');
    } elseif ($action === 'move') {
        $key = is_string($_POST['category_key'] ?? null) ? $_POST['category_key'] : '';
        CategoryEditor::move($key, (int) ($_POST['direction'] ?? 0));
        Auth::redirect('admin/kategoriler.php');
    }
}

if ($mode === 'list' && isset($_GET['yeni'])) {
    $mode = 'editor';
} elseif ($mode === 'list' && is_string($_GET['kategori'] ?? null) && $_GET['kategori'] !== '') {
    $category = CategoryEditor::open($_GET['kategori']);
    if ($category === null) {
        $errors[] = 'Kategori bulunamadı.';
    } else {
        $mode = 'editor';
        $posted = [
            'title' => $category['title'],
            'slug' => $category['key'],
            'active' => $category['active'],
            'key' => $category['key'],
            'seo_title' => $category['seo_title'],
            'seo_description' => $category['seo_description'],
            'image_alt' => $category['image_alt'],
            'index' => $category['index'],
        ];
    }
}

$rows = CategoryEditor::all();
$pageTitle = 'Kategoriler';
$crumbCurrent = 'Kategoriler';
$activeNav = 'yazi-kategoriler';

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($mode === 'editor'): ?>
    <?php
    $categoryImage = is_array($category) ? (string) ($category['image'] ?? '') : '';
    $uploadRoot = trim((string) setting('upload_dir', 'assets/uploads'), '/');
    $categoryManaged = $categoryImage !== '' && str_starts_with($categoryImage, $uploadRoot . '/');
    ?>
    <form class="settings-form" method="post" action="<?= root_url('admin/kategoriler.php') ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="category_key" value="<?= e($posted['key']) ?>">
        <section class="settings-card">
            <h2><?= $posted['key'] === '' ? 'Yeni kategori' : 'Kategoriyi düzenle' ?></h2>
            <p class="settings-lead">Ad, blogda ve kategori sayfasının başlığında görünür. Adres kalıcıdır: /blog/kategori/adres</p>
            <div class="fields-2">
                <label class="field" for="category-title">
                    <span>Kategori adı</span>
                    <input id="category-title" name="title" type="text" maxlength="48" value="<?= e($posted['title']) ?>" required>
                </label>
                <label class="field" for="category-slug">
                    <span>Adres</span>
                    <input id="category-slug" name="slug" type="text" maxlength="64" value="<?= e($posted['slug']) ?>" placeholder="Boşsa addan yazılır">
                </label>
            </div>
            <label class="check" for="category-active"><input id="category-active" name="active" type="checkbox"<?= !empty($posted['active']) ? ' checked' : '' ?>> <span>Blogda göster</span></label>
        </section>
        <section class="settings-card">
            <h2>Görsel</h2>
            <p class="settings-lead">Kategori sayfasının kapağı ve arama sonucundaki paylaşım görseli budur. Yüklenen dosya WebP’ye çevrilir.</p>
            <?php if ($categoryImage !== ''): ?>
                <img class="about-preview" src="<?= root_url($categoryImage) ?>" alt="">
            <?php endif; ?>
            <div class="field">
                <span>Yeni görsel</span>
                <label class="file-pick">
                    <input id="category-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                    <span class="file-btn">Dosya seç</span>
                    <span class="file-name">Dosya seçilmedi</span>
                </label>
            </div>
            <label class="field" for="category-alt">
                <span>Görsel açıklaması</span>
                <input id="category-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) $posted['image_alt']) ?>">
            </label>
            <?php if ($categoryManaged): ?>
                <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
            <?php endif; ?>
        </section>
        <section class="settings-card">
            <h2>Arama motoru</h2>
            <p class="settings-lead">Arama sonucu bu başlık ve açıklamayı görür. Kanonik adres her zaman /blog/kategori/adres olur. Yazısı olan ve indexi açık kategoriler site haritasına girer.</p>
            <div class="fields-2">
                <label class="field" for="seo-title">
                    <span>Arama başlığı</span>
                    <input id="seo-title" name="seo_title" type="text" maxlength="70" value="<?= e((string) $posted['seo_title']) ?>" placeholder="Boşsa kategori adı kullanılır">
                </label>
                <label class="field" for="seo-description">
                    <span>Arama açıklaması</span>
                    <textarea id="seo-description" name="seo_description" maxlength="160" rows="3" placeholder="Boşsa kısa bir kategori cümlesi kullanılır"><?= e((string) $posted['seo_description']) ?></textarea>
                </label>
            </div>
            <label class="check" for="category-index"><input id="category-index" name="index" type="checkbox"<?= !empty($posted['index']) ? ' checked' : '' ?>> <span>Arama motorları bu kategoriyi indexlesin</span></label>
            <div class="settings-save">
                <a class="login-submit slide-add" href="<?= root_url('admin/kategoriler.php') ?>">Vazgeç</a>
                <button class="login-submit" type="submit"><?= $posted['key'] === '' ? 'Kategori ekle' : 'Kategoriyi kaydet' ?></button>
            </div>
        </section>
    </form>
<?php else: ?>
    <div class="slide-head">
        <h2>Yazı kategorileri</h2>
        <a class="login-submit slide-add" href="<?= root_url('admin/kategoriler.php?yeni=1') ?>">Yeni kategori</a>
    </div>
    <?php if ($rows === []): ?>
        <p class="settings-lead">Henüz kategori yok.</p>
    <?php else: ?>
        <div class="slide-list">
            <?php foreach ($rows as $index => $row): ?>
                <article class="slide-row">
                    <div class="slide-thumb"><span><?= (int) $index + 1 ?></span></div>
                    <div>
                        <strong><?= e($row['title']) ?></strong>
                        <p><?= e($row['key']) ?> · <?= $row['posts'] === 0 ? 'Yazı yok' : (int) $row['posts'] . ' yazı' ?> · <?= $row['active'] ? 'Yayında' : 'Gizli' ?> · <?= !empty($row['index']) ? 'Index' : 'Noindex' ?></p>
                    </div>
                    <div class="slide-actions">
                        <form method="post" action="<?= root_url('admin/kategoriler.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="category_key" value="<?= e($row['key']) ?>">
                            <input type="hidden" name="direction" value="-1">
                            <button type="submit"<?= $index === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                        </form>
                        <form method="post" action="<?= root_url('admin/kategoriler.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="category_key" value="<?= e($row['key']) ?>">
                            <input type="hidden" name="direction" value="1">
                            <button type="submit"<?= $index === count($rows) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                        </form>
                        <form method="post" action="<?= root_url('admin/kategoriler.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="category_key" value="<?= e($row['key']) ?>">
                            <?php if (!$row['active']): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                            <button type="submit"><?= $row['active'] ? 'Gizle' : 'Yayınla' ?></button>
                        </form>
                        <a href="<?= root_url('admin/kategoriler.php?kategori=' . rawurlencode($row['key'])) ?>">Düzenle</a>
                        <form method="post" action="<?= root_url('admin/kategoriler.php') ?>" data-confirm="Bu kategori silinsin mi?">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_key" value="<?= e($row['key']) ?>">
                            <button type="submit">Sil</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="field-hint">Sıra, blog kenar çubuğundaki sıradır.</p>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
