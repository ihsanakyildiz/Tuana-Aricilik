<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require_once dirname(__DIR__) . '/app/ContactDesk.php';

Auth::boot();
Auth::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        Auth::flash('err', 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.');
    } elseif ($action === 'save') {
        $errors = ContactDesk::save($_POST);
        Auth::flash($errors === [] ? 'ok' : 'err', $errors === [] ? 'İletişim sayfası kaydedildi.' : $errors[0]);
    } elseif ($action === 'read') {
        ContactDesk::markAllRead();
        Auth::flash('ok', 'Mesajlar okundu olarak işaretlendi.');
    } elseif ($action === 'delete') {
        ContactDesk::delete((int) ($_POST['id'] ?? 0));
        Auth::flash('ok', 'Mesaj silindi.');
    } else {
        Auth::flash('err', 'Bu işlem tanınmıyor.');
    }
    header('Location: ' . root_path() . '/admin/iletisim.php', true, 303);
    exit;
}

$openId = (int) ($_GET['mesaj'] ?? 0);
if ($openId > 0) {
    ContactDesk::markRead($openId);
}
$page = ContactDesk::page();
$details = ContactDesk::details();
$messages = ContactDesk::recent(30);
$open = $openId > 0 ? ContactDesk::find($openId) : null;
$unread = ContactDesk::unreadCount();
$csrf = Auth::csrfToken();

$pageTitle = 'İletişim';
$crumbCurrent = 'İletişim';
$activeNav = 'iletisim';

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<div class="settings-grid">
    <section class="settings-card">
        <h2>Sayfa</h2>
        <p class="settings-lead">Üstteki İletişim düğmesi bu sayfayı açar. Adres, telefon ve e-posta alt bilgide de görünür. Boş bıraktığın satır sitede çıkmaz.</p>
        <form method="post" action="<?= root_url('admin/iletisim.php') ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <div class="fields-2">
                <label class="field">Sayfa başlığı <input type="text" name="title" maxlength="80" value="<?= e($page['title']) ?>" required></label>
                <label class="field">Form başlığı <input type="text" name="form_title" maxlength="80" value="<?= e($page['form_title']) ?>"></label>
            </div>
            <label class="field">Giriş metni <textarea name="lead" maxlength="400" rows="3"><?= e($page['lead']) ?></textarea></label>
            <label class="field">Form açıklaması <textarea name="form_note" maxlength="240" rows="2"><?= e($page['form_note']) ?></textarea></label>
            <label class="field">Gönderince görünecek yazı <input type="text" name="success" maxlength="240" value="<?= e($page['success']) ?>"></label>
            <div class="fields-2">
                <label class="field">E-posta <input type="email" name="email" maxlength="120" value="<?= e($details['email']) ?>"></label>
                <label class="field">Telefon <input type="text" name="phone" maxlength="40" value="<?= e($details['phone']) ?>"></label>
                <label class="field">WhatsApp <input type="text" name="whatsapp" maxlength="40" value="<?= e($details['whatsapp']) ?>"></label>
                <label class="field">Çalışma saatleri <input type="text" name="hours" maxlength="80" value="<?= e($details['hours']) ?>"></label>
            </div>
            <label class="field">Adres <input type="text" name="address" maxlength="180" value="<?= e($details['address']) ?>"></label>
            <div class="fields-2">
                <label class="field">Harita adresi <input type="url" name="map_url" maxlength="300" placeholder="https://" value="<?= e($page['map_url']) ?>"></label>
                <label class="field">Harita düğmesi <input type="text" name="map_label" maxlength="40" value="<?= e($page['map_label']) ?>"></label>
            </div>
            <label class="field">Konu listesi <textarea name="topics" maxlength="400" rows="4" placeholder="Her satıra bir konu. Boşsa ziyaretçi konuyu kendisi yazar."><?= e(implode("\n", $page['topics'])) ?></textarea></label>
            <label class="check"><input type="checkbox" name="form_on" value="1"<?= $page['form_on'] ? ' checked' : '' ?>><span>İletişim formu açık</span></label>
            <label class="check"><input type="checkbox" name="show_email" value="1"<?= $page['show_email'] ? ' checked' : '' ?>><span>E-postayı göster</span></label>
            <label class="check"><input type="checkbox" name="show_phone" value="1"<?= $page['show_phone'] ? ' checked' : '' ?>><span>Telefonu göster</span></label>
            <label class="check"><input type="checkbox" name="show_whatsapp" value="1"<?= $page['show_whatsapp'] ? ' checked' : '' ?>><span>WhatsApp hattını göster</span></label>
            <label class="check"><input type="checkbox" name="show_address" value="1"<?= $page['show_address'] ? ' checked' : '' ?>><span>Adresi göster</span></label>
            <label class="check"><input type="checkbox" name="show_hours" value="1"<?= $page['show_hours'] ? ' checked' : '' ?>><span>Çalışma saatlerini göster</span></label>
            <label class="check"><input type="checkbox" name="panel" value="1"<?= $page['panel'] ? ' checked' : '' ?>><span>Yeni mesajda panel bildirimi</span></label>
            <label class="check"><input type="checkbox" name="mail" value="1"<?= $page['mail'] ? ' checked' : '' ?>><span>Yeni mesajda yöneticiye e-posta</span></label>
            <div class="sale-actions">
                <button class="sale-btn" type="submit">İletişim sayfasını kaydet</button>
                <a class="sale-btn is-ghost" href="<?= root_url('/iletisim') ?>" target="_blank" rel="noopener">Sayfayı aç</a>
            </div>
        </form>
    </section>
</div>
<section class="settings-card">
    <h2>Gelen kutusu<?= $unread > 0 ? ' · ' . (int) $unread . ' yeni' : '' ?></h2>
    <?php if (is_array($open)): ?>
        <?php $sent = strtotime($open['created_at']); ?>
        <article class="message-open">
            <p class="settings-lead"><?= e($open['name']) ?> · <?= e($open['email']) ?><?= $open['phone'] !== '' ? ' · ' . e($open['phone']) : '' ?><?= $sent !== false ? ' · ' . e(date('d.m.Y H:i', $sent)) : '' ?></p>
            <h3><?= e($open['subject']) ?></h3>
            <p class="message-copy"><?= e($open['body']) ?></p>
            <form method="post" action="<?= root_url('admin/iletisim.php') ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $open['id'] ?>">
                <button class="sale-btn is-danger" type="submit">Bu mesajı sil</button>
            </form>
        </article>
    <?php endif; ?>
    <?php if ($messages === []): ?>
        <p class="settings-lead">Henüz mesaj yok.</p>
    <?php else: ?>
        <ul class="notice-list">
            <?php foreach ($messages as $message): ?>
                <?php $when = strtotime($message['created_at']); ?>
                <li class="<?= $message['read'] ? '' : 'is-new' ?>">
                    <a href="<?= root_url('admin/iletisim.php?mesaj=' . (int) $message['id']) ?>">
                        <strong><?= e($message['subject']) ?></strong>
                        <span><?= e($message['name']) ?> · <?= e($message['email']) ?><?= $when !== false ? ' · ' . e(date('d.m.Y H:i', $when)) : '' ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= root_url('admin/iletisim.php') ?>">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="read">
            <button class="sale-btn is-ghost" type="submit">Tümünü okundu say</button>
        </form>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
