<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/ProductCategoryEditor.php';

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
    'description' => '',
    'image_alt' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
        $mode = $action === 'save' ? 'editor' : 'list';
    } elseif ($action === 'save') {
        $saved = ProductCategoryEditor::save($_POST, $_FILES);
        $errors = $saved['errors'];
        $posted = $saved['values'];
        if ($errors === []) {
            Auth::flash('ok', $posted['key'] === '' ? 'Kategori eklendi.' : 'Kategori kaydedildi.');
            Auth::redirect('admin/urun-kategorileri.php');
        }
        $mode = 'editor';
        if ($posted['key'] !== '') {
            $category = ProductCategoryEditor::open($posted['key']);
        }
    } elseif ($action === 'delete') {
        $key = is_string($_POST['category_key'] ?? null) ? $_POST['category_key'] : '';
        $errors = ProductCategoryEditor::remove($key);
        if ($errors === []) {
            Auth::flash('ok', 'Kategori silindi.');
            Auth::redirect('admin/urun-kategorileri.php');
        }
    } elseif ($action === 'delete-many') {
        $keys = $_POST['category_key'] ?? [];
        $keys = is_array($keys) ? $keys : [];
        $removed = 0;
        $blocked = '';
        foreach ($keys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $result = ProductCategoryEditor::remove($key);
            if ($result === []) {
                $removed++;
            } elseif ($blocked === '' && isset($result[0])) {
                $blocked = (string) $result[0];
            }
        }
        if ($removed > 0 && $blocked === '') {
            Auth::flash('ok', $removed === 1 ? 'Kategori silindi.' : $removed . ' kategori silindi.');
            Auth::redirect('admin/urun-kategorileri.php');
        }
        if ($blocked !== '') {
            $errors[] = $blocked;
        } else {
            $errors[] = 'Silinecek kategori seçilmedi.';
        }
    } elseif ($action === 'toggle') {
        $key = is_string($_POST['category_key'] ?? null) ? $_POST['category_key'] : '';
        ProductCategoryEditor::setActive($key, isset($_POST['active']));
        Auth::redirect('admin/urun-kategorileri.php');
    } elseif ($action === 'move') {
        $key = is_string($_POST['category_key'] ?? null) ? $_POST['category_key'] : '';
        ProductCategoryEditor::move($key, (int) ($_POST['direction'] ?? 0));
        Auth::redirect('admin/urun-kategorileri.php');
    }
}

if ($mode === 'list' && isset($_GET['yeni'])) {
    $mode = 'editor';
} elseif ($mode === 'list' && is_string($_GET['kategori'] ?? null) && $_GET['kategori'] !== '') {
    $category = ProductCategoryEditor::open($_GET['kategori']);
    if ($category === null) {
        $errors[] = 'Kategori bulunamadı.';
    } else {
        $mode = 'editor';
        $posted = [
            'title' => $category['title'],
            'slug' => $category['key'],
            'active' => $category['active'],
            'key' => $category['key'],
            'description' => $category['description'],
            'image_alt' => $category['image_alt'],
        ];
    }
}

