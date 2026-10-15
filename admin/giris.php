<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/partials/admin/icons.php';

Auth::boot();
if (Auth::check()) {
    Auth::redirect(Auth::mustChange() ? 'admin/hesap.php' : 'admin/');
}

$errors = [];
$username = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $honeypot = trim((string) ($_POST['company'] ?? ''));
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } elseif ($honeypot !== '') {
        $errors[] = 'Kullanıcı adı veya şifre hatalı.';
    } elseif (!db_pdo() instanceof PDO) {
        $errors[] = 'Veritabanına bağlanılamadı.';
    } else {
        $result = Auth::attempt($username, $password, isset($_POST['remember']));
        if ($result === 'ok') {
            Auth::redirect(Auth::mustChange() ? 'admin/hesap.php' : 'admin/');
        }
        $errors[] = $result === 'locked'
            ? 'Çok fazla hatalı deneme. 15 dakika sonra yeniden dene.'
            : 'Kullanıcı adı veya şifre hatalı.';
    }
}

$siteName = (string) setting('site_name', 'Tuana Arıcılık');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş · <?= e($siteName) ?></title>
    <link rel="icon" href="<?= root_url(favicon_file()) ?>" type="<?= e(favicon_type(favicon_file())) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= root_url('assets/css/admin.css') ?>">
</head>
<body class="login-body">
    <main class="login-card">
        <a class="login-brand" href="<?= root_url('/') ?>">
            <span class="brand-mark"><?= admin_icon('drop') ?></span>
            <span class="brand-name">Tuana</span>
        </a>
        <h1>Tekrar hoş geldin <span aria-hidden="true">👋</span></h1>
        <p class="login-lead">Yönetim paneline girmek için kullanıcı adını ve şifreni yaz.</p>

        <?php if ($errors !== []): ?>
            <div class="form-alert" role="alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= root_url('admin/giris.php') ?>" novalidate>
            <input class="hp" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

            <label class="field" for="username">
                <span>Kullanıcı adı <abbr title="zorunlu">*</abbr></span>
                <input id="username" name="username" type="text" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="32" required>
            </label>

            <label class="field" for="password">
                <span>Şifre <abbr title="zorunlu">*</abbr></span>
                <span class="field-box">
                    <input id="password" name="password" type="password" autocomplete="current-password" maxlength="128" required>
                    <button class="ghost-eye" type="button" data-toggle-password="password" aria-label="Şifreyi göster" aria-pressed="false"><?= admin_icon('eye') ?></button>
                </span>
            </label>

            <div class="login-row">
                <label class="check" for="remember">
                    <input id="remember" name="remember" type="checkbox">
                    <span>Oturumum açık kalsın</span>
                </label>
            </div>

            <button class="login-submit" type="submit">Giriş yap</button>
        </form>
    </main>
    <p class="login-foot">© <?= e(date('Y')) ?> <?= e($siteName) ?></p>
    <script src="<?= root_url('assets/js/admin.js') ?>" defer></script>
</body>
</html>
