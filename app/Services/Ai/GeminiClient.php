<?php
declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Gemini API の薄いクライアント(無料枠運用が前提)。
 * - モデルは env GEMINI_MODEL(既定 gemini-2.5-flash)。無料枠のRPD/RPMは
 *   モデルごとに異なるため、呼び出し側でスリープと --limit を入れて使う
 * - JSONモード(responseMimeType)で構造化出力を受け取る
 * - APIキー未設定なら null を返すだけ(サイト本体は一切影響を受けない)
 */
final class GeminiClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /**
     * @return ?array JSONモードの出力をデコードした配列。失敗時 null
     */
    public function generateJson(string $prompt, ?string $systemInstruction = null): ?array
    {
        $apiKey = (string) config('ai.gemini_api_key');
        if ($apiKey === '') {
            fwrite(STDERR, "GEMINI_API_KEY が未設定です\n");
            return null;
        }
        $model = (string) config('ai.gemini_model', 'gemini-2.5-flash');

        $body = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature'      => 0.7,
                'responseMimeType' => 'application/json',
            ],
        ];
        if ($systemInstruction !== null) {
            $body['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        $ch = curl_init(sprintf(self::ENDPOINT, rawurlencode($model)));
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($code === 429) {
            throw new AiQuotaException('Gemini quota exceeded: ' . substr((string) $res, 0, 500));
        }
        if (!is_string($res) || $code !== 200) {
            fwrite(STDERR, "Gemini API error (HTTP {$code}): " . substr((string) $res, 0, 300) . "\n");
            return null;
        }
        $data = json_decode($res, true);
        $text = (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if ($text === '') {
            return null;
        }
        $json = json_decode($text, true);
        return is_array($json) ? $json : null;
    }

    /**
     * Google検索グラウンディング付きの生成(公式サイトURLの発見専用)。
     *
     * 用途を「URL候補の発見」に限定する理由:
     *  - Custom Search JSON API が新規顧客に閉鎖されたため、その代替
     *  - グラウンディング結果そのものは公開コンテンツに使わない
     *    (記事本文は、発見したURLを自前で取得・検証したテキストから
     *     検索なしの通常生成で書く。従来の安全設計を維持)
     *
     * 無料枠の担保: 日次カウンター(GROUNDING_DAILY_CAP・既定100)で自動停止。
     *
     * @return ?array{text:string,links:array<int,string>} 失敗時 null
     */
    public function generateGrounded(string $prompt): ?array
    {
        $apiKey = (string) config('ai.gemini_api_key');
        if ($apiKey === '') {
            fwrite(STDERR, "GEMINI_API_KEY が未設定です\n");
            return null;
        }
        if (!$this->consumeGroundingQuota()) {
            fwrite(STDERR, '本日のグラウンディング上限(' . $this->groundingCap() . ")に達したため停止します\n");
            return null;
        }
        $model = (string) config('ai.gemini_model', 'gemini-2.5-flash');

        // 注意: グラウンディング使用時は responseMimeType(JSONモード)と併用しない
        $body = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'tools'    => [['google_search' => new \stdClass()]],
            'generationConfig' => ['temperature' => 0.2],
        ];

        $ch = curl_init(sprintf(self::ENDPOINT, rawurlencode($model)));
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code === 429) {
            throw new AiQuotaException('Gemini grounding quota exceeded: ' . substr((string) $res, 0, 500));
        }
        if (!is_string($res) || $code !== 200) {
            fwrite(STDERR, "Gemini grounding error (HTTP {$code}): " . substr((string) $res, 0, 300) . "\n");
            return null;
        }
        $data = json_decode($res, true);
        return is_array($data) ? self::parseGrounded($data) : null;
    }

    /**
     * グラウンディング応答から本文と参照URL群を取り出す(テスト可能な純関数)。
     * URLの候補は 1) groundingChunks の web.uri(リダイレクトプロキシURL。
     * 取得時に実URLへ解決される) 2) 本文中に書かれた素のURL の順で集める。
     *
     * @return array{text:string,links:array<int,string>}
     */
    public static function parseGrounded(array $data): array
    {
        $cand = (array) ($data['candidates'][0] ?? []);
        $text = '';
        foreach ((array) ($cand['content']['parts'] ?? []) as $part) {
            $text .= (string) ($part['text'] ?? '');
        }
        $links = [];
        foreach ((array) ($cand['groundingMetadata']['groundingChunks'] ?? []) as $chunk) {
            $uri = (string) ($chunk['web']['uri'] ?? '');
            if ($uri !== '') {
                $links[] = $uri;
            }
        }
        if (preg_match_all('~https?://[^\s"\'()<>\]]+~u', $text, $m)) {
            foreach ($m[0] as $u) {
                $links[] = rtrim($u, '.,、。');
            }
        }
        return ['text' => $text, 'links' => array_values(array_unique($links))];
    }

    /** グラウンディングの残り回数(表示用)。 */
    public function groundingRemainingToday(): int
    {
        $f = $this->groundingCounterFile();
        $used = is_file($f) ? (int) file_get_contents($f) : 0;
        return max(0, $this->groundingCap() - $used);
    }

    private function groundingCap(): int
    {
        return max(1, (int) config('ai.grounding_daily_cap', 100));
    }

    private function consumeGroundingQuota(): bool
    {
        $f = $this->groundingCounterFile();
        $used = is_file($f) ? (int) file_get_contents($f) : 0;
        if ($used >= $this->groundingCap()) {
            return false;
        }
        @file_put_contents($f, (string) ($used + 1), LOCK_EX);
        return true;
    }

    private function groundingCounterFile(): string
    {
        return BASE_PATH . '/storage/logs/grounding-usage-' . date('Y-m-d') . '.txt';
    }
}
