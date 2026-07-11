<?php
declare(strict_types=1);

namespace App\Services;

/**
 * title / description / canonical / OGP / JSON-LD を生成する。
 * 詳細ページは最低限(title, canonical, OGP, Hotel + AggregateRating)。
 * オリジナル文章生成はせず hotelSpecial を description の元にする(文字数調整のみ)。
 */
final class SeoService
{
    /**
     * @param array<string,mixed> $hotel
     * @param array<int,array{icon:string,text:string}> $highlights おすすめポイント(3-2)
     * @param array<int,array{q:string,a:string}> $faq よくある質問(3-5)
     * @return array<string,mixed>
     */
    public function forDetail(array $hotel, array $highlights = [], array $faq = []): array
    {
        $appName = (string) config('app.name');
        $name = (string) ($hotel['hotelName'] ?? '');
        $area = (string) ($hotel['area'] ?? '');
        $canonical = (string) config('app.url') . '/hotel/' . rawurlencode((string) ($hotel['hotelNo'] ?? ''));
        $image = ($hotel['hotelImageUrls'][0] ?? '') ?: '';

        // 検索意図語(魅力・評価・写真)を含めた title
        $title = trim("{$name}の魅力・評価・写真まとめ｜{$area}の旅館 - {$appName}");

        // D-10: 楽天の説明文を流用しない一意な description。
        // 改修3-2: 相対評価ハイライトがあれば冒頭に組み込み、このサイト独自の一文にする。
        $description = $this->buildDescription($hotel, $highlights);

        // Hotel の構造化データ。
        // ※ AggregateRating は削除(改修6-3)。楽天収集の評価を自サイトの構造化データとして
        //   出すのはGoogleのセルフサービングレビュー・ポリシーに抵触するリスクがあるため、
        //   リッチリザルトは FAQPage で狙う。ページ本文の★表示は問題ないので維持。
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type'    => 'Hotel',
            'name'     => $name,
            'url'      => $canonical,
            'address'  => ['@type' => 'PostalAddress', 'addressRegion' => $area]
                + (($hotel['address'] ?? '') !== '' ? ['streetAddress' => (string) $hotel['address']] : []),
            'image'    => array_slice((array) ($hotel['hotelImageUrls'] ?? []), 0, 5),
        ];
        if (($hotel['latitude'] ?? null) !== null && ($hotel['longitude'] ?? null) !== null) {
            $jsonLd['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (string) $hotel['latitude'],
                'longitude' => (string) $hotel['longitude'],
            ];
        }

        // 改修3-5: FAQが2問以上あるとき FAQPage を併記(リッチリザルト狙い)
        $jsonLdList = [$jsonLd];
        if (count($faq) >= 2) {
            $jsonLdList[] = [
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => array_map(static fn (array $f) => [
                    '@type'          => 'Question',
                    'name'           => $f['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                ], $faq),
            ];
        }

        $out = [
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'image'       => $image,
            'jsonLd'      => count($jsonLdList) === 1 ? $jsonLdList[0] : $jsonLdList,
        ];
        // メモ2: クチコミ0件の宿は「表示はするがインデックスさせない」
        if ((int) ($hotel['reviewCount'] ?? 0) <= 0) {
            $out['robots'] = 'noindex,follow';
        }
        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    public function forTop(): array
    {
        $appName = (string) config('app.name');
        return [
            'title'       => "{$appName}｜泊まりたくなる旅館を、眺めて見つける",
            'description' => '眺めるだけでも楽しい、写真主役の旅館カタログ。露天風呂・記念日・一人旅など、気分から次の宿を探せます。',
            'canonical'   => (string) config('app.url') . '/',
            'image'       => '',
            'jsonLd'      => [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => $appName,
                'url'      => (string) config('app.url'),
            ],
        ];
    }

    private function trimText(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 1) . '…';
    }

    /**
     * D-10: 説明文テンプレート。
     * 「{都道府県}にある評価{評価点}の宿。{設備タグ}が魅力。」を基本形に、
     * 取れた変数だけを差し込んで一意な文章を組み立てる(楽天の説明文は流用しない)。
     *
     * @param array<string,mixed> $hotel
     * @param array<int,array{icon:string,text:string}> $highlights
     */
    private function buildDescription(array $hotel, array $highlights = []): string
    {
        $area   = trim((string) ($hotel['area'] ?? ''));
        $name   = trim((string) ($hotel['hotelName'] ?? ''));
        $rating = (float) ($hotel['reviewAverage'] ?? 0);
        $count  = (int) ($hotel['reviewCount'] ?? 0);

        // タグキー → 表示ラベル(taxonomy参照)。最大3つ。
        $tagDefs   = (array) config('taxonomy.tags', []);
        $tagLabels = [];
        foreach ((array) ($hotel['tags'] ?? []) as $t) {
            $label = (string) ($tagDefs[$t]['label'] ?? '');
            if ($label !== '') {
                $tagLabels[] = $label;
            }
        }
        $tagLabels = array_slice(array_unique($tagLabels), 0, 3);

        // 冒頭: 「{都道府県}にある{評価}の宿「{宿名}」。」
        $head = $area !== '' ? "{$area}にある" : '';
        if ($rating > 0) {
            $head .= '評価' . number_format($rating, 1) . 'の宿';
        } else {
            $head .= '宿';
        }
        if ($name !== '') {
            $head .= "「{$name}」";
        }
        $head .= '。';

        // 設備タグの魅力訴求
        $middle = $tagLabels !== [] ? implode('・', $tagLabels) . 'が魅力。' : '';

        // 改修3-2: 相対評価ハイライト(県平均比較など)があれば、このサイト独自の一文として優先
        $unique = '';
        if ($highlights !== []) {
            $unique = rtrim((string) $highlights[0]['text'], '。') . '。';
        }

        // クチコミ件数(あれば信頼性の補強)
        $tail = $count > 0 ? "クチコミ{$count}件。写真を眺めて泊まりたい宿を見つけよう。" : '写真を眺めて泊まりたい宿を見つけよう。';

        return $this->trimText($head . $unique . $middle . $tail, 140);
    }
}