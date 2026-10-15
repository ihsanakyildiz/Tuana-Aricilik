<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/uye-ol/$#', $rawPath) === 1) {
    header('Location: ' . absolute_url('/uye-ol'), true, 301);
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
$posted = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($posted as $key => $value) {
        $posted[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $honeypot = trim((string) ($_POST['company'] ?? ''));
    if (!MemberAccount::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } elseif ($honeypot !== '') {
        $errors[] = 'Üyelik oluşturulamadı.';
    } else {
        $errors = MemberAccount::register($_POST);
        if ($errors === []) {
            MemberAccount::flash('ok', 'Üyeliğin açıldı. Kargo ve fatura bilgilerini kaydedebilirsin.');
            MemberAccount::resume($next);
        }
    }
}

$pageTitle = 'Üye ol';
$metaDescription = 'Tuana Arıcılık üyelik kaydı';
$canonicalPath = '/uye-ol';
$bodyClass = 'is-page is-shop';
$robots = 'noindex,follow';
$shopTitle = 'Üye ol';
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => 'Üye ol', 'url' => ''],
];

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container account-auth">
            <?php if ($errors !== []): ?>
                <div class="account-alert" role="alert">
                    <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <section class="shop-panel">
                <h2>Üyelik bilgileri</h2>
                <p class="account-lead">Ad, iletişim ve şifre ile üyelik açılır. Adres ve fatura bilgisi üyelikten sonra hesabına eklenir.</p>
                <form class="shop-filters" method="post" action="<?= url('/uye-ol') ?>" novalidate>
                    <input class="hp" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <input type="hidden" name="csrf" value="<?= e(MemberAccount::csrfToken()) ?>">
                    <input type="hidden" name="sonraki" value="<?= e($next) ?>">
                    <div class="account-fields-2">
                        <label class="shop-field">
                            <span>Ad</span>
                            <input type="text" name="first_name" value="<?= e($posted['first_name']) ?>" maxlength="60" autocomplete="given-name" required>
                        </label>
                        <label class="shop-field">
                            <span>Soyad</span>
                            <input type="text" name="last_name" value="<?= e($posted['last_name']) ?>" maxlength="60" autocomplete="family-name" required>
                        </label>
                    </div>
                    <label class="shop-field">
                        <span>E-posta</span>
                        <input type="email" name="email" value="<?= e($posted['email']) ?>" maxlength="190" autocomplete="email" required>
                    </label>
                    <label class="shop-field">
                        <span>Telefon</span>
                        <input type="tel" name="phone" value="<?= e($posted['phone']) ?>" maxlength="20" autocomplete="tel" placeholder="05xx xxx xx xx" required>
                    </label>
                    <div class="account-fields-2">
                        <label class="shop-field">
                            <span>Şifre</span>
                            <input type="password" name="password" maxlength="128" autocomplete="new-password" required>
                        </label>
                        <label class="shop-field">
                            <span>Şifre tekrar</span>
                            <input type="password" name="password_confirm" maxlength="128" autocomplete="new-password" required>
                        </label>
                    </div>
                    <p class="account-hint">Şifre en az 8 karakter olmalı ve bir harf ile bir rakam içermeli.</p>
                    <button class="btn" type="submit">Üye ol</button>
                </form>
                <p class="account-switch">Üyeliğin varsa <a href="<?= url('/uye-giris' . ($next === '' ? '' : '?sonraki=' . rawurlencode($next))) ?>">giriş yap</a>.</p>
            </section>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
