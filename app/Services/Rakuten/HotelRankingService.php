<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

/**
 * HotelRanking を呼び出し、「人気旅館」「高評価旅館」等の横スクロール群を構築する。
 */
final class HotelRankingService
{
    private RakutenApiClient $client;

    public function __construct(?RakutenApiClient $client = null)
    {
        $this->client = $client ?? new RakutenApiClient();
    }

    /**
     * @param string|null $genre 'onsen' など(未指定で全体ランキング)
     * @return array<int,array<string,mixed>>
     */
    public function ranking(?string $genre = null, int $limit = 12): array
    {
        $params = [];
        if ($genre !== null) {
            $params['genre'] = $genre;
        }
        $response = $this->client->get('ranking', $params);

        $groups = $response['Rankings'] ?? [];

        $cards = [];
        foreach ((array) $groups as $group) {
            // 新形式(2026年5月刷新後): 各ジャンルグループ直下に "hotels" (フラットな宿情報の配列)
            $hotels = $group['hotels'] ?? null;
            if (is_array($hotels)) {
                foreach ($hotels as $basic) {
                    if (is_array($basic)) {
                        $cards[] = HotelCardMapper::fromBasicInfo($basic);
                        if (count($cards) >= $limit) {
                            return $cards;
                        }
                    }
                }
                continue;
            }

            // 旧形式フォールバック: hotel[0].hotelBasicInfo / hotelBasicInfo 直下
            $basic = $group['hotel'][0]['hotelBasicInfo']
                ?? $group['hotelBasicInfo']
                ?? null;
            if (is_array($basic)) {
                $cards[] = HotelCardMapper::fromBasicInfo($basic);
            }
            if (count($cards) >= $limit) {
                break;
            }
        }
        return $cards;
    }
}
