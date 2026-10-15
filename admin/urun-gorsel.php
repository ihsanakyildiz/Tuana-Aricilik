<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';
require dirname(__DIR__) . '/app/ProductEditor.php';

Auth::boot();
Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

$answer = static function (int $status, string $message, array $files = [], bool $withToken = false): void {
    http_response_code($status);
    $payload = [
        'error' => $status === 200 ? 0 : 1,
        'msg' => $message,
        'files' => $files,
        'baseurl' => '',
        'isImages' => array_fill(0, count($files), true),
    ];
    if ($withToken) {
        $payload['csrf'] = Auth::csrfToken();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $answer(405, 'İstek geçersiz.');
    return;
}

$csrf = isset($_POST['csrf']) ? (string) $_POST['csrf'] : (string) ($_SERVER['HTTP_X_CSRF'] ?? '');
if (!Auth::checkCsrf($csrf, false)) {
    $answer(403, 'Oturum doğrulanamadı.');
    return;
}

$file = is_array($_FILES['image'] ?? null) ? $_FILES['image'] : [];
$stored = ProductEditor::storeInlineImage($file);
if (isset($stored['error'])) {
    $answer(422, (string) $stored['error']);
    return;
}

$answer(200, '', [(string) ($stored['url'] ?? '')], true);
