<?php
declare(strict_types=1);

namespace App\Services;

/**
 * ページ内の全レールを横断して hotelNo の重複を除去するビルダー(改修 Phase 2)。
 *
 * 【背景】
 *  「同じ○○の宿」「この宿もおすすめ」「人気の宿」「カテゴリ別レール」が
 *  それぞれ独立に検索していたため、同じ宿が1ページに何度も出ていた。
 *
 * 【使い方】
 *  $b = new RailBuilder($currentHotelNo);          // 最初から除外したい宿
 *  $b->add('near', '近くの宿', $items, [...]);      // 先に add したレールが優先
 *  $b->add('tag',  '露天風呂が評判の宿', $items);    // 前レールと重複した宿は自動で抜ける
 *  $rails = $b->all();                              // min 件数未満のレールは出ない
 *  $seen  = $b->seenHotelNos();                     // JSレールの exclude に渡す
 */
final class RailBuilder
{
    /** @var array<string,true> */
    private array $seen = [];

    /** @var array<int,array<string,mixed>> */
    private array $rails = [];

    public function __construct(string ...$excludeHotelNos)
    {
        foreach ($excludeHotelNos as $no) {
            if ($no !== '') {
                $this->seen[$no] = true;
            }
        }
    }

    /**
     * レールを追加する。既出の hotelNo は除外し、残りが $min 件未満ならレールごと捨てる。
     *
     * @param array<int,array<string,mixed>> $items カード配列
     * @param array<string,mixed> $meta title/items 以外の付加情報(cardType, tag, moreHref 等)
     */
    public function add(string $key, string $title, array $items, array $meta = [], int $min = 3, int $max = 10): void
    {
        $unique = [];
        foreach ($items as $it) {
            $no = (string) ($it['hotelNo'] ?? '');
            if ($no === '' || isset($this->seen[$no])) {
                continue;
            }
            $unique[$no] = $it; // 同一レール内の重複もキーで潰す
            if (count($unique) >= $max) {
                break;
            }
        }
        if (count($unique) < $min) {
            return; // 薄いレールは出さない(重複除去後のスカスカ防止)
        }
        foreach (array_keys($unique) as $no) {
            $this->seen[$no] = true;
        }
        $this->rails[] = $meta + ['key' => $key, 'title' => $title, 'items' => array_values($unique)];
    }

    /** @return array<int,string> ページ内で表示済みの hotelNo(JSレールの exclude 用) */
    public function seenHotelNos(): array
    {
        return array_keys($this->seen);
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->rails;
    }
}
