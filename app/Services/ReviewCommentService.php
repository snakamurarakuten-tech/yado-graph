<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 軸別評価からクチコミ風コメントを合成する(セクション3-2)。
 * LLMは使わず、突出軸の検出 → 語彙変換 → テンプレ合成 → 禁止語フィルタ。
 * hotelNoをシードにするので同じ宿では常に同じ文、宿ごとには多様になる。
 */
final class ReviewCommentService
{
    /**
     * @param array<string,mixed> $hotel 'axis' => [axisKey => ['label','value']], 'hotelNo'
     * @return string 生成コメント(軸データが無ければ空文字)
     */
    public function forHotel(array $hotel): string
    {
        $axis = (array) ($hotel['axis'] ?? []);
        if (count($axis) < 3) {
            return '';
        }

        $cfg = (array) config('review_templates');
        $values = [];
        foreach ($axis as $k => $a) {
            $values[$k] = (float) ($a['value'] ?? 0);
        }
        $mean = array_sum($values) / max(1, count($values));

        // 突出軸(平均+delta以上)を高い順に
        $standoutDelta = (float) ($cfg['standout_delta'] ?? 0.3);
        arsort($values);
        $strong = [];
        foreach ($values as $k => $v) {
            if ($v >= $mean + $standoutDelta) {
                $strong[] = $k;
            }
        }
        // 突出が無ければ最高軸を1つ強みとして使う
        if ($strong === []) {
            $strong = [array_key_first($values)];
        }
        $strong = array_slice($strong, 0, 2);

        // 弱い軸(平均-delta以下)を1つ
        $weakDelta = (float) ($cfg['weak_delta'] ?? 0.4);
        asort($values);
        $weakKey = '';
        foreach ($values as $k => $v) {
            if ($v <= $mean - $weakDelta && !in_array($k, $strong, true)) {
                $weakKey = $k;
                break;
            }
        }

        // 決定的RNG(hotelNoシード)。禁止語に当たったらオフセットして数回リトライ。
        $seedBase = crc32((string) ($hotel['hotelNo'] ?? '') . '|' . implode(',', $strong));
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $text = $this->compose($cfg, $strong, $weakKey, $seedBase + $attempt);
            if (!$this->hasBanned($cfg, $text)) {
                return $text;
            }
        }
        // それでも当たる場合は締めを外して最小構成で返す
        return $this->compose($cfg, $strong, '', $seedBase, true);
    }

    private function compose(array $cfg, array $strong, string $weakKey, int $seed, bool $minimal = false): string
    {
        $kw = (array) ($cfg['axis_keywords'] ?? []);
        $tpl = (array) ($cfg['templates'] ?? []);

        $pick = function (array $arr, int $s): string {
            return $arr === [] ? '' : (string) $arr[$s % count($arr)];
        };
        $kwOf = function (string $axisKey, int $s) use ($kw, $pick): string {
            return $pick((array) ($kw[$axisKey] ?? []), $s);
        };

        $kw1 = $kwOf($strong[0], $seed);
        $parts = [];

        if (count($strong) >= 2) {
            $kw2 = $kwOf($strong[1], $seed + 7);
            $parts[] = str_replace(['{kw1}', '{kw2}'], [$kw1, $kw2], $pick((array) $tpl['two'], $seed));
        } else {
            $parts[] = str_replace('{kw1}', $kw1, $pick((array) $tpl['one'], $seed));
        }

        // 弱い軸への言及(minimal時は省略)
        if (!$minimal && $weakKey !== '' && ($seed % 3 === 0)) {
            $weakWord = $kwOf($weakKey, $seed + 3);
            $parts[] = str_replace(['{weak}', '{kw1}'], [$weakWord, $kw1], $pick((array) $tpl['weak'], $seed + 1));
        }

        // 締め(minimal時は省略)
        if (!$minimal) {
            $parts[] = $pick((array) $tpl['closing'], $seed + 5);
        }

        return trim(implode('', array_filter($parts)));
    }

    private function hasBanned(array $cfg, string $text): bool
    {
        foreach ((array) ($cfg['banned_words'] ?? []) as $w) {
            if ($w !== '' && mb_strpos($text, (string) $w) !== false) {
                return true;
            }
        }
        return false;
    }
}
