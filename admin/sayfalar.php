<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/PageEditor.php';

Auth::boot();
Auth::requireLogin();

$errors = [];
$mode = 'list';
$record = null;
$posted = [
    'title' => '',
    'slug' => '',
    'status' => 'draft',
    'excerpt' => '',
    'body' => '',
    'image_alt' => '',
    'seo_title' => '',
    'seo_description' => '',
    'index' => true,
    'key' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
        $mode = $action === 'save' ? 'editor' : ($action === 'save-advanced' ? 'advanced' : 'list');
        if ($action === 'save' || $action === 'save-advanced') {
            $posted = array_merge($posted, [
                'title' => is_string($_POST['title'] ?? null) ? $_POST['title'] : '',
                'slug' => is_string($_POST['slug'] ?? null) ? $_POST['slug'] : '',
                'body' => is_string($_POST['body'] ?? null) ? $_POST['body'] : '',
                'key' => is_string($_POST['page_key'] ?? null) ? $_POST['page_key'] : '',
                'excerpt' => is_string($_POST['excerpt'] ?? null) ? $_POST['excerpt'] : '',
                'seo_title' => is_string($_POST['seo_title'] ?? null) ? $_POST['seo_title'] : '',
                'seo_description' => is_string($_POST['seo_description'] ?? null) ? $_POST['seo_description'] : '',
                'status' => (($_POST['status'] ?? '') === 'publish') ? 'publish' : 'draft',
                'index' => isset($_POST['index']),
            ]);
        }
    } elseif ($action === 'save-advanced') {
        $saved = PageEditor::saveAdvanced($_POST);
        $errors = $saved['errors'];
        $posted = array_merge($posted, $saved['values']);
        if ($errors === []) {
            Auth::flash('ok', 'Gelişmiş sayfa hazır. Bölümleri istediğin sıraya diz.');
            Auth::redirect('admin/gelismis.php?sayfa=' . rawurlencode($saved['key']));
        }
        $mode = 'advanced';
    } elseif ($action === 'save') {
        $saved = PageEditor::save($_POST, $_FILES);
        $errors = $saved['errors'];
        $posted = $saved['values'];
        if ($errors === []) {
            Auth::flash('ok', $posted['key'] === '' ? 'Sayfa eklendi.' : 'Sayfa kaydedildi.');
            Auth::redirect('admin/sayfalar.php?sayfa=' . rawurlencode($saved['key']));
        }
        $mode = 'editor';
        if ($saved['key'] !== '') {
            $record = PageEditor::find($saved['key']);
        }
    } elseif ($action === 'delete') {
        $key = is_string($_POST['page_key'] ?? null) ? $_POST['page_key'] : '';
        $errors = PageEditor::remove($key);
        if ($errors === []) {
            Auth::flash('ok', 'Sayfa silindi.');
            Auth::redirect('admin/sayfalar.php');
        }
    } elseif ($action === 'toggle') {
        $key = is_string($_POST['page_key'] ?? null) ? $_POST['page_key'] : '';
        PageEditor::setActive($key, isset($_POST['active']));
        Auth::redirect('admin/sayfalar.php');
    } elseif ($action === 'move') {
        $key = is_string($_POST['page_key'] ?? null) ? $_POST['page_key'] : '';
        PageEditor::move($key, (int) ($_POST['direction'] ?? 0));
        Auth::redirect('admin/sayfalar.php');
    }
}

if ($mode === 'list' && isset($_GET['yeni'])) {
    $yeni = (string) $_GET['yeni'];
    if ($yeni === 'klasik') {
        $mode = 'editor';
    } elseif ($yeni === 'gelismis') {
        $mode = 'advanced';
    } else {
        $mode = 'choose';
    }
} elseif ($mode === 'list' && is_string($_GET['sayfa'] ?? null) && $_GET['sayfa'] !== '') {
    $record = PageEditor::find($_GET['sayfa']);
    if ($record === null) {
        $errors[] = 'Sayfa bulunamadı.';
    } elseif (($record['kind'] ?? '') === 'advanced') {
        Auth::redirect('admin/gelismis.php?sayfa=' . rawurlencode((string) $record['key']));
    } else {
        $mode = 'editor';
        $posted = [
            'title' => $record['title'],
            'slug' => $record['key'],
            'status' => $record['active'] ? 'publish' : 'draft',
            'excerpt' => $record['excerpt'],
            'body' => PageEditor::editorHtml($record),
            'image_alt' => $record['image_alt'],
            'seo_title' => $record['seo_title'],
            'seo_description' => $record['seo_description'],
            'index' => $record['index'],
            'key' => $record['key'],
        ];
    }
}

