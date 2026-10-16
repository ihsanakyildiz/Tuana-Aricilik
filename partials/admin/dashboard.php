<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/OrderBook.php';
require_once dirname(__DIR__, 2) . '/app/LocationCatalog.php';
require_once dirname(__DIR__, 2) . '/app/MenuEditor.php';

$months = [
    1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis', 5 => 'May', 6 => 'Haz',
    7 => 'Tem', 8 => 'Ağu', 9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara',
];
$dayNames = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];
$todayLabel = date('j') . ' ' . $months[(int) date('n')] . ' ' . date('Y');
$hour = (int) date('G');
$greet = $hour < 6 ? 'İyi geceler,' : ($hour < 12 ? 'Günaydın,' : ($hour < 18 ? 'İyi günler,' : 'İyi akşamlar,'));
$greetName = (isset($adminName) && is_string($adminName) && $adminName !== '') ? $adminName : 'Yönetici';

$countLabel = static function (int $value): string {
    return number_format($value, 0, ',', '.');
};
$money = static function (float $amount): string {
    $label = ProductEditor::moneyLabel(number_format(max(0, $amount), 2, '.', ''));

    return $label !== '' ? $label : '0,00 TL';
};
$compact = static function (float $amount): string {
    return number_format(max(0, $amount), 0, ',', '.');
};
$growthText = static function (float $current, float $previous): string {
    if ($previous <= 0.0) {
        return $current > 0.0 ? 'Yeni' : '0%';
    }
    $pct = (($current - $previous) / $previous) * 100;
    $sign = $pct > 0 ? '+' : '';

    return $sign . number_format($pct, 1, ',', '.') . '%';
};
$scale = static function (array $values): array {
    $max = 0.0;
    foreach ($values as $value) {
        $max = max($max, (float) $value);
    }
    $scaled = [];
    foreach ($values as $value) {
        $scaled[] = $max > 0.0 ? (int) round(((float) $value / $max) * 100) : 0;
    }

    return $scaled;
};
$axisLabels = static function (float $max) use ($compact): array {
    if ($max <= 0.0) {
        return ['0', '0', '0', '0'];
    }

    return [
        $compact($max),
        $compact($max * 0.75),
        $compact($max * 0.5),
        $compact($max * 0.25),
    ];
};
$shortDate = static function (string $ymd) use ($months): string {
    $stamp = strtotime($ymd);
    if ($stamp === false) {
        return $ymd;
    }

    return date('j', $stamp) . ' ' . ($months[(int) date('n', $stamp)] ?? '');
};
$sumRows = static function (array $rows): float {
    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float) ($row['revenue'] ?? 0);
    }

    return $total;
};
$countRows = static function (array $rows): int {
    $total = 0;
    foreach ($rows as $row) {
        $total += (int) ($row['orders'] ?? 0);
    }

    return $total;
};
$statusClass = static function (string $status): string {
    return match ($status) {
        'odendi', 'teslim_edildi' => 'is-yayinda',
        'kargoda' => 'is-ship',
        'hazirlaniyor' => 'is-prep',
        'iade', 'iptal' => 'is-tukendi',
        default => 'is-taslak',
    };
};
$statusColors = [
    'odeme_bekliyor' => '#c4841d',
    'odendi' => '#1c9d57',
    'teslimde_odenecek' => '#d89a1c',
    'hazirlaniyor' => '#6b46d4',
    'kargoda' => '#2f6fed',
    'teslim_edildi' => '#0f8f86',
    'iade' => '#9a3e28',
    'iptal' => '#e23b57',
];
$paymentColors = [
    'kart' => '#6b46d4',
    'havale' => '#c4841d',
    'elden' => '#1c9d57',
];

$chartLeft = 54.0;
$chartWidth = 562.0;
$chartTop = 16.0;
$chartPlot = 140.0;
$chartRight = $chartLeft + $chartWidth;
$smoothPath = static function (array $points) use ($chartTop, $chartPlot): string {
    $count = count($points);
    if ($count === 0) {
        return '';
    }
    $minY = $chartTop;
    $maxY = $chartTop + $chartPlot;
    $path = sprintf('M %.1f %.1f', $points[0][0], $points[0][1]);
    for ($index = 0; $index < $count - 1; $index++) {
        $p0 = $points[$index === 0 ? 0 : $index - 1];
        $p1 = $points[$index];
        $p2 = $points[$index + 1];
        $p3 = $points[$index + 2 < $count ? $index + 2 : $index + 1];
        $c1x = $p1[0] + ($p2[0] - $p0[0]) / 8;
        $c1y = min($maxY, max($minY, $p1[1] + ($p2[1] - $p0[1]) / 8));
        $c2x = $p2[0] - ($p3[0] - $p1[0]) / 8;
        $c2y = min($maxY, max($minY, $p2[1] - ($p3[1] - $p1[1]) / 8));
        $path .= sprintf(' C %.1f %.1f, %.1f %.1f, %.1f %.1f', $c1x, $c1y, $c2x, $c2y, $p2[0], $p2[1]);
    }

    return $path;
};
$chartGeometry = static function (array $values) use ($smoothPath, $chartLeft, $chartWidth, $chartTop, $chartPlot): array {
    $count = count($values);
    if ($count === 0) {
        return ['line' => '', 'fill' => '', 'points' => [], 'hot' => []];
    }
    $step = $count > 1 ? $chartWidth / ($count - 1) : $chartWidth;
    $points = [];
    $hot = [];
    foreach ($values as $index => $value) {
        $points[] = [
            $chartLeft + ($index * $step),
            $chartTop + (1 - ((float) $value / 100)) * $chartPlot,
        ];
        if ((float) $value > 0) {
            $hot[] = $index;
        }
    }
    $line = $smoothPath($points);
    $base = $chartTop + $chartPlot;
    $fill = $line . sprintf(' L %.1f %.1f L %.1f %.1f Z', $points[$count - 1][0], $base, $points[0][0], $base);

    return ['line' => $line, 'fill' => $fill, 'points' => $points, 'hot' => $hot];
};

