<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/SiteSettings.php';

Auth::boot();
Auth::requireLogin();

$values = SiteSettings::current();
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($_POST === [] && $length > 0) {
        $errors[] = 'Gönderilen dosya çok büyük. Daha küçük bir görsel dene.';
    } elseif (!Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $errors[] = 'Oturum doğrulanamadı. Sayfayı yenileyip yeniden dene.';
    } else {
        $errors = SiteSettings::save($_POST, $_FILES);
        if ($errors === []) {
            Auth::flash('ok', 'Ayarlar kaydedildi.');
            Auth::redirect('admin/ayarlar.php');
        }
        $posted = SiteSettings::current();
        foreach ([
            'site_name', 'meta_title', 'meta_description', 'theme_color', 'site_url',
            'email', 'phone', 'whatsapp', 'address', 'hours', 'contact_title',
            'contact_label', 'contact_url', 'copyright', 'footer_about', 'photo_credit',
            'upload_dir', 'theme_dir', 'maintenance_message',
        ] as $key) {
            if (isset($_POST[$key]) && is_string($_POST[$key])) {
                $posted[$key] = $_POST[$key];
            }
        }
        $posted['maintenance'] = isset($_POST['maintenance']);
        $postedSocials = $_POST['socials'] ?? [];
        if (is_array($postedSocials)) {
            $socials = [];
            foreach (SiteSettings::NETWORKS as $network => $label) {
                $socials[$network] = is_string($postedSocials[$network] ?? null) ? $postedSocials[$network] : '';
            }
            $posted['socials'] = $socials;
        }
        $values = $posted;
    }
}

$pageTitle = 'Ayarlar';
$crumbCurrent = 'Ayarlar';
$activeNav = 'ayarlar';
$logo = (string) ($values['logo'] ?? '');
$favicon = (string) ($values['favicon'] ?? '');
$socials = is_array($values['socials'] ?? null) ? $values['socials'] : [];