$designed = PageEditor::designed();
$rows = PageEditor::all();
$pageTitle = match ($mode) {
    'choose' => 'Sayfa ekle',
    'editor' => $posted['key'] === '' ? 'Yeni klasik sayfa' : 'Klasik sayfayı düzenle',
    'advanced' => $posted['key'] === '' ? 'Yeni gelişmiş sayfa' : 'Gelişmiş sayfa',
    default => 'Sayfalar',
};
$crumbCurrent = 'Sayfalar';
$activeNav = 'sayfalar';
$uploadRoot = trim((string) setting('upload_dir', 'assets/uploads'), '/');
$featured = is_array($record) ? (string) ($record['image'] ?? '') : '';
$featuredManaged = $featured !== '' && str_starts_with($featured, $uploadRoot . '/pages/');
$configured = rtrim((string) setting('site_url', ''), '/');
if ($configured !== '') {
    $permalinkBase = $configured;
} else {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $permalinkBase = ($https ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost') . root_path();
}

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($mode === 'choose'): ?>
    <div class="slide-head">
        <h2>Sayfa yapısı</h2>
        <a class="login-submit slide-add" href="<?= root_url('admin/sayfalar.php') ?>">Vazgeç</a>
    </div>
    <p class="settings-lead">Yeni sayfanın düzenini seç.</p>
    <div class="page-kinds">
        <a class="page-kind" href="<?= root_url('admin/sayfalar.php?yeni=klasik') ?>">
            <strong>Klasik sayfa</strong>
            <small>Başlık, metin, kapak görseli ve arama kaydı. Tek bir yazı alanı.</small>
        </a>
        <a class="page-kind" href="<?= root_url('admin/sayfalar.php?yeni=gelismis') ?>">
            <strong>Gelişmiş sayfa</strong>
            <small>Karşılama, kartlar ve bölümleri istediğin sırayla dizer, her sayfaya kendi düzenini verirsin.</small>
        </a>
    </div>
<?php elseif ($mode === 'advanced'): ?>
    <form class="post-editor" method="post" action="<?= root_url('admin/sayfalar.php') ?>" data-post-editor data-permalink-base="<?= e($permalinkBase) ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save-advanced">
        <input type="hidden" name="page_key" value="<?= e((string) $posted['key']) ?>">
        <div class="post-main">
            <section class="settings-card">
                <label class="field post-title-field" for="post-title">
                    <span>Başlık</span>
                    <input class="post-title-input" id="post-title" name="title" type="text" maxlength="140" value="<?= e((string) $posted['title']) ?>" data-post-title required>
                </label>
                <p class="permalink">Kalıcı bağlantı: <strong data-permalink><?= e($posted['slug'] !== '' ? $permalinkBase . '/' . $posted['slug'] : $permalinkBase . '/') ?></strong></p>
                <label class="field" for="post-slug">
                    <span>Adres</span>
                    <input id="post-slug" name="slug" type="text" maxlength="64" value="<?= e((string) $posted['slug']) ?>" placeholder="Boşsa başlıktan yazılır" data-post-slug data-auto="<?= $posted['key'] === '' ? '1' : '0' ?>">
                </label>
                <p class="field-hint">Kayıttan sonra karşılama, kart ve bölümleri kendi sırana göre eklersin. Sayfa bandı otomatik açılır.</p>
                <label class="field" for="post-excerpt">
                    <span>Özet <em class="seo-count" data-count-for="post-excerpt">0/300</em></span>
                    <textarea id="post-excerpt" name="excerpt" maxlength="300" rows="3" data-limit="300" placeholder="Boşsa arama açıklaması kullanılır"><?= e((string) $posted['excerpt']) ?></textarea>
                </label>
            </section>
        </div>
        <aside class="post-side">
            <section class="settings-card">
                <h2>Yayın</h2>
                <label class="field" for="post-status">
                    <span>Durum</span>
                    <select id="post-status" name="status">
                        <option value="draft"<?= $posted['status'] !== 'publish' ? ' selected' : '' ?>>Taslak</option>
                        <option value="publish"<?= $posted['status'] === 'publish' ? ' selected' : '' ?>>Yayında</option>
                    </select>
                </label>
                <div class="settings-save post-publish">
                    <a class="login-submit slide-add" href="<?= root_url('admin/sayfalar.php') ?>">Vazgeç</a>
                    <button class="login-submit" type="submit"><?= $posted['status'] === 'publish' ? 'Yayınla' : 'Taslağı kaydet' ?></button>
                </div>
            </section>
            <section class="settings-card">
                <h2>Arama motoru</h2>
                <p class="settings-lead">Kanonik adres /adres olur. Adres değişirse eski adres yenisine yönlenir. Yayında, indexi açık ve en az bir görünür bölümü olan sayfalar site haritasına girer.</p>
                <label class="field" for="post-seo-title">
                    <span>Arama başlığı <em class="seo-count" data-count-for="post-seo-title">0/70</em></span>
                    <input id="post-seo-title" name="seo_title" type="text" maxlength="70" data-limit="70" value="<?= e((string) $posted['seo_title']) ?>" placeholder="Boşsa sayfa bandının başlığı kullanılır">
                </label>
                <label class="field" for="post-seo-description">
                    <span>Arama açıklaması <em class="seo-count" data-count-for="post-seo-description">0/160</em></span>
                    <textarea id="post-seo-description" name="seo_description" maxlength="160" rows="4" data-limit="160" placeholder="Boşsa özet kullanılır"><?= e((string) $posted['seo_description']) ?></textarea>
                </label>
                <label class="check" for="post-index"><input id="post-index" name="index" type="checkbox"<?= !empty($posted['index']) ? ' checked' : '' ?>> <span>Arama motorları bu sayfayı indexlesin</span></label>
            </section>
        </aside>
    </form>
<?php elseif ($mode === 'editor'): ?>
    <form class="post-editor" method="post" action="<?= root_url('admin/sayfalar.php') ?>" enctype="multipart/form-data" data-post-editor data-permalink-base="<?= e($permalinkBase) ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="page_key" value="<?= e((string) $posted['key']) ?>">
        <div class="post-main">
            <section class="settings-card">
                <label class="field post-title-field" for="post-title">
                    <span>Başlık</span>
                    <input class="post-title-input" id="post-title" name="title" type="text" maxlength="140" value="<?= e((string) $posted['title']) ?>" data-post-title required>
                </label>
                <p class="permalink">Kalıcı bağlantı: <strong data-permalink><?= e($posted['slug'] !== '' ? $permalinkBase . '/' . $posted['slug'] : $permalinkBase . '/') ?></strong></p>
                <label class="field" for="post-slug">
                    <span>Adres</span>
                    <input id="post-slug" name="slug" type="text" maxlength="64" value="<?= e((string) $posted['slug']) ?>" placeholder="Boşsa başlıktan yazılır" data-post-slug data-auto="<?= $posted['key'] === '' ? '1' : '0' ?>">
                </label>
                <div class="field">
                    <span id="post-body-label">İçerik</span>
                    <textarea class="post-body" id="post-body" name="body" rows="16" aria-labelledby="post-body-label" data-editor data-upload="<?= root_url('admin/sayfa-gorsel.php') ?>" data-csrf="<?= e(Auth::csrfToken()) ?>"><?= e((string) $posted['body']) ?></textarea>
                </div>
                <p class="field-hint">Başlık, liste, tablo, bağlantı ve görsel ekleyebilirsin. Kayıtta güvenli olmayan kod ayıklanır.</p>
                <label class="field" for="post-excerpt">
                    <span>Özet <em class="seo-count" data-count-for="post-excerpt">0/300</em></span>
                    <textarea id="post-excerpt" name="excerpt" maxlength="300" rows="3" data-limit="300" placeholder="Boşsa arama açıklaması, o da boşsa ilk paragraf kullanılır"><?= e((string) $posted['excerpt']) ?></textarea>
                </label>
            </section>
        </div>
        <aside class="post-side">
            <section class="settings-card">
                <h2>Yayın</h2>
                <label class="field" for="post-status">
                    <span>Durum</span>
                    <select id="post-status" name="status">
                        <option value="draft"<?= $posted['status'] !== 'publish' ? ' selected' : '' ?>>Taslak</option>
                        <option value="publish"<?= $posted['status'] === 'publish' ? ' selected' : '' ?>>Yayında</option>
                    </select>
                </label>
                <div class="settings-save post-publish">
                    <a class="login-submit slide-add" href="<?= root_url('admin/sayfalar.php') ?>">Vazgeç</a>
                    <button class="login-submit" type="submit"><?php
                        if ($posted['status'] !== 'publish') {
                            echo 'Taslağı kaydet';
                        } elseif ($posted['key'] !== '') {
                            echo 'Güncelle';
                        } else {
                            echo 'Yayınla';
                        }
                    ?></button>
                </div>
                <?php if ($posted['key'] !== '' && $posted['status'] === 'publish'): ?>
                    <p class="field-hint"><a href="<?= root_url('/' . $posted['key']) ?>" target="_blank" rel="noopener">Sayfayı görüntüle</a></p>
                <?php endif; ?>
            </section>
            <section class="settings-card">
                <h2>Kapak görseli</h2>
                <p class="settings-lead">Sayfa bandında ve paylaşım kaydında kullanılır. Boşsa bantta hazır görsel kalır.</p>
                <?php if ($featured !== ''): ?>
                    <img class="about-preview" src="<?= root_url($featured) ?>" alt="">
                <?php endif; ?>
                <div class="field">
                    <span>Görsel</span>
                    <label class="file-pick">
                        <input id="page-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
                <label class="field" for="page-alt">
                    <span>Görsel açıklaması</span>
                    <input id="page-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) $posted['image_alt']) ?>">
                </label>
                <?php if ($featuredManaged): ?>
                    <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                <?php endif; ?>
            </section>
            <section class="settings-card">
                <h2>Arama motoru</h2>
                <p class="settings-lead">Kanonik adres her zaman /adres olur. Adres değişirse eski adres yenisine yönlenir. Eski /sayfa/adres bağlantısı da yenisine yönlenir. Gövdesi olan, yayındaki ve indexi açık sayfalar site haritasına girer.</p>
                <label class="field" for="post-seo-title">
                    <span>Arama başlığı <em class="seo-count" data-count-for="post-seo-title">0/70</em></span>
                    <input id="post-seo-title" name="seo_title" type="text" maxlength="70" data-limit="70" value="<?= e((string) $posted['seo_title']) ?>" placeholder="Boşsa sayfa başlığı kullanılır">
                </label>
                <label class="field" for="post-seo-description">
                    <span>Arama açıklaması <em class="seo-count" data-count-for="post-seo-description">0/160</em></span>
                    <textarea id="post-seo-description" name="seo_description" maxlength="160" rows="4" data-limit="160" placeholder="Boşsa özet kullanılır"><?= e((string) $posted['seo_description']) ?></textarea>
                </label>
                <label class="check" for="post-index"><input id="post-index" name="index" type="checkbox"<?= !empty($posted['index']) ? ' checked' : '' ?>> <span>Arama motorları bu sayfayı indexlesin</span></label>
            </section>
        </aside>
    </form>
<?php else: ?>
    <div class="slide-head">
        <h2>Tüm sayfalar</h2>
        <a class="login-submit slide-add" href="<?= root_url('admin/sayfalar.php?yeni=1') ?>">Sayfa ekle</a>
    </div>
    <?php if ($designed === [] && $rows === []): ?>
        <p class="settings-lead">Henüz sayfa yok.</p>
    <?php else: ?>
        <div class="slide-list">
            <article class="slide-row">
                <div class="slide-thumb"><span>A</span></div>
                <div>
                    <strong>Anasayfa</strong>
                    <p>/ · Gelişmiş · Yayında</p>
                </div>
                <div class="slide-actions">
                    <a href="<?= root_url('admin/gelismis.php?sayfa=home') ?>">Düzenle</a>
                    <a href="<?= root_url('/') ?>" target="_blank" rel="noopener">Görüntüle</a>
                </div>
            </article>
            <?php foreach ($designed as $row): ?>
                <article class="slide-row">
                    <div class="slide-thumb"><span>T</span></div>
                    <div>
                        <strong><?= e($row['title']) ?></strong>
                        <p><?= e($row['url']) ?> · Gelişmiş · <?= $row['active'] ? 'Yayında' : 'Gizli' ?></p>
                    </div>
                    <div class="slide-actions">
                        <a href="<?= root_url('admin/gelismis.php?sayfa=' . rawurlencode($row['key'])) ?>">Düzenle</a>
                        <?php if ($row['active']): ?>
                            <a href="<?= root_url($row['url']) ?>" target="_blank" rel="noopener">Görüntüle</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php foreach ($rows as $index => $row): ?>
                <article class="slide-row">
                    <div class="slide-thumb">
                        <?php if ($row['image'] !== ''): ?>
                            <img src="<?= root_url($row['image']) ?>" alt="">
                        <?php else: ?>
                            <span><?= (int) $index + 1 ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php $isAdvanced = ($row['kind'] ?? '') === 'advanced'; ?>
                        <strong><?= e($row['title']) ?></strong>
                        <p>/<?= e($row['key']) ?> · <?= $isAdvanced ? 'Gelişmiş' : 'Klasik' ?> · <?= $row['active'] ? 'Yayında' : 'Taslak' ?> · <?= $row['index'] ? 'Index' : 'Noindex' ?></p>
                    </div>
                    <div class="slide-actions">
                        <form method="post" action="<?= root_url('admin/sayfalar.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="page_key" value="<?= e($row['key']) ?>">
                            <input type="hidden" name="direction" value="-1">
                            <button type="submit"<?= $index === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                        </form>
                        <form method="post" action="<?= root_url('admin/sayfalar.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="page_key" value="<?= e($row['key']) ?>">
                            <input type="hidden" name="direction" value="1">
                            <button type="submit"<?= $index === count($rows) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                        </form>
                        <form method="post" action="<?= root_url('admin/sayfalar.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="page_key" value="<?= e($row['key']) ?>">
                            <?php if (!$row['active']): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                            <button type="submit"><?= $row['active'] ? 'Taslak yap' : 'Yayınla' ?></button>
                        </form>
                        <a href="<?= root_url(($isAdvanced ? 'admin/gelismis.php?sayfa=' : 'admin/sayfalar.php?sayfa=') . rawurlencode($row['key'])) ?>">Düzenle</a>
                        <?php if ($row['active']): ?>
                            <a href="<?= root_url('/' . $row['key']) ?>" target="_blank" rel="noopener">Görüntüle</a>
                        <?php endif; ?>
                        <form method="post" action="<?= root_url('admin/sayfalar.php') ?>" data-confirm="Bu sayfa silinsin mi?">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="page_key" value="<?= e($row['key']) ?>">
                            <button type="submit">Sil</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="field-hint">Anasayfa, Hakkımızda ve Blog gelişmiş düzendir. Yeni gelişmiş sayfada karşılama, kart ve bölümleri kendi sırana göre eklersin. Klasik sayfa tek metin alanıdır.</p>
<?php endif; ?>
<?php if ($mode === 'editor'): ?>
    <link rel="stylesheet" href="<?= root_url('assets/vendor/jodit/jodit.min.css') ?>">
    <script src="<?= root_url('assets/vendor/jodit/jodit.min.js') ?>"></script>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
