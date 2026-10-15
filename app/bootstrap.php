<?php

declare(strict_types=1);

$config = require __DIR__ . '/../config/app.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/Installer.php';

function app_config(): array
{
    global $config;

    return is_array($config) ? $config : [];
}

try {
    $pdo = db_connect($config['db']);
    Installer::ensure($pdo);
    db_set($pdo);
} catch (Throwable $exception) {
    db_set(null);
    $GLOBALS['tuana_db_error'] = $exception->getMessage();
}
