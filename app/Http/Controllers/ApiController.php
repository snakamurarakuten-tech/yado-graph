<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Rakuten\KeywordSearchService;
use App\Services\Storage\HotelRepository;

/**
 * 軽量JSON API。フロント(お気に入り類似レコメンドC-8, 履歴パーソナライズC-7)が
 * タグ傾向を投げると、そのタグに対応する宿カードを返す。
 *
 * GET /api/recommend?tags=onsen,rotenburo&exclude=143637,150000&limit=12
 *
 * ※ 価格・空室は返さない(送客特化の方針どおり)。カードは HotelCardMapper 形式。
 */
final class ApiController
{
    private KeywordSearchService $keyword;
    private HotelRepository $repo;

    public function __construct(?KeywordSearchService $keyword = null, ?HotelRepository $repo = null)
    {
        $this->keyword = $keyword ?? new KeywordSearchService();
        $this->repo = $repo ?? new HotelRepository();
    }

    /**
     * hotelNo指定でカードを一括取得(改修4-4: お気に入り共有リンクの受け取り側)。
     * GET /api/hotels?nos=143637,150000
     * DBのみ参照(APIは叩かない=乱用されてもレート制限を消費しない)。
     */
    public function hotels(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $nos = $this->splitParam($_GET['nos'] ?? '');
        $cards = array_map(
            fn (array $c): array => $this->slimCard($c),
            $this->repo->findMany($nos)
        );
        echo json_encode(['items' => $cards], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    public function recommend(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $tags    = $this->splitParam($_GET['tags'] ?? '');
        $exclude = array_map('strval', $this->splitParam($_GET['exclude'] ?? ''));
        $limit   = max(1, min((int) ($_GET['limit'] ?? 12), 20));

        // ---- DBファースト(改修: 「2つ選んでレコメンド」を高速・正確に) ----
        // 従来は毎回楽天キーワードAPIを叩いていた(遅い・掲載フィルタが効かない)。
        // DBがあればタグごとに検索して評価順でマージする。
        if ($this->repo->count() > 0) {
            $seen = array_fill_keys($exclude, true);
            $cards = [];
            $searchTags = $tags !== [] ? array_slice($tags, 0, 4) : [''];
            foreach ($searchTags as $t) {
                $q = ['sort' => 'rating', 'perPage' => $limit + 8, 'excludeNoReview' => true];
                if ($t !== '') {
                    $q['tags'] = [$t];
                }
                foreach ($this->repo->search($q)['items'] as $card) {
                    $no = (string) ($card['hotelNo'] ?? '');
                    if ($no === '' || isset($seen[$no])) {
                        continue;
                    }
                    $seen[$no] = true;
                    $cards[] = $card;
                    if (count($cards) >= $limit) {
                        break 2;
                    }
                }
            }
            echo json_encode(
                ['items' => array_map(fn (array $c): array => $this->slimCard($c), $cards)],
                JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
            );
            return;
        }

        // ---- APIフォールバック(DB未構築時のみ) ----
        $tagDefs = (array) config('taxonomy.tags', []);

        // タグ → 検索語。タグが無ければ人気テーマから無難に。
        $searches = [];
        foreach ($tags as $t) {
            $s = (string) ($tagDefs[$t]['search'] ?? '');
            if ($s !== '') {
                $searches[] = $s;
            }
        }
        if ($searches === []) {
            $searches = ['温泉 旅館'];
        }

        // 各検索語から集めて重複・除外を落とす
        $seen = array_fill_keys($exclude, true);
        $cards = [];
        foreach ($searches as $s) {
            foreach ($this->keyword->searchByKeyword($s, 12) as $card) {
                $no = (string) ($card['hotelNo'] ?? '');
                if ($no === '' || isset($seen[$no])) {
                    continue;
                }
                $seen[$no] = true;
                $cards[] = $this->slimCard($card);
                if (count($cards) >= $limit) {
                    break 2;
                }
            }
        }

        echo json_encode(['items' => $cards], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** JS描画に必要な最小フィールドだけに絞る。 */
    private function slimCard(array $card): array
    {
        return [
            'hotelNo'       => (string) ($card['hotelNo'] ?? ''),
            'hotelName'     => (string) ($card['hotelName'] ?? ''),
            'area'          => (string) ($card['area'] ?? ''),
            'imageUrl'      => (string) ($card['imageUrl'] ?? ''),
            'reviewAverage' => (float) ($card['reviewAverage'] ?? 0),
            'reviewCount'   => (int) ($card['reviewCount'] ?? 0),
            'affiliateUrl'  => (string) ($card['affiliateUrl'] ?? ''),
            'badges'        => array_map(
                static fn ($b) => ['label' => (string) ($b['label'] ?? ''), 'tone' => (string) ($b['tone'] ?? 'amber')],
                (array) ($card['badges'] ?? [])
            ),
            'tags'          => array_map('strval', (array) ($card['tags'] ?? [])),
        ];
    }

    /** "a,b, c" → ['a','b','c'] */
    private function splitParam(mixed $raw): array
    {
        $s = is_string($raw) ? $raw : '';
        if ($s === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $s)), static fn ($v) => $v !== ''));
    }
}
