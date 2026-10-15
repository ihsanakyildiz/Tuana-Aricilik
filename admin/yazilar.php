<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/PostEditor.php';

Auth::boot();
Auth::requireLogin();

$errors = [];
$mode = 'list';
$record = null;
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul'));
$posted = [
    'title' => '',
    'slug' => '',
    'status' => 'draft',
    'published' => $now->format('Y-m-d\TH:i'),
    'author' => (string) setting('site_name', 'Tuana Arıcılık'),
    'excerpt' => '',
    'body' => '',
    'category_key' => '',
    'tags' => '',
    'image_alt' => '',
    'figure_alt' => '',
    'figure_caption' => '',
    'seo_title' => '',
    'seo_description' => '',
    'index' => true,
    'key' => '',
    'embed_keys' => [],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
        $mode = $action === 'save' ? 'editor' : 'list';
        if ($action === 'save') {
            $posted = array_merge($posted, [
                'title' => is_string($_POST['title'] ?? null) ? $_POST['title'] : '',
                'slug' => is_string($_POST['slug'] ?? null) ? $_POST['slug'] : '',
                'body' => is_string($_POST['body'] ?? null) ? $_POST['body'] : '',
                'key' => is_string($_POST['post_key'] ?? null) ? $_POST['post_key'] : '',
            ]);
        }
    } elseif ($action === 'save') {
        $saved = PostEditor::save($_POST, $_FILES);
        $errors = $saved['errors'];
        $posted = $saved['values'];
        if ($errors === []) {
            Auth::flash('ok', $posted['key'] === '' ? 'Yazı eklendi.' : 'Yazı kaydedildi.');
            Auth::redirect('admin/yazilar.php?yazi=' . rawurlencode($saved['key']));
        }
        $mode = 'editor';
        if ($saved['key'] !== '') {
            $record = PostEditor::find($saved['key']);
        }
    } elseif ($action === 'delete') {
        $key = is_string($_POST['post_key'] ?? null) ? $_POST['post_key'] : '';
        $errors = PostEditor::remove($key);
        if ($errors === []) {
            Auth::flash('ok', 'Yazı silindi.');
            Auth::redirect('admin/yazilar.php');
        }
    } elseif ($action === 'toggle') {
        $key = is_string($_POST['post_key'] ?? null) ? $_POST['post_key'] : '';
        PostEditor::setActive($key, isset($_POST['active']));
        Auth::redirect('admin/yazilar.php');
    } elseif ($action === 'move') {
        $key = is_string($_POST['post_key'] ?? null) ? $_POST['post_key'] : '';
        PostEditor::move($key, (int) ($_POST['direction'] ?? 0));
        Auth::redirect('admin/yazilar.php');
    }
}

if ($mode === 'list' && isset($_GET['yeni'])) {
    $mode = 'editor';
} elseif ($mode === 'list' && is_string($_GET['yazi'] ?? null) && $_GET['yazi'] !== '') {
    $record = PostEditor::find($_GET['yazi']);
    if ($record === null) {
        $errors[] = 'Yazı bulunamadı.';
    } else {
        $mode = 'editor';
        $posted = [
            'title' => $record['title'],
            'slug' => $record['key'],
            'status' => $record['active'] ? 'publish' : 'draft',
            'published' => $record['published_input'] !== '' ? $record['published_input'] : $posted['published'],
            'author' => $record['author'] !== '' ? $record['author'] : $posted['author'],
            'excerpt' => $record['excerpt'],
            'body' => PostEditor::editorHtml($record),
            'category_key' => $record['category_key'],
            'tags' => implode(', ', $record['tags']),
            'image_alt' => $record['image_alt'],
            'figure_alt' => $record['figure_alt'],
            'figure_caption' => $record['figure_caption'],
            'seo_title' => $record['seo_title'],
            'seo_description' => $record['seo_description'],
            'index' => $record['index'],
            'key' => $record['key'],
            'embed_keys' => $record['embed_keys'],
        ];
    }
}

