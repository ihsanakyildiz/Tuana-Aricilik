<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/uye-giris/$#', $rawPath) === 1) {
    header('Location: ' . absolute_url('/uye-giris'), true, 301);
    exit;
}

public_maintenance();
MemberAccount::boot(true);
header('X-Robots-Tag: noindex, follow');
header('Cache-Control: no-store');

$next = MemberAccount::safeNext((string) ($_POST['sonraki'] ?? $_GET['sonraki'] ?? ''));
if (MemberAccount::check()) {
    MemberAccount::resume($next);
}

$errors = [];
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $honeypot = trim((string) ($_POST['company'] ?? ''));
    if (!MemberAccount::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } elseif ($honeypot !== '') {
        $errors[] = 'E-posta veya şifre hatalı.';
    } else {
        $result = MemberAccount::login($email, $password);
        if ($result === 'ok') {
            MemberAccount::resume($next);
        }
        $errors[] = $result === 'locked'
            ? 'Çok fazla hatalı deneme. 15 dakika sonra yeniden dene.'
            : 'E-posta veya şifre hatalı.';
    }
}

$pageTitle = 'Giriş';
$metaDescription = 'Tuana Arıcılık üye girişi';
$canonicalPath = '/uye-giris';
$bodyClass = 'is-page is-shop';
$robots = 'noindex,follow';
$shopTitle = 'Giriş';
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Giriş', 'url' => ''],
];
$flash = MemberAccount::pullFlash();

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container account-auth">
            <?php if ($flash !== null): ?>
                <p class="account-note" role="status"><?= e($flash['text']) ?></p>
            <?php endif; ?>
            <?php if ($errors !== []): ?>
                <div class="account-alert" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <section class="shop-panel">
                <h2>Üye girişi</h2>
                <p class="account-lead">Satın almak için üye girişi yap. Kargo ve fatura bilgilerin hesabında durur.</p>
                <form class="shop-filters" method="post" action="<?= url('/uye-giris') ?>" novalidate>
                    <input class="hp" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <input type="hidden" name="csrf" value="<?= e(MemberAccount::csrfToken()) ?>">
                    <input type="hidden" name="sonraki" value="<?= e($next) ?>">
                    <label class="shop-field">
                        <span>E-posta</span>
                        <input type="email" name="email" value="<?= e($email) ?>" maxlength="190" autocomplete="email" required>
                    </label>
                    <label class="shop-field">
                        <span>Şifre</span>
                        <input type="password" name="password" maxlength="128" autocomplete="current-password" required>
                    </label>
                    <button class="btn" type="submit">Giriş yap</button>
                </form>
                <p class="account-switch">Üyeliğin yoksa <a href="<?= url('/uye-ol' . ($next === '' ? '' : '?sonraki=' . rawurlencode($next))) ?>">üye ol</a>.</p>
            </section>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
