<?php

declare(strict_types=1);

ini_set('display_errors', '0');

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Paytr.php';
require_once __DIR__ . '/app/OrderBook.php';

header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'METHOD';
    exit;
}

try {
    $result = Paytr::verify($_POST);
    if ($result['accepted']) {
        $applied = OrderBook::applyPaytr(
            $result['order_no'],
            $result['paid'],
            $result['paid_cents'],
            $result['note'],
            $result['card']
        );
        if ($applied === 'ok' && $result['paid']) {
            $fresh = OrderBook::find($result['order_no']);
            if (is_array($fresh)) {
                OrderBook::ensureCard($fresh);
            }
        }
        if ($applied === 'missing') {
            http_response_code(404);
            echo 'ORDER NOT FOUND';
            exit;
        }
        if ($applied === 'mismatch') {
            http_response_code(409);
            echo 'AMOUNT MISMATCH';
            exit;
        }
    }
    echo $result['response'];
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'ERROR';
}
exit;
