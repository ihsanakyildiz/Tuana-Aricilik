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
if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
    Auth::redirect('admin/sayfalar.php');
}

$record = $slug === 'home' ? null : PageEditor::find($slug);
$page = null;
if ($slug === 'home') {
    $page = [
        'slug' => 'home',
        'title' => 'Anasayfa',
        'kind' => 'home',
        'active' => true,
        'url' => '/',
        'locked' => true,
    ];
} elseif (is_array($record) && ($record['kind'] ?? '') === 'advanced') {
    $page = [
        'slug' => (string) $record['key'],
        'title' => (string) $record['title'],
        'kind' => 'advanced',
        'active' => !empty($record['active']),
        'url' => '/' . (string) $record['key'],
        'locked' => false,
    ];
} elseif (is_array($record)) {
    Auth::redirect('admin/sayfalar.php?sayfa=' . rawurlencode($slug));
} else {
    foreach (PageEditor::designed() as $designed) {
        if ($designed['key'] !== $slug) {
            continue;
        }
        $page = [
            'slug' => $designed['key'],
            'title' => $designed['title'],
            'kind' => 'designed',
            'active' => $designed['active'],
            'url' => $designed['url'],
            'locked' => true,
        ];
        break;
    }
}
if ($page === null) {
    Auth::redirect('admin/sayfalar.php');
}

SectionEditor::usePage($slug);
$errors = [];
$posted = is_array($record) ? [
    'title' => (string) $record['title'],
    'slug' => (string) $record['key'],
    'status' => !empty($record['active']) ? 'publish' : 'draft',
    'excerpt' => (string) ($record['excerpt'] ?? ''),
    'seo_title' => (string) ($record['seo_title'] ?? ''),
    'seo_description' => (string) ($record['seo_description'] ?? ''),
    'index' => !empty($record['index']),
] : [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } elseif ($action === 'create') {
        $type = is_string($_POST['type'] ?? null) ? $_POST['type'] : '';
        $created = SectionEditor::create($type);
        $errors = $created['errors'];
        if ($errors === []) {
            Auth::flash('ok', 'Bölüm eklendi.');
            $key = (string) $created['key'];
            if ($type === 'grid') {
                Auth::redirect('admin/grid.php?sayfa=' . rawurlencode($slug) . '&bolum=' . rawurlencode($key));
            }
            if ($type === 'hero') {
                Auth::redirect('admin/hero.php?sayfa=' . rawurlencode($slug) . '&bolum=' . rawurlencode($key) . '&donus=gelismis');
            }
            $editable = ['features', 'about', 'banner', 'services', 'why', 'benefits', 'promo', 'testimonials', 'blog'];
            if (in_array($type, $editable, true)) {
                Auth::redirect('admin/bolumler.php?sayfa=' . rawurlencode($slug) . '&bolum=' . rawurlencode($key) . '&donus=gelismis');
            }
            Auth::redirect('admin/gelismis.php?sayfa=' . rawurlencode($slug));
        }
    } elseif ($action === 'delete') {
        $errors = SectionEditor::remove((int) ($_POST['section_id'] ?? 0));
        if ($errors === []) {
            Auth::flash('ok', 'Bölüm sayfadan kaldırıldı.');
            Auth::redirect('admin/gelismis.php?sayfa=' . rawurlencode($slug));
        }
    } elseif ($action === 'toggle') {
        SectionEditor::setActive((int) ($_POST['section_id'] ?? 0), isset($_POST['active']));
        Auth::flash('ok', isset($_POST['active']) ? 'Bölüm yayında.' : 'Bölüm gizlendi.');
        Auth::redirect('admin/gelismis.php?sayfa=' . rawurlencode($slug));
    } elseif ($action === 'move') {
        SectionEditor::move((int) ($_POST['section_id'] ?? 0), (int) ($_POST['direction'] ?? 0));
        Auth::redirect('admin/gelismis.php?sayfa=' . rawurlencode($slug));
    } elseif ($action === 'save-page' && $page['kind'] === 'advanced') {
        $saved = PageEditor::saveAdvanced($_POST);
        $errors = $saved['errors'];
        $posted = array_merge($posted, $saved['values']);
        if ($errors === []) {
            Auth::flash('ok', 'Sayfa kaydedildi.');
            Auth::redirect('admin/gelismis.php?sayfa=' . rawurlencode($saved['key']));
        }
    }
}

