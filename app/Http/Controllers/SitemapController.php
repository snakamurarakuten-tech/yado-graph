<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CategoryService;
use App\Support\Database;

/**
 * SEO用のクロール導線(改修 Phase 6-1)。
 *  - /sitemap.xml … トップ・静的ページ・全カテゴリ・DB内の全宿
 *  - /robots.txt  … Sitemap行 + APIのDisallow
 * DB未構築でもトップとカテゴリだけで有効なサイトマップを返す。
 */
final class SitemapController
{
    public function index(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $base = rtrim((string) config('app.url'), '/');

        $urls = [];
        $urls[] = ['loc' => $base . '/', 'priority' => '1.0'];
        $urls[] = ['loc' => $base . '/categories', 'priority' => '0.8'];
        $urls[] = ['loc' => $base . '/areas', 'priority' => '0.8'];
        $urls[] = ['loc' => $base . '/features', 'priority' => '0.7'];
        $urls[] = ['loc' => $base . '/about', 'priority' => '0.3'];
        $urls[] = ['loc' => $base . '/privacy', 'priority' => '0.3'];

        // 特集(公開済みのみ)
        foreach ((new \App\Services\ContentStore())->publishedFeatures() as $f) {
            $urls[] = [
                'loc'      => $base . '/features/' . rawurlencode((string) $f['slug']),
                'lastmod'  => (string) ($f['publishedAt'] ?? ''),
                'priority' => '0.7',
            ];
        }

        // エリア(温泉地)ページ: 宿が3軒以上ある温泉地のみ(thin回避・増強②)
        try {
            $repo = new \App\Services\Storage\HotelRepository();
            $counts = $repo->onsenAreaCounts();
            $byName = [];
            foreach ((new \App\Services\OnsenAreaService())->all() as $a) {
                $byName[$a['name']] = $a['key'];
            }
            foreach ($counts as $name => $cnt) {
                $key = $byName[$name] ?? '';
                if ($key === '' || $cnt < \App\Http\Controllers\AreaController::MIN_HOTELS) {
                    continue;
                }
                $urls[] = ['loc' => $base . '/area/' . rawurlencode($key), 'priority' => '0.8'];
                foreach (\App\Http\Controllers\AreaController::MIN_HOTELS <= $cnt ? \App\Http\Controllers\AreaController::SUB_TAGS : [] as $t) {
                    $tc = (int) $repo->search(['onsenArea' => (string) $name, 'tags' => [$t], 'excludeNoReview' => true, 'perPage' => 1])['total'];
                    if ($tc >= \App\Http\Controllers\AreaController::MIN_HOTELS) {
                        $urls[] = ['loc' => $base . '/area/' . rawurlencode($key) . '/' . rawurlencode($t), 'priority' => '0.7'];
                    }
                }
            }
        } catch (\Throwable) {
            // DB未構築時はエリアURLなしで返す
        }

        foreach ((new CategoryService())->all() as $cat) {
            $key = (string) ($cat['key'] ?? '');
            if ($key !== '') {
                $urls[] = ['loc' => $base . '/category/' . rawurlencode($key), 'priority' => '0.7'];
            }
        }

        try {
            $stmt = Database::pdo()->query('SELECT hotelNo, fetchedAt FROM hotels WHERE reviewCount > 0 ORDER BY reviewCount DESC');
            foreach ($stmt as $row) {
                $urls[] = [
                    'loc'     => $base . '/hotel/' . rawurlencode((string) $row['hotelNo']),
                    'lastmod' => date('Y-m-d', (int) ($row['fetchedAt'] ?: time())),
                    'priority' => '0.6',
                ];
            }
        } catch (\Throwable) {
            // DB未構築なら宿URLなしで返す
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>';
            if (!empty($u['lastmod'])) {
                echo '<lastmod>' . $u['lastmod'] . '</lastmod>';
            }
            echo '<priority>' . $u['priority'] . '</priority></url>' . "\n";
        }
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $base = rtrim((string) config('app.url'), '/');
        echo "User-agent: *\n";
        echo "Disallow: /api/\n";
        echo "Allow: /\n\n";
        echo "Sitemap: {$base}/sitemap.xml\n";
    }
}
