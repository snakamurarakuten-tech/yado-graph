<?php
declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Google Custom Search JSON API のクライアント(公式HPの発見用)。
 *
 * 【完全無料の担保】無料枠は100クエリ/日。日次カウンターファイルで
 * DAILY_CAP(90・安全マージン)に達したら自動停止し、課金を構造的に防ぐ。
 */
final class CustomSearchClient
{
    private const ENDPOINT = 'https://www.googleapis.com/customsearch/v1';

    /** 無料枠100/日に対する安全上限 */
    public const DAILY_CAP = 90;

    /**
     * @return array<int,array{link:string,title:string}> 上位候補。上限到達/未設定/失敗は []
     */
    public function search(string $query, int $num = 5): array
    {
        $key = (string) config('ai.cse_key');
        $cx  = (string) config('ai.cse_cx');
        if ($key === '' || $cx === '') {
            fwrite(STDERR, "GOOGLE_CSE_KEY / GOOGLE_CSE_CX が未設定です\n");
            return [];
        }
        if (!$this->consumeQuota()) {
            fwrite(STDERR, '本日のCustom Search無料枠上限(' . self::DAILY_CAP . ")に達したため停止します\n");
            return [];
        }

        $url = self::ENDPOINT . '?' . http_build_query([
            'key' => $key, 'cx' => $cx, 'q' => $query,
            'num' => max(1, min($num, 10)), 'hl' => 'ja', 'gl' => 'jp',
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($res) || $code !== 200) {
            fwrite(STDERR, "Custom Search error (HTTP {$code})\n");
            return [];
        }
        $data = json_decode($res, true);
        $out = [];
        foreach ((array) ($data['items'] ?? []) as $item) {
            $out[] = ['link' => (string) ($item['link'] ?? ''), 'title' => (string) ($item['title'] ?? '')];
        }
        return $out;
    }

    /** 残クエリ数(表示用)。 */
    public function remainingToday(): int
    {
        return max(0, self::DAILY_CAP - $this->usedToday());
    }

    private function usedToday(): int
    {
        $f = $this->counterFile();
        return is_file($f) ? (int) file_get_contents($f) : 0;
    }

    private function consumeQuota(): bool
    {
        $used = $this->usedToday();
        if ($used >= self::DAILY_CAP) {
            return false;
        }
        @file_put_contents($this->counterFile(), (string) ($used + 1), LOCK_EX);
        return true;
    }

    private function counterFile(): string
    {
        return BASE_PATH . '/storage/logs/cse-usage-' . date('Y-m-d') . '.txt';
    }
}
