<?php
declare(strict_types=1);

/**
 * タグ・ムードの共通語彙(タクソノミー)。
 *
 * ここ1か所を編集するだけで、以下すべてに反映される設計:
 *   - バッジ自動付与(BadgeService / badges.php)
 *   - 感情訴求型の棚タイトル生成(ShelfService)
 *   - 初回オンボーディング(Netflix風の好み選択)の選択肢
 *   - 履歴/お気に入りに基づくパーソナライズ(JS側は data-tags を読むだけ)
 *
 * 楽天APIは「露天風呂フラグ」等の構造化フラグを常に返すわけではないため、
 * hotelName / hotelSpecial / access などのテキストに対するキーワード一致で
 * タグを推定する(ルールベース)。閾値・語彙は後から自由に足せる。
 */

return [
    /**
     * 施設に付与しうるタグ。
     *  - label:    表示名
     *  - keywords: このいずれかがテキストに含まれればタグ成立(部分一致・大小無視)
     *  - search:   このタグでレール/類似を引くときの楽天キーワード検索語
     */
    'tags' => [
        'onsen' => [
            'label'    => '温泉',
            'keywords' => ['温泉', '湯宿', '名湯', '源泉', 'かけ流し', '掛け流し', '湯本'],
            'search'   => '温泉 旅館',
        ],
        'rotenburo' => [
            'label'    => '露天風呂',
            'keywords' => ['露天', '露天風呂', '貸切風呂', '貸切露天', '半露天'],
            'search'   => '露天風呂',
        ],
        'sauna' => [
            'label'    => 'サウナ',
            'keywords' => ['サウナ', 'ととのう', 'ロウリュ'],
            'search'   => 'サウナ 旅館',
        ],
        'food' => [
            'label'    => '食事自慢',
            'keywords' => ['会席', '懐石', '料理自慢', '美食', 'グルメ', '海の幸', '山の幸', '蟹', 'カニ', '和牛', 'ブランド牛'],
            'search'   => '料理自慢 旅館',
        ],
        'view' => [
            'label'    => '絶景',
            'keywords' => ['絶景', 'オーシャンビュー', '海一望', '海を望む', '眺望', '眺め', '露天からの', '天空', 'リゾート', '湖畔'],
            'search'   => '絶景 旅館',
        ],
        'quiet' => [
            'label'    => '静けさ・隠れ家',
            'keywords' => ['隠れ家', '静か', 'しずか', '離れ', '全室離れ', '大人', '静寂', '喧騒を離れ', 'プライベート'],
            'search'   => '隠れ家 旅館',
        ],
        'couple' => [
            'label'    => '記念日',
            'keywords' => ['記念日', 'アニバーサリー', 'カップル', '二人', 'ふたり', 'ハネムーン', '誕生日'],
            'search'   => '記念日 旅館',
        ],
        'family' => [
            'label'    => '家族・子連れ',
            'keywords' => ['家族', 'ファミリー', '子連れ', 'お子様', '子供', 'キッズ'],
            'search'   => '家族 旅館 子連れ',
        ],
        'solo' => [
            'label'    => '一人旅',
            'keywords' => ['一人', 'ひとり', 'おひとり', '1人', '独り', 'ソロ'],
            'search'   => '一人旅 旅館',
        ],
        'design' => [
            'label'    => 'デザイン',
            'keywords' => ['デザイナーズ', 'モダン', 'スタイリッシュ', 'リノベ', 'ラグジュアリー', '洗練'],
            'search'   => 'デザイナーズ 宿',
        ],
    ],

    /**
     * 初回オンボーディング(Netflix風「好きなジャンルを選んでください」)の2階層。
     *  - axis:   'purpose'(目的軸) / 'preference'(こだわり軸)
     *  - key:    保存キー(タグと対応。tagsに無い purpose 系は独自キーでOK)
     *  - label:  カード表題
     *  - emoji:  カードの装飾(任意)
     *  - tags:   この好みが効いたときに加点する tags[] のキー群
     *  - search: この好みからレールを引くときの楽天キーワード
     */
    'onboarding' => [
        'min_select' => 2, // Netflix同様、最低選択数
        'cards' => [
            // --- 目的軸 ---
            ['axis' => 'purpose', 'key' => 'escape',  'label' => '現実逃避・一人時間', 'emoji' => '🌙', 'tags' => ['quiet', 'solo', 'onsen'], 'search' => '一人旅 隠れ家 温泉'],
            ['axis' => 'purpose', 'key' => 'couple',  'label' => 'カップル・記念日',   'emoji' => '💛', 'tags' => ['couple', 'view'],          'search' => '記念日 旅館'],
            ['axis' => 'purpose', 'key' => 'family',  'label' => '家族・子連れ',       'emoji' => '👨‍👩‍👧', 'tags' => ['family'],                'search' => '家族 旅館 子連れ'],
            ['axis' => 'purpose', 'key' => 'friends', 'label' => '友人・グループ',     'emoji' => '🍻', 'tags' => ['food', 'sauna'],           'search' => 'グループ 旅館'],
            // --- こだわり軸 ---
            ['axis' => 'preference', 'key' => 'onsen',  'label' => '露天風呂・温泉重視', 'emoji' => '♨️', 'tags' => ['rotenburo', 'onsen'], 'search' => '露天風呂'],
            ['axis' => 'preference', 'key' => 'food',   'label' => '食事重視',           'emoji' => '🍽️', 'tags' => ['food'],             'search' => '料理自慢 旅館'],
            ['axis' => 'preference', 'key' => 'sauna',  'label' => 'サウナ充実',         'emoji' => '🧖', 'tags' => ['sauna'],            'search' => 'サウナ 旅館'],
            ['axis' => 'preference', 'key' => 'quiet',  'label' => '静けさ・隠れ家感',   'emoji' => '🍃', 'tags' => ['quiet'],            'search' => '隠れ家 旅館'],
            ['axis' => 'preference', 'key' => 'view',   'label' => '絶景・ロケーション', 'emoji' => '🏔️', 'tags' => ['view'],             'search' => '絶景 旅館'],
        ],
    ],

    /**
     * 感情訴求型の棚(ムード×タグ)。ShelfServiceが表示のたびに
     * ランダムに数本ピックし、並び順もシャッフルする。
     *  - title:   棚タイトル(感情に訴えるコピー)
     *  - tag:     この棚が対応する tags[] キー(パーソナライズ時の突き合わせに使用)
     *  - search:  楽天キーワード検索語
     */
    'shelves' => [
        ['title' => '現実逃避したい人へ',        'tag' => 'quiet',     'search' => '隠れ家 温泉'],
        ['title' => '一人で行っても浮かない宿',  'tag' => 'solo',      'search' => '一人旅 歓迎 旅館'],
        ['title' => 'とにかく静かに過ごしたい夜に', 'tag' => 'quiet',   'search' => '静か 離れ 宿'],
        ['title' => '露天風呂にただ浸かりたい',   'tag' => 'rotenburo', 'search' => '露天風呂 かけ流し'],
        ['title' => 'ごはんが目当ての旅',        'tag' => 'food',      'search' => '料理自慢 旅館'],
        ['title' => '「ととのう」を旅先で',      'tag' => 'sauna',     'search' => 'サウナ 旅館'],
        ['title' => '記念日をちゃんとする',      'tag' => 'couple',    'search' => '記念日 旅館'],
        ['title' => '窓の外がぜんぶ景色の宿',    'tag' => 'view',      'search' => '絶景 オーシャンビュー 宿'],
        ['title' => '家族でとる、なんでもない時間', 'tag' => 'family',  'search' => '家族 旅館 子連れ'],
        ['title' => '温泉に入りに行くだけの旅',  'tag' => 'onsen',     'search' => '温泉 名湯 旅館'],
    ],
];
