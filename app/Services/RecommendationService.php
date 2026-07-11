<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Rakuten\HotelRankingService;
use App\Services\Rakuten\KeywordSearchService;
use App\Services\ShelfService;
use App\Services\Storage\HotelRepository;

/**
 * 「近くの宿」「同じ県の宿」「○○が評判の宿」等の横スクロール群を構築する。
 * RailBuilder(Phase 2)でページ内の重複を除去し、レールタイトルはタグから具体化する。
 * DB(セクション2)があればDBから、無ければ KeywordHotelSearch / HotelRanking から。
 */
final class RecommendationService
{
    private KeywordSearchService $keyword;
    private HotelRankingService $ranking;
    private ShelfService $shelves;
    private HotelRepository $repo;

    public function __construct(
        ?KeywordSearchService $keyword = null,
        ?HotelRankingService $ranking = null,
        ?ShelfService $shelves = null,
        ?HotelRepository $repo = null
    ) {
        $this->keyword = $keyword ?? new KeywordSearchService();
        $this->ranking = $ranking ?? new HotelRankingService();
        $this->shelves = $shelves ?? new ShelfService();
        $this->repo = $repo ?? new HotelRepository();
    }

    /**
     * 詳細ページ用のおすすめ群。表示中の宿とレール間の重複を除外する。
     *
     * 優先順:
     *  1) 近くの宿(半径10km・DB必須) … 温泉地単位の回遊を生む
     *  2) 同じ県の人気宿
     *  3) 「{タグ}が評判の宿」(タグ由来でタイトル具体化)
     *
     * @param array<string,mixed> $hotel 表示中の $hotel
     * @return array{rails:array<int,array<string,mixed>>,seen:array<int,string>}
     */
    public function forDetail(array $hotel): array
    {
        $currentNo = (string) ($hotel['hotelNo'] ?? '');
        $area = (string) ($hotel['area'] ?? '');
        $tags = array_map('strval', (array) ($hotel['tags'] ?? []));
        $b = new RailBuilder($currentNo);

        if ($this->repo->count() > 0) {
            // 1) 近くの宿(緯度経度があるとき)
            $lat = $hotel['latitude'] ?? null;
            $lng = $hotel['longitude'] ?? null;
            if ($lat !== null && $lng !== null) {
                $near = $this->repo->nearby((float) $lat, (float) $lng, $currentNo, 10.0, 14);
                $b->add('near', '徒歩・車で行ける近くの宿', $near);
            }

            // 2) 同じ県の人気宿
            if ($area !== '') {
                $sameArea = $this->repo->search(['pref' => $area, 'sort' => 'popular', 'perPage' => 20, 'excludeNoReview' => true])['items'];
                $b->add('area', '同じ' . $area . 'の宿', $sameArea, [
                    'moreHref' => '/search?pref=' . rawurlencode($area), // 一覧=県で絞った検索結果へ
                ]);
            }

            // 3) タグ由来のレール(タイトルを具体化: 「露天風呂が評判の宿」)
            if ($tags !== []) {
                $tagKey = $tags[0];
                $byTag = $this->repo->search(['tags' => [$tagKey], 'sort' => 'rating', 'perPage' => 20, 'excludeNoReview' => true])['items'];
                $cat = (new \App\Services\CategoryService())->findByTag($tagKey);
                $b->add('tag-' . $tagKey, $this->tagRailTitle($tagKey), $byTag, [
                    'moreHref' => $cat !== null ? '/category/' . rawurlencode((string) $cat['key']) : '/categories',
                ]);
            } else {
                $b->add('popular', 'この宿もおすすめ', $this->repo->search(['sort' => 'popular', 'perPage' => 20, 'excludeNoReview' => true])['items']);
            }

            return ['rails' => $b->all(), 'seen' => $b->seenHotelNos()];
        }

        // ---- APIフォールバック(DB未構築時) ----
        if ($area !== '') {
            $b->add('area', '同じ' . $area . 'の宿', $this->keyword->searchByKeyword($area, 15));
        }
        $b->add('popular', 'この宿もおすすめ', $this->ranking->ranking(null, 15));

        return ['rails' => $b->all(), 'seen' => $b->seenHotelNos()];
    }

    /** タグキー → レールタイトル。taxonomy の label から生成。 */
    private function tagRailTitle(string $tagKey): string
    {
        $label = (string) (config("taxonomy.tags.{$tagKey}.label") ?? '');
        return $label !== '' ? $label . 'が評判の宿' : 'この宿もおすすめ';
    }

    /**
     * トップページ用の複数レール(APIフォールバック時のみ使用。DB時は TopController が組む)。
     * @return array<int,array{title:string,items:array,cardType:string}>
     */
    public function forTop(): array
    {
        $rails = [];

        $popular = $this->ranking->ranking(null, 12);
        if ($popular !== []) {
            $rails[] = ['title' => '人気の宿', 'items' => $popular, 'cardType' => 'poster', 'tag' => '', 'mood' => false];
        }

        foreach ($this->shelves->emotionalShelves(4) as $shelf) {
            $rails[] = $shelf;
        }

        return $rails;
    }
}
