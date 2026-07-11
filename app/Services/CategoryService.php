<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Rakuten\KeywordSearchService;
use App\Services\Storage\HotelRepository;

/**
 * カテゴリ(依頼P1-3/4/5)。DB(セクション2)があればDBを横断検索し、無ければAPIへフォールバック。
 * カテゴリ辞書(config/categories.php)を元に、トップ用レール／カテゴリ単体ページを組む。
 */
final class CategoryService
{
    private KeywordSearchService $keyword;
    private CardFilter $filter;
    private HotelRepository $repo;

    public function __construct(
        ?KeywordSearchService $keyword = null,
        ?CardFilter $filter = null,
        ?HotelRepository $repo = null
    ) {
        $this->keyword = $keyword ?? new KeywordSearchService();
        $this->filter  = $filter ?? new CardFilter();
        $this->repo    = $repo ?? new HotelRepository();
    }

    private function dbReady(): bool
    {
        return $this->repo->count() > 0;
    }

    /** カテゴリ定義 → DB横断検索の条件。 */
    /**
     * 価格帯キー('u10000'等) → ['min'=>int,'max'=>int]。不明キーは絞り込みなし(0)。
     * 検索ページ(SearchController)からも使う(改修4-1/4-2)。
     */
    public static function priceBand(string $key): array
    {
        if ($key !== '') {
            foreach ((array) config('categories.price_bands', []) as $band) {
                if ((string) ($band['key'] ?? '') === $key) {
                    return ['min' => (int) ($band['min'] ?? 0), 'max' => (int) ($band['max'] ?? 0)];
                }
            }
        }
        return ['min' => 0, 'max' => 0];
    }

    /** 並び替えキーの正規化(不正値は popular に落とす)。 */
    public static function normalizeSort(string $sort): string
    {
        return in_array($sort, ['popular', 'rating', 'reviews'], true) ? $sort : 'popular';
    }

    private function queryFor(array $cat): array
    {
        $tag = (string) ($cat['tag'] ?? '');
        if ($tag !== '') {
            return ['tags' => [$tag]];
        }
        // タグが無いカテゴリはキーワードを語に分解してAND一致
        $terms = preg_split('/\s+/u', trim((string) ($cat['query'] ?? ''))) ?: [];
        return ['terms' => array_values(array_filter($terms))];
    }

    /** 全カテゴリのメタ(一覧ページ・タブ・フッター用)。 */
    public function all(): array
    {
        $out = [];
        foreach ((array) config('categories.categories', []) as $key => $c) {
            $out[] = [
                'key'      => (string) $key,
                'label'    => (string) ($c['label'] ?? $key),
                'href'     => '/category/' . rawurlencode((string) $key),
                'cardType' => (string) ($c['cardType'] ?? 'poster'),
                'tag'      => (string) ($c['tag'] ?? ''),
                'feature'  => (bool) ($c['feature'] ?? false),
            ];
        }
        return $out;
    }

    /** キーからカテゴリ定義を引く。無ければ null。 */
    public function find(string $key): ?array
    {
        $c = (array) config('categories.categories', []);
        if (!isset($c[$key])) {
            return null;
        }
        return ['key' => $key] + (array) $c[$key];
    }

    /**
     * タグキーからカテゴリを逆引きする(改修: タグチップ・タグレールの内部リンク用)。
     * 例: 'rotenburo' → カテゴリ 'roten'。該当が無ければ null。
     */
    public function findByTag(string $tag): ?array
    {
        if ($tag === '') {
            return null;
        }
        foreach ((array) config('categories.categories', []) as $key => $c) {
            if ((string) ($c['tag'] ?? '') === $tag) {
                return ['key' => (string) $key] + (array) $c;
            }
        }
        return null;
    }

    /**
     * トップページ用のカテゴリレール。各レールに「一覧はこちら」用の href を持たせる。
     * @param int $count 表示するレール本数
     * @return array<int,array<string,mixed>>
     */
    public function topRails(int $count = 6): array
    {
        $rails = [];
        foreach ($this->all() as $cat) {
            if (count($rails) >= $count) {
                break;
            }
            $def = $this->find($cat['key']);
            $query = (string) ($def['query'] ?? '');
            if ($query === '') {
                continue;
            }
            if ($this->dbReady()) {
                $items = $this->repo->search($this->queryFor($def) + ['sort' => 'popular', 'perPage' => 12])['items'];
            } else {
                $items = $this->filter->apply($this->keyword->search($query, ['hits' => 12]), []);
            }
            if ($items === []) {
                continue;
            }
            $rails[] = [
                'key'      => $cat['key'],
                'title'    => $cat['label'],
                'items'    => $items,
                'cardType' => $cat['cardType'],
                'tag'      => $cat['tag'],
                'moreHref' => $cat['href'],
            ];
        }
        return $rails;
    }

    /**
     * カテゴリ単体のカード(ページング＋絞り込み)。
     * @param array{page?:int,price?:string,area?:string} $opts
     * @return array{items:array,hasMore:bool}
     */
    public function fetch(string $key, array $opts = []): array
    {
        $cat = $this->find($key);
        if ($cat === null) {
            return ['items' => [], 'hasMore' => false];
        }
        $page    = max(1, (int) ($opts['page'] ?? 1));
        $perPage = max(4, (int) config('categories.per_page', 20));

        if ($this->dbReady()) {
            // DB横断: エリア・価格帯・レビュー有無で絞り込み、並べ替え可能(改修4-2)
            $band = self::priceBand((string) ($opts['price'] ?? ''));
            $res = $this->repo->search($this->queryFor($cat) + [
                'pref'            => (string) ($opts['area'] ?? ''),
                'minCharge'       => $band['min'],
                'maxCharge'       => $band['max'],
                'excludeNoReview' => (bool) config('filters.exclude_no_review', false),
                'sort'            => self::normalizeSort((string) ($opts['sort'] ?? 'popular')),
                'page'            => $page,
                'perPage'         => $perPage,
            ]);
            return ['items' => $res['items'], 'hasMore' => $page * $perPage < $res['total']];
        }

        // APIフォールバック
        $raw = $this->keyword->search((string) $cat['query'], [
            'hits' => min($perPage, 30),
            'page' => $page,
            'sort' => (string) ($opts['sort'] ?? 'standard'),
        ]);
        $items = $this->filter->apply($raw, [
            'price' => (string) ($opts['price'] ?? ''),
            'area'  => (string) ($opts['area'] ?? ''),
        ]);
        $hasMore = count($raw) >= min($perPage, 30);
        return ['items' => $items, 'hasMore' => $hasMore];
    }
}