$rows = PostEditor::all();
$categories = CategoryEditor::all();
$cards = PostEditor::cards();
$pageTitle = $mode === 'editor' ? ($posted['key'] === '' ? 'Yeni yazı' : 'Yazıyı düzenle') : 'Yazılar';
$crumbCurrent = 'Yazılar';
$activeNav = 'yazi-yazilar';
$uploadRoot = trim((string) setting('upload_dir', 'assets/uploads'), '/');
$featured = is_array($record) ? (string) ($record['image'] ?? '') : '';
$figureImage = is_array($record) ? (string) ($record['figure_image'] ?? '') : '';
$featuredManaged = $featured !== '' && str_starts_with($featured, $uploadRoot . '/');
$figureManaged = $figureImage !== '' && str_starts_with($figureImage, $uploadRoot . '/');
$permalinkBase = rtrim(absolute_url('/yazi'), '/');
$selectedCards = is_array($posted['embed_keys'] ?? null) ? $posted['embed_keys'] : [];

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
    <form class="post-editor" method="post" action="<?= root_url('admin/yazilar.php') ?>" enctype="multipart/form-data" data-post-editor data-permalink-base="<?= e($permalinkBase) ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="post_key" value="<?= e((string) $posted['key']) ?>">
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
                    <textarea class="post-body" id="post-body" name="body" rows="16" aria-labelledby="post-body-label" data-editor data-upload="<?= e(root_url('admin/yazi-gorsel.php')) ?>" data-csrf="<?= e(Auth::csrfToken()) ?>"><?= e((string) $posted['body']) ?></textarea>
                </div>
                <p class="field-hint">Başlık, liste, tablo, bağlantı ve görsel ekleyebilirsin. Kayıtta güvenli olmayan kod ayıklanır.</p>
                <label class="field" for="post-excerpt">
                    <span>Özet <em class="seo-count" data-count-for="post-excerpt">0/300</em></span>
                    <textarea id="post-excerpt" name="excerpt" maxlength="300" rows="3" data-limit="300" placeholder="Boşsa arama açıklaması, o da boşsa ilk paragraf kullanılır"><?= e((string) $posted['excerpt']) ?></textarea>
                </label>
            </section>
            <section class="settings-card">
                <h2>Yazı içi görsel</h2>
                <p class="settings-lead">Eski yazılardaki sabit görsel. Yeni görselleri editörün içine ekleyebilirsin.</p>
                <?php if ($figureImage !== ''): ?>
                    <img class="about-preview" src="<?= root_url($figureImage) ?>" alt="">
                <?php endif; ?>
                <div class="field">
                    <span>Görsel</span>
                    <label class="file-pick">
                        <input id="figure-image" name="figure" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
                <label class="field" for="figure-alt">
                    <span>Görsel açıklaması</span>
                    <input id="figure-alt" name="figure_alt" type="text" maxlength="140" value="<?= e((string) $posted['figure_alt']) ?>">
                </label>
                <label class="field" for="figure-caption">
                    <span>Alt yazı</span>
                    <input id="figure-caption" name="figure_caption" type="text" maxlength="180" value="<?= e((string) $posted['figure_caption']) ?>">
                </label>
                <?php if ($figureManaged): ?>
                    <label class="check" for="remove-figure"><input id="remove-figure" name="remove_figure" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                <?php endif; ?>
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
                <label class="field" for="post-published">
                    <span>Yayın tarihi</span>
                    <input id="post-published" name="published" type="datetime-local" value="<?= e((string) $posted['published']) ?>" required>
                </label>
                <label class="field" for="post-author">
                    <span>Yazar</span>
                    <input id="post-author" name="author" type="text" maxlength="60" value="<?= e((string) $posted['author']) ?>">
                </label>
                <div class="settings-save post-publish">
                    <a class="login-submit slide-add" href="<?= root_url('admin/yazilar.php') ?>">Vazgeç</a>
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
                    <p class="field-hint"><a href="<?= url('/yazi/' . $posted['key']) ?>" target="_blank" rel="noopener">Yazıyı görüntüle</a></p>
                <?php endif; ?>
            </section>
            <section class="settings-card">
                <h2>Kategori</h2>
                <?php if ($categories === []): ?>
                    <p class="field-hint">Henüz kategori yok. Yazı kategorisiz de kaydedilebilir.</p>
                <?php else: ?>
                    <div class="choice-list">
                        <label class="check"><input type="radio" name="category_key" value=""<?= $posted['category_key'] === '' ? ' checked' : '' ?>> <span>Kategorisiz</span></label>
                        <?php foreach ($categories as $category): ?>
                            <label class="check"><input type="radio" name="category_key" value="<?= e($category['key']) ?>"<?= $posted['category_key'] === $category['key'] ? ' checked' : '' ?>> <span><?= e($category['title']) ?><?= $category['active'] ? '' : ' (gizli)' ?></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <section class="settings-card">
                <h2>Etiketler</h2>
                <label class="field" for="post-tags">
                    <span>Virgülle ayır</span>
                    <input id="post-tags" name="tags" type="text" maxlength="220" value="<?= e((string) $posted['tags']) ?>" placeholder="Bal, Hasat">
                </label>
            </section>
            <section class="settings-card">
                <h2>Öne çıkan görsel</h2>
                <p class="settings-lead">Listede, yazının başında ve paylaşım kaydında kullanılır.</p>
                <?php if ($featured !== ''): ?>
                    <img class="about-preview" src="<?= root_url($featured) ?>" alt="">
                <?php endif; ?>
                <div class="field">
                    <span>Görsel</span>
                    <label class="file-pick">
                        <input id="post-image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                        <span class="file-btn">Dosya seç</span>
                        <span class="file-name">Dosya seçilmedi</span>
                    </label>
                </div>
                <label class="field" for="post-alt">
                    <span>Görsel açıklaması</span>
                    <input id="post-alt" name="image_alt" type="text" maxlength="140" value="<?= e((string) $posted['image_alt']) ?>">
                </label>
                <?php if ($featuredManaged): ?>
                    <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
                <?php endif; ?>
            </section>
            <?php if ($cards !== []): ?>
                <section class="settings-card">
                    <h2>Yazıdaki kartlar</h2>
                    <p class="settings-lead">Yazının altında gösterilir. En fazla 6 kart.</p>
                    <div class="choice-list">
                        <?php foreach ($cards as $card): ?>
                            <?php if (!$card['active'] && !in_array($card['key'], $selectedCards, true)) { continue; } ?>
                            <label class="check"><input type="checkbox" name="embed_keys[]" value="<?= e($card['key']) ?>"<?= in_array($card['key'], $selectedCards, true) ? ' checked' : '' ?>> <span><?= e($card['title']) ?><?= $card['active'] ? '' : ' (gizli)' ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <section class="settings-card">
                <h2>Arama motoru</h2>
                <p class="settings-lead">Kanonik adres her zaman /yazi/adres olur. Adres değişirse eski adres yenisine yönlenir. Gövdesi olan, yayındaki ve indexi açık yazılar site haritasına girer.</p>
                <label class="field" for="post-seo-title">
                    <span>Arama başlığı <em class="seo-count" data-count-for="post-seo-title">0/70</em></span>
                    <input id="post-seo-title" name="seo_title" type="text" maxlength="70" data-limit="70" value="<?= e((string) $posted['seo_title']) ?>" placeholder="Boşsa yazı başlığı kullanılır">
                </label>
                <label class="field" for="post-seo-description">
                    <span>Arama açıklaması <em class="seo-count" data-count-for="post-seo-description">0/160</em></span>
                    <textarea id="post-seo-description" name="seo_description" maxlength="160" rows="4" data-limit="160" placeholder="Boşsa özet kullanılır"><?= e((string) $posted['seo_description']) ?></textarea>
                </label>
                <label class="check" for="post-index"><input id="post-index" name="index" type="checkbox"<?= !empty($posted['index']) ? ' checked' : '' ?>> <span>Arama motorları bu yazıyı indexlesin</span></label>
            </section>
        </aside>
    </form>
