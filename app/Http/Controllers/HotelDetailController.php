<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\FaqService;
use App\Services\HighlightService;
use App\Services\Rakuten\HotelDetailService;
use App\Services\Rakuten\InstagramService;
use App\Services\RecommendationService;
use App\Services\ReviewCommentService;
use App\Services\SeoService;
use App\Services\SurroundingsService;
use App\Support\HttpNotFoundException;
use App\Support\View;

/**
 * 旅館詳細ページ。
 * リクエスト受け取り → Service呼び出し → Viewへ渡す のみ。ロジックは持たない。
 *
 * 改修(Phase 1/2/3):
 *  - 周辺情報はバッチ取り込み済みならDBの値を使う(リクエスト時のOverpass呼び出しを回避)
 *  - レコメンドは RailBuilder で重複除去し、seen を JSレールへ引き継ぐ
 *  - おすすめポイント(HighlightService)/ FAQ(FaqService)/ 泉質辞書 を追加
 */
final class HotelDetailController
{
    private HotelDetailService $detail;
    private RecommendationService $recommend;
    private SeoService $seo;
    private InstagramService $instagram;
    private ReviewCommentService $reviewComment;
    private SurroundingsService $surroundings;
    private HighlightService $highlights;
    private FaqService $faq;

    public function __construct(
        ?HotelDetailService $detail = null,
        ?RecommendationService $recommend = null,
        ?SeoService $seo = null,
        ?InstagramService $instagram = null,
        ?ReviewCommentService $reviewComment = null,
        ?SurroundingsService $surroundings = null,
        ?HighlightService $highlights = null,
        ?FaqService $faq = null
    ) {
        $this->detail = $detail ?? new HotelDetailService();
        $this->recommend = $recommend ?? new RecommendationService();
        $this->seo = $seo ?? new SeoService();
        $this->instagram = $instagram ?? new InstagramService();
        $this->reviewComment = $reviewComment ?? new ReviewCommentService();
        $this->surroundings = $surroundings ?? new SurroundingsService();
        $this->highlights = $highlights ?? new HighlightService();
        $this->faq = $faq ?? new FaqService();
    }

