<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CategoryService;
use App\Services\Rakuten\KeywordSearchService;
use App\Services\Storage\HotelRepository;
use App\Support\View;

/**
 * フリーワード検索(改修 Phase 4-1)。
 *  - GET /search      検索ページ(キーワード・都道府県・価格帯・並び替え)
 *  - GET /api/search  もっと見る/無限スクロール用JSON
 *
 * DBがあればDB横断(searchtext LIKE)、無ければ楽天キーワード検索へフォールバック。
 * 検索結果はクエリの組み合わせが無限にあるため noindex(重複・薄いページの量産防止)。
 */
final class SearchController
{
    private HotelRepository $repo;
    private KeywordSearchService $keyword;

    public function __construct(?HotelRepository $repo = null, ?KeywordSearchService $keyword = null)
    {
        $this->repo = $repo ?? new HotelRepository();
        $this->keyword = $keyword ?? new KeywordSearchService();
    }

    public function index(): void
    {
        $params = $this->params();
        $result = $this->run($params, page: 1);

        View::render('search/index', [
            'q'          => $params['q'],
            'pref'       => $params['pref'],
            'price'      => $params['price'],
            'sort'       => $params['sort'],
            'items'      => $result['items'],
            'total'      => $result['total'],
            'hasMore'    => $result['hasMore'],
            'prefList'   => $this->repo->prefList(),
            'priceBands' => (array) config('categories.price_bands', []),
            'seo' => [
                'title'       => ($params['q'] !== '' ? $params['q'] . 'の検索結果｜' : '宿をさがす｜') . config('app.name'),
                'description' => 'キーワード・エリア・価格帯から旅館をさがせます。',
                'canonical'   => (string) config('app.url') . '/search',
                'image'       => '',
                'jsonLd'      => null,
                'robots'      => 'noindex,follow', // 検索結果はインデックスさせない(改修4-1)
            ],
            'pageCss'   => 'category', // chip等のスタイルを流用(検索固有分は brand.css)
            'pageJs'    => 'search',
            'activeTab' => 'search',
        ]);
    }

    /** もっと見る/無限スクロール用JSON */
    public function feed(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $params = $this->params();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->run($params, $page);

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

        echo json_encode(
            ['items' => $items, 'hasMore' => $result['hasMore'], 'total' => $result['total']],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /** @return array{q:string,pref:string,price:string,sort:string} */
    private function params(): array
    {
        return [
            'q'     => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60),
            'pref'  => mb_substr(trim((string) ($_GET['pref'] ?? '')), 0, 10),
            'price' => (string) ($_GET['price'] ?? ''),
            'sort'  => CategoryService::normalizeSort((string) ($_GET['sort'] ?? 'popular')),
        ];
    }

    /**
     * @param array{q:string,pref:string,price:string,sort:string} $p
     * @return array{items:array,total:int,hasMore:bool}
     */
    private function run(array $p, int $page): array
    {
        $perPage = max(4, (int) config('categories.per_page', 20));

        if ($this->repo->count() > 0) {
            $band = CategoryService::priceBand($p['price']);
            $terms = $p['q'] !== ''
                ? array_values(array_filter(preg_split('/\s+/u', $p['q']) ?: []))
                : [];
            $res = $this->repo->search([
                'excludeNoReview' => (bool) config('filters.exclude_no_review', true), // メモ2
                'terms'     => $terms,
                'pref'      => $p['pref'],
                'minCharge' => $band['min'],
                'maxCharge' => $band['max'],
                'sort'      => $p['sort'],
                'page'      => $page,
                'perPage'   => $perPage,
            ]);
            return [
                'items'   => $res['items'],
                'total'   => (int) $res['total'],
                'hasMore' => $page * $perPage < (int) $res['total'],
            ];
        }

        // APIフォールバック(キーワードのみ・総数は不明)
        if ($p['q'] === '') {
            return ['items' => [], 'total' => 0, 'hasMore' => false];
        }
        $items = $this->keyword->search($p['q'], ['hits' => $perPage, 'page' => $page]);
        return ['items' => $items, 'total' => count($items), 'hasMore' => count($items) >= $perPage];
    }
}
