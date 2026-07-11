<?php
declare(strict_types=1);

namespace App\Services\Ai;

/**
 * AI生成文のバリデーター(公開前の機械チェック)。
 * ここを通らない出力は破棄する — 「書かない」が常に安全側。
 *
 *  - 数値チェック: 出力中のすべての数字列が、入力素材(公式HP本文+DB事実)に
 *    存在すること(ハルシネーションで最も実害が出るのは数値)
 *  - 禁止表現: 効能の断定・最上級の断定・価格/キャンペーン(陳腐化と誤りの温床)
 *  - 長さ: 空・極端に短い出力を弾く
 */
final class AiOutputValidator
{
    private const BANNED_PATTERNS = [
        '/(必ず|絶対に?)(治|効|痩)/u',
        '/(日本一|世界一|最安値|No\.?1|ナンバーワン)/iu',
        '/(完治|治癒|治療効果)/u',
        '/(円|¥|税込|税抜)/u',          // 価格は書かせない(変動・誤りの温床)
        '/(キャンペーン|クーポン|割引|セール|OFF)/iu',
        '/(閉業|休業|廃業)/u',            // 営業状態の言及は誤爆リスクが高すぎる
    ];

    /**
     * @param string $output 生成テキスト(結合済み)
     * @param string $sourceText 入力素材(公式HP本文+DB事実の連結)
     * @return array{ok:bool,reason:string}
     */
    public function validate(string $output, string $sourceText): array
    {
        $output = trim($output);
        if (mb_strlen($output) < 40) {
            return ['ok' => false, 'reason' => 'too_short'];
        }
        foreach (self::BANNED_PATTERNS as $p) {
            if (preg_match($p, $output)) {
                return ['ok' => false, 'reason' => 'banned:' . $p];
            }
        }
        // 数値照合: 出力中の数字列(2桁以上)が素材に存在するか。
        // 1桁は「3つの湯船」等の言い換えで頻出し誤検知が多いため対象外。
        preg_match_all('/\d{2,}/u', $output, $m);
        $normalizedSource = (string) preg_replace('/[,、\s]/u', '', $sourceText);
        foreach (array_unique($m[0]) as $num) {
            if (!str_contains($normalizedSource, (string) $num)) {
                return ['ok' => false, 'reason' => 'unverified_number:' . $num];
            }
        }
        return ['ok' => true, 'reason' => ''];
    }
}