$blankDay = ['orders' => 0, 'revenue' => 0.0];
$daily = [];
for ($i = 13; $i >= 0; $i--) {
    $daily[date('Y-m-d', strtotime('-' . $i . ' days'))] = $blankDay;
}
$monthMap = [];
$mapStart = strtotime((date('Y') - 1) . '-01-01');
$mapEnd = strtotime(date('Y') . '-12-01');
if ($mapStart !== false && $mapEnd !== false) {
    for ($stamp = $mapStart; $stamp <= $mapEnd; $stamp = strtotime('+1 month', $stamp)) {
        if ($stamp === false) {
            break;
        }
        $monthMap[date('Y-m-01', $stamp)] = $blankDay;
    }
}

$products = [];
$categories = [];
$recent = [];
$statusCounts = [];
$statusSums = [];
$payCounts = [];
$paySums = [];
$orderTotal = 0;
$liveOrders = 0;
$revenueAll = 0.0;
$todayRevenue = 0.0;
$waiting = 0;
$members = 0;
$buyers = 0;
$guestBuyers = 0;
$published = 0;
$draft = 0;
$soldOut = 0;
$sectionCount = 0;
$sectionPages = 0;
$blockCounts = [];
$menuCount = 0;
$menuItems = 0;
$headerMenu = '';
$cargoCount = 0;
$cargoLive = 0;
$bankCount = 0;
$enabledPayments = [];
$locations = ['il' => 0, 'ilce' => 0, 'mahalle' => 0, 'koy' => 0];

