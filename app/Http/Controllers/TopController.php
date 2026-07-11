<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CategoryService;
use App\Services\Rakuten\HotelRankingService;
use App\Services\SeoService;
use App\Services\Storage\HotelRepository;
use App\Support\View;

/**
 * トップページ。Netflix風の横スクロールレールを用途別に複数並べる。
 * DB(セクション2)があればDBから、無ければAPIから組み立てる。
 */
final class TopController
{
    private HotelRankingService $ranking;
    private CategoryService $categories;
    private SeoService $seo;
    private HotelRepository $repo;

    public function __construct(
        ?HotelRankingService $ranking = null,
        ?CategoryService $categories = null,
        ?SeoService $seo = null,
        ?HotelRepository $repo = null
    ) {
        $this->ranking = $ranking ?? new HotelRankingService();
        $this->categories = $categories ?? new CategoryService();
        $this->seo = $seo ?? new SeoService();
        $this->repo = $repo ?? new HotelRepository();
    }

    public function index(): void
    {
        $dbReady = $this->repo->count() > 0;
        $popular = $dbReady
            ? $this->repo->search(['sort' => 'popular', 'perPage' => 20, 'excludeNoReview' => true])['items']
            : $this->ranking->ranking(null, 12);

        // 改修 Phase 2: RailBuilder でページ内の重複を除去する。
        // 「人気の宿」→ カテゴリ別レールの順に追加し、後のレールから重複分が抜ける。
        // 重複除去後に3件未満のレールは自動で非表示(スカスカ防止)。
        $builder = new \App\Services\RailBuilder();
        if ($popular !== []) {
            $builder->add('popular', '人気の宿', $popular, [
                'cardType' => 'poster',
                'tag'      => '',
                'moreHref' => '/categories',
            ], min: 3, max: 12);
        }

        // カテゴリ別レール(P1-3)。各レールに「一覧はこちら」= /category/{key}
        foreach ($this->categories->topRails(6) as $rail) {
            $builder->add(
                (string) ($rail['key'] ?? ''),
                (string) ($rail['title'] ?? ''),
                (array) ($rail['items'] ?? []),
                array_diff_key($rail, ['key' => 1, 'title' => 1, 'items' => 1])
            );
        }
        $rails = $builder->all();

        // P2-8: 「今日の一軒」は日付シードで決定的に選出(同日中は不変)。
        $seed = crc32(date('Y-m-d'));
        if ($dbReady) {
            $hero = $this->repo->daily($seed);
        } else {
            $heroPool = $popular;
            if ($heroPool === []) {
                foreach ($rails as $r) {
                    foreach ($r['items'] as $it) {
                        $heroPool[] = $it;
                    }
                }
            }
            $hero = $this->dailyPick($heroPool);
        }

        // 上部タブ(スクロール連動)用のセクション見出し一覧
        $sections = array_map(
            static fn (array $r) => ['key' => (string) ($r['key'] ?? ''), 'title' => (string) ($r['title'] ?? '')],
            $rails
        );

        View::render('top/index', [
            'rails'      => $rails,
            'hero'       => $hero,
            'sections'   => $sections,
            // JSレール(あなた好み/最近見た)がサーバー描画済みの宿を除外できるよう共有(Phase 2-3)
            'seenHotelNos' => array_values(array_filter(array_merge(
                $builder->seenHotelNos(),
                [$hero !== null ? (string) ($hero['hotelNo'] ?? '') : '']
            ), static fn (string $n): bool => $n !== '')),
            'onboarding' => (array) config('taxonomy.onboarding', []),
            'seo'        => $this->seo->forTop(),
            'pageCss'    => 'top',
            'pageJs'     => 'top',
            'activeTab'  => 'home',
        ]);
    }

    /**
     * 日付(YYYY-MM-DD)をシードにしたハッシュで1軒を決定的に選ぶ。
     * @param array<int,array<string,mixed>> $pool
     * @return array<string,mixed>|null
     */
    private function dailyPick(array $pool): ?array
    {
        if ($pool === []) {
            return null;
        }
        $seed = crc32(date('Y-m-d'));
        return $pool[$seed % count($pool)];
    }
}
