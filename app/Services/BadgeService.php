<?php
declare(strict_types=1);

namespace App\Services;

/**
 * バッジ自動付与(依頼B-5)。
 * 条件テーブル(config/badges.php)を上から評価し、合致したバッジを返す。
 * 条件・語彙は設定ファイル側にあるため、コード変更なしで増減・調整できる。
 */
final class BadgeService
{
    /**
     * @param array<string,mixed> $hotel カード/詳細どちらの正規化配列でも可
     *        参照キー: reviewAverage, reviewCount, minCharge, tags
     * @return array<int,array{id:string,label:string,tone:string}>
     */
    public function forHotel(array $hotel): array
    {
        $rules = (array) config('badges.rules', []);
        $max   = (int) config('badges.max_per_hotel', 3);

        $rating  = (float) ($hotel['reviewAverage'] ?? 0);
        $reviews = (int) ($hotel['reviewCount'] ?? 0);
        $price   = (int) ($hotel['minCharge'] ?? 0);
        $tags    = array_map('strval', (array) ($hotel['tags'] ?? []));

        $badges = [];
        foreach ($rules as $rule) {
            $when = (array) ($rule['when'] ?? []);
            if ($this->matches($when, $rating, $reviews, $price, $tags)) {
                $badges[] = [
                    'id'    => (string) ($rule['id'] ?? ''),
                    'label' => (string) ($rule['label'] ?? ''),
                    'tone'  => (string) ($rule['tone'] ?? 'amber'),
                ];
            }
            if (count($badges) >= $max) {
                break;
            }
        }
        return $badges;
    }

    /**
     * @param array<string,mixed> $when
     * @param array<int,string>   $tags
     */
    private function matches(array $when, float $rating, int $reviews, int $price, array $tags): bool
    {
        foreach ($when as $key => $val) {
            $ok = match ($key) {
                'rating_gte'  => $rating >= (float) $val,
                'rating_lt'   => $rating <  (float) $val,
                'reviews_gte' => $reviews >= (int) $val,
                'reviews_lt'  => $reviews <  (int) $val && $reviews > 0, // 0件(未評価)は「少ない」に含めない
                'price_gte'   => $price > 0 && $price >= (int) $val,
                'price_lt'    => $price > 0 && $price <  (int) $val,
                'has_tag'     => in_array((string) $val, $tags, true),
                'has_all_tags'=> $this->hasAll((array) $val, $tags),
                'has_any_tags'=> $this->hasAny((array) $val, $tags),
                default       => true, // 未知キーは無視(前方互換)
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    private function hasAll(array $need, array $tags): bool
    {
        foreach ($need as $t) {
            if (!in_array((string) $t, $tags, true)) {
                return false;
            }
        }
        return $need !== [];
    }

    private function hasAny(array $need, array $tags): bool
    {
        foreach ($need as $t) {
            if (in_array((string) $t, $tags, true)) {
                return true;
            }
        }
        return false;
    }
}
