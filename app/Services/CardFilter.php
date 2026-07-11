<?php
declare(strict_types=1);

namespace App\Services;

/**
 * カード配列に対する共通フィルタ。
 *  - P2-7: レビュー欠損の扱い(除外 or そのまま。表示側は「レビューなし」を機械的に出す)
 *  - P1-5: 価格帯(minCharge)・エリア(都道府県)での絞り込み
 *
 * サイト上に価格の数値は出さない方針のため、価格は「バンドでの絞り込み」にのみ使う。
 */
final class CardFilter
{
    /**
     * まとめて適用。
     * @param array<int,array<string,mixed>> $cards
     * @param array{price?:string,area?:string,excludeNoReview?:bool} $opts
     * @return array<int,array<string,mixed>>
     */
    public function apply(array $cards, array $opts = []): array
    {
        $priceBand = (string) ($opts['price'] ?? '');
        $area      = (string) ($opts['area'] ?? '');
        $excludeNoReview = (bool) ($opts['excludeNoReview'] ?? config('filters.exclude_no_review', false));

        [$min, $max] = $this->bandRange($priceBand);

        return array_values(array_filter($cards, function (array $c) use ($min, $max, $area, $excludeNoReview): bool {
            if ($excludeNoReview && (int) ($c['reviewCount'] ?? 0) <= 0) {
                return false;
            }
            if ($area !== '' && !str_contains((string) ($c['area'] ?? ''), $area)) {
                return false;
            }
            if ($min > 0 || $max > 0) {
                $price = (int) ($c['minCharge'] ?? 0);
                if ($price <= 0) {
                    return false; // 料金不明は価格帯フィルタ時は除外
                }
                if ($min > 0 && $price < $min) {
                    return false;
                }
                if ($max > 0 && $price > $max) {
                    return false;
                }
            }
            return true;
        }));
    }

    /** 価格バンドキー → [min,max]。未指定/不明は [0,0]。 */
    private function bandRange(string $key): array
    {
        if ($key === '') {
            return [0, 0];
        }
        foreach ((array) config('categories.price_bands', []) as $b) {
            if ((string) ($b['key'] ?? '') === $key) {
                return [(int) ($b['min'] ?? 0), (int) ($b['max'] ?? 0)];
            }
        }
        return [0, 0];
    }
}