require dirname(__DIR__) . '/partials/admin/start.php';
?>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= e((string) $error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form class="settings-form" method="post" action="<?= root_url('admin/ayarlar.php') ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e(Auth::csrfToken()) ?>">

    <div class="settings-grid">
        <section class="settings-card">
            <h2>Site kimliği</h2>
            <p class="settings-lead">Ad, başlık ve açıklama tarayıcı sekmesinde ve arama sonuçlarında görünür.</p>
            <div class="fields-2">
                <label class="field" for="site-name">
                    <span>Site adı <abbr title="zorunlu">*</abbr></span>
                    <input id="site-name" name="site_name" type="text" maxlength="80" value="<?= e((string) $values['site_name']) ?>" required>
                </label>
                <label class="field" for="meta-title">
                    <span>Site başlığı</span>
                    <input id="meta-title" name="meta_title" type="text" maxlength="140" value="<?= e((string) $values['meta_title']) ?>">
                </label>
            </div>
            <label class="field" for="meta-description">
                <span>Site açıklaması</span>
                <textarea id="meta-description" name="meta_description" maxlength="320" rows="3"><?= e((string) $values['meta_description']) ?></textarea>
            </label>
            <div class="fields-2">
                <label class="field" for="theme-color">
                    <span>Tema rengi</span>
                    <input id="theme-color" name="theme_color" type="color" value="<?= e((string) $values['theme_color']) ?>">
                </label>
                <label class="field" for="site-url">
                    <span>Site adresi</span>
                    <input id="site-url" name="site_url" type="url" maxlength="180" placeholder="https://www.tuanaaricilik.com" value="<?= e((string) $values['site_url']) ?>">
                </label>
            </div>
            <p class="field-hint">Site bir alt klasördeyse klasörü de yaz: <code>http://localhost/tuanaAricilik</code>. Boş bırakılırsa adres otomatik kullanılır.</p>
        </section>

        <section class="settings-card">
            <h2>Logo ve favicon</h2>
            <p class="settings-lead">Logo üst menüde ve alt bilgide, favicon tarayıcı sekmesinde görünür.</p>
            <div class="fields-2">
                <div class="media-field">
                    <div class="media-preview">
                        <?php if ($logo !== ''): ?>
                            <img src="<?= root_url($logo) ?>" alt="Site logosu">
                        <?php else: ?>
                            <span>Logo yok</span>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <span>Logo</span>
                        <label class="file-pick">
                            <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
                            <span class="file-btn">Dosya seç</span>
                            <span class="file-name">Dosya seçilmedi</span>
                        </label>
                    </div>
                    <?php if ($logo !== ''): ?>
                        <label class="check" for="remove-logo"><input id="remove-logo" name="remove_logo" type="checkbox"> <span>Logoyu kaldır</span></label>
                    <?php endif; ?>
                </div>
                <div class="media-field">
                    <div class="media-preview">
                        <img src="<?= root_url($favicon !== '' ? $favicon : 'assets/favicon.svg') ?>" alt="Favicon">
                    </div>
                    <div class="field">
                        <span>Favicon</span>
                        <label class="file-pick">
                            <input id="favicon" name="favicon" type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/x-icon,.ico">
                            <span class="file-btn">Dosya seç</span>
                            <span class="file-name">Dosya seçilmedi</span>
                        </label>
                    </div>
                    <?php if ($favicon !== ''): ?>
                        <label class="check" for="remove-favicon"><input id="remove-favicon" name="remove_favicon" type="checkbox"> <span>Faviconu varsayılana döndür</span></label>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="settings-card">
            <h2>İletişim</h2>
            <p class="settings-lead">Bu bilgiler alt bilgideki iletişim sütununda ve üstteki düğmede kullanılır. Sayfa metni, form ve gelen kutusu İçerik, İletişim ekranındadır.</p>
            <div class="fields-2">
                <label class="field" for="email">
                    <span>E-posta</span>
                    <input id="email" name="email" type="email" maxlength="120" value="<?= e((string) $values['email']) ?>">
                </label>
                <label class="field" for="phone">
                    <span>Telefon</span>
                    <input id="phone" name="phone" type="text" maxlength="40" value="<?= e((string) $values['phone']) ?>">
                </label>
                <label class="field" for="whatsapp">
                    <span>WhatsApp</span>
                    <input id="whatsapp" name="whatsapp" type="text" maxlength="40" placeholder="+90 555 123 45 67" value="<?= e((string) $values['whatsapp']) ?>">
                </label>
                <label class="field" for="hours">
                    <span>Çalışma saatleri</span>
                    <input id="hours" name="hours" type="text" maxlength="80" placeholder="Pzt–Cmt 09:00–18:00" value="<?= e((string) $values['hours']) ?>">
                </label>
            </div>
            <label class="field" for="address">
                <span>Adres</span>
                <input id="address" name="address" type="text" maxlength="180" value="<?= e((string) $values['address']) ?>">
            </label>
            <div class="fields-2">
                <label class="field" for="contact-title">
                    <span>İletişim başlığı</span>
                    <input id="contact-title" name="contact_title" type="text" maxlength="40" value="<?= e((string) $values['contact_title']) ?>">
                </label>
                <label class="field" for="contact-label">
                    <span>Üst düğme yazısı</span>
                    <input id="contact-label" name="contact_label" type="text" maxlength="40" value="<?= e((string) $values['contact_label']) ?>">
                </label>
            </div>
            <label class="field" for="contact-url">
                <span>Üst düğme adresi</span>
                <input id="contact-url" name="contact_url" type="text" maxlength="180" value="<?= e((string) $values['contact_url']) ?>">
            </label>
        </section>

        <section class="settings-card">
            <h2>Alt bilgi</h2>
            <p class="settings-lead">Telif satırında <code>{yil}</code> yazarsan yıl her ocakta kendiliğinden değişir. Bağlantı sütunları <a href="<?= root_url('admin/footer.php') ?>">Footer</a> sayfasından düzenlenir.</p>
            <label class="field" for="copyright">
                <span>Telif hakkı yazısı</span>
                <input id="copyright" name="copyright" type="text" maxlength="180" value="<?= e((string) $values['copyright']) ?>">
            </label>
            <label class="field" for="footer-about">
                <span>Kısa tanıtım</span>
                <textarea id="footer-about" name="footer_about" maxlength="600" rows="4"><?= e((string) $values['footer_about']) ?></textarea>
            </label>
            <label class="field" for="photo-credit">
                <span>Fotoğraf notu</span>
                <input id="photo-credit" name="photo_credit" type="text" maxlength="180" value="<?= e((string) $values['photo_credit']) ?>">
            </label>
        </section>

        <section class="settings-card">
            <h2>Sosyal medya</h2>
            <p class="settings-lead">Adresi boş bıraktığın kanal sitede gösterilmez.</p>
            <div class="fields-2">
                <?php foreach (SiteSettings::NETWORKS as $network => $label): ?>
                    <label class="field" for="social-<?= e($network) ?>">
                        <span><?= e($label) ?></span>
                        <input id="social-<?= e($network) ?>" name="socials[<?= e($network) ?>]" type="text" maxlength="180" inputmode="url" placeholder="https://" value="<?= e((string) ($socials[$network] ?? '')) ?>">
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="settings-card">
            <h2>Klasörler ve yayın</h2>
            <p class="settings-lead">Yeni logo ve favicon yüklemeleri ilk klasöre kaydedilir. Tema klasörü sitenin CSS ve JavaScript dosyalarını taşır.</p>
            <div class="fields-2">
                <label class="field" for="upload-dir">
                    <span>Görsel yükleme klasörü</span>
                    <input id="upload-dir" name="upload_dir" type="text" maxlength="80" value="<?= e((string) $values['upload_dir']) ?>" required>
                </label>
                <label class="field" for="theme-dir">
                    <span>Tema klasörü</span>
                    <input id="theme-dir" name="theme_dir" type="text" maxlength="80" value="<?= e((string) $values['theme_dir']) ?>" required>
                </label>
            </div>
            <label class="check" for="maintenance">
                <input id="maintenance" name="maintenance" type="checkbox"<?= !empty($values['maintenance']) ? ' checked' : '' ?>>
                <span>Bakım modu açık olsun</span>
            </label>
            <label class="field" for="maintenance-message">
                <span>Bakım duyurusu</span>
                <input id="maintenance-message" name="maintenance_message" type="text" maxlength="240" value="<?= e((string) $values['maintenance_message']) ?>">
            </label>
            <p class="field-hint">Bakım modu ziyaretçi sayfalarını kapatır. Yönetim paneli açık kalır.</p>
        </section>
    </div>

    <div class="settings-save">
        <button class="login-submit" type="submit">Ayarları kaydet</button>
    </div>
</form>
<?php require dirname(__DIR__) . '/partials/admin/end.php'; ?>
