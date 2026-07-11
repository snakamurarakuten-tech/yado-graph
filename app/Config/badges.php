<?php
declare(strict_types=1);

/**
 * バッジ自動付与の条件テーブル(依頼B-5)。
 *
 * 上から順に評価し、条件に合致したものを付与する(最大 max_per_hotel 個)。
 * 条件は BadgeService が解釈する。新しいバッジは末尾に足すだけで増やせる。
 *
 * 使える条件キー:
 *   rating_gte / rating_lt        … 評価点(reviewAverage)の下限・上限
 *   reviews_gte / reviews_lt      … クチコミ件数(reviewCount)の下限・上限
 *   price_lt / price_gte          … 最低料金(minCharge, 円)の上限・下限
 *   has_tag                       … タグを1つ以上持つ(taxonomy.tags のキュー)
 *   has_all_tags / has_any_tags   … 複数タグ(AND / OR)
 *
 * tone: 'gold'(主役級) / 'amber'(標準) / 'cool'(設備系) — 見た目の色分け用。
 */

return [
    'max_per_hotel' => 3,

    'rules' => [
        // 高評価なのにクチコミが少ない = まだ知られていない良宿
        [
            'id'    => 'hidden_gem',
            'label' => '隠れた名宿',
            'tone'  => 'gold',
            'when'  => ['rating_gte' => 4.3, 'reviews_lt' => 50],
        ],
        // 露天 × 高評価
        [
            'id'    => 'roten_meishuku',
            'label' => '露天風呂の名宿',
            'tone'  => 'amber',
            'when'  => ['has_tag' => 'rotenburo', 'rating_gte' => 4.0],
        ],
        // サウナ設備あり
        [
            'id'    => 'sauna',
            'label' => 'サウナ充実',
            'tone'  => 'cool',
            'when'  => ['has_tag' => 'sauna'],
        ],
        // 料理自慢 × 高評価
        [
            'id'    => 'gourmet',
            'label' => '食事が評判',
            'tone'  => 'amber',
            'when'  => ['has_tag' => 'food', 'rating_gte' => 4.0],
        ],
        // 圧倒的なクチコミ数 = 定番の人気宿
        [
            'id'    => 'crowd_favorite',
            'label' => 'みんなの定番',
            'tone'  => 'amber',
            'when'  => ['reviews_gte' => 500, 'rating_gte' => 4.0],
        ],
        // 静けさ・隠れ家
        [
            'id'    => 'hideaway',
            'label' => '静かな隠れ家',
            'tone'  => 'cool',
            'when'  => ['has_tag' => 'quiet'],
        ],
        // 絶景ロケーション
        [
            'id'    => 'scenic',
            'label' => '絶景ロケーション',
            'tone'  => 'cool',
            'when'  => ['has_tag' => 'view'],
        ],
        // 高評価そのもの(4.5以上)
        [
            'id'    => 'top_rated',
            'label' => '高評価',
            'tone'  => 'gold',
            'when'  => ['rating_gte' => 4.5, 'reviews_gte' => 30],
        ],
    ],
];
