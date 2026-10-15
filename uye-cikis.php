<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/MemberAccount.php';

MemberAccount::boot(true);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && MemberAccount::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    MemberAccount::logout();
    header('Location: ' . root_path() . '/', true, 303);
    exit;
}

header('Location: ' . root_path() . '/hesabim', true, 303);
exit;