<?php else: ?>
    <div class="slide-head">
        <h2>Tüm yazılar</h2>
        <a class="login-submit slide-add" href="<?= root_url('admin/yazilar.php?yeni=1') ?>">Yeni yazı</a>
    </div>
    <?php if ($rows === []): ?>
        <p class="settings-lead">Henüz yazı yok.</p>
    <?php else: ?>
        <div class="slide-list">
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
                        <strong><?= e($row['title']) ?></strong>
                        <p><?= e($row['key']) ?> · <?= e($row['category'] !== '' ? $row['category'] : 'Kategorisiz') ?> · <?= e($row['date']) ?> · <?= $row['active'] ? 'Yayında' : 'Taslak' ?> · <?= $row['index'] ? 'Index' : 'Noindex' ?></p>
                    </div>
                    <div class="slide-actions">
                        <form method="post" action="<?= root_url('admin/yazilar.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="post_key" value="<?= e($row['key']) ?>">
                            <input type="hidden" name="direction" value="-1">
                            <button type="submit"<?= $index === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                        </form>
                        <form method="post" action="<?= root_url('admin/yazilar.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="post_key" value="<?= e($row['key']) ?>">
                            <input type="hidden" name="direction" value="1">
                            <button type="submit"<?= $index === count($rows) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                        </form>
                        <form method="post" action="<?= root_url('admin/yazilar.php') ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="post_key" value="<?= e($row['key']) ?>">
                            <?php if (!$row['active']): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                            <button type="submit"><?= $row['active'] ? 'Taslak yap' : 'Yayınla' ?></button>
                        </form>
                        <a href="<?= root_url('admin/yazilar.php?yazi=' . rawurlencode($row['key'])) ?>">Düzenle</a>
                        <?php if ($row['active']): ?>
                            <a href="<?= url('/yazi/' . $row['key']) ?>" target="_blank" rel="noopener">Görüntüle</a>
                        <?php endif; ?>
                        <form method="post" action="<?= root_url('admin/yazilar.php') ?>" data-confirm="Bu yazı silinsin mi?">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="post_key" value="<?= e($row['key']) ?>">
                            <button type="submit">Sil</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="field-hint">Sıra, anasayfadaki ve blogdaki liste sırasıdır. Taslak yazılar sitede görünmez.</p>
<?php endif; ?>
<?php if ($mode === 'editor'): ?>
    <link rel="stylesheet" href="<?= root_url('assets/vendor/jodit/jodit.min.css') ?>">
    <script src="<?= root_url('assets/vendor/jodit/jodit.min.js') ?>"></script>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
