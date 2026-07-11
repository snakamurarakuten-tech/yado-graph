<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\OnsenAreaService;
use App\Services\Storage\HotelRepository;
use App\Support\HttpNotFoundException;
use App\Support\View;

/**
 * エリア(温泉地)ページ(増強②)。
 *  - /area/{key}        温泉地トップ 例: /area/kusatsu(「草津温泉 旅館」を狙う)
 *  - /area/{key}/{tag}  温泉地×テーマ 例: /area/kusatsu/rotenburo(「草津温泉 露天風呂」)
 *
 * 品質管理: 該当宿が MIN_HOTELS 未満の組み合わせはページ自体を生成しない(404)。
 * 薄いページの量産を避け、sitemap にも該当ありのURLだけ載せる。
 */
final class AreaController
{
    /** これ未満の軒数のページは生成しない(thin content回避) */
    public const MIN_HOTELS = 3;

    /** サブページに使うテーマタグ(taxonomy.tags のキー) */
    public const SUB_TAGS = ['rotenburo', 'food', 'view', 'quiet', 'solo'];

    private OnsenAreaService $areas;
    private HotelRepository $repo;

    public function __construct(?OnsenAreaService $areas = null, ?HotelRepository $repo = null)
    {
        $this->areas = $areas ?? new OnsenAreaService();
        $this->repo = $repo ?? new HotelRepository();
    }

    /** 温泉地一覧ハブ(/areas)。掲載3軒以上の温泉地を県ごとに並べる。 */
    public function index(): void
    {
        $counts = $this->repo->onsenAreaCounts();
        $byPref = [];
        foreach ($this->areas->all() as $a) {
            $cnt = (int) ($counts[$a['name']] ?? 0);
            if ($cnt >= self::MIN_HOTELS && $a['key'] !== '') {
                $byPref[$a['pref']][] = ['key' => $a['key'], 'name' => $a['name'], 'count' => $cnt];
            }
        }
        View::render('area/index', [
            'byPref' => $byPref,
            'seo' => [
                'title'       => '温泉地から宿をさがす - ' . config('app.name'),
                'description' => '全国の主要温泉地ごとに、旅館をランキング形式で紹介します。',
                'canonical'   => rtrim((string) config('app.url'), '/') . '/areas',
                'image'       => '',
                'jsonLd'      => null,
            ],
            'pageCss'   => 'category',
            'pageJs'    => 'search',
            'activeTab' => 'search',
        ]);
    }

    public function show(string $key): void
    {
        $this->render($key, '');
    }

    public function showTag(string $key, string $tag): void
    {
        $this->render($key, $tag);
    }

