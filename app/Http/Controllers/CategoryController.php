<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CategoryService;
use App\Support\HttpNotFoundException;
use App\Support\View;

/**
 * カテゴリ関連(依頼P1-3/4/5)。
 *  - /categories        全カテゴリ一覧
 *  - /category/{key}     カテゴリ単体ページ(価格帯フィルタ＋もっと見る/無限スクロール)
 *  - /api/category/{key} 追加読み込み用JSON
 */
final class CategoryController
{
    private CategoryService $categories;

    public function __construct(?CategoryService $categories = null)
    {
        $this->categories = $categories ?? new CategoryService();
    }

    /** 全カテゴリ一覧ページ */
    public function index(): void
    {
        View::render('category/list', [
            'categories' => $this->categories->all(),
            'seo' => [
                'title'       => 'カテゴリ一覧｜' . config('app.name'),
                'description' => '気分やこだわりから宿を探す。露天風呂・一人旅・絶景・サウナなど、カテゴリ別に宿を眺められます。',
                'canonical'   => (string) config('app.url') . '/categories',
                'image'       => '',
                'jsonLd'      => null,
            ],
            'pageCss'   => 'category',
            'pageJs'    => 'category-list',
            'activeTab' => 'home',
        ]);
    }

    /** カテゴリ単体ページ */
    public function show(string $key): void
    {
        $cat = $this->categories->find($key);
        if ($cat === null) {
            throw new HttpNotFoundException('このカテゴリは見つかりませんでした。');
        }

        $price = (string) ($_GET['price'] ?? '');
        $sort  = CategoryService::normalizeSort((string) ($_GET['sort'] ?? 'popular'));
        $result = $this->categories->fetch($key, ['page' => 1, 'price' => $price, 'sort' => $sort]);

        View::render('category/show', [
            'category'   => $cat,
            'items'      => $result['items'],
            'hasMore'    => $result['hasMore'],
            'priceBands' => (array) config('categories.price_bands', []),
            'activePrice'=> $price,
            'activeSort' => $sort,
            'seo' => [
                'title'       => $cat['label'] . '｜' . config('app.name'),
                'description' => $cat['label'] . 'を楽天トラベルのデータから集めました。気になる宿は楽天トラベルで空室・料金を確認できます。',
                'canonical'   => (string) config('app.url') . '/category/' . rawurlencode($key),
                'image'       => '',
                'jsonLd'      => null,
            ],
            'pageCss'   => 'category',
            'pageJs'    => 'category',
            'activeTab' => 'home',
        ]);
    }

    /** 追加読み込み(もっと見る/無限スクロール)用JSON */
    public function feed(string $key): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $result = $this->categories->fetch($key, [
            'page'  => max(1, (int) ($_GET['page'] ?? 1)),
            'price' => (string) ($_GET['price'] ?? ''),
            'area'  => (string) ($_GET['area'] ?? ''),
            'sort'  => CategoryService::normalizeSort((string) ($_GET['sort'] ?? 'popular')),
        ]);

        $items = array_map(static function (array $c): array {
            return [
                'hotelNo'       => (string) ($c['hotelNo'] ?? ''),
                'hotelName'     => (string) ($c['hotelName'] ?? ''),
                'area'          => (string) ($c['area'] ?? ''),
                'imageUrl'      => (string) ($c['imageUrl'] ?? ''),
                'reviewAverage' => (float) ($c['reviewAverage'] ?? 0),
                'reviewCount'   => (int) ($c['reviewCount'] ?? 0),
                'affiliateUrl'  => (string) ($c['affiliateUrl'] ?? ''),
                'badges'        => array_map(
                    static fn ($b) => ['label' => (string) ($b['label'] ?? ''), 'tone' => (string) ($b['tone'] ?? 'amber')],
                    (array) ($c['badges'] ?? [])
                ),
                'tags'          => array_map('strval', (array) ($c['tags'] ?? [])),
            ];
        }, $result['items']);

        echo json_encode(['items' => $items, 'hasMore' => $result['hasMore']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
