<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/Auth.php';

Auth::boot();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Auth::checkCsrf(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
    Auth::flash('err', 'Çıkış doğrulanamadı. Yeniden dene.');
    Auth::redirect(Auth::check() ? 'admin/' : 'admin/giris.php');
}

Auth::logout();
header('Location: ' . root_path() . '/admin/giris.php', true, 303);
exit;