$rows = SectionEditor::rows();
$gridIds = [];
foreach ($rows as $row) {
    if ($row['type'] === 'grid') {
        $gridIds[$row['key']] = true;
    }
}
$topRows = [];
foreach ($rows as $row) {
    $parent = (string) ($row['content']['placed_in'] ?? '');
    if ($parent !== '' && isset($gridIds[$parent])) {
        continue;
    }
    $topRows[] = $row;
}
$adding = isset($_GET['ekle']);
$catalog = [
    'grid' => [
        'label' => 'Grid satırı',
        'text' => '12 kolonluk satır. Karşılama, kart ve bölümleri yan yana kolonlara bölersin.',
    ],
    'hero' => [
        'label' => 'Karşılama',
        'text' => 'Tam ekran karşılama. Anasayfadaki slaytlardan başlar; slaytları bu sayfaya özel düzenlersin.',
    ],
    'pagehead' => [
        'label' => 'Sayfa bandı',
        'text' => 'Başlık, kısa açıklama ve sayfa yolu.',
    ],
];
foreach (SectionCatalog::DESIGNS as $type => $design) {
    $catalog[$type] = ['label' => $design['label'], 'text' => $design['text']];
}
$catalog['blog'] = [
    'label' => 'Yazı listesi',
    'text' => 'Yayındaki blog yazılarını kartlar halinde dizer.',
];

$editUrl = static function (array $row) use ($slug): string {
    $key = (string) $row['key'];
    if (($row['type'] ?? '') === 'grid') {
        return 'admin/grid.php?sayfa=' . rawurlencode($slug) . '&bolum=' . rawurlencode($key);
    }
    if (($row['type'] ?? '') === 'hero') {
        if ($slug === 'home' && $key === 'hero') {
            return 'admin/hero.php?donus=gelismis';
        }

        return 'admin/hero.php?sayfa=' . rawurlencode($slug) . '&bolum=' . rawurlencode($key) . '&donus=gelismis';
    }

    return 'admin/bolumler.php?sayfa=' . rawurlencode($slug) . '&bolum=' . rawurlencode($key) . '&donus=gelismis';
};

