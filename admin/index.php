<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pageTitle = 'Genel bakış';
$crumbCurrent = 'Genel bakış';
$activeNav = 'panel';

require dirname(__DIR__) . '/partials/admin/start.php';
require dirname(__DIR__) . '/partials/admin/dashboard.php';
require dirname(__DIR__) . '/partials/admin/end.php';
