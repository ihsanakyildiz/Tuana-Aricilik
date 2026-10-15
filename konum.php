<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/LocationCatalog.php';

public_maintenance();
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, max-age=60');

$parentId = filter_input(INPUT_GET, 'ust', FILTER_VALIDATE_INT);
if (!is_int($parentId) || $parentId < 0) {
    http_response_code(400);
    echo '[]';
    exit;
}

try {
    $choices = LocationCatalog::publicChoices($parentId);
} catch (Throwable $exception) {
    http_response_code(503);
    echo '[]';
    exit;
}

echo json_encode($choices, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
