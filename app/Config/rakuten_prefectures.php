<?php
declare(strict_types=1);

/**
 * 楽天トラベルAPIの都道府県コード(largeClassCode=japan 固定 + middleClassCode)。
 *
 * GetAreaClass API が新API(2026刷新)で使えないアプリ設定のためのフォールバック。
 * SimpleHotelSearch は middleClassCode(都道府県)だけでも全宿を返すため、
 * この47件を総当たりすれば全国を網羅できる。
 *
 * middleClassCode は楽天トラベルの地区コード仕様に基づく(ローマ字表記)。
 */

return [
    ['middle' => 'hokkaido',  'name' => '北海道'],
    ['middle' => 'aomori',    'name' => '青森県'],
    ['middle' => 'iwate',     'name' => '岩手県'],
    ['middle' => 'miyagi',    'name' => '宮城県'],
    ['middle' => 'akita',     'name' => '秋田県'],
    ['middle' => 'yamagata',  'name' => '山形県'],
    ['middle' => 'fukushima', 'name' => '福島県'],
    ['middle' => 'ibaraki',   'name' => '茨城県'],
    ['middle' => 'tochigi',   'name' => '栃木県'],
    ['middle' => 'gunma',     'name' => '群馬県'],
    ['middle' => 'saitama',   'name' => '埼玉県'],
    ['middle' => 'chiba',     'name' => '千葉県'],
    ['middle' => 'tokyo',     'name' => '東京都'],
    ['middle' => 'kanagawa',  'name' => '神奈川県'],
    ['middle' => 'niigata',   'name' => '新潟県'],
    ['middle' => 'toyama',    'name' => '富山県'],
    ['middle' => 'ishikawa',  'name' => '石川県'],
    ['middle' => 'fukui',     'name' => '福井県'],
    ['middle' => 'yamanashi', 'name' => '山梨県'],
    ['middle' => 'nagano',    'name' => '長野県'],
    ['middle' => 'gifu',      'name' => '岐阜県'],
    ['middle' => 'shizuoka',  'name' => '静岡県'],
    ['middle' => 'aichi',     'name' => '愛知県'],
    ['middle' => 'mie',       'name' => '三重県'],
    ['middle' => 'shiga',     'name' => '滋賀県'],
    ['middle' => 'kyoto',     'name' => '京都府'],
    ['middle' => 'osaka',     'name' => '大阪府'],
    ['middle' => 'hyogo',     'name' => '兵庫県'],
    ['middle' => 'nara',      'name' => '奈良県'],
    ['middle' => 'wakayama',  'name' => '和歌山県'],
    ['middle' => 'tottori',   'name' => '鳥取県'],
    ['middle' => 'shimane',   'name' => '島根県'],
    ['middle' => 'okayama',   'name' => '岡山県'],
    ['middle' => 'hiroshima', 'name' => '広島県'],
    ['middle' => 'yamaguchi', 'name' => '山口県'],
    ['middle' => 'tokushima', 'name' => '徳島県'],
    ['middle' => 'kagawa',    'name' => '香川県'],
    ['middle' => 'ehime',     'name' => '愛媛県'],
    ['middle' => 'kochi',     'name' => '高知県'],
    ['middle' => 'fukuoka',   'name' => '福岡県'],
    ['middle' => 'saga',      'name' => '佐賀県'],
    ['middle' => 'nagasaki',  'name' => '長崎県'],
    ['middle' => 'kumamoto',  'name' => '熊本県'],
    ['middle' => 'oita',      'name' => '大分県'],
    ['middle' => 'miyazaki',  'name' => '宮崎県'],
    ['middle' => 'kagoshima', 'name' => '鹿児島県'],
    ['middle' => 'okinawa',   'name' => '沖縄県'],
];
