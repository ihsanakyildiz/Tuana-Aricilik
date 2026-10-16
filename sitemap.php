<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/CategoryEditor.php';

$urls = [];
$add = static function (string $path, string $updated) use (&$urls): void {
    $stamp = strtotime($updated);
    $urls[] = [
        'loc' => absolute_url($path),
        'lastmod' => date('Y-m-d', $stamp === false ? time() : $stamp),
    ];
};

$pdo = db_pdo();
if ($pdo instanceof PDO) {
    $pages = $pdo->query(
        "SELECT page_slug, MAX(updated_at) AS updated_at
         FROM sections
         WHERE is_active = 1 AND page_slug IN ('home', 'blog', 'hakkimizda')
         GROUP BY page_slug"
    )->fetchAll();
    $pageUpdated = [];
    foreach ($pages as $page) {
        if (is_array($page)) {
            $pageUpdated[(string) $page['page_slug']] = (string) $page['updated_at'];
        }
    }
    $add('/', (string) ($pageUpdated['home'] ?? ''));
    $add('/iletisim', '');
    $add('/blog', (string) ($pageUpdated['blog'] ?? ''));
    if (isset($pageUpdated['hakkimizda'])) {
        $add('/hakkimizda', $pageUpdated['hakkimizda']);
    }

    $classicPages = $pdo->query(
        "SELECT block_key, content, updated_at FROM blocks WHERE block_type = 'page' AND is_active = 1 ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
    foreach ($classicPages as $classicPage) {
        if (!is_array($classicPage)) {
            continue;
        }
        $classicContent = decode_value((string) ($classicPage['content'] ?? ''));
        $classicContent = is_array($classicContent) ? $classicContent : [];
        $pageKey = (string) $classicPage['block_key'];
        if (($classicContent['kind'] ?? 'classic') === 'advanced') {
            if (!post_index_enabled($classicContent)) {
                continue;
            }
            $sectionCount = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE page_slug = ? AND is_active = 1');
            $sectionCount->execute([$pageKey]);
            if ((int) $sectionCount->fetchColumn() > 0) {
                $add('/' . $pageKey, (string) $classicPage['updated_at']);
            }
            continue;
        }
        if (post_index_enabled($classicContent) && post_has_body($classicContent)) {
            $add('/' . $pageKey, (string) $classicPage['updated_at']);
        }
    }

    $posts = $pdo->query(
        "SELECT block_key, content, updated_at FROM blocks WHERE block_type = 'post' AND is_active = 1 ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
    $postRows = [];
    foreach ($posts as $post) {
        if (!is_array($post)) {
            continue;
        }
        $content = decode_value((string) ($post['content'] ?? ''));
        $postContent = is_array($content) ? $content : [];
        $postRows[] = [
            'key' => (string) $post['block_key'],
            'category' => (string) ($postContent['category_key'] ?? ''),
            'updated' => (string) $post['updated_at'],
        ];
        if (post_index_enabled($postContent) && post_has_body($postContent)) {
            $add('/yazi/' . (string) $post['block_key'], (string) $post['updated_at']);
        }
    }

    foreach (CategoryEditor::all() as $category) {
        $record = CategoryEditor::find($category['key']);
        if ($record === null || !$record['active'] || !$record['index']) {
            continue;
        }
        $updated = $record['updated'];
        $count = 0;
        foreach ($postRows as $post) {
            if ($post['category'] !== $record['key']) {
                continue;
            }
            $count++;
            if ($post['updated'] > $updated) {
                $updated = $post['updated'];
            }
        }
        if ($count < 1) {
            continue;
        }
        $add(category_url($record['key']), $updated);
    }

    $shopUpdated = '';
    $shopProducts = $pdo->query(
        "SELECT block_key, updated_at FROM blocks WHERE block_type = 'product' AND is_active = 1 ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
    foreach ($shopProducts as $shopProduct) {
        if (!is_array($shopProduct)) {
            continue;
        }
        $shopStamp = (string) $shopProduct['updated_at'];
        if ($shopStamp > $shopUpdated) {
            $shopUpdated = $shopStamp;
        }
        $add('/urun/' . (string) $shopProduct['block_key'], $shopStamp);
    }
    $add('/urunler', $shopUpdated);
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=300');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $url) {
    echo '  <url><loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
    echo '<lastmod>' . htmlspecialchars($url['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</lastmod></url>' . "\n";
}
echo '</urlset>';