$pageTitle = (string) $page['title'];
$crumbCurrent = 'Gelişmiş sayfa';
$activeNav = 'sayfalar';
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
            <p><?= e((string) $error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<p class="slide-back"><a href="<?= root_url('admin/sayfalar.php') ?>">Sayfalara dön</a></p>
<?php if ($adding): ?>
    <section class="settings-card">
        <h2><?= e((string) $page['title']) ?> için bölüm seç</h2>
        <p class="settings-lead">Seçtiğin bölüm listenin sonuna eklenir. Sonra yukarı ve aşağı ile sırasını değiştirirsin. Aynı tasarımdan istediğin kadar kullanabilirsin.</p>
    </section>
    <div class="design-grid">
        <?php foreach ($catalog as $type => $design): ?>
            <article class="design-card">
                <?php if (SectionCatalog::has($type)): ?>
                    <div class="design-stage" data-preview>
                        <iframe src="<?= root_url('admin/onizleme.php?tip=' . rawurlencode($type)) ?>" title="<?= e($design['label']) ?> önizlemesi" loading="lazy" tabindex="-1"></iframe>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= root_url('admin/gelismis.php?sayfa=' . rawurlencode($slug) . '&ekle=1') ?>">
                    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                    <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <h2><?= e($design['label']) ?></h2>
                    <p><?= e($design['text']) ?></p>
                    <button class="login-submit" type="submit">Bu tasarımı ekle</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php if ($page['kind'] === 'advanced'): ?>
        <form class="settings-form" method="post" action="<?= root_url('admin/gelismis.php?sayfa=' . rawurlencode($slug)) ?>" data-post-editor data-permalink-base="<?= e($permalinkBase) ?>">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
            <input type="hidden" name="page_key" value="<?= e($slug) ?>">
            <input type="hidden" name="action" value="save-page">
            <section class="settings-card">
                <h2>Sayfa</h2>
                <div class="fields-2">
                    <label class="field" for="post-title">
                        <span>Başlık</span>
                        <input id="post-title" name="title" type="text" maxlength="140" value="<?= e((string) ($posted['title'] ?? '')) ?>" data-post-title required>
                    </label>
                    <label class="field" for="post-slug">
                        <span>Adres</span>
                        <input id="post-slug" name="slug" type="text" maxlength="64" value="<?= e((string) ($posted['slug'] ?? '')) ?>" data-post-slug data-auto="0">
                    </label>
                </div>
                <p class="permalink">Kalıcı bağlantı: <strong data-permalink><?= e($permalinkBase . '/' . (string) ($posted['slug'] ?? $slug)) ?></strong></p>
                <div class="fields-2">
                    <label class="field" for="post-status">
                        <span>Durum</span>
                        <select id="post-status" name="status">
                            <option value="draft"<?= (($posted['status'] ?? '') !== 'publish') ? ' selected' : '' ?>>Taslak</option>
                            <option value="publish"<?= (($posted['status'] ?? '') === 'publish') ? ' selected' : '' ?>>Yayında</option>
                        </select>
                    </label>
                    <label class="field" for="post-excerpt">
                        <span>Özet</span>
                        <input id="post-excerpt" name="excerpt" type="text" maxlength="300" value="<?= e((string) ($posted['excerpt'] ?? '')) ?>">
                    </label>
                </div>
                <div class="fields-2">
                    <label class="field" for="post-seo-title">
                        <span>Arama başlığı</span>
                        <input id="post-seo-title" name="seo_title" type="text" maxlength="70" value="<?= e((string) ($posted['seo_title'] ?? '')) ?>" placeholder="Boşsa sayfa bandının başlığı kullanılır">
                    </label>
                    <label class="field" for="post-seo-description">
                        <span>Arama açıklaması</span>
                        <input id="post-seo-description" name="seo_description" type="text" maxlength="160" value="<?= e((string) ($posted['seo_description'] ?? '')) ?>">
                    </label>
                </div>
                <label class="check" for="post-index"><input id="post-index" name="index" type="checkbox"<?= !empty($posted['index']) ? ' checked' : '' ?>> <span>Arama motorları bu sayfayı indexlesin</span></label>
                <div class="settings-save">
                    <button class="login-submit" type="submit">Sayfayı kaydet</button>
                    <?php if (!empty($page['active'])): ?>
                        <a href="<?= root_url((string) $page['url']) ?>" target="_blank" rel="noopener">Sayfayı görüntüle</a>
                    <?php endif; ?>
                </div>
            </section>
        </form>
    <?php else: ?>
        <section class="settings-card">
            <h2><?= e((string) $page['title']) ?></h2>
            <p class="settings-lead">Adres <?= e((string) $page['url']) ?> olarak kalır. Karşılama, kartlar ve bölümleri aşağıdaki sırayla dizer, her birinin içeriğini ayrı açarsın.</p>
            <?php if (!empty($page['active'])): ?>
                <p><a href="<?= root_url((string) $page['url']) ?>" target="_blank" rel="noopener">Sayfayı görüntüle</a></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <div class="slide-head">
        <h2>Bölüm sırası</h2>
        <a class="login-submit slide-add" href="<?= root_url('admin/gelismis.php?sayfa=' . rawurlencode($slug) . '&ekle=1') ?>">Bölüm ekle</a>
    </div>
    <?php if ($topRows === []): ?>
        <p class="settings-lead">Bu sayfada henüz bölüm yok. Bölüm ekle ile grid, karşılama, kart veya hazır bir tasarım seç.</p>
    <?php else: ?>
        <div class="slide-list">
            <?php foreach ($topRows as $index => $row): ?>
                <article class="slide-row">
                    <div class="slide-thumb"><span><?= (int) $index + 1 ?></span></div>
                    <div>
                        <strong><?= e((string) ($row['title'] !== '' ? $row['title'] : SectionCatalog::label((string) $row['type']))) ?></strong>
                        <p><?= e(SectionCatalog::label((string) $row['type'])) ?> · <?= !empty($row['active']) ? 'Yayında' : 'Gizli' ?></p>
                    </div>
                    <div class="slide-actions">
                        <form method="post" action="<?= root_url('admin/gelismis.php?sayfa=' . rawurlencode($slug)) ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="direction" value="-1">
                            <button type="submit"<?= $index === 0 ? ' disabled' : '' ?> aria-label="Yukarı taşı">↑</button>
                        </form>
                        <form method="post" action="<?= root_url('admin/gelismis.php?sayfa=' . rawurlencode($slug)) ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                            <input type="hidden" name="action" value="move">
                            <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="direction" value="1">
                            <button type="submit"<?= $index === count($topRows) - 1 ? ' disabled' : '' ?> aria-label="Aşağı taşı">↓</button>
                        </form>
                        <form method="post" action="<?= root_url('admin/gelismis.php?sayfa=' . rawurlencode($slug)) ?>">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                            <?php if (empty($row['active'])): ?><input type="hidden" name="active" value="1"><?php endif; ?>
                            <button type="submit"><?= !empty($row['active']) ? 'Gizle' : 'Yayınla' ?></button>
                        </form>
                        <a href="<?= root_url($editUrl($row)) ?>">Düzenle</a>
                        <form method="post" action="<?= root_url('admin/gelismis.php?sayfa=' . rawurlencode($slug)) ?>" data-confirm="Bu bölüm sayfadan kaldırılsın mı?">
                            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="page_slug" value="<?= e($slug) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="section_id" value="<?= (int) $row['id'] ?>">
                            <button type="submit"<?= ($slug === 'home' && ($row['type'] ?? '') === 'hero' && $row['key'] === 'hero') ? ' disabled' : '' ?>>Kaldır</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <p class="field-hint">Üstteki bölüm sitede de üstte durur. Grid satırının içinde kolonlara bölüm eklersin. Kolondaki bölümler bu listede ayrı durmaz.</p>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
