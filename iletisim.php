<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';
require_once __DIR__ . '/app/ContactDesk.php';

$rawPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#/iletisim/$#', $rawPath) === 1) {
    header('Location: ' . absolute_url('/iletisim'), true, 301);
    exit;
}

public_maintenance();
MemberAccount::boot(true);

$page = ContactDesk::page();
$details = ContactDesk::details();
$errors = [];
$draft = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'body' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($draft as $key => $value) {
        $draft[$key] = (string) ($_POST[$key] ?? '');
    }
    if (!MemberAccount::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } else {
        $result = ContactDesk::receive($_POST);
        if ($result['ok']) {
            MemberAccount::flash('ok', $page['success']);
            header('Location: ' . absolute_url('/iletisim'), true, 303);
            exit;
        }
        $errors = $result['errors'];
    }
}

$flash = MemberAccount::pullFlash();
$pageTitle = $page['title'];
$metaDescription = $page['lead'] !== '' ? $page['lead'] : 'İletişim';
$canonicalPath = '/iletisim';
$bodyClass = 'is-page is-shop';
$robots = 'index,follow';
$shopTitle = $page['title'];
$shopCrumbs = [
    ['label' => 'Ana Sayfa', 'url' => '/'],
    ['label' => $page['title'], 'url' => ''],
];

$lines = [];
if ($page['show_address'] && $details['address'] !== '') {
    $lines[] = ['icon' => 'pin', 'text' => $details['address']];
}
if ($page['show_email'] && $details['email'] !== '') {
    $lines[] = ['icon' => 'mail', 'text' => $details['email'], 'href' => 'mailto:' . $details['email']];
}
if ($page['show_phone'] && $details['phone'] !== '') {
    $lines[] = ['icon' => 'phone', 'text' => $details['phone'], 'href' => 'tel:' . preg_replace('/\s+/', '', $details['phone'])];
}
if ($page['show_whatsapp'] && $details['whatsapp'] !== '') {
    $digits = preg_replace('/\D+/', '', $details['whatsapp']);
    if ($digits !== '') {
        $lines[] = ['icon' => 'whatsapp', 'text' => $details['whatsapp'], 'href' => 'https://wa.me/' . $digits, 'external' => true];
    }
}
if ($page['show_hours'] && $details['hours'] !== '') {
    $lines[] = ['icon' => 'clock', 'text' => $details['hours']];
}

require __DIR__ . '/partials/header.php';
require __DIR__ . '/partials/shop-banner.php';
?>
<div class="page-canvas">
    <section class="shop">
        <div class="container contact-layout">
            <section class="shop-panel">
                <h2><?= e($page['title']) ?></h2>
                <?php if ($page['lead'] !== ''): ?>
                    <p class="account-lead"><?= e($page['lead']) ?></p>
                <?php endif; ?>
                <?php if ($lines !== []): ?>
                    <ul class="contact-lines">
                        <?php foreach ($lines as $line): ?>
                            <li>
                                <span aria-hidden="true"><?= icon((string) $line['icon']) ?></span>
                                <?php if (isset($line['href'])): ?>
                                    <a href="<?= url((string) $line['href']) ?>"<?= !empty($line['external']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e((string) $line['text']) ?></a>
                                <?php else: ?>
                                    <span><?= e((string) $line['text']) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($page['map_url'] !== ''): ?>
                    <p class="account-switch"><a href="<?= e($page['map_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($page['map_label']) ?></a></p>
                <?php endif; ?>
            </section>
            <?php if ($page['form_on']): ?>
                <section class="shop-panel">
                    <h2><?= e($page['form_title']) ?></h2>
                    <?php if ($page['form_note'] !== ''): ?>
                        <p class="account-lead"><?= e($page['form_note']) ?></p>
                    <?php endif; ?>
                    <?php if ($flash !== null): ?>
                        <p class="account-note" role="status"><?= e($flash['text']) ?></p>
                    <?php endif; ?>
                    <?php if ($errors !== []): ?>
                        <div class="account-alert" role="alert">
                            <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form class="shop-filters" method="post" action="<?= url('/iletisim') ?>">
                        <input class="hp" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <input type="hidden" name="csrf" value="<?= e(MemberAccount::csrfToken()) ?>">
                        <div class="account-fields-2">
                            <label class="shop-field">
                                <span>Ad</span>
                                <input type="text" name="name" maxlength="80" value="<?= e($draft['name']) ?>" autocomplete="name" required>
                            </label>
                            <label class="shop-field">
                                <span>E-posta</span>
                                <input type="email" name="email" maxlength="120" value="<?= e($draft['email']) ?>" autocomplete="email" required>
                            </label>
                        </div>
                        <label class="shop-field">
                            <span>Telefon</span>
                            <input type="text" name="phone" maxlength="40" value="<?= e($draft['phone']) ?>" autocomplete="tel">
                        </label>
                        <label class="shop-field">
                            <span>Konu</span>
                            <?php if ($page['topics'] === []): ?>
                                <input type="text" name="subject" maxlength="80" value="<?= e($draft['subject']) ?>" required>
                            <?php else: ?>
                                <select name="subject" required>
                                    <option value="">Seç</option>
                                    <?php foreach ($page['topics'] as $topic): ?>
                                        <option value="<?= e($topic) ?>"<?= $draft['subject'] === $topic ? ' selected' : '' ?>><?= e($topic) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </label>
                        <label class="shop-field">
                            <span>Mesaj</span>
                            <textarea name="body" maxlength="2000" rows="6" required><?= e($draft['body']) ?></textarea>
                        </label>
                        <button class="btn" type="submit">Gönder</button>
                    </form>
                </section>
            <?php elseif ($flash !== null): ?>
                <p class="account-note" role="status"><?= e($flash['text']) ?></p>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>
