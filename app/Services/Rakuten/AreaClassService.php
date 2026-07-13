<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

/**
 * GetAreaClass API で 都道府県(large) → 市区町村等(middle) → 小エリア(small) の
 * 全階層コードを取得し、SimpleHotelSearch 用のエリアコード一覧に平坦化する。
 *
 * 初期投入(エリア総当たり列挙・bin/fetch-hotels.php --enumerate-area)専用。
 * 日次cronのキーワード列挙(KeywordSearchService)とは別ルートで、テーマに縛られず
 * 全国を機械的にカバーするために使う。
 *
 * 注意: 楽天APIのレスポンス形状は 2026年5月刷新の影響を受けている可能性があるため、
 * 新旧いくつかの想定形状を再帰的に走査して吸収している。初回実行時は
 * `allSmallAreas()` の件数(通常は数百〜千程度)をログで必ず目視確認すること。
 * 想定外の形状だった場合は0件になるので、その際は実際のJSONを1度ダンプして
 * walk() のキー名を合わせ直す必要がある。
 */
final class AreaClassService
{
    private RakutenApiClient $client;

    public function __construct(?RakutenApiClient $client = null)
    {
        $this->client = $client ?? new RakutenApiClient();
    }

    /**
     * @return array<int,array{large:string,middle:string,small:string,label:string}>
     */
    public function allSmallAreas(): array
    {
        $raw = $this->client->get('area_class', []);
        $out = [];
        $this->walk($raw, $out);

        // 重複除去(large+middle+smallの組み合わせでユニーク化)
        $seen = [];
        $unique = [];
        foreach ($out as $a) {
            $key = $a['large'] . '/' . $a['middle'] . '/' . $a['small'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $a;
        }

        // GetAreaClass が使えない(新API刷新でアプリ未対応=API Configuration not found)
        // 場合は空になる。その際は47都道府県の固定辞書にフォールバックする。
        // SimpleHotelSearch は middleClassCode(都道府県)だけで最大3,000件返せるため、
        // small を空にして県単位で総当たりすれば全国を網羅できる。
        if ($unique === []) {
            foreach ((array) config('rakuten_prefectures', []) as $pref) {
                $unique[] = [
                    'large'  => 'japan',
                    'middle' => (string) $pref['middle'],
                    'small'  => '', // 県単位検索(smallClassCode省略)
                    'label'  => (string) $pref['name'],
                ];
            }
        }
        return $unique;
    }

    /**
     * レスポンス形状の違いを吸収しつつ再帰的に走査し、smallClassCode を持つ末端を集める。
     * @param array<int,array{large:string,middle:string,small:string,label:string}> $out
     */
    private function walk(
        mixed $node,
        array &$out,
        string $large = '',
        string $largeName = '',
        string $middle = '',
        string $middleName = ''
    ): void {
        if (!is_array($node)) {
            return;
        }

        // パターンA: { largeClasses: [...] } のようなラッパーキー配下を素通りして再帰
        foreach (['largeClasses', 'middleClasses', 'smallClasses', 'detailClasses', 'areaClasses'] as $wrapperKey) {
            if (isset($node[$wrapperKey]) && is_array($node[$wrapperKey])) {
                foreach ($node[$wrapperKey] as $item) {
                    $this->walk($item, $out, $large, $largeName, $middle, $middleName);
                }
            }
        }

        // パターンB(旧形式): { largeClass: [ {largeClassCode,largeClassName}, {middleClasses:[...]} ] }
        foreach (['largeClass', 'middleClass', 'smallClass'] as $classKey) {
            if (isset($node[$classKey]) && is_array($node[$classKey])) {
                $codeCol = $classKey . 'Code';
                $nameCol = $classKey . 'Name';
                $curLarge = $large;
                $curLargeName = $largeName;
                $curMiddle = $middle;
                $curMiddleName = $middleName;
                foreach ($node[$classKey] as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    if (isset($entry[$codeCol])) {
                        if ($classKey === 'largeClass') {
                            $curLarge = (string) $entry[$codeCol];
                            $curLargeName = (string) ($entry[$nameCol] ?? '');
                        }
                        if ($classKey === 'middleClass') {
                            $curMiddle = (string) $entry[$codeCol];
                            $curMiddleName = (string) ($entry[$nameCol] ?? '');
                        }
                        if ($classKey === 'smallClass' && $curLarge !== '' && $curMiddle !== '') {
                            $out[] = [
                                'large' => $curLarge,
                                'middle' => $curMiddle,
                                'small' => (string) $entry[$codeCol],
                                'label' => $curLargeName . $curMiddleName . (string) ($entry[$nameCol] ?? ''),
                            ];
                        }
                    }
                    $this->walk($entry, $out, $curLarge, $curLargeName, $curMiddle, $curMiddleName);
                }
            }
        }

        // パターンC(新形式想定): フラットに large/middle/smallClassCode が同階層に並ぶ
        if (isset($node['smallClassCode'], $node['largeClassCode'], $node['middleClassCode'])) {
            $out[] = [
                'large' => (string) $node['largeClassCode'],
                'middle' => (string) $node['middleClassCode'],
                'small' => (string) $node['smallClassCode'],
                'label' => (string) ($node['areaName'] ?? $node['smallClassName'] ?? ''),
            ];
        }

        // 数値添字配列ならそのまま再帰(上のどのパターンにも当たらない中間ラッパー対策)
        if (array_is_list($node)) {
            foreach ($node as $item) {
                $this->walk($item, $out, $large, $largeName, $middle, $middleName);
            }
        }
    }
}
