<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\View;

/**
 * 閲覧履歴。初期版はガワのみ返し、中身はJSがLocalStorageから描画する。
 */
final class HistoryController
{
    public function index(): void
    {
        View::render('history/index', [
            'seo' => [
                'title'       => '閲覧履歴｜' . config('app.name'),
                'description' => '最近見た旅館の履歴です。',
                'canonical'   => (string) config('app.url') . '/history',
                'image'       => '',
                'jsonLd'      => null,
            ],
            'pageCss'   => 'collection',
            'pageJs'    => 'history',
            'activeTab' => 'history',
        ]);
    }
}
