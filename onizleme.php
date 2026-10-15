<?php

declare(strict_types=1);

$query = isset($_SERVER['QUERY_STRING']) && is_string($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: admin/onizleme.php' . $query, true, 302);
header('Cache-Control: no-store');
exit;