$rows = ProductCategoryEditor::all();
$pageTitle = 'Ürün kategorileri';
$crumbCurrent = 'Kategoriler';
$activeNav = 'urun-kategoriler';

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
    <form class="settings-form" method="post" action="<?= root_url('admin/urun-kategorileri.php') ?>" enctype="multipart/form-data" data-post-editor>
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="category_key" value="<?= e($posted['key']) ?>">
        <section class="settings-card">
            <h2><?= $posted['key'] === '' ? 'Yeni ürün kategorisi' : 'Kategoriyi düzenle' ?></h2>
            <p class="settings-lead">Ürünler bu kategorilere bağlanacak. Satışta sepet yok; ürün sayfasında satın al düğmesi duracak.</p>
            <div class="fields-2">
                <label class="field" for="category-title">
                    <span>Kategori adı</span>
                    <input id="category-title" name="title" type="text" maxlength="80" value="<?= e($posted['title']) ?>" data-post-title required>
                </label>
                <label class="field" for="category-slug">
                    <span>Adres</span>
                    <input id="category-slug" name="slug" type="text" maxlength="64" value="<?= e($posted['slug']) ?>" data-post-slug<?= $posted['key'] === '' ? ' data-auto="1"' : '' ?> placeholder="Boşsa addan yazılır">
                </label>
            </div>
            <label class="field" for="category-description">
                <span>Kısa açıklama</span>
                <textarea id="category-description" name="description" maxlength="300" rows="3"><?= e($posted['description']) ?></textarea>
            </label>
            <label class="check" for="category-active"><input id="category-active" name="active" type="checkbox"<?= !empty($posted['active']) ? ' checked' : '' ?>> <span>Mağazada göster</span></label>
        </section>
        <section class="settings-card">
            <h2>Görsel</h2>
            <p class="settings-lead">Kategori kapağı. Yüklenen dosya WebP’ye çevrilir.</p>
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
                <input id="category-alt" name="image_alt" type="text" maxlength="140" value="<?= e($posted['image_alt']) ?>">
            </label>
            <?php if ($categoryManaged): ?>
                <label class="check" for="remove-image"><input id="remove-image" name="remove_image" type="checkbox"> <span>Yüklenen görseli kaldır</span></label>
            <?php endif; ?>
            <div class="settings-save">
                <a class="login-submit slide-add" href="<?= root_url('admin/urun-kategorileri.php') ?>">Vazgeç</a>
                <button class="login-submit" type="submit"><?= $posted['key'] === '' ? 'Kategori ekle' : 'Kategoriyi kaydet' ?></button>
            </div>
        </section>
    </form>
