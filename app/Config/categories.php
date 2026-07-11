<?php
declare(strict_types=1);

/**
 * カテゴリ辞書と絞り込み条件(依頼P1-3 / P1-5)。
 *
 * 文章は書かない方針のため、各カテゴリは「固定ラベル + 楽天API検索条件」だけで構成する。
 * ここに1行足すだけでカテゴリが増える(自動増殖)。
 *
 * 各カテゴリ:
 *   key      … URL・計測に使う識別子(/category/{key})
 *   label    … 表示名(固定ラベル。文章生成はしない)
 *   query    … 楽天KeywordHotelSearchの keyword
 *   cardType … トップでの見せ方 'poster'|'wide'
 *   feature  … トップの上部タブに出すか(主要カテゴリの目印)
 */

return [
    'categories' => [
        'roten' => [
            'label' => '露天風呂のある宿', 'query' => '露天風呂', 'cardType' => 'wide', 'tag' => 'rotenburo', 'feature' => true,
        ],
        'solo' => [
            'label' => '一人旅にちょうどいい宿', 'query' => '一人旅 プラン', 'cardType' => 'poster', 'tag' => 'solo', 'feature' => true,
        ],
        'view' => [
            'label' => '絶景・オーシャンビュー', 'query' => 'オーシャンビュー 絶景', 'cardType' => 'wide', 'tag' => 'view', 'feature' => true,
        ],
        'onsen' => [
            'label' => '温泉が自慢の宿', 'query' => '温泉 かけ流し', 'cardType' => 'wide', 'tag' => 'onsen', 'feature' => true,
        ],
        'anniversary' => [
            'label' => '記念日におすすめ', 'query' => '記念日 プラン', 'cardType' => 'poster', 'tag' => 'couple', 'feature' => true,
        ],
        'sauna' => [
            'label' => 'サウナのある宿', 'query' => 'サウナ', 'cardType' => 'poster', 'tag' => 'sauna', 'feature' => true,
        ],
        'gourmet' => [
            'label' => '食事が評判の宿', 'query' => '料理自慢 会席', 'cardType' => 'wide', 'tag' => 'food', 'feature' => true,
        ],
        'family' => [
            'label' => '家族・子連れで安心', 'query' => '家族 子連れ 歓迎', 'cardType' => 'poster', 'tag' => 'family', 'feature' => false,
        ],
        'pet' => [
            'label' => 'ペットと泊まれる宿', 'query' => 'ペット可 同伴', 'cardType' => 'poster', 'tag' => '', 'feature' => false,
        ],
        'pickup' => [
            'label' => '送迎ありの宿', 'query' => '送迎 無料送迎', 'cardType' => 'poster', 'tag' => '', 'feature' => false,
        ],
        'hideaway' => [
            'label' => '静かな隠れ家', 'query' => '隠れ家 離れ', 'cardType' => 'wide', 'tag' => 'quiet', 'feature' => false,
        ],
    ],

    /**
     * 価格帯フィルタ(P1-5)。返却データの最低料金(minCharge)で絞り込む。
     * ※ サイト上に価格の数値は出さない(送客Cookie方針を維持)。バンドのラベルのみ表示。
     * max=0 は上限なし。
     */
    'price_bands' => [
        ['key' => 'u10000', 'label' => 'お手頃',       'min' => 1,     'max' => 9999],
        ['key' => 'm10_20', 'label' => 'スタンダード', 'min' => 10000, 'max' => 19999],
        ['key' => 'm20_30', 'label' => 'ちょっと贅沢', 'min' => 20000, 'max' => 29999],
        ['key' => 'o30000', 'label' => '記念日・特別', 'min' => 30000, 'max' => 0],
    ],

    // 一覧ページの1ページ表示件数
    'per_page' => 20,
];
