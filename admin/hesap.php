<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';

$allowPasswordChange = true;
Auth::boot();
Auth::requireLogin(true);

$errors = [];
$username = Auth::username();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } else {
        $result = Auth::updateCredentials($_POST);
        if ($result === true) {
            Auth::flash('ok', 'Giriş bilgileri güncellendi.');
            Auth::redirect('admin/');
        }
        $errors = $result;
        $username = trim((string) ($_POST['username'] ?? $username));
    }
}

$pageTitle = 'Hesap';
$crumbCurrent = 'Hesap';
$activeNav = 'hesap';
$mustChange = Auth::mustChange();

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<section class="account-card">
    <h2>Giriş bilgileri</h2>
    <p class="account-lead">Kullanıcı adı ve şifre buradan değişir. Kayıttan sonra diğer cihazlardaki açık oturumlar kapanır.</p>

    <?php if ($mustChange): ?>
        <div class="form-alert form-note" role="status">
            <p>İlk giriştesin. Devam etmeden kendi kullanıcı adını ve şifreni belirle.</p>
        </div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="form-alert" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= e((string) $error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= root_url('admin/hesap.php') ?>" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

        <label class="field" for="current-password">
            <span>Mevcut şifre <abbr title="zorunlu">*</abbr></span>
            <span class="field-box">
                <input id="current-password" name="current_password" type="password" autocomplete="current-password" maxlength="128" required>
                <button class="ghost-eye" type="button" data-toggle-password="current-password" aria-label="Şifreyi göster" aria-pressed="false"><?= admin_icon('eye') ?></button>
            </span>
        </label>

        <label class="field" for="account-username">
            <span>Kullanıcı adı <abbr title="zorunlu">*</abbr></span>
            <input id="account-username" name="username" type="text" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="32" required>
        </label>

        <label class="field" for="new-password">
            <span>Yeni şifre<?= $mustChange ? ' <abbr title="zorunlu">*</abbr>' : '' ?></span>
            <span class="field-box">
                <input id="new-password" name="new_password" type="password" autocomplete="new-password" maxlength="128"<?= $mustChange ? ' required' : '' ?>>
                <button class="ghost-eye" type="button" data-toggle-password="new-password" aria-label="Şifreyi göster" aria-pressed="false"><?= admin_icon('eye') ?></button>
            </span>
        </label>

        <label class="field" for="confirm-password">
            <span>Yeni şifre tekrar<?= $mustChange ? ' <abbr title="zorunlu">*</abbr>' : '' ?></span>
            <span class="field-box">
                <input id="confirm-password" name="new_password_confirm" type="password" autocomplete="new-password" maxlength="128"<?= $mustChange ? ' required' : '' ?>>
                <button class="ghost-eye" type="button" data-toggle-password="confirm-password" aria-label="Şifreyi göster" aria-pressed="false"><?= admin_icon('eye') ?></button>
            </span>
        </label>

        <ul class="password-rules">
            <li>En az 10, en fazla 128 karakter</li>
            <li>En az bir harf ve bir rakam</li>
            <li>Kullanıcı adından ve mevcut şifreden farklı</li>
        </ul>

        <button class="login-submit account-submit" type="submit">Kaydet</button>
    </form>
</section>
<?php require dirname(__DIR__) . '/partials/admin/end.php';