try {
    $products = ProductEditor::all();
    $categories = ProductCategoryEditor::all();
    $pdo = db_pdo();
    if ($pdo instanceof PDO) {
        $statusStmt = $pdo->query('SELECT status, COUNT(*) AS orders, COALESCE(SUM(GREATEST(total - refunded, 0)), 0) AS revenue FROM orders GROUP BY status');
        foreach ($statusStmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = (string) $row['status'];
            $statusCounts[$status] = (int) $row['orders'];
            $statusSums[$status] = (float) $row['revenue'];
            $orderTotal += (int) $row['orders'];
            if (!in_array($status, ['iptal', 'iade'], true)) {
                $liveOrders += (int) $row['orders'];
                $revenueAll += (float) $row['revenue'];
            }
        }
        $waiting = $statusCounts['odeme_bekliyor'] ?? 0;

        $payStmt = $pdo->query("SELECT payment_method, COUNT(*) AS orders, COALESCE(SUM(GREATEST(total - refunded, 0)), 0) AS revenue FROM orders WHERE status NOT IN ('iptal', 'iade') GROUP BY payment_method");
        foreach ($payStmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $payCounts[(string) $row['payment_method']] = (int) $row['orders'];
            $paySums[(string) $row['payment_method']] = (float) $row['revenue'];
        }

        $from = (date('Y') - 1) . '-01-01 00:00:00';
        $dayStmt = $pdo->prepare("SELECT DATE(created_at) AS day, COUNT(*) AS orders, COALESCE(SUM(GREATEST(total - refunded, 0)), 0) AS revenue FROM orders WHERE status NOT IN ('iptal', 'iade') AND created_at >= ? GROUP BY DATE(created_at)");
        $dayStmt->execute([$from]);
        foreach ($dayStmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) $row['day'];
            if (isset($daily[$key])) {
                $daily[$key] = ['orders' => (int) $row['orders'], 'revenue' => (float) $row['revenue']];
            }
        }
        $monthStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS month, COUNT(*) AS orders, COALESCE(SUM(GREATEST(total - refunded, 0)), 0) AS revenue FROM orders WHERE status NOT IN ('iptal', 'iade') AND created_at >= ? GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')");
        $monthStmt->execute([$from]);
        foreach ($monthStmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) $row['month'];
            if (isset($monthMap[$key])) {
                $monthMap[$key] = ['orders' => (int) $row['orders'], 'revenue' => (float) $row['revenue']];
            }
        }

        $todayKey = date('Y-m-d');
        $todayRevenue = (float) ($daily[$todayKey]['revenue'] ?? 0);

        $saleStmt = $pdo->query("SELECT product_key, COUNT(*) AS orders, COALESCE(SUM(qty), 0) AS qty, COALESCE(SUM(GREATEST(total - refunded, 0)), 0) AS revenue FROM orders WHERE status NOT IN ('iptal', 'iade') GROUP BY product_key");
        $sales = [];
        foreach ($saleStmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sales[(string) $row['product_key']] = [
                'orders' => (int) $row['orders'],
                'qty' => (int) $row['qty'],
                'amount' => (float) $row['revenue'],
            ];
        }
        foreach ($products as $index => $product) {
            $sale = $sales[(string) $product['key']] ?? ['orders' => 0, 'qty' => 0, 'amount' => 0.0];
            $products[$index]['orders'] = $sale['orders'];
            $products[$index]['sold_qty'] = $sale['qty'];
            $products[$index]['sold_amount'] = $sale['amount'];
        }
        usort($products, static function (array $a, array $b): int {
            $byOrders = ((int) $b['orders']) <=> ((int) $a['orders']);
            if ($byOrders !== 0) {
                return $byOrders;
            }

            return strcmp((string) $a['title'], (string) $b['title']);
        });

        $recentStmt = $pdo->query('SELECT order_no, product_title, product_image, qty, total, status, payment_method, created_at FROM orders ORDER BY id DESC LIMIT 5');
        foreach ($recentStmt->fetchAll() as $row) {
            if (is_array($row)) {
                $recent[] = $row;
            }
        }

        $members = (int) $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
        $buyers = (int) $pdo->query("SELECT COUNT(DISTINCT member_id) FROM orders WHERE member_id > 0 AND status NOT IN ('iptal', 'iade')")->fetchColumn();
        $guestEmails = [];
        $guestStmt = $pdo->query("SELECT order_no, shipping_json FROM orders WHERE member_id = 0 AND status NOT IN ('iptal', 'iade')");
        foreach ($guestStmt->fetchAll() as $guestRow) {
            if (!is_array($guestRow)) {
                continue;
            }
            $guestShipping = decode_value((string) ($guestRow['shipping_json'] ?? ''));
            $guestEmail = is_array($guestShipping) ? strtolower(trim((string) ($guestShipping['email'] ?? ''))) : '';
            $guestKey = $guestEmail !== '' ? $guestEmail : 'order:' . (string) $guestRow['order_no'];
            $guestEmails[$guestKey] = true;
        }
        $guestBuyers = count($guestEmails);
        $sectionRow = $pdo->query('SELECT COUNT(*) AS sections, COUNT(DISTINCT page_slug) AS pages FROM sections')->fetch();
        if (is_array($sectionRow)) {
            $sectionCount = (int) $sectionRow['sections'];
            $sectionPages = (int) $sectionRow['pages'];
        }
        $blockStmt = $pdo->query('SELECT block_type, COUNT(*) AS total, COALESCE(SUM(is_active), 0) AS live FROM blocks GROUP BY block_type');
        foreach ($blockStmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $blockCounts[(string) $row['block_type']] = [
                'total' => (int) $row['total'],
                'live' => (int) $row['live'],
            ];
        }
    }

    $locations = LocationCatalog::totals();
    $menus = MenuEditor::menus();
    $menuCount = count($menus);
    foreach ($menus as $menu) {
        $menuItems += (int) $menu['count'];
        if (!empty($menu['header'])) {
            $headerMenu = (string) $menu['name'];
        }
    }
    $cargoRows = OrderBook::cargoMethods(false);
    $cargoCount = count($cargoRows);
    foreach ($cargoRows as $method) {
        if (!empty($method['active'])) {
            $cargoLive++;
        }
    }
    $bankCount = count(OrderBook::banks());
    $enabledPayments = OrderBook::enabledPayments();
} catch (Throwable) {
    // Eksik bir kaynak paneli durdurmasın; sayfa sıfırlarla açılır.
}
foreach ($products as $index => $product) {
    if (!is_array($product)) {
        continue;
    }
    $products[$index]['orders'] = (int) ($product['orders'] ?? 0);
    $products[$index]['sold_qty'] = (int) ($product['sold_qty'] ?? 0);
    $products[$index]['sold_amount'] = (float) ($product['sold_amount'] ?? 0);
    if (!empty($product['active'])) {
        if (!empty($product['stock_track']) && (int) $product['stock_qty'] <= 0) {
            $soldOut++;
        } else {
            $published++;
        }
    } else {
        $draft++;
    }
}