    private function render(string $key, string $tag): void
    {
        $area = $this->areas->byKey($key);
        if ($area === null) {
            throw new HttpNotFoundException('この温泉地ページは見つかりませんでした。');
        }

        // テーマタグの検証(定義外タグのURLは404)
        $tagDefs = (array) config('taxonomy.tags', []);
        $tagLabel = '';
        if ($tag !== '') {
            if (!in_array($tag, self::SUB_TAGS, true) || empty($tagDefs[$tag]['label'])) {
                throw new HttpNotFoundException('このページは見つかりませんでした。');
            }
            $tagLabel = (string) $tagDefs[$tag]['label'];
        }

        // 宿一覧(クチコミ数順=ランキング)。thin回避のため最少軒数を満たさなければ404
        $query = [
            'onsenArea'       => $area['name'],
            'sort'            => 'reviews',
            'perPage'         => 30,
            'excludeNoReview' => true,
        ];
        if ($tag !== '') {
            $query['tags'] = [$tag];
        }
        $result = $this->repo->search($query);
        $items = $result['items'];
        if (count($items) < self::MIN_HOTELS) {
            throw new HttpNotFoundException('この温泉地は準備中です。');
        }

        // 統計(温泉地全体。タグページでも全体статを見せる)
        $stats = $this->repo->onsenAreaStats($area['name']);

        // テーマチップ(該当 MIN_HOTELS 軒以上のタグのみ表示=リンク先が必ず生きる)
        $tagChips = [];
        foreach (self::SUB_TAGS as $t) {
            $label = (string) ($tagDefs[$t]['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $cnt = (int) $this->repo->search([
                'onsenArea' => $area['name'], 'tags' => [$t],
                'excludeNoReview' => true, 'perPage' => 1,
            ])['total'];
            if ($cnt >= self::MIN_HOTELS) {
                $tagChips[] = ['key' => $t, 'label' => $label, 'count' => $cnt];
            }
        }

        // エリアFAQ(データから自動生成)
        $faq = [];
        if ($stats['medCharge'] > 0) {
            $faq[] = [
                'q' => $area['name'] . 'の宿泊料金の相場は?',
                'a' => '当サイト掲載の旅館では、1泊 ' . number_format($stats['medCharge']) . '円が料金の中央値です(時期・プランにより変動します)。',
            ];
        }
        foreach ($tagChips as $c) {
            if ($c['key'] === 'rotenburo') {
                $faq[] = [
                    'q' => $area['name'] . 'で露天風呂のある宿は?',
                    'a' => '掲載' . $stats['cnt'] . '軒のうち' . $c['count'] . '軒に露天風呂の情報があります。',
                ];
            }
        }
        if ($items !== []) {
            $top = $items[0];
            $faq[] = [
                'q' => $area['name'] . 'で最もクチコミが集まっている宿は?',
                'a' => '「' . (string) $top['hotelName'] . '」(クチコミ' . number_format((int) $top['reviewCount']) . '件・評価' . number_format((float) $top['reviewAverage'], 1) . ')です。',
            ];
        }

        // SEO
        $base = rtrim((string) config('app.url'), '/');
        $year = date('Y');
        $canonical = $base . '/area/' . rawurlencode($key) . ($tag !== '' ? '/' . rawurlencode($tag) : '');
        $h1 = $tag !== ''
            ? "{$area['name']}の{$tagLabel}が評判の宿"
            : "{$area['name']}の旅館おすすめランキング";
        $title = $tag !== ''
            ? "{$area['name']}の{$tagLabel}の宿おすすめ" . count($items) . "選【{$year}年】- " . config('app.name')
            : "{$area['name']}の旅館おすすめランキング" . count($items) . "選【{$year}年】- " . config('app.name');

        $jsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type'    => 'BreadcrumbList',
                'itemListElement' => array_values(array_filter([
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'ホーム', 'item' => $base . '/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => '温泉地', 'item' => $base . '/areas'],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $area['name'], 'item' => $base . '/area/' . rawurlencode($key)],
                    $tag !== '' ? ['@type' => 'ListItem', 'position' => 4, 'name' => $tagLabel, 'item' => $canonical] : null,
                ])),
            ],
            [
                '@context' => 'https://schema.org',
                '@type'    => 'ItemList',
                'name'     => $h1,
                'itemListElement' => array_map(static fn (array $it, int $i): array => [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'name'     => (string) $it['hotelName'],
                    'url'      => $base . '/hotel/' . rawurlencode((string) $it['hotelNo']),
                ], array_slice($items, 0, 10), array_keys(array_slice($items, 0, 10))),
            ],
        ];
        if (count($faq) >= 2) {
            $jsonLd[] = [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => array_map(static fn (array $f): array => [
                    '@type'          => 'Question',
                    'name'           => $f['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                ], $faq),
            ];
        }

        View::render('area/show', [
            'area'      => $area,
            'tag'       => $tag,
            'tagLabel'  => $tagLabel,
            'h1'        => $h1,
            'items'     => $items,
            'stats'     => $stats,
            'tagChips'  => $tagChips,
            'faq'       => $faq,
            'seo' => [
                'title'       => $title,
                'description' => mb_substr($area['desc'], 0, 80) . ' 掲載' . count($items) . '軒をクチコミ数順にランキングで紹介。',
                'canonical'   => $canonical,
                'image'       => (string) ($items[0]['imageUrl'] ?? ''),
                'jsonLd'      => $jsonLd,
            ],
            'pageCss'   => 'category',
            'pageJs'    => 'search', // reveal等の共通初期化を流用(検索グリッドが無ければ何もしない)
            'activeTab' => 'search',
        ]);
    }
}
