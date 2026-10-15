<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/LocationCatalog.php';

Auth::boot();
Auth::requireLogin();

$errors = [];
$parentId = max(0, (int) ($_GET['ust'] ?? $_POST['ust'] ?? 0));
$query = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
if (function_exists('mb_substr')) {
    $query = mb_substr($query, 0, 80, 'UTF-8');
} else {
    $query = substr($query, 0, 80);
}
$page = max(1, (int) ($_GET['sayfa'] ?? $_POST['sayfa'] ?? 1));

$listPath = static function (int $parent, string $search, int $listPage): string {
    $params = [];
    if ($parent > 0) {
        $params['ust'] = (string) $parent;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($listPage > 1) {
        $params['sayfa'] = (string) $listPage;
    }
    $built = http_build_query($params);

    return 'admin/konumlar.php' . ($built === '' ? '' : '?' . $built);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } else {
        switch ($action) {
            case 'create':
                $errors = LocationCatalog::create(
                    (int) ($_POST['parent_id'] ?? 0),
                    (string) ($_POST['kind'] ?? ''),
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['postal_code'] ?? '')
                );
                if ($errors === []) {
                    Auth::flash('ok', 'Kayıt eklendi.');
                    Auth::redirect($listPath((int) ($_POST['parent_id'] ?? 0), $query, 1));
                }
                break;
            case 'update':
                $recordId = (int) ($_POST['id'] ?? 0);
                $current = LocationCatalog::find($recordId);
                $nextParent = $current !== null && $current['kind'] === 'il' ? 0 : (int) ($_POST['parent_id'] ?? 0);
                $nextKind = $current !== null && !in_array($current['kind'], ['mahalle', 'koy'], true)
                    ? $current['kind']
                    : (string) ($_POST['kind'] ?? '');
                $errors = LocationCatalog::update(
                    $recordId,
                    $nextParent,
                    $nextKind,
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['postal_code'] ?? '')
                );
                if ($errors === []) {
                    Auth::flash('ok', 'Kayıt güncellendi.');
                    Auth::redirect($listPath($nextParent, $query, 1));
                }
                break;
            case 'toggle':
                LocationCatalog::setActive((int) ($_POST['id'] ?? 0), isset($_POST['active']));
                Auth::redirect($listPath($parentId, $query, $page));
                break;
            case 'delete':
                $errors = LocationCatalog::remove((int) ($_POST['id'] ?? 0));
                if ($errors === []) {
                    Auth::flash('ok', 'Kayıt silindi.');
                    Auth::redirect($listPath($parentId, $query, $page));
                }
                break;
            case 'sync':
                $result = LocationCatalog::syncOfficial(isset($_POST['retire']));
                Auth::flash($result['ok'] ? 'ok' : 'err', $result['message']);
                Auth::redirect($listPath(0, '', 1));
                break;
            default:
                $errors[] = 'İşlem tanınmadı.';
                break;
        }
    }
}

$parent = $parentId > 0 ? LocationCatalog::find($parentId) : null;
if ($parentId > 0 && $parent === null) {
    $errors[] = 'Üst kayıt bulunamadı.';
    $parentId = 0;
}
$ancestors = $parentId > 0 ? LocationCatalog::ancestors($parentId) : [];
$editing = null;
$editId = (int) ($_GET['kayit'] ?? 0);
if ($editId > 0 && $errors === []) {
    $editing = LocationCatalog::find($editId);
    if ($editing === null) {
        $errors[] = 'Kayıt bulunamadı.';
    }
}
$list = LocationCatalog::page($parentId, $query, $page);
$totals = LocationCatalog::totals();
$childKinds = LocationCatalog::childKinds($parent['kind'] ?? '');
$syncedAt = (int) setting('locations_synced_at', 0);
$source = trim((string) setting('locations_source', ''));

$pageTitle = 'Adres listesi';
$crumbCurrent = 'Adres listesi';
$activeNav = 'konumlar';

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p class="slide-back">
    <a href="<?= root_url('admin/konumlar.php') ?>">İller</a>
    <?php foreach ($ancestors as $crumb): ?>
        <?php if ($crumb['id'] === $parentId): ?>
            / <span><?= e($crumb['name']) ?></span>
        <?php else: ?>
            / <a href="<?= root_url($listPath($crumb['id'], '', 1)) ?>"><?= e($crumb['name']) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</p>

<div class="slide-head">
    <h2><?= $parent === null ? 'İller' : e($parent['name'] . ' ' . (LocationCatalog::childKinds($parent['kind']) === [] ? '' : match ($parent['kind']) {
        'il' => 'ilçeleri',
        'ilce' => 'mahalle ve köyleri',
        default => '',
    })) ?></h2>