$rolling = [];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m-01', strtotime(date('Y-m-01') . ' -' . $i . ' months'));
    $rolling[$key] = $monthMap[$key] ?? $blankDay;
}
$yearRows = [];
for ($month = 1; $month <= 12; $month++) {
    $key = date('Y') . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '-01';
    $yearRows[$key] = $monthMap[$key] ?? $blankDay;
}
$lastYearRows = [];
for ($month = 1; $month <= 12; $month++) {
    $key = (date('Y') - 1) . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '-01';
    $lastYearRows[$key] = $monthMap[$key] ?? $blankDay;
}
$thisMonthKey = date('Y-m-01');
$prevMonthKey = date('Y-m-01', strtotime('first day of last month'));
$olderWeek = array_slice($daily, 0, 7, true);
$newerWeek = array_slice($daily, 7, 7, true);
$dailySum = $sumRows($daily);
$rollingSum = $sumRows($rolling);
$yearSum = $sumRows($yearRows);
$marksOf = static function (array $rows) use ($shortDate): array {
    $keys = array_keys($rows);
    $last = count($keys) - 1;
    $mid = (int) floor($last / 2);

    return [
        $shortDate((string) ($keys[0] ?? '')),
        $shortDate((string) ($keys[$mid] ?? '')),
        $shortDate((string) ($keys[$last] ?? '')),
    ];
};
$pack = static function (array $rows) use ($scale, $axisLabels, $sumRows): array {
    $values = [];
    $max = 0.0;
    foreach ($rows as $row) {
        $amount = (float) ($row['revenue'] ?? 0);
        $values[] = $amount;
        $max = max($max, $amount);
    }

    return [
        'scaled' => $scale($values),
        'axis' => $axisLabels($max),
    ];
};
$dailyPack = $pack($daily);
$rollingPack = $pack($rolling);
$yearPack = $pack($yearRows);
$periods = [
    'today' => [
        'tab' => '14 gün',
        'count' => $countLabel($countRows($daily)) . ' sipariş',
        'earning' => $todayRevenue > 0 ? 'Bugün ' . $money($todayRevenue) : 'Bugün satış yok',
        'revenue' => $money($dailySum),
        'orders' => $countLabel($countRows($daily)),
        'growth' => $growthText($sumRows($newerWeek), $sumRows($olderWeek)),
        'hint' => 'Son 7 gün, önceki 7 güne göre',
        'scaled' => $dailyPack['scaled'],
        'axis' => $dailyPack['axis'],
        'marks' => $marksOf($daily),
    ],
    'month' => [
        'tab' => '12 ay',
        'count' => $countLabel($countRows($rolling)) . ' sipariş',
        'earning' => 'Bu ay ' . $money((float) ($monthMap[$thisMonthKey]['revenue'] ?? 0)),
        'revenue' => $money($rollingSum),
        'orders' => $countLabel($countRows($rolling)),
        'growth' => $growthText(
            (float) ($monthMap[$thisMonthKey]['revenue'] ?? 0),
            (float) ($monthMap[$prevMonthKey]['revenue'] ?? 0)
        ),
        'hint' => 'Bu ay, geçen aya göre',
        'scaled' => $rollingPack['scaled'],
        'axis' => $rollingPack['axis'],
        'marks' => $marksOf($rolling),
    ],
    'year' => [
        'tab' => 'Bu yıl',
        'count' => $countLabel($countRows($yearRows)) . ' sipariş',
        'earning' => date('Y') . ' toplamı ' . $money($yearSum),
        'revenue' => $money($yearSum),
        'orders' => $countLabel($countRows($yearRows)),
        'growth' => $growthText($yearSum, $sumRows($lastYearRows)),
        'hint' => 'Bu yıl, geçen yıla göre',
        'scaled' => $yearPack['scaled'],
        'axis' => $yearPack['axis'],
        'marks' => $marksOf($yearRows),
    ],
];
$reportJson = [];
foreach ($periods as $key => $period) {
    $reportJson[$key] = [
        'count' => $period['count'],
        'earning' => $period['earning'],
        'revenue' => $period['revenue'],
        'orders' => $period['orders'],
        'growth' => $period['growth'],
        'hint' => $period['hint'],
    ];
}

$week = [];
foreach ($newerWeek as $key => $row) {
    $stamp = strtotime((string) $key);
    $week[] = [
        'label' => $stamp !== false ? ($dayNames[(int) date('N', $stamp)] ?? '') : '',
        'revenue' => (float) $row['revenue'],
    ];
}
$weekMax = 0.0;
$weekSum = 0.0;
$weekPeak = '';
foreach ($week as $day) {
    $weekSum += $day['revenue'];
    if ($day['revenue'] >= $weekMax) {
        $weekMax = $day['revenue'];
        $weekPeak = $day['revenue'] > 0 ? $day['label'] . ' · ' . $money($day['revenue']) : '';
    }
}
$weekNote = $weekPeak !== '' ? $weekPeak : 'Bu hafta satış yok';

$donutStops = [];
$donutCursor = 0.0;
$statusLegend = [];
foreach (OrderBook::statuses() as $status => $label) {
    $count = $statusCounts[$status] ?? 0;
    if ($count < 1 || $orderTotal < 1) {
        continue;
    }
    $end = $donutCursor + ($count / $orderTotal) * 360;
    $color = $statusColors[$status] ?? '#6b46d4';
    $donutStops[] = $color . ' ' . number_format($donutCursor, 2, '.', '') . 'deg ' . number_format($end, 2, '.', '') . 'deg';
    $donutCursor = $end;
    $statusLegend[] = [
        'label' => $label,
        'count' => $count,
        'revenue' => (float) ($statusSums[$status] ?? 0),
        'color' => $color,
    ];
}
$donutStyle = $donutStops === []
    ? 'background: conic-gradient(#eceaf2 0deg 360deg)'
    : 'background: conic-gradient(' . implode(', ', $donutStops) . ')';

$paymentRows = [];
$paymentMax = 0.0;
foreach (OrderBook::payments() as $key => $label) {
    $amount = (float) ($paySums[$key] ?? 0);
    $paymentMax = max($paymentMax, $amount);
    $paymentRows[] = [
        'label' => $label,
        'orders' => $payCounts[$key] ?? 0,
        'revenue' => $amount,
        'on' => isset($enabledPayments[$key]),
        'color' => $paymentColors[$key] ?? '#6b46d4',
    ];
}
$categoryMax = 0;
foreach ($categories as $category) {
    $categoryMax = max($categoryMax, (int) $category['products']);
}
$categoryColors = ['#6b46d4', '#c4841d', '#1c9d57', '#2f6fed', '#e23b57', '#0f8f86'];

