<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\View;

/**
 * お気に入り一覧。初期版はガワのみ返し、中身はJSがLocalStorageから描画する。
 */
final class FavoriteController
{
    public function index(): void
    {
        View::render('favorites/index', [
            'seo' => [
                'title'       => 'お気に入り｜' . config('app.name'),
                'description' => '気になった旅館をまとめて見返せます。',
                'canonical'   => (string) config('app.url') . '/favorites',
                'image'       => '',
                'jsonLd'      => null,
            ],
            'pageCss'   => 'collection',
            'pageJs'    => 'favorites',
            'activeTab' => 'favorites',
        ]);
    }
}
