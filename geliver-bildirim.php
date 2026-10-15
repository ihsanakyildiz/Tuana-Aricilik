<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Geliver.php';
require_once __DIR__ . '/app/OrderBook.php';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'METHOD';
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo 'BAD REQUEST';
    exit;
}

try {
    $errors = OrderBook::geliverNotice(Geliver::webhook($payload));
    if ($errors !== []) {
        http_response_code(500);
        echo 'ERROR';
        exit;
    }
    echo 'OK';
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'ERROR';
}
exit;
