<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

/**
 * KeywordHotelSearch / SimpleHotelSearch のレスポンス(新旧API形状の両対応)を
 * カード配列へ正規化する共通処理。KeywordSearchService / AreaHotelSearchService で共用。
 */
final class HotelResponseNormalizer
{
    /**
     * @param array<string,mixed> $response
     * @return array<int,array<string,mixed>>
     */
    public static function toCards(array $response): array
    {
        $hotels = $response['hotels'] ?? [];
        if (!is_array($hotels)) {
            return [];
        }

        $cards = [];
        foreach ($hotels as $entry) {
            $basic = self::pickBasicInfo($entry);
            if ($basic === null) {
                continue;
            }
            $cards[] = HotelCardMapper::fromBasicInfo($basic);
        }
        return $cards;
    }

    /** hotels[] の各要素から hotelBasicInfo 相当を取り出す(新旧APIレスポンス形状の両対応)。 */
    private static function pickBasicInfo(mixed $entry): ?array
    {
        if (!is_array($entry)) {
            return null;
        }
        // 新形式(2026年5月刷新後): hotelNo等がエントリ直下にフラットに入っている
        if (isset($entry['hotelNo']) || isset($entry['hotelName'])) {
            return $entry;
        }
        if (isset($entry['hotelBasicInfo'])) {
            return $entry['hotelBasicInfo'];
        }
        $blocks = $entry['hotel'] ?? $entry;
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (is_array($block) && isset($block['hotelBasicInfo'])) {
                    return $block['hotelBasicInfo'];
                }
            }
        }
        return null;
    }
}