$blockNote = static function (string $type) use ($blockCounts, $countLabel): string {
    $row = $blockCounts[$type] ?? ['total' => 0, 'live' => 0];
    if ($row['total'] < 1) {
        return 'Kayıt yok';
    }

    return $countLabel($row['live']) . ' yayında';
};
$blockTotal = static function (string $type) use ($blockCounts): int {
    return (int) ($blockCounts[$type]['total'] ?? 0);
};
$productNoteParts = [$countLabel($published) . ' yayında'];
if ($draft > 0) {
    $productNoteParts[] = $countLabel($draft) . ' taslak';
}
if ($soldOut > 0) {
    $productNoteParts[] = $countLabel($soldOut) . ' tükendi';
}
$orderNote = $waiting > 0 ? $countLabel($waiting) . ' ödeme bekliyor' : 'İptal hariç';
$revenueNote = ($todayRevenue > 0 && abs($todayRevenue - $revenueAll) < 0.001) ? 'Tamamı bugün' : 'Bugün ' . $money($todayRevenue);
$memberBits = [];
if ($buyers > 0) {
    $memberBits[] = $countLabel($buyers) . ' üye sipariş verdi';
}
if ($guestBuyers > 0) {
    $memberBits[] = $countLabel($guestBuyers) . ' üye olmadan';
}
$memberNote = $memberBits !== [] ? implode(' · ', $memberBits) : 'Kayıtlı üye';
$enabledNote = $enabledPayments === [] ? 'Yöntem kapalı' : implode(', ', array_values($enabledPayments));
$siteName = (string) setting('site_name', 'Tuana Arıcılık');

$tiles = [
    ['href' => root_url('admin/urunler.php'), 'label' => 'Ürünler', 'value' => $countLabel(count($products)), 'note' => implode(' · ', $productNoteParts), 'text' => false],
    ['href' => root_url('admin/urun-kategorileri.php'), 'label' => 'Ürün kategorileri', 'value' => $countLabel(count($categories)), 'note' => $countLabel(array_sum(array_map(static fn (array $row): int => (int) $row['products'], $categories))) . ' bağlı ürün', 'text' => false],
    ['href' => root_url('admin/siparisler.php'), 'label' => 'Siparişler', 'value' => $countLabel($liveOrders), 'note' => $orderNote, 'text' => false],
    ['href' => root_url('admin/musteriler.php'), 'label' => 'Müşteriler', 'value' => $countLabel($members), 'note' => $memberNote, 'text' => false],
    ['href' => root_url('admin/kargo.php'), 'label' => 'Kargo', 'value' => $countLabel($cargoCount), 'note' => $cargoCount > 0 ? $countLabel($cargoLive) . ' yayında' : 'Henüz eklenmedi', 'text' => false],
    ['href' => root_url('admin/odeme.php'), 'label' => 'Ödeme', 'value' => $countLabel(count($enabledPayments)) . '/3', 'note' => $bankCount > 0 ? $countLabel($bankCount) . ' IBAN' : 'IBAN yok', 'text' => true],
    ['href' => root_url('admin/konumlar.php'), 'label' => 'Adres listesi', 'value' => $countLabel((int) $locations['il']), 'note' => $countLabel((int) $locations['ilce']) . ' ilçe · ' . $countLabel((int) $locations['mahalle']) . ' mahalle · ' . $countLabel((int) $locations['koy']) . ' köy', 'text' => false],
    ['href' => root_url('admin/sayfalar.php'), 'label' => 'Sayfalar', 'value' => $countLabel($blockTotal('page')), 'note' => $blockNote('page'), 'text' => false],
    ['href' => root_url('admin/yazilar.php'), 'label' => 'Yazılar', 'value' => $countLabel($blockTotal('post')), 'note' => $blockNote('post'), 'text' => false],
    ['href' => root_url('admin/kategoriler.php'), 'label' => 'Yazı kategorileri', 'value' => $countLabel($blockTotal('category')), 'note' => $blockNote('category'), 'text' => false],
    ['href' => root_url('admin/bolumler.php?kartlar=1'), 'label' => 'Kartlar', 'value' => $countLabel($blockTotal('card')), 'note' => $blockNote('card'), 'text' => false],
    ['href' => root_url('admin/menuler.php'), 'label' => 'Menüler', 'value' => $countLabel($menuCount), 'note' => $headerMenu !== '' ? $headerMenu : $countLabel($menuItems) . ' bağlantı', 'text' => false],
    ['href' => root_url('admin/bolumler.php'), 'label' => 'Bölümler', 'value' => $countLabel($sectionCount), 'note' => $countLabel($sectionPages) . ' sayfada', 'text' => false],
    ['href' => root_url('admin/hero.php'), 'label' => 'Karşılama', 'value' => 'Kapak', 'note' => 'Ana sayfa görseli', 'text' => true],
    ['href' => root_url('admin/ayarlar.php'), 'label' => 'Genel ayarlar', 'value' => $siteName, 'note' => 'Site adı', 'text' => true],
];

