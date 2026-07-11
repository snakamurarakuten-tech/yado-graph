<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

use App\Services\Storage\HotelRepository;

/**
 * 宿詳細の取得。DB(セクション2)を最優先で参照し、未構築なら楽天APIへフォールバック。
 * 生フィールドの吸収は HotelExtractor に委譲する。
 */
final class HotelDetailService
{
    private RakutenApiClient $client;
    private HotelExtractor $extractor;
    private HotelRepository $repo;

    public function __construct(
        ?RakutenApiClient $client = null,
        ?HotelExtractor $extractor = null,
        ?HotelRepository $repo = null
    ) {
        $this->client = $client ?? new RakutenApiClient();
        $this->extractor = $extractor ?? new HotelExtractor();
        $this->repo = $repo ?? new HotelRepository();
    }

    /**
     * @return array<string,mixed>|null 見つからなければ null
     */
    /** DBヒットでもこの日数を超えていたらバッチの優先再取得キューに積む */
    private const STALE_DAYS = 14;

    public function find(string $hotelNo): ?array
    {
        // 1) DB優先(バッチ取り込み済み・write-through済みの宿はAPIを一切叩かない)
        $fromDb = $this->repo->find($hotelNo);
        if ($fromDb !== null) {
            // 鮮度チェック: 古くてもリクエスト内では同期再取得しない(stale-while-revalidate)。
            // キューに積んでおき、夜間バッチ(--detail)が優先的に更新する。
            if ((int) ($fromDb['fetchedAt'] ?? 0) < time() - self::STALE_DAYS * 86400) {
                $this->queueRefresh($hotelNo);
            }
            return $fromDb;
        }

        // 2) APIフォールバック(DB未取り込みの宿のみ)
        $blocks = $this->fetchBlocks($hotelNo);
        if ($blocks === null) {
            return null;
        }
        $hotel = $this->extractor->fromBlocks($blocks);
        if ($hotel === []) {
            return null;
        }

        // 3) write-through: 掲載条件を満たす宿だけDBへ保存(メモ2・3)。
        //    条件外の宿もページ表示自体はする(SEO側で noindex)。
        $hotel['fetchedAt'] = $hotel['fetchedAt'] ?? time();
        try {
            if ((new \App\Services\HotelFilterService())->isEligible($hotel)) {
                $this->repo->upsert($hotel);
            }
        } catch (\Throwable) {
            // 保存に失敗しても表示は続行
        }
        return $hotel;
    }

    /** バッチが優先的に詳細化するためのキュー(1行1hotelNo)。 */
    private function queueRefresh(string $hotelNo): void
    {
        $file = BASE_PATH . '/storage/db/refresh-queue.txt';
        @file_put_contents($file, $hotelNo . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 楽天 HotelDetailSearch を叩き、hotels[0]['hotel'] のブロック配列を返す。
     * バッチ取り込みからも使う。
     * @return array<int,mixed>|null
     */
    public function fetchBlocks(string $hotelNo): ?array
    {
        $response = $this->client->get('hotel_detail', [
            'hotelNo'      => $hotelNo,
            'responseType' => 'large',
        ]);
        $hotels = $response['hotels'] ?? null;
        if (!is_array($hotels) || $hotels === []) {
            return null;
        }
        $blocks = $hotels[0]['hotel'] ?? $hotels[0] ?? null;
        return is_array($blocks) ? $blocks : null;
    }
}