</div>
<p class="settings-lead">
    <?= (int) $totals['il'] ?> il, <?= (int) $totals['ilce'] ?> ilçe,
    <?= e(number_format((int) $totals['mahalle'], 0, ',', '.')) ?> mahalle,
    <?= e(number_format((int) $totals['koy'], 0, ',', '.')) ?> köy.
    <?php if ($syncedAt > 0): ?>
        Son resmi yükleme: <?= e(date('d.m.Y H:i', $syncedAt)) ?><?= $source !== '' ? ' · ' . e($source) : '' ?>.
    <?php endif; ?>
    Üyelik formunda il, ilçe ve mahalle veya köy bu listeden seçilir. Gizlenen kayıt yeni adreslerde görünmez.
</p>

<?php if (is_array($editing)): ?>
    <?php $targets = LocationCatalog::moveTargets($editing['kind']); ?>
    <form class="settings-form" method="post" action="<?= root_url('admin/konumlar.php') ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <input type="hidden" name="ust" value="<?= (int) $parentId ?>">
        <input type="hidden" name="q" value="<?= e($query) ?>">
        <section class="settings-card">
            <h2><?= e(LocationCatalog::kindLabel($editing['kind'])) ?> düzenle</h2>
            <div class="fields-2">
                <label class="field" for="edit-name">
                    <span>Ad</span>
                    <input id="edit-name" name="name" type="text" maxlength="80" value="<?= e($editing['name']) ?>" required>
                </label>
                <?php if (in_array($editing['kind'], ['mahalle', 'koy'], true)): ?>
                    <label class="field" for="edit-kind">
                        <span>Tür</span>
                        <select id="edit-kind" name="kind">
                            <option value="mahalle"<?= $editing['kind'] === 'mahalle' ? ' selected' : '' ?>>Mahalle</option>
                            <option value="koy"<?= $editing['kind'] === 'koy' ? ' selected' : '' ?>>Köy</option>
                        </select>
                    </label>
                    <label class="field" for="edit-postal">
                        <span>Posta kodu</span>
                        <input id="edit-postal" name="postal_code" type="text" maxlength="5" inputmode="numeric" value="<?= e($editing['postal_code']) ?>">
                    </label>
                <?php endif; ?>
                <?php if ($targets !== []): ?>
                    <label class="field" for="edit-parent">
                        <span>Bağlı olduğu yer</span>
                        <select id="edit-parent" name="parent_id">
                            <?php foreach ($targets as $target): ?>
                                <option value="<?= (int) $target['id'] ?>"<?= (int) $target['id'] === $editing['parent_id'] ? ' selected' : '' ?>><?= e($target['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
            </div>
            <div class="settings-save">
                <a class="login-submit slide-add" href="<?= root_url($listPath($parentId, $query, $list['page'])) ?>">Vazgeç</a>
                <button class="login-submit" type="submit">Kaydı güncelle</button>
            </div>
        </section>
    </form>
<?php endif; ?>

<?php if ($childKinds !== []): ?>
    <form class="settings-form" method="post" action="<?= root_url('admin/konumlar.php') ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="parent_id" value="<?= (int) $parentId ?>">
        <input type="hidden" name="ust" value="<?= (int) $parentId ?>">
        <input type="hidden" name="q" value="<?= e($query) ?>">
        <?php if (count($childKinds) === 1): ?>
            <input type="hidden" name="kind" value="<?= e($childKinds[0]) ?>">
        <?php endif; ?>
        <section class="settings-card">
            <h2><?= count($childKinds) === 1 ? e(LocationCatalog::kindLabel($childKinds[0]) . ' ekle') : 'Mahalle veya köy ekle' ?></h2>
            <div class="fields-2">
                <label class="field" for="create-name">
                    <span>Ad</span>
                    <input id="create-name" name="name" type="text" maxlength="80" required>
                </label>
                <?php if (count($childKinds) > 1): ?>
                    <label class="field" for="create-kind">
                        <span>Tür</span>
                        <select id="create-kind" name="kind">
                            <?php foreach ($childKinds as $kind): ?>
                                <option value="<?= e($kind) ?>"><?= e(LocationCatalog::kindLabel($kind)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field" for="create-postal">
                        <span>Posta kodu</span>
                        <input id="create-postal" name="postal_code" type="text" maxlength="5" inputmode="numeric">
                    </label>
                <?php endif; ?>
            </div>
            <div class="settings-save">
                <button class="login-submit" type="submit">Ekle</button>
            </div>
        </section>
    </form>
<?php endif; ?>

<form class="settings-form loc-search" method="get" action="<?= root_url('admin/konumlar.php') ?>">
    <?php if ($parentId > 0): ?><input type="hidden" name="ust" value="<?= (int) $parentId ?>"><?php endif; ?>
    <label class="field" for="loc-q">
        <span>Bu listede ara</span>
        <input id="loc-q" name="q" type="search" maxlength="80" value="<?= e($query) ?>">
    </label>
    <button class="login-submit slide-add" type="submit">Ara</button>
</form>

<?php if ($list['rows'] === []): ?>
    <p class="settings-lead"><?= $query !== '' ? 'Bu aramaya uyan kayıt yok.' : 'Bu düzeyde kayıt yok.' ?></p>
<?php else: ?>
    <div class="table-wrap">
        <table class="loc-table">
            <thead>
                <tr>
                    <th>Ad</th>
                    <th>Tür</th>
                    <th>Posta kodu</th>
                    <th>Durum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list['rows'] as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e((string) $row['name']) ?></strong>
                            <?php if ((int) $row['child_count'] > 0): ?>
                                <p><?= (int) $row['child_count'] ?> alt kayıt</p>
                            <?php endif; ?>
                        </td>
                        <td><?= e(LocationCatalog::kindLabel((string) $row['kind'])) ?><?= (string) $row['source_key'] === '' ? ' · Elle' : '' ?></td>
                        <td><?= e((string) $row['postal_code']) ?></td>
                        <td><?= (int) $row['is_active'] === 1 ? 'Listede' : 'Gizli' ?></td>
                        <td class="slide-actions">
                            <?php if (LocationCatalog::childKinds((string) $row['kind']) !== []): ?>
                                <a href="<?= root_url($listPath((int) $row['id'], '', 1)) ?>">İçindekiler</a>
                            <?php endif; ?>
                            <a href="<?= root_url($listPath($parentId, $query, $list['page']) . (str_contains($listPath($parentId, $query, $list['page']), '?') ? '&' : '?') . 'kayit=' . (int) $row['id']) ?>">Düzenle</a>
                            <form method="post" action="<?= root_url('admin/konumlar.php') ?>">
                                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="ust" value="<?= (int) $parentId ?>">
                                <input type="hidden" name="q" value="<?= e($query) ?>">
                                <input type="hidden" name="sayfa" value="<?= (int) $list['page'] ?>">
                                <?php if ((int) $row['is_active'] !== 1): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                                <button type="submit"><?= (int) $row['is_active'] === 1 ? 'Gizle' : 'Göster' ?></button>
                            </form>
                            <form method="post" action="<?= root_url('admin/konumlar.php') ?>" data-confirm="Bu kayıt silinsin mi?">
                                <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="ust" value="<?= (int) $parentId ?>">
                                <input type="hidden" name="q" value="<?= e($query) ?>">
                                <input type="hidden" name="sayfa" value="<?= (int) $list['page'] ?>">
                                <button type="submit">Sil</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($list['pages'] > 1): ?>
        <p class="field-hint loc-pager">
            <?php if ($list['page'] > 1): ?><a href="<?= root_url($listPath($parentId, $query, $list['page'] - 1)) ?>">Önceki</a><?php endif; ?>
            <span><?= (int) $list['page'] ?> / <?= (int) $list['pages'] ?></span>
            <?php if ($list['page'] < $list['pages']): ?><a href="<?= root_url($listPath($parentId, $query, $list['page'] + 1)) ?>">Sonraki</a><?php endif; ?>
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($parentId === 0): ?>
    <form class="settings-form" method="post" action="<?= root_url('admin/konumlar.php') ?>">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="action" value="sync">
        <section class="settings-card">
            <h2>Resmi listeyi güncelle</h2>
            <p class="settings-lead">TurkiyeAPI 2025 il, ilçe, mahalle ve köy listesini indirir. Yeni kayıtları ekler, değişen resmi adları ve posta kodlarını günceller. Elle eklenen kayıtlar ve gizlediğin yerler durur. İşlem bir dakika sürebilir.</p>
            <label class="check" for="retire-missing">
                <input id="retire-missing" name="retire" type="checkbox">
                <span>Güncel listede artık olmayan resmi kayıtları gizle</span>
            </label>
            <div class="settings-save">
                <button class="login-submit" type="submit"><?= $totals['il'] === 0 ? 'Listeyi yükle' : 'Resmi listeyi uygula' ?></button>
            </div>
        </section>
    </form>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
