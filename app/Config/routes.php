<?php
declare(strict_types=1);

use App\Http\Controllers\ApiController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\HotelDetailController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\SurpriseController;
use App\Http\Controllers\TopController;
use App\Http\Controllers\TrackController;

/**
 * ルーティングテーブル。
 * Laravel移行時は routes/web.php の Route::get() へ1対1で移し替える。
 */
return [
    ['GET', '/',                     [TopController::class, 'index']],
    ['GET', '/hotel/{hotelNo}',      [HotelDetailController::class, 'show']],
    ['GET', '/favorites',            [FavoriteController::class, 'index']], // ガワのみ(JSがLocalStorageから描画)
    ['GET', '/history',              [HistoryController::class, 'index']],  // 同上
    ['GET', '/surprise',             [SurpriseController::class, 'go']],    // 今日の1軒(C-9): ランダム1軒へ302
    ['GET', '/categories',           [CategoryController::class, 'index']], // 全カテゴリ一覧(P1-4/P3-10)
    ['GET', '/about',                [StaticPageController::class, 'about']],   // 運営者情報(セクション6)
    ['GET', '/privacy',              [StaticPageController::class, 'privacy']], // プライバシーポリシー(セクション6)
    ['GET', '/category/{key}',       [CategoryController::class, 'show']],  // カテゴリ単体(P1-4)
    ['GET', '/api/category/{key}',   [CategoryController::class, 'feed']],  // もっと見る/無限スクロール(P1-4)
    ['GET', '/api/recommend',        [ApiController::class, 'recommend']],  // タグ傾向→類似カード(C-7/C-8)
    ['GET', '/api/hotels',           [ApiController::class, 'hotels']],     // hotelNo一括→カード(改修4-4 共有)
    ['POST', '/api/track',           [TrackController::class, 'hit']],      // クリック計測(P0-2)
    ['GET', '/features',             [FeatureController::class, 'index']], // 特集一覧
    ['GET', '/features/{slug}',      [FeatureController::class, 'show']],  // 特集本文
    ['GET', '/areas',                [AreaController::class, 'index']],    // 温泉地一覧ハブ
    ['GET', '/area/{key}',           [AreaController::class, 'show']],     // 温泉地ページ(増強②)
    ['GET', '/area/{key}/{tag}',     [AreaController::class, 'showTag']],  // 温泉地×テーマ(増強②)
    ['GET', '/search',               [SearchController::class, 'index']],  // フリーワード検索(改修4-1)
    ['GET', '/api/search',           [SearchController::class, 'feed']],   // 検索のもっと見る(改修4-1)
    ['GET', '/sitemap.xml',          [SitemapController::class, 'index']],  // SEO: サイトマップ(改修6-1)
    ['GET', '/robots.txt',           [SitemapController::class, 'robots']], // SEO: robots(改修6-1)
];
