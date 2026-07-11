<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Rakuten\HotelRankingService;
use App\Services\Rakuten\KeywordSearchService;

/**
 * 「今日の1軒」シャッフル(依頼C-9)。
 * 条件を問わずランダムに1軒を選び、その詳細ページへ302リダイレクトする。
 * トップの「今日の1軒」ボタンからのGET遷移先。
 */
final class SurpriseController
{
    private HotelRankingService $ranking;
    private KeywordSearchService $keyword;

    public function __construct(?HotelRankingService $ranking = null, ?KeywordSearchService $keyword = null)
    {
        $this->ranking = $ranking ?? new HotelRankingService();
        $this->keyword = $keyword ?? new KeywordSearchService();
    }

    public function go(): void
    {
        $pool = $this->ranking->ranking(null, 30);

        // ランキングが空/少ないときはランダムなテーマ検索で候補を足す
        if (count($pool) < 5) {
            $shelves = (array) config('taxonomy.shelves', []);
            if ($shelves !== []) {
                $pick = $shelves[array_rand($shelves)];
                $extra = $this->keyword->searchByKeyword((string) ($pick['search'] ?? '温泉 旅館'), 20);
                $pool = array_merge($pool, $extra);
            }
        }

        $candidates = array_values(array_filter(
            $pool,
            static fn ($c) => ($c['hotelNo'] ?? '') !== ''
        ));

        if ($candidates === []) {
            // 候補が全く無い(APIキー未設定など)場合はトップへ戻す
            $this->redirect('/');
            return;
        }

        $chosen = $candidates[array_rand($candidates)];
        $this->redirect('/hotel/' . rawurlencode((string) $chosen['hotelNo']));
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
    }
}
