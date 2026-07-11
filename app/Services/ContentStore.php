<?php
declare(strict_types=1);

namespace App\Services;

/**
 * リポジトリ同梱コンテンツ(content/)の読み出し。
 *
 * 生成物をDBではなく content/ に置く理由:
 *  - Render無料枠の揮発FSでも消えない(リポジトリが保存先)
 *  - 「ローカルで生成 → 目視レビュー → git push」= push が承認行為を兼ねる
 *  - 生成の根拠(取得元URL・取得日)がファイルに残り、後から監査できる
 *
 * content/official/{hotelNo}.json … この宿のこだわり(公式サイトより)
 * content/features/{slug}.json    … 特集ページ("published": true のみ公開)
 */
final class ContentStore
{
    /**
     * 公式サイト由来のこだわり文。
     * @return ?array{catch:string,points:array<int,array{title:string,body:string}>,sourceUrl:string,fetchedAt:string}
     */
    public function officialSummary(string $hotelNo): ?array
    {
        $hotelNo = preg_replace('/\D/', '', $hotelNo) ?? '';
        if ($hotelNo === '') {
            return null;
        }
        $file = BASE_PATH . '/content/official/' . $hotelNo . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || empty($data['points'])) {
            return null;
        }
        return $data;
    }

    /**
     * 公開済みの特集一覧(publishedAt 降順)。
     * @return array<int,array<string,mixed>>
     */
    public function publishedFeatures(): array
    {
        $out = [];
        foreach (glob(BASE_PATH . '/content/features/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && !empty($data['published']) && !empty($data['slug'])) {
                $out[] = $data;
            }
        }
        usort($out, static fn ($a, $b) => strcmp((string) ($b['publishedAt'] ?? ''), (string) ($a['publishedAt'] ?? '')));
        return $out;
    }

    /** @return ?array<string,mixed> 公開済みの特集をslugで取得 */
    public function feature(string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
            return null;
        }
        $file = BASE_PATH . '/content/features/' . $slug . '.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        return (is_array($data) && !empty($data['published'])) ? $data : null;
    }
}