$firstPeriod = $periods['today'];
$chartY = static function (float $percent) use ($chartTop, $chartPlot): float {
    return $chartTop + (1 - ($percent / 100)) * $chartPlot;
};
?>
<div class="dashboard is-live">
    <section class="kpi-grid">
        <article class="card hello-card">
            <div class="hello-copy">
                <p class="eyebrow" data-greet><?= e($greet) ?></p>
                <h2><?= e($greetName) ?></h2>
            </div>
            <div class="hello-art" aria-hidden="true">
                <svg viewBox="0 0 150 120">
                    <ellipse cx="92" cy="104" rx="42" ry="8" fill="#e7e2f4"/>
                    <circle cx="104" cy="58" r="34" fill="#efeafb"/>
                    <path d="M58 104c1-22 10-34 24-34 8 0 14 3 18 8" fill="#6b46d4"/>
                    <path d="M78 78c12 2 22 10 26 20" fill="none" stroke="#6b46d4" stroke-width="8" stroke-linecap="round"/>
                    <circle cx="74" cy="48" r="13" fill="#f3d2c2"/>
                    <path d="M62 46c2-12 10-18 20-16 6 1 10 6 11 12" fill="#2c2444"/>
                    <rect x="96" y="46" width="26" height="34" rx="6" fill="#f0b429"/>
                    <rect x="100" y="40" width="18" height="8" rx="2" fill="#d89a1c"/>
                    <rect x="100" y="56" width="18" height="12" rx="2" fill="#fff6df"/>
                    <circle cx="118" cy="34" r="7" fill="#fff"/>
                    <path d="M118 29.5c2.2 2.6 3.4 4.4 3.4 6.2a3.4 3.4 0 1 1-6.8 0c0-1.8 1.2-3.6 3.4-6.2z" fill="#e2a93b"/>
                </svg>
            </div>
            <div class="hello-meta">
                <span><?= admin_icon('calendar') ?><?= e($todayLabel) ?></span>
                <span class="hello-split"></span>
                <span><?= admin_icon('clock') ?><time id="admin-clock">00:00:00</time></span>
            </div>
        </article>

        <article class="card stat-card">
            <div class="stat-top">
                <p class="eyebrow">Sipariş</p>
                <span class="bubble"><?= admin_icon('cart') ?></span>
            </div>
            <p class="stat-value"><?= e($countLabel($liveOrders)) ?></p>
            <p class="dash-note"><?= e($orderNote) ?></p>
        </article>
        <article class="card stat-card">
            <div class="stat-top">
                <p class="eyebrow">Ciro</p>
                <span class="bubble"><?= admin_icon('card') ?></span>
            </div>
            <p class="stat-value is-money"><?= e($money($revenueAll)) ?></p>
            <p class="dash-note"><?= e($revenueNote) ?></p>
        </article>
        <article class="card stat-card">
            <div class="stat-top">
                <p class="eyebrow">Müşteri</p>
                <span class="bubble"><?= admin_icon('users') ?></span>
            </div>
            <p class="stat-value"><?= e($countLabel($members)) ?></p>
            <p class="dash-note"><?= e($memberNote) ?></p>
        </article>
        <article class="card stat-card">
            <div class="stat-top">
                <p class="eyebrow">Ürün</p>
                <span class="bubble"><?= admin_icon('bag') ?></span>
            </div>
            <p class="stat-value"><?= e($countLabel(count($products))) ?></p>
            <p class="dash-note"><?= e(implode(' · ', $productNoteParts)) ?></p>
        </article>
    </section>

    <section class="dash-charts">
        <article class="card report-card">
            <div class="card-head">
                <h2>Satış <span class="count" data-report-count>(<?= e($firstPeriod['count']) ?>)</span></h2>
                <div class="tabs" role="tablist" aria-label="Satış aralığı">
                    <?php foreach ($periods as $key => $period): ?>
                        <button
                            class="tab<?= $key === 'today' ? ' is-on' : '' ?>"
                            type="button"
                            role="tab"
                            data-period="<?= e($key) ?>"
                            aria-selected="<?= $key === 'today' ? 'true' : 'false' ?>"
                        ><?= e($period['tab']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="report-metrics">
                <div>
                    <span class="metric-ico is-green"><?= admin_icon('card') ?></span>
                    <div>
                        <small>Ciro</small>
                        <strong data-report-revenue><?= e($firstPeriod['revenue']) ?></strong>
                    </div>
                </div>
                <div>
                    <span class="metric-ico is-purple"><?= admin_icon('cart') ?></span>
                    <div>
                        <small>Sipariş</small>
                        <strong data-report-orders><?= e($firstPeriod['orders']) ?></strong>
                    </div>
                </div>
                <div>
                    <span class="metric-ico is-purple"><?= admin_icon('trend') ?></span>
                    <div>
                        <small data-report-hint><?= e($firstPeriod['hint']) ?></small>
                        <strong data-report-growth><?= e($firstPeriod['growth']) ?></strong>
                    </div>
                </div>
            </div>
            <div class="chart-wrap">
                <p class="earning" data-report-earning><?= e($firstPeriod['earning']) ?></p>
                <svg class="area-chart" viewBox="0 0 640 204" role="img" aria-label="İptal edilmeyen siparişlerin tutar eğrisi">
                    <defs>
                        <linearGradient id="salesFill" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#6b46d4" stop-opacity="0.34"/>
                            <stop offset="100%" stop-color="#6b46d4" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <?php foreach ([100, 75, 50, 25] as $percent): ?>
                        <?php $y = $chartY((float) $percent); ?>
                        <line x1="<?= e((string) $chartLeft) ?>" y1="<?= e((string) $y) ?>" x2="<?= e((string) $chartRight) ?>" y2="<?= e((string) $y) ?>" class="grid-line"/>
                    <?php endforeach; ?>
                    <?php foreach ($periods as $key => $period): ?>
                        <?php $geometry = $chartGeometry($period['scaled']); ?>
                        <g class="chart-set<?= $key === 'today' ? ' is-on' : '' ?>" data-chart="<?= e($key) ?>">
                            <?php foreach ($period['axis'] as $index => $label): ?>
                                <?php $y = $chartY((float) ([100, 75, 50, 25][$index] ?? 0)); ?>
                                <text x="0" y="<?= e((string) ($y + 4)) ?>" class="grid-label"><?= e((string) $label) ?></text>
                            <?php endforeach; ?>
                            <?php if ($geometry['fill'] !== ''): ?>
                                <path d="<?= e($geometry['fill']) ?>" class="area-fill"/>
                                <path d="<?= e($geometry['line']) ?>" class="area-line"/>
                            <?php endif; ?>
                            <?php foreach ($geometry['hot'] as $hot): ?>
                                <?php $point = $geometry['points'][$hot] ?? null; ?>
                                <?php if (is_array($point)): ?>
                                    <circle cx="<?= e((string) $point[0]) ?>" cy="<?= e((string) $point[1]) ?>" r="4" class="area-dot"/>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php foreach ($period['marks'] as $index => $mark): ?>
                                <?php
                                $x = $index === 0 ? $chartLeft : ($index === 1 ? $chartLeft + ($chartWidth / 2) : $chartRight);
                                $anchor = $index === 0 ? 'start' : ($index === 1 ? 'middle' : 'end');
                                ?>
                                <text x="<?= e((string) $x) ?>" y="198" text-anchor="<?= e($anchor) ?>" class="grid-label"><?= e((string) $mark) ?></text>
                            <?php endforeach; ?>
                        </g>
                    <?php endforeach; ?>
                </svg>
                <p class="chart-note">Eğri, iptal edilmeyen siparişlerin tutarını gösterir. Dikey eksen seçili aralığın en yüksek gününe göredir.</p>
            </div>
        </article>

        <article class="card donut-card">
            <div class="card-head">
                <h2>Sipariş durumu</h2>
                <a class="ghost-btn" href="<?= root_url('admin/siparisler.php') ?>">Liste</a>
            </div>
            <div class="donut" style="<?= e($donutStyle) ?>" role="img" aria-label="<?= e($orderTotal > 0 ? $countLabel($orderTotal) . ' sipariş' : 'Henüz sipariş yok') ?>">
                <div class="donut-hole">
                    <span>Toplam</span>
                    <strong><?= e($countLabel($orderTotal)) ?></strong>
                </div>
            </div>
            <?php if ($statusLegend === []): ?>
                <p class="dash-empty">Henüz sipariş yok.</p>
            <?php else: ?>
                <ul class="dash-legend">
                    <?php foreach ($statusLegend as $slice): ?>
                        <li>
                            <i style="background: <?= e($slice['color']) ?>"></i>
                            <span><?= e($slice['label']) ?></span>
                            <b><?= e($countLabel($slice['count'])) ?> · <?= e($money($slice['revenue'])) ?></b>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <article class="card week-card">
            <div class="card-head">
                <h2>Son 7 gün</h2>
            </div>
            <p class="dash-note"><?= e($weekNote) ?></p>
            <svg class="dash-week" viewBox="0 0 340 168" role="img" aria-label="Son 7 günün satış tutarı. Toplam <?= e($money($weekSum)) ?>">
                <?php foreach ($week as $index => $day): ?>
                    <?php
                    $slot = 340 / 7;
                    $barWidth = 22;
                    $height = $day['revenue'] > 0 && $weekMax > 0 ? max(4, ($day['revenue'] / $weekMax) * 108) : 4;
                    $x = ($index * $slot) + (($slot - $barWidth) / 2);
                    $y = 128 - $height;
                    ?>
                    <rect x="<?= e((string) $x) ?>" y="<?= e((string) $y) ?>" width="<?= e((string) $barWidth) ?>" height="<?= e((string) $height) ?>" rx="6" class="dash-col<?= $day['revenue'] > 0 ? '' : ' is-empty' ?>"/>
                    <text x="<?= e((string) ($x + ($barWidth / 2))) ?>" y="150" text-anchor="middle" class="grid-label"><?= e($day['label']) ?></text>
                <?php endforeach; ?>
            </svg>
            <p class="chart-note">Yedi günün toplamı <?= e($money($weekSum)) ?>.</p>
        </article>
    </section>

    <section class="dash-mid">
        <article class="card">
            <div class="card-head">
                <h2>Ödeme yöntemleri</h2>
                <a class="ghost-btn" href="<?= root_url('admin/odeme.php') ?>">Ayarlar</a>
            </div>
            <p class="dash-note"><?= e($enabledNote) ?></p>
            <div class="dash-meters">
                <?php foreach ($paymentRows as $row): ?>
                    <?php $width = $paymentMax > 0 ? (int) round(($row['revenue'] / $paymentMax) * 100) : 0; ?>
                    <div class="dash-meter">
                        <span><?= e($row['label']) ?><?= $row['on'] ? '' : ' · kapalı' ?></span>
                        <span class="dash-track"><span style="width: <?= e((string) $width) ?>%; background: <?= e($row['color']) ?>"></span></span>
                        <b><?= e($money($row['revenue'])) ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="card">
            <div class="card-head">
                <h2>Kategori payı</h2>
                <a class="ghost-btn" href="<?= root_url('admin/urun-kategorileri.php') ?>">Kategoriler</a>
            </div>
            <?php if ($categories === []): ?>
                <p class="dash-empty">Henüz ürün kategorisi yok.</p>
            <?php else: ?>
                <div class="dash-meters">
                    <?php foreach ($categories as $index => $category): ?>
                        <?php
                        $count = (int) $category['products'];
                        $width = $categoryMax > 0 ? (int) round(($count / $categoryMax) * 100) : 0;
                        $color = $categoryColors[$index % count($categoryColors)];
                        ?>
                        <div class="dash-meter">
                            <span><?= e((string) $category['title']) ?><?= !empty($category['active']) ? '' : ' · gizli' ?></span>
                            <span class="dash-track"><span style="width: <?= e((string) $width) ?>%; background: <?= e($color) ?>"></span></span>
                            <b><?= e($countLabel($count)) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="card">
            <div class="card-head">
                <h2>Stok</h2>
                <a class="ghost-btn" href="<?= root_url('admin/urunler.php') ?>">Ürünler</a>
            </div>
            <?php if ($products === []): ?>
                <p class="dash-empty">Henüz ürün yok.</p>
            <?php else: ?>
                <ul class="dash-stock">
                    <?php foreach ($products as $product): ?>
                        <?php
                        if (empty($product['stock_track'])) {
                            $stockLabel = 'Takipsiz';
                            $stockPill = 'is-taslak';
                        } elseif ((int) $product['stock_qty'] <= 0) {
                            $stockLabel = 'Tükendi';
                            $stockPill = 'is-tukendi';
                        } elseif ((int) $product['stock_qty'] <= 5) {
                            $stockLabel = (string) $product['stock_qty'] . ' adet';
                            $stockPill = 'is-prep';
                        } else {
                            $stockLabel = (string) $product['stock_qty'] . ' adet';
                            $stockPill = 'is-yayinda';
                        }
                        ?>
                        <li>
                            <a href="<?= root_url('admin/urunler.php?urun=' . rawurlencode((string) $product['key'])) ?>">
                                <b><?= e((string) $product['title']) ?></b>
                                <span class="catalog-status <?= e($stockPill) ?>"><?= e($stockLabel) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>

    <section class="dash-links" aria-label="Panel bölümleri">
        <?php foreach ($tiles as $tile): ?>
            <a href="<?= $tile['href'] ?>">
                <span><?= e($tile['label']) ?></span>
                <strong<?= !empty($tile['text']) ? ' class="is-text"' : '' ?>><?= e($tile['value']) ?></strong>
                <small><?= e($tile['note']) ?></small>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="split-grid">
        <article class="card table-card">
            <div class="card-head">
                <h2>Son siparişler</h2>
                <a class="ghost-btn" href="<?= root_url('admin/siparisler.php') ?>">Tümü</a>
            </div>
            <?php if ($recent === []): ?>
                <p class="dash-empty">Henüz sipariş yok.</p>
            <?php else: ?>
                <div class="catalog-scroll">
                    <table class="catalog-table">
                        <thead>
                            <tr>
                                <th>Sipariş</th>
                                <th>Ürün</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $order): ?>
                                <?php
                                $status = (string) $order['status'];
                                $payment = (string) $order['payment_method'];
                                $stamp = strtotime((string) $order['created_at']);
                                $when = $stamp !== false ? date('d.m.Y H:i', $stamp) : '';
                                $statusLabel = OrderBook::statuses()[$status] ?? $status;
                                $paymentLabel = OrderBook::payments()[$payment] ?? $payment;
                                ?>
                                <tr>
                                    <td>
                                        <a class="catalog-order-title" href="<?= root_url('admin/siparisler.php?no=' . rawurlencode((string) $order['order_no'])) ?>"><?= e((string) $order['order_no']) ?></a>
                                        <span class="catalog-sub"><?= e($when) ?></span>
                                    </td>
                                    <td>
                                        <div class="catalog-product">
                                            <?php if ((string) $order['product_image'] !== ''): ?>
                                                <img src="<?= root_url((string) $order['product_image']) ?>" alt="" width="42" height="42">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= e((string) $order['product_title']) ?></strong>
                                                <span><?= e((string) $order['qty']) ?> adet · <?= e($paymentLabel) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($money((float) $order['total'])) ?></td>
                                    <td><span class="catalog-status <?= e($statusClass($status)) ?>"><?= e($statusLabel) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <article class="card table-card">
            <div class="card-head">
                <h2>Ürün satışları</h2>
                <a class="ghost-btn" href="<?= root_url('admin/urunler.php') ?>">Tümü</a>
            </div>
            <?php if ($products === []): ?>
                <p class="dash-empty">Henüz ürün yok.</p>
            <?php else: ?>
                <div class="catalog-scroll">
                    <table class="catalog-table">
                        <thead>
                            <tr>
                                <th>Ürün</th>
                                <th>Satış</th>
                                <th>Ciro</th>
                                <th>Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <?php
                                if (empty($product['stock_track'])) {
                                    $stockLabel = 'Takipsiz';
                                    $stockPill = 'is-taslak';
                                } elseif ((int) $product['stock_qty'] <= 0) {
                                    $stockLabel = 'Tükendi';
                                    $stockPill = 'is-tukendi';
                                } else {
                                    $stockLabel = (string) $product['stock_qty'];
                                    $stockPill = (int) $product['stock_qty'] <= 5 ? 'is-prep' : 'is-yayinda';
                                }
                                $meta = trim((string) $product['category']);
                                if ($meta === '') {
                                    $meta = trim((string) $product['amount'] . ' ' . (string) $product['unit']);
                                }
                                ?>
                                <tr>
                                    <td>
                                        <a class="catalog-product" href="<?= root_url('admin/urunler.php?urun=' . rawurlencode((string) $product['key'])) ?>">
                                            <?php if ((string) $product['image'] !== ''): ?>
                                                <img src="<?= root_url((string) $product['image']) ?>" alt="" width="42" height="42">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= e((string) $product['title']) ?></strong>
                                                <?php if ($meta !== ''): ?>
                                                    <span><?= e($meta) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </td>
                                    <td><?= e($countLabel((int) $product['orders'])) ?> <span class="catalog-sub"><?= e($countLabel((int) $product['sold_qty'])) ?> adet</span></td>
                                    <td><?= e($money((float) $product['sold_amount'])) ?></td>
                                    <td><span class="catalog-status <?= e($stockPill) ?>"><?= e($stockLabel) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>
</div>
<script type="application/json" id="report-data"><?= json_encode($reportJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
