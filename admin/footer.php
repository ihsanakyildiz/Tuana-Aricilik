<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/FooterLinks.php';

Auth::boot();
Auth::requireLogin();

$errors = [];
$columns = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } else {
        $saved = FooterLinks::save($_POST);
        if ($saved['errors'] === []) {
            Auth::flash('ok', 'Footer bağlantıları kaydedildi.');
            Auth::redirect('admin/footer.php');
        }
        $errors = $saved['errors'];
        $columns = $saved['columns'];
    }
}
if ($columns === null) {
    $columns = FooterLinks::editor();
}

$pageTitle = 'Footer';
$crumbCurrent = 'Footer';
$activeNav = 'footer';
$csrf = Auth::csrfToken();
$linkRow = static function (int $col, string $label, string $url): void {
    ?>
    <div class="footer-link-row" data-footer-row>
        <label class="field">
            <span>Yazı</span>
            <input name="link_label[<?= $col ?>][]" type="text" maxlength="80" value="<?= e($label) ?>" autocomplete="off">
        </label>
        <label class="field">
            <span>Adres</span>
            <input name="link_url[<?= $col ?>][]" type="text" maxlength="180" value="<?= e($url) ?>" autocomplete="off" spellcheck="false">
        </label>
        <div class="menu-node-tools">
            <button class="menu-mini" type="button" data-footer-up>Yukarı</button>
            <button class="menu-mini" type="button" data-footer-down>Aşağı</button>
            <button class="menu-mini is-remove" type="button" data-footer-remove>Kaldır</button>
        </div>
    </div>
    <?php
};

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>
<form class="settings-grid" method="post" action="<?= root_url('admin/footer.php') ?>" data-footer-board>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <p class="settings-lead">Soldaki marka yazısı, sosyal hesaplar ve sağdaki iletişim sütunu Genel ayarlar ile İletişim sayfasından gelir. Buradan iki bağlantı sütununun başlığını ve linklerini düzenlersin.</p>
    <div class="footer-board">
        <?php foreach ($columns as $index => $column): ?>
            <?php
            $links = is_array($column['links'] ?? null) ? $column['links'] : [];
            ?>
            <section class="settings-card" data-footer-col="<?= (int) $index ?>">
                <h2><?= $index === 0 ? 'Sol sütun' : 'Sağ sütun' ?></h2>
                <label class="field" for="footer-title-<?= (int) $index ?>">
                    <span>Başlık</span>
                    <input id="footer-title-<?= (int) $index ?>" name="col_title[]" type="text" maxlength="40" value="<?= e((string) ($column['title'] ?? '')) ?>">
                </label>
                <div data-footer-links>
                    <?php foreach ($links as $link): ?>
                        <?php if (!is_array($link)) { continue; } ?>
                        <?php $linkRow((int) $index, (string) ($link['label'] ?? ''), (string) ($link['url'] ?? '')); ?>
                    <?php endforeach; ?>
                </div>
                <template data-footer-template>
                    <?php $linkRow((int) $index, '', ''); ?>
                </template>
                <button class="menu-mini" type="button" data-footer-add>Bağlantı ekle</button>
                <p class="field-hint">Adres <code>/sayfa</code>, <code>#bolum</code>, <code>mailto:</code> veya <code>https://</code> olabilir. Bir sütunda en fazla <?= (string) FooterLinks::MAX_LINKS ?> bağlantı durur.</p>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="settings-save">
        <button class="login-submit" type="submit">Bağlantıları kaydet</button>
    </div>
</form>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
