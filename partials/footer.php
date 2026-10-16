<?php

declare(strict_types=1);

$columns = setting('footer_columns', []);
$socials = setting('socials', []);
$intro = setting('intro', []);
if (!is_array($columns)) {
    $columns = [];
}
if (!is_array($socials)) {
    $socials = [];
}
if (!is_array($intro)) {
    $intro = [];
}
?>
</main>
<footer class="site-footer" id="iletisim">
    <div class="container footer-grid">
        <div class="footer-brand">
            <?php $logoClass = 'brand-sm'; require __DIR__ . '/logo.php'; ?>
            <p><?= e((string) setting('footer_about', '')) ?></p>
            <ul class="socials">
                <?php foreach ($socials as $social): ?>
                    <?php
                    if (!is_array($social) || trim((string) ($social['url'] ?? '')) === '') {
                        continue;
                    }
                    $socialUrl = (string) $social['url'];
                    $socialExternal = preg_match('#^https?://#i', $socialUrl) === 1;
                    ?>
                    <li>
                        <a href="<?= url($socialUrl) ?>" aria-label="<?= e((string) ($social['label'] ?? 'Sosyal')) ?>"<?= $socialExternal ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
                            <?= icon((string) ($social['network'] ?? '')) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php foreach ($columns as $column): ?>
            <?php if (!is_array($column)) { continue; } ?>
            <div class="footer-col">
                <h2><?= e((string) ($column['title'] ?? '')) ?></h2>
                <ul>
                    <?php foreach (($column['links'] ?? []) as $link): ?>
                        <?php if (!is_array($link)) { continue; } ?>
                        <li>
                            <a href="<?= url((string) ($link['url'] ?? '#')) ?>">
                                <span aria-hidden="true">›</span>
                                <?= e((string) ($link['label'] ?? '')) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <div class="footer-col">
            <h2><a href="<?= url('/iletisim') ?>"><?= e((string) setting('contact_title', 'İletişim')) ?></a></h2>
            <ul class="contact-list">
                <?php if (trim((string) setting('address', '')) !== ''): ?>
                    <li>
                        <span aria-hidden="true"><?= icon('pin') ?></span>
                        <span><?= e((string) setting('address', '')) ?></span>
                    </li>
                <?php endif; ?>
                <?php if (trim((string) setting('email', '')) !== ''): ?>
                    <li>
                        <span aria-hidden="true"><?= icon('mail') ?></span>
                        <a href="<?= url('mailto:' . (string) setting('email', '')) ?>"><?= e((string) setting('email', '')) ?></a>
                    </li>
                <?php endif; ?>
                <?php if (trim((string) setting('phone', '')) !== ''): ?>
                    <li>
                        <span aria-hidden="true"><?= icon('phone') ?></span>
                        <a href="<?= url('tel:' . preg_replace('/\s+/', '', (string) setting('phone', ''))) ?>"><?= e((string) setting('phone', '')) ?></a>
                    </li>
                <?php endif; ?>
                <?php
                $whatsapp = trim((string) setting('whatsapp', ''));
                $whatsappDigits = preg_replace('/\D+/', '', $whatsapp);
                ?>
                <?php if ($whatsapp !== '' && $whatsappDigits !== ''): ?>
                    <li>
                        <span aria-hidden="true"><?= icon('whatsapp') ?></span>
                        <a href="<?= e('https://wa.me/' . $whatsappDigits) ?>" target="_blank" rel="noopener noreferrer"><?= e($whatsapp) ?></a>
                    </li>
                <?php endif; ?>
                <?php if (trim((string) setting('hours', '')) !== ''): ?>
                    <li>
                        <span aria-hidden="true"><?= icon('clock') ?></span>
                        <span><?= e((string) setting('hours', '')) ?></span>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="container footer-bottom">
        <p><?= e(copyright_text()) ?></p>
        <p class="photo-credit"><?= e((string) setting('photo_credit', '')) ?></p>
    </div>
</footer>

<?php if ($intro !== []): ?>
<dialog class="intro-dialog" id="intro-dialog">
    <form method="dialog">
        <button class="dialog-close" type="submit" aria-label="Kapat">×</button>
    </form>
    <div class="intro-layout">
        <?= media_tag((string) ($intro['image'] ?? ''), (string) ($intro['image_alt'] ?? ''), 'intro-photo', true) ?>
        <div>
            <p class="eyebrow"><?= e((string) setting('site_name', 'Tuana Arıcılık')) ?></p>
            <h2><?= e((string) ($intro['title'] ?? '')) ?></h2>
            <p><?= e((string) ($intro['text'] ?? '')) ?></p>
        </div>
    </div>
</dialog>
<?php endif; ?>

<?php if (!empty($GLOBALS['tuana_db_error']) && (app_config()['debug'] ?? false)): ?>
<!-- Veritabanı yedeği kullanıldı: <?= e((string) $GLOBALS['tuana_db_error']) ?> -->
<?php endif; ?>
<script src="<?= theme_file('js/main.js') ?>" defer></script>
<?php if (isset($pageScript) && is_string($pageScript) && $pageScript !== ''): ?>
<script src="<?= theme_file($pageScript) ?>" defer></script>
<?php endif; ?>
<script>
    window.addEventListener("load", function () {
        if (!document.documentElement.hasAttribute("data-motion-ready")) {
            document.documentElement.classList.remove("motion-ok");
        }
    });
</script>
</body>
</html>