    public function show(string $hotelNo): void
    {
        $hotel = $this->detail->find($hotelNo);
        if ($hotel === null) {
            throw new HttpNotFoundException('この旅館は見つかりませんでした。');
        }

        // どの取得経路(DB/API・軽量/詳細)でも安全に描画できるよう既定値を補完
        $hotel += [
            'hotelName' => '', 'hotelSpecial' => '', 'catchCopy' => '', 'aboutLeisure' => '',
            'hotelImageUrls' => [], 'roomImageUrls' => [], 'reviewAverage' => 0.0, 'reviewCount' => 0,
            'axis' => [], 'address' => '', 'area' => '', 'access' => '', 'nearestStation' => '',
            'parkingInformation' => '', 'checkinTime' => '', 'checkoutTime' => '', 'minCharge' => 0,
            'latitude' => null, 'longitude' => null, 'affiliateUrl' => '', 'facilities' => [],
            'tags' => [], 'badges' => [], 'fetchedAt' => 0,
            'bathType' => '', 'bathQuality' => '', 'bathBenefits' => '',
        ];

        $ctaUrl = ($hotel['affiliateUrl'] ?? '') !== '' ? $hotel['affiliateUrl'] : '#';

        // クチコミ全件ページへのアフィリエイトリンク(CTR用)。無ければ施設ページCTAへ
        $reviewMoreUrl = ($hotel['reviewUrl'] ?? '') !== ''
            ? \App\Services\Rakuten\AffiliateLinkBuilder::wrapUrl((string) $hotel['reviewUrl'])
            : $ctaUrl;

        // 旧形式(日本測地系・秒単位)でDBに入っていた座標も読み出し時に正規化(地図バグ対応)
        if ($hotel['latitude'] !== null && $hotel['longitude'] !== null) {
            $rawLat = (float) $hotel['latitude'];
            $rawLng = (float) $hotel['longitude'];
            $hotel['latitude'] = \App\Services\Rakuten\HotelExtractor::normalizeLat($rawLat, $rawLng);
            $hotel['longitude'] = \App\Services\Rakuten\HotelExtractor::normalizeLng($rawLat, $rawLng);
        }

        // 周辺情報: バッチ取り込み済み(data JSONに保存済み)ならそれを使い、
        // 無ければ従来どおりオンデマンド取得(Phase 1-4 の移行フォールバック)
        $surroundings = is_array($hotel['surroundings'] ?? null)
            ? $hotel['surroundings']
            : $this->surroundings->forHotel($hotel);

        // レコメンド(RailBuilder で重複除去済み)
        $reco = $this->recommend->forDetail($hotel);

        // おすすめポイント(3-2)と FAQ(3-5)
        $highlights = $this->highlights->forHotel($hotel);
        $faq = $this->faq->forHotel($hotel);

        // ---- コンテンツ増強(メモ後続: 1〜4) ----
        $repo = new \App\Services\Storage\HotelRepository();
        $pref = (string) $hotel['area'];
        $hotelNo9 = (string) $hotel['hotelNo'];

        // 増強1: 軸別評価 × 県平均の比較(レーダーの下にバー+テキストで表示)
        $axisCompare = [];
        $prefAxis = $repo->prefAxisStats($pref);
        foreach ((array) $hotel['axis'] as $key => $a) {
            $avg = (float) ($prefAxis[$key] ?? 0);
            if ($avg <= 0) {
                continue;
            }
            $axisCompare[] = [
                'label' => (string) $a['label'],
                'value' => (float) $a['value'],
                'avg'   => $avg,
                'diff'  => round((float) $a['value'] - $avg, 1),
            ];
        }

        // 増強2: 近くの宿との比較テーブル(自宿 + 近隣3軒)
        $nearbyCompare = [];
        if ($hotel['latitude'] !== null && $hotel['longitude'] !== null) {
            $nearbyCompare = array_slice(
                $repo->nearby((float) $hotel['latitude'], (float) $hotel['longitude'], $hotelNo9, 10.0, 3),
                0,
                3
            );
        }

        // 増強3: 市区町村の文脈(「◯◯市の宿事情」)
        $city = \App\Services\Storage\HotelRepository::cityFromAddress((string) $hotel['address'], $pref);
        $cityStats = $city !== '' ? $repo->cityStats($city, $hotelNo9) : ['cnt' => 0];

        // 増強4: こんな人におすすめ
        $audiences = $this->highlights->audiences($hotel);

        // 増強6: 評価から見る強みと注意点(マイナス面も正直に書く=信頼性)
        $prosCons = $this->highlights->prosCons($hotel);

        // 増強①: 温泉地ガイド(宿名・住所から温泉地を判定して解説を表示)
        $onsenArea = (new \App\Services\OnsenAreaService())->forHotel($hotel);

        // この宿のこだわり(公式サイト由来・content/official にあれば表示)
        $officialSummary = (new \App\Services\ContentStore())->officialSummary($hotelNo9);

        // 泉質辞書(3-3): bathQuality に泉質名が含まれれば解説を添える
        $onsenNote = null;
        $quality = (string) $hotel['bathQuality'];
        if ($quality !== '') {
            foreach ((array) config('onsen_glossary', []) as $key => $def) {
                if (mb_strpos($quality, (string) $key) !== false) {
                    $onsenNote = $def;
                    break;
                }
            }
        }

        // タグチップ(内部リンク): タグキー → ラベル。
        // リンク先はタグに対応するカテゴリページ(逆引き)。カテゴリが無いタグはリンクなしで表示。
        $tagDefs = (array) config('taxonomy.tags', []);
        $catService = new \App\Services\CategoryService();
        $tagChips = [];
        foreach ((array) $hotel['tags'] as $t) {
            $label = (string) ($tagDefs[$t]['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $cat = $catService->findByTag((string) $t);
            $tagChips[] = [
                'key'   => (string) $t,
                'label' => $label,
                'href'  => $cat !== null ? '/category/' . rawurlencode((string) $cat['key']) : '',
            ];
        }

        // パンくず(SEO・セクション6)
        // 注: BreadcrumbList では最後の項目以外に item(URL)が必須。
        //     href を空にすると構造化データが無効になるため、必ずURLを持たせる。
        $breadcrumb = [
            ['label' => 'ホーム', 'href' => '/'],
            ['label' => 'カテゴリ', 'href' => '/categories'],
        ];
        if (($hotel['area'] ?? '') !== '') {
            $breadcrumb[] = [
                'label' => (string) $hotel['area'],
                'href'  => '/search?pref=' . rawurlencode((string) $hotel['area']),
            ];
        }
        // 最後の項目(この宿)は自ページなので href なし = リンクにしない
        $breadcrumb[] = ['label' => (string) ($hotel['hotelName'] ?? ''), 'href' => ''];

        View::render('detail/index', [
            'hotel'   => $hotel,
            'rails'   => $reco['rails'],
            'seenHotelNos' => array_values(array_unique(array_merge($reco['seen'], [(string) $hotel['hotelNo']]))),
            'instagramUrl' => $this->instagram->hashtagUrl($hotel), // メモ1: ハッシュタグ検索へのディープリンク
            'reviewComment' => $this->reviewComment->forHotel($hotel), // 3-2 自動生成コメント
            'highlights'    => $highlights,   // 3-2 おすすめポイント(県内相対評価)
            'faq'           => $faq,          // 3-5 よくある質問
            'reviewMoreUrl' => $reviewMoreUrl, // クチコミ全件(楽天側)へのCTRリンク
            'onsenNote'     => $onsenNote,    // 3-3 泉質の一般解説
            'tagChips'      => $tagChips,     // 内部リンク用タグチップ
            'axisCompare'   => $axisCompare,  // 増強1: 軸別×県平均
            'nearbyCompare' => $nearbyCompare,// 増強2: 近隣比較テーブル
            'city'          => $city,         // 増強3: 市区町村
            'cityStats'     => $cityStats,    // 増強3: 市区町村統計
            'audiences'     => $audiences,    // 増強4: こんな人におすすめ
            'prosCons'      => $prosCons,     // 増強6: 強みと注意点
            'onsenArea'     => $onsenArea,    // 増強①: 温泉地ガイド
            'officialSummary' => $officialSummary, // こだわり(公式サイトより)
            'surroundings'  => $surroundings, // 4 周辺情報
            'breadcrumb'    => $breadcrumb,
            'xsell'   => [
                'rentacar_url' => (string) config('xsell.rentacar_url'),
                'gasshuku_url' => (string) config('xsell.gasshuku_url'),
            ],
            'seo'     => $this->seo->forDetail($hotel, $highlights, $faq),
            'pageCss' => 'detail',
            'pageJs'  => 'detail',
            'activeTab' => 'home',
            // sticky CTA(レイアウトが参照)
            'showStickyCta' => true,
            'stickyCtaUrl'  => $ctaUrl,
        ]);
    }
}
