<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\View;

/**
 * 運営者情報・プライバシーポリシー等の静的ページ(セクション6)。
 * ステマ規制対応のアフィリエイト明記もこれらのページ＋フッターで担保する。
 */
final class StaticPageController
{
    public function about(): void
    {
        View::render('static/about', [
            'seo' => $this->seo('運営者情報', 'YADO GRAPHの運営方針と、楽天トラベルアフィリエイトの利用について。', '/about'),
            'pageCss'   => 'static',
            'pageJs'    => 'static',
            'activeTab' => 'home',
        ]);
    }

    public function privacy(): void
    {
        View::render('static/privacy', [
            'seo' => $this->seo('プライバシーポリシー', 'YADO GRAPHのプライバシーポリシー。アクセス解析・Cookie・アフィリエイトの取り扱いについて。', '/privacy'),
            'pageCss'   => 'static',
            'pageJs'    => 'static',
            'activeTab' => 'home',
        ]);
    }

    private function seo(string $title, string $desc, string $path): array
    {
        return [
            'title'       => $title . '｜' . config('app.name'),
            'description' => $desc,
            'canonical'   => (string) config('app.url') . $path,
            'image'       => '',
            'jsonLd'      => null,
        ];
    }
}
