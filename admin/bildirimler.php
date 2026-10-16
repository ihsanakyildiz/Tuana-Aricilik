<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/Mailbox.php';
require_once dirname(__DIR__) . '/app/Notices.php';

Auth::boot();
Auth::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        Auth::flash('err', 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.');
    } else {
        if ($action === 'smtp') {
            $errors = Mailbox::save($_POST);
            Auth::flash($errors === [] ? 'ok' : 'err', $errors === [] ? 'SMTP ayarları kaydedildi.' : $errors[0]);
        } elseif ($action === 'notify') {
            Notices::save($_POST);
            Auth::flash('ok', 'Bildirim seçimleri kaydedildi.');
        } elseif ($action === 'test') {
            $sent = Mailbox::trial($_POST);
            Auth::flash($sent['ok'] ? 'ok' : 'err', $sent['ok'] ? 'Deneme e-postası ' . $sent['to'] . ' adresine gönderildi.' : $sent['error']);
        } elseif ($action === 'read') {
            Notices::markAllRead();
            Auth::flash('ok', 'Bildirimler okundu olarak işaretlendi.');
        } else {
            Auth::flash('err', 'Bu işlem tanınmıyor.');
        }
    }
    header('Location: ' . root_path() . '/admin/bildirimler.php', true, 303);
    exit;
}

$pageTitle = 'Bildirimler';
$crumbCurrent = 'Bildirimler';
$activeNav = 'bildirimler';
$smtp = Mailbox::config();
$notify = Notices::config();
$notices = Notices::recent(15);
$csrf = Auth::csrfToken();

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<div class="settings-grid">
    <section class="settings-card">
        <h2>SMTP</h2>
        <p class="settings-lead">Yeni üyelik ve yeni sipariş e-postaları bu hesaptan gider. Şifre alanı boş kaydedilirse kayıtlı şifre durur. Deneme, formdaki bilgileri kullanır ve kaydetmez. Panel bildirimi SMTP olmadan da çalışır.</p>
        <form method="post" action="<?= root_url('admin/bildirimler.php') ?>" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="fields-2">
                <label class="field">SMTP sunucusu <input type="text" name="host" maxlength="120" value="<?= e($smtp['host']) ?>" placeholder="smtp.ornek.com" required></label>
                <label class="field">Port <input type="number" name="port" min="1" max="65535" value="<?= (int) $smtp['port'] ?>" required></label>
            </div>
            <label class="field">Şifreleme
                <select name="encryption">
                    <option value="tls"<?= $smtp['encryption'] === 'tls' ? ' selected' : '' ?>>TLS (genelde 587)</option>
                    <option value="ssl"<?= $smtp['encryption'] === 'ssl' ? ' selected' : '' ?>>SSL (genelde 465)</option>
                    <option value="none"<?= $smtp['encryption'] === 'none' ? ' selected' : '' ?>>Yok</option>
                </select>
            </label>
            <div class="fields-2">
                <label class="field">Kullanıcı adı <input type="text" name="username" maxlength="120" value="<?= e($smtp['username']) ?>" autocomplete="off"></label>
                <label class="field">Şifre <input type="password" name="password" maxlength="200" value="" placeholder="<?= $smtp['password'] !== '' ? 'Kayıtlı. Değiştirmek için yeni değeri yaz.' : '' ?>" autocomplete="new-password"></label>
            </div>
            <div class="fields-2">
                <label class="field">Gönderen adı <input type="text" name="from_name" maxlength="80" value="<?= e($smtp['from_name']) ?>"></label>
                <label class="field">Gönderen e-posta <input type="email" name="from_email" maxlength="120" value="<?= e($smtp['from_email']) ?>" required></label>
            </div>
            <label class="field">Bildirimlerin gideceği adres <input type="email" name="to_email" maxlength="120" value="<?= e($smtp['to_email']) ?>" required></label>
            <label class="field">Deneme adresi <input type="email" name="test_to" maxlength="120" value="" placeholder="Boşsa bildirim adresine gider" autocomplete="off"></label>
            <div class="sale-actions">
                <button class="sale-btn" type="submit" name="action" value="smtp">SMTP ayarlarını kaydet</button>
                <button class="sale-btn is-ghost" type="submit" name="action" value="test">Deneme e-postası gönder</button>
            </div>
        </form>
    </section>
    <section class="settings-card">
        <h2>Ne zaman haber verilsin</h2>
        <p class="settings-lead">Yönetici kutuları panele ve yönetici adresine gider. Müşteri kutuları, siparişte yazılan adrese gider. E-posta, SMTP bilgileri kayıtlıysa gider.</p>
        <form method="post" action="<?= root_url('admin/bildirimler.php') ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="notify">
            <label class="check"><input type="checkbox" name="member_panel" value="1"<?= $notify['member_panel'] ? ' checked' : '' ?>><span>Yeni üyelikte panel bildirimi</span></label>
            <label class="check"><input type="checkbox" name="member_mail" value="1"<?= $notify['member_mail'] ? ' checked' : '' ?>><span>Yeni üyelikte yöneticiye e-posta</span></label>
            <label class="check"><input type="checkbox" name="order_panel" value="1"<?= $notify['order_panel'] ? ' checked' : '' ?>><span>Yeni siparişte panel bildirimi</span></label>
            <label class="check"><input type="checkbox" name="order_mail" value="1"<?= $notify['order_mail'] ? ' checked' : '' ?>><span>Yeni siparişte yöneticiye e-posta</span></label>
            <label class="check"><input type="checkbox" name="customer_order" value="1"<?= $notify['customer_order'] ? ' checked' : '' ?>><span>Müşteriye sipariş özeti</span></label>
            <label class="check"><input type="checkbox" name="customer_status" value="1"<?= $notify['customer_status'] ? ' checked' : '' ?>><span>Müşteriye durum ve kargo e-postası</span></label>
            <button class="sale-btn" type="submit">Bildirim seçimlerini kaydet</button>
        </form>
    </section>
</div>
<section class="settings-card">
    <h2>Son bildirimler</h2>
    <?php if ($notices === []): ?>
        <p class="settings-lead">Henüz bildirim yok.</p>
    <?php else: ?>
        <ul class="notice-list">
            <?php foreach ($notices as $notice): ?>
                <?php
                $target = (string) $notice['href'];
                $join = str_contains($target, '?') ? '&' : '?';
                ?>
                <li class="<?= $notice['read'] ? '' : 'is-new' ?>">
                    <a href="<?= root_url($target . $join . 'bildirim=' . (int) $notice['id']) ?>">
                        <strong><?= e((string) $notice['title']) ?></strong>
                        <span><?= e((string) $notice['body']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= root_url('admin/bildirimler.php') ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="read">
            <button class="sale-btn is-ghost" type="submit">Tümünü okundu say</button>
        </form>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
