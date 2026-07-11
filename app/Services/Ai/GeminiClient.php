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
}
