<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/SiteSettings.php';

$legalDocs = SiteSettings::documents();
$legalRequired = !empty($legalRequired);
$legalChecked = static function (string $name): string {
    return (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string) ($_POST[$name] ?? '') === '1') ? ' checked' : '';
};
?>
<div class="legal-consents">
    <?php foreach ([
        ['accept_kvkk', 'kvkk_label', 'kvkk_url', 'kvkk-onay'],
        ['accept_terms', 'terms_label', 'terms_url', 'sozlesme-onay'],
    ] as [$legalName, $legalLabelKey, $legalUrlKey, $legalId]): ?>
        <?php
        $legalLabel = (string) $legalDocs[$legalLabelKey];
        $legalUrl = (string) $legalDocs[$legalUrlKey];
        ?>
        <label class="legal-check">
            <input id="<?= e($legalId) ?>" type="checkbox" name="<?= e($legalName) ?>" value="1"<?= $legalChecked($legalName) ?><?= $legalRequired ? ' required' : '' ?>>
            <span><?php if ($legalUrl !== ''): ?><a href="<?= url($legalUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($legalLabel) ?></a><?php else: ?><?= e($legalLabel) ?><?php endif; ?>'ni okudum ve kabul ediyorum.</span>
        </label>
    <?php endforeach; ?>
</div>
