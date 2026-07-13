<?php
declare(strict_types=1);

use App\Support\Env;

/**
 * アプリ設定。値は .env から読み込む。
 * Laravel移行時は config/app.php・config/services.php へ分割して移す。
 */
return [
    'app' => [
        'name'  => Env::get('APP_NAME', 'YADO GRAPH'),
        'url'   => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
        'debug' => (bool) Env::get('APP_DEBUG', false),
    ],

    'rakuten' => [
        'app_id'       => (string) Env::get('RAKUTEN_APP_ID', ''),
        'access_key'   => (string) Env::get('RAKUTEN_ACCESS_KEY', ''),
        'affiliate_id' => (string) Env::get('RAKUTEN_AFFILIATE_ID', ''),
        // メモ3 TODO: 施設絞り込み(squeezeCondition)。対応値を確認後に設定(既定は無効)
        'squeeze_condition' => (string) Env::get('RAKUTEN_SQUEEZE_CONDITION', ''),
        // 2026年5月の楽天API刷新で app.rakuten.co.jp から移行(要 accessKey / Origin / Referer)
        'base_url'     => 'https://openapi.rakuten.co.jp/engine/api',
        // 楽天APIは短時間の連続アクセスで一時利用不可になるため、
        // 非キャッシュリクエスト間に挟む最小間隔(ミリ秒)
        'throttle_ms'  => 600,
        'endpoints' => [
            'hotel_detail'   => '/Travel/HotelDetailSearch/20170426',
            'simple_search'  => '/Travel/SimpleHotelSearch/20170426',
            'keyword_search' => '/Travel/KeywordHotelSearch/20170426',
            'ranking'        => '/Travel/HotelRanking/20170426',
            'area_class'     => '/Travel/GetAreaClass/20131024',
        ],
    ],

    'cache' => [
        'path'        => BASE_PATH . '/storage/cache',
        // 一律TTL(分)。0で無効。Laravel移行時は Cache ファサード + config/cache.php へ
        'ttl_minutes' => (int) Env::get('CACHE_TTL_MINUTES', 60),
    ],

    // 自前DB(SQLite)。夜間バッチで取り込み、サイト表示はここを参照(セクション2)。
    'db' => [
        'path' => (string) Env::get('DB_PATH', BASE_PATH . '/storage/db/yado.sqlite'),
    ],

    // 周辺情報(セクション4)。aboutLeisure優先、無ければOverpass(OSM)で補完。
    'surroundings' => [
        'enabled'      => (bool) Env::get('SURROUNDINGS_ENABLED', true),
        'overpass_url' => (string) Env::get('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'radius_m'     => (int) Env::get('SURROUNDINGS_RADIUS_M', 2500), // 2〜3km目安
        'min_spots'    => (int) Env::get('SURROUNDINGS_MIN_SPOTS', 3),   // これ未満は非表示
        'max_spots'    => (int) Env::get('SURROUNDINGS_MAX_SPOTS', 8),
    ],

    // クロスセル(別アフィリエイト)。URLは全旅館共通の静的リンク
    'xsell' => [
        'rentacar_url' => (string) Env::get('RENTACAR_AFFILIATE_URL', '#'), // 未整備のため既定で非表示
        'gasshuku_url' => (string) Env::get('GASSHUKU_AFFILIATE_URL', '#'),
    ],

    // タグ/ムード語彙・バッジ条件は個別ファイルに外出し(後から調整しやすく)
    'taxonomy' => require __DIR__ . '/taxonomy.php',
    'badges'   => require __DIR__ . '/badges.php',
    'review_templates' => require __DIR__ . '/review_templates.php',
    // AI生成(特集・こだわり文)。すべて無料枠での運用が前提
    'ai' => [
        'gemini_api_key' => (string) Env::get('GEMINI_API_KEY', ''),
        'gemini_model'   => (string) Env::get('GEMINI_MODEL', 'gemini-2.5-flash'),
        'cse_key'        => (string) Env::get('GOOGLE_CSE_KEY', ''),
        'cse_cx'         => (string) Env::get('GOOGLE_CSE_CX', ''),
        'notify_email'   => (string) Env::get('NOTIFY_EMAIL', ''),
        // 公式サイトURLの発見手段。'gemini'(既定)=検索グラウンディング /
        // 'cse'=Custom Search JSON API(新規顧客に閉鎖済み。旧アカウント用)
        'search_provider'    => (string) Env::get('SEARCH_PROVIDER', 'gemini'),
        'grounding_daily_cap'=> (int) Env::get('GROUNDING_DAILY_CAP', 100),
    ],

    // 特集の自動生成テーマプール(月別・bin/generate-feature.php --auto が使用)
    'feature_themes' => require __DIR__ . '/feature_themes.php',

    // 泉質の一般解説辞書(改修3-3: 温泉・お風呂セクション)
    'onsen_glossary' => require __DIR__ . '/onsen_glossary.php',
    // 掲載フィルタ(メモ3: ビジネスホテル等の除外)
    'hotel_filters' => require __DIR__ . '/hotel_filters.php',
    // 温泉地ガイド辞書(増強①: 詳細ページの「◯◯温泉について」)
    'onsen_areas' => require __DIR__ . '/onsen_areas.php',
    // カテゴリ辞書・価格帯(P1)
    'categories' => require __DIR__ . '/categories.php',

    // 一覧フィルタの既定(P2-7)
    'filters' => [
        // レビュー0件の宿を一覧から除外するか。false=表示して「レビューなし」を出す
        'exclude_no_review' => (bool) Env::get('EXCLUDE_NO_REVIEW', true), // メモ2: 既定で0件宿を除外
    ],

    // クリック計測(P0-2)。GA4測定ID(任意)＋簡易ファイルログ
    'tracking' => [
        'ga4_id'   => (string) Env::get('GA4_MEASUREMENT_ID', ''),
        'log_path' => BASE_PATH . '/storage/logs',
        'enabled'  => (bool) Env::get('CLICK_LOG_ENABLED', true),
    ],

    /**
     * Instagram埋め込み(依頼B-4)。
     * Instagramグラフ APIのハッシュタグ/位置情報検索にはビジネス/クリエイター
     * アカウントと長期アクセストークンが必要。未設定なら詳細ページのSNS枠は自動的に
     * 非表示になる(APIキー未設定でも画面が壊れない方針を踏襲)。
     */
    'instagram' => [
        'access_token'    => (string) Env::get('INSTAGRAM_ACCESS_TOKEN', ''),
        // グラフAPIのハッシュタグ検索に使う「あなたのIGビジネスアカウントID」
        'ig_user_id'      => (string) Env::get('INSTAGRAM_IG_USER_ID', ''),
        'graph_base'      => 'https://graph.facebook.com/v19.0',
        // 投稿フィルタ(質の担保)
        'min_like_count'  => (int) Env::get('INSTAGRAM_MIN_LIKES', 30),   // いいね数の下限(取得できる場合のみ)
        'recent_months'   => (int) Env::get('INSTAGRAM_RECENT_MONTHS', 12), // 直近◯ヶ月以内を優先
        'max_posts'       => (int) Env::get('INSTAGRAM_MAX_POSTS', 9),      // カルーセル最大枚数
    ],
];
