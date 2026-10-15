<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

public_maintenance();

$settings = settings();
$sections = sections_for('home');
$pageTitle = (string) ($settings['site_name'] ?? 'Tuana Arıcılık');
$metaDescription = (string) ($settings['meta_description'] ?? '');

require __DIR__ . '/partials/header.php';

foreach ($sections as $section) {
    if (!is_array($section) || section_is_placed($section)) {
        continue;
    }
    render_section($section);
}

require __DIR__ . '/partials/footer.php';