<?php else:
    $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    if (mb_strlen($query, 'UTF-8') > 80) {
        $query = mb_substr($query, 0, 80, 'UTF-8');
    }
    $statusFilter = is_string($_GET['durum'] ?? null) ? $_GET['durum'] : '';
    if (!in_array($statusFilter, ['yayinda', 'gizli'], true)) {
        $statusFilter = '';
    }
    $pageSize = is_string($_GET['adet'] ?? null) ? (int) $_GET['adet'] : 8;
    if (!in_array($pageSize, [8, 16, 32], true)) {
        $pageSize = 8;
    }
    $view = ($_GET['gorunum'] ?? '') === 'kart' ? 'kart' : 'liste';
    $page = max(1, (int) ($_GET['sayfa'] ?? 1));
    $needle = mb_strtolower($query, 'UTF-8');
    $liveCount = 0;
    $hiddenCount = 0;
    $productCount = 0;
    $emptyCount = 0;
    $matched = [];
    foreach ($rows as $row) {
        if (!empty($row['active'])) {
            $liveCount++;
        } else {
            $hiddenCount++;
        }
        $productCount += (int) $row['products'];
        if ((int) $row['products'] === 0) {
            $emptyCount++;
        }
        if ($statusFilter === 'yayinda' && empty($row['active'])) {
            continue;
        }
        if ($statusFilter === 'gizli' && !empty($row['active'])) {
            continue;
        }
        if ($needle !== '') {
            $haystack = mb_strtolower((string) $row['title'] . ' ' . (string) $row['key'], 'UTF-8');
            if (mb_strpos($haystack, $needle) === false) {
                continue;
            }
        }
        $matched[] = $row;
    }
    $pageCount = max(1, (int) ceil(count($matched) / $pageSize));
    if ($page > $pageCount) {
        $page = $pageCount;
    }
    $pageRows = array_slice($matched, ($page - 1) * $pageSize, $pageSize);
    $plain = $query === '' && $statusFilter === '' && $view === 'liste' && $pageCount === 1;
    $positions = [];
    foreach ($rows as $index => $row) {
        $positions[(string) $row['key']] = $index;
    }
    $catalogUrl = static function (array $extra = []) use ($query, $statusFilter, $pageSize, $view, $page): string {
        $params = [
            'q' => $query,
            'durum' => $statusFilter,
            'adet' => (string) $pageSize,
            'gorunum' => $view === 'kart' ? 'kart' : '',
            'sayfa' => (string) $page,
        ];
        foreach ($extra as $key => $value) {
            $params[(string) $key] = (string) $value;
        }
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === '' || ($key === 'adet' && $value === '8') || ($key === 'sayfa' && $value === '1')) {
                continue;
            }
            $parts[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return root_url('admin/urun-kategorileri.php' . ($parts !== [] ? '?' . implode('&', $parts) : ''));
    };
    $publishedLabel = static function (string $value): string {
        $stamp = strtotime($value);

        return $stamp === false ? '—' : date('d.m.Y H:i', $stamp);
    };
    $rangeStart = $matched === [] ? 0 : (($page - 1) * $pageSize) + 1;
    $rangeEnd = min(count($matched), $page * $pageSize);
    ?>
    <section class="catalog-kpis is-4" aria-label="Kategori özeti">
        <article class="catalog-kpi">
            <span class="metric-ico is-purple"><?= admin_icon('grid') ?></span>
            <div>
                <p>Kategori</p>
                <strong><?= e((string) count($rows)) ?></strong>
                <small>Yayında <?= e((string) $liveCount) ?></small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-green"><?= admin_icon('bag') ?></span>
            <div>
                <p>Ürün</p>
                <strong><?= e((string) $productCount) ?></strong>
                <small>Kategorilere bağlı</small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-gold"><?= admin_icon('draft') ?></span>
            <div>
                <p>Gizli</p>
                <strong><?= e((string) $hiddenCount) ?></strong>
                <small>Mağazada kapalı</small>
            </div>
        </article>
        <article class="catalog-kpi">
            <span class="metric-ico is-blue"><?= admin_icon('list') ?></span>
            <div>
                <p>Boş</p>
                <strong><?= e((string) $emptyCount) ?></strong>
                <small>Ürünü olmayan</small>
            </div>
        </article>
    </section>
    <div class="catalog-toolbar">
        <form class="catalog-filters" method="get" action="<?= root_url('admin/urun-kategorileri.php') ?>">
            <?php if ($view === 'kart'): ?><input type="hidden" name="gorunum" value="kart"><?php endif; ?>
            <label class="catalog-search">
                <span class="search-ico"><?= admin_icon('search') ?></span>
                <input name="q" type="search" value="<?= e($query) ?>" placeholder="Kategori adı veya adres" maxlength="80">
            </label>
            <select name="durum" data-auto aria-label="Durum">
                <option value="">Durum</option>
                <option value="yayinda"<?= $statusFilter === 'yayinda' ? ' selected' : '' ?>>Yayında</option>
                <option value="gizli"<?= $statusFilter === 'gizli' ? ' selected' : '' ?>>Gizli</option>
            </select>
            <select name="adet" data-auto aria-label="Sayfa boyutu">
                <?php foreach ([8, 16, 32] as $size): ?>
                    <option value="<?= (string) $size ?>"<?= $pageSize === $size ? ' selected' : '' ?>><?= (string) $size ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="catalog-views" role="group" aria-label="Görünüm">
            <a class="<?= $view === 'liste' ? 'is-on' : '' ?>" href="<?= $catalogUrl(['gorunum' => '', 'sayfa' => '1']) ?>" aria-label="Liste"><?= admin_icon('list') ?></a>
            <a class="<?= $view === 'kart' ? 'is-on' : '' ?>" href="<?= $catalogUrl(['gorunum' => 'kart', 'sayfa' => '1']) ?>" aria-label="Kartlar"><?= admin_icon('grid') ?></a>
        </div>
        <a class="catalog-shop" href="<?= root_url('/urunler') ?>" target="_blank" rel="noopener">Mağazayı aç</a>
        <a class="catalog-add" href="<?= root_url('admin/urun-kategorileri.php?yeni=1') ?>"><?= admin_icon('plus') ?> Kategori ekle</a>
    </div>
    <div class="catalog-sheet" data-catalog>
        <div class="catalog-bulk">
            <p><span data-selected-count>0</span> kategori seçildi</p>
            <button form="catalog-bulk" type="submit">Seçilenleri sil</button>
        </div>
        <form id="catalog-bulk" method="post" action="<?= root_url('admin/urun-kategorileri.php') ?>" data-confirm="Seçilen kategoriler silinsin mi?">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="delete-many">
        </form>
        <?php if ($pageRows === []): ?>
            <p class="catalog-empty"><?= $rows === [] ? 'Henüz kategori yok.' : 'Bu filtreye uyan kategori yok.' ?></p>
        <?php elseif ($view === 'kart'): ?>
            <div class="catalog-cards">
                <?php foreach ($pageRows as $row): ?>
                    <article class="catalog-card">
                        <label class="catalog-pick">
                            <input form="catalog-bulk" data-check type="checkbox" name="category_key[]" value="<?= e((string) $row['key']) ?>">
                        </label>
                        <?php if ((string) $row['image'] !== ''): ?>
                            <img src="<?= root_url((string) $row['image']) ?>" alt="">
                        <?php else: ?>
                            <span class="catalog-photo"></span>
                        <?php endif; ?>
                        <div class="catalog-card-body">
                            <strong><?= e((string) $row['title']) ?></strong>
                            <small><?= e((string) (int) $row['products']) ?> ürün</small>
                            <span class="catalog-status <?= !empty($row['active']) ? 'is-yayinda' : 'is-taslak' ?>"><?= !empty($row['active']) ? 'Yayında' : 'Gizli' ?></span>
                            <div class="catalog-actions">
                                <?php if (!empty($row['active'])): ?>
                                    <a class="catalog-icon" href="<?= root_url('/urunler/kategori/' . $row['key']) ?>" target="_blank" rel="noopener" aria-label="Sitede gör"><?= admin_icon('eye') ?></a>
                                <?php endif; ?>
                                <a class="catalog-icon" href="<?= root_url('admin/urun-kategorileri.php?kategori=' . rawurlencode((string) $row['key'])) ?>" aria-label="Düzenle"><?= admin_icon('pencil') ?></a>
                                <form method="post" action="<?= root_url('admin/urun-kategorileri.php') ?>" data-confirm="Bu kategori silinsin mi?">
                                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_key" value="<?= e((string) $row['key']) ?>">
                                    <button class="catalog-icon is-danger" type="submit" aria-label="Sil"><?= admin_icon('trash') ?></button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="catalog-scroll">
                <table class="catalog-table">
                    <thead>
                        <tr>
                            <th><input data-check-all type="checkbox" aria-label="Sayfadaki kategorilerin tümünü seç"></th>
                            <th>Kategori</th>
                            <th>Adres</th>
                            <th>Ürün</th>
                            <th>Durum</th>
                            <th>Güncelleme</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pageRows as $row): ?>
                            <?php $position = $positions[(string) $row['key']] ?? 0; ?>
                            <tr>
                                <td><input form="catalog-bulk" data-check type="checkbox" name="category_key[]" value="<?= e((string) $row['key']) ?>" aria-label="<?= e((string) $row['title']) ?> seç"></td>
                                <td>
                                    <div class="catalog-product">
                                        <?php if ((string) $row['image'] !== ''): ?>
                                            <img src="<?= root_url((string) $row['image']) ?>" alt="">
                                        <?php else: ?>
                                            <span class="catalog-photo"></span>
                                        <?php endif; ?>
                                        <span>
                                            <strong><?= e((string) $row['title']) ?></strong>
                                            <?php
                                            $blurb = (string) $row['description'];
                                            if (mb_strlen($blurb, 'UTF-8') > 72) {
                                                $blurb = mb_substr($blurb, 0, 72, 'UTF-8') . '…';
                                            }
                                            ?>
                                            <?php if ($blurb !== ''): ?><small><?= e($blurb) ?></small><?php endif; ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= e((string) $row['key']) ?></td>
                                <td><?= e((string) (int) $row['products']) ?></td>
                                <td><span class="catalog-status <?= !empty($row['active']) ? 'is-yayinda' : 'is-taslak' ?>"><?= !empty($row['active']) ? 'Yayında' : 'Gizli' ?></span></td>
                                <td><?= e($publishedLabel((string) $row['updated_at'])) ?></td>
                                <td>
                                    <div class="catalog-actions">
                                        <?php if ($plain): ?>
                                            <div class="catalog-move">
                                                <form method="post" action="<?= root_url('admin/urun-kategorileri.php') ?>">
                                                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="move">
                                                    <input type="hidden" name="category_key" value="<?= e((string) $row['key']) ?>">
                                                    <input type="hidden" name="direction" value="-1">
                                                    <button class="catalog-icon" type="submit"<?= $position === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                                                </form>
                                                <form method="post" action="<?= root_url('admin/urun-kategorileri.php') ?>">
                                                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="move">
                                                    <input type="hidden" name="category_key" value="<?= e((string) $row['key']) ?>">
                                                    <input type="hidden" name="direction" value="1">
                                                    <button class="catalog-icon" type="submit"<?= $position === count($rows) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['active'])): ?>
                                            <a class="catalog-icon" href="<?= root_url('/urunler/kategori/' . $row['key']) ?>" target="_blank" rel="noopener" aria-label="Sitede gör" title="Sitede gör"><?= admin_icon('eye') ?></a>
                                        <?php endif; ?>
                                        <a class="catalog-icon" href="<?= root_url('admin/urun-kategorileri.php?kategori=' . rawurlencode((string) $row['key'])) ?>" aria-label="Düzenle" title="Düzenle"><?= admin_icon('pencil') ?></a>
                                        <form method="post" action="<?= root_url('admin/urun-kategorileri.php') ?>">
                                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="category_key" value="<?= e((string) $row['key']) ?>">
                                            <?php if (empty($row['active'])): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                                            <button class="catalog-icon" type="submit" aria-label="<?= !empty($row['active']) ? 'Gizle' : 'Yayınla' ?>" title="<?= !empty($row['active']) ? 'Gizle' : 'Yayınla' ?>"><?= admin_icon(!empty($row['active']) ? 'draft' : 'plus') ?></button>
                                        </form>
                                        <form method="post" action="<?= root_url('admin/urun-kategorileri.php') ?>" data-confirm="Bu kategori silinsin mi?">
                                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="category_key" value="<?= e((string) $row['key']) ?>">
                                            <button class="catalog-icon is-danger" type="submit" aria-label="Sil" title="Sil"><?= admin_icon('trash') ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="catalog-pager">
            <p><?= e((string) $rangeStart) ?>–<?= e((string) $rangeEnd) ?> / <?= e((string) count($matched)) ?> kategori</p>
            <?php if ($pageCount > 1): ?>
                <nav class="catalog-pages" aria-label="Sayfalar">
                    <?php if ($page > 1): ?><a href="<?= $catalogUrl(['sayfa' => (string) ($page - 1)]) ?>">Önceki</a><?php endif; ?>
                    <?php for ($number = 1; $number <= $pageCount; $number++): ?>
                        <?php if ($number === $page): ?>
                            <span class="is-on"><?= e((string) $number) ?></span>
                        <?php else: ?>
                            <a href="<?= $catalogUrl(['sayfa' => (string) $number]) ?>"><?= e((string) $number) ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $pageCount): ?><a href="<?= $catalogUrl(['sayfa' => (string) ($page + 1)]) ?>">Sonraki</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    <p class="field-hint">Yukarı ve aşağı, filtre yokken mağazadaki kategori sırasını değiştirir. İçinde ürün olan kategori silinmez.</p>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
