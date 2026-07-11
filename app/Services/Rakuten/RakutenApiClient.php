<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

use App\Services\CacheService;

/**
 * 楽天トラベルAPIとのHTTP通信・エラーハンドリングの共通基盤。
 * 各 XxxService はこのクライアント経由でエンドポイントを叩き、
 * 生JSONの取得までを担当する(整形は各Serviceの役割)。
 */
final class RakutenApiClient
{
    private CacheService $cache;

    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
    }

    /**
     * 指定エンドポイントを叩いてデコード済み配列を返す。
     * 失敗時は例外を投げず空配列を返す(呼び出し側でフォールバックしやすくする)。
     *
     * @param string $endpointKey config('rakuten.endpoints.*') のキー
     * @param array<string,scalar> $params クエリパラメータ(applicationId等は自動付与)
     * @return array<string,mixed>
     */
    public function get(string $endpointKey, array $params): array
    {
        $appId = (string) config('rakuten.app_id');
        $accessKey = (string) config('rakuten.access_key');
        if ($appId === '' || $accessKey === '') {
            // APIキー未設定時は通信せず空を返す(開発中でも画面が壊れないように)
            return [];
        }

        $endpoint = (string) config("rakuten.endpoints.{$endpointKey}");
        $query = $params + [
            'applicationId' => $appId,
            'format'        => 'json',
            'formatVersion' => 2,
            // 座標を「世界測地系・度単位(WGS84 decimal)」で受け取る。
            // 既定(datumType=2)は日本測地系・秒単位(例: 133166.3)で返ってくるため、
            // そのまま度として扱うと地図・近隣検索・距離計算がすべて壊れる。
            'datumType'     => 1,
        ];
        $affiliateId = (string) config('rakuten.affiliate_id');
        if ($affiliateId !== '') {
            $query['affiliateId'] = $affiliateId;
        }

        $url = rtrim((string) config('rakuten.base_url'), '/') . $endpoint
            . '?' . http_build_query($query);

        // 同一URLはTTL内キャッシュ。ここでレート制限を実質的に吸収する。
        return $this->cache->remember('rakuten:' . $url, function () use ($url, $accessKey): array {
            $this->throttle();
            $raw = $this->request($url, $accessKey);
            if ($raw === null) {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || isset($decoded['error'])) {
                return [];
            }
            return $decoded;
        });
    }

    /** 実HTTPリクエスト。curl優先、無ければ file_get_contents にフォールバック。 */
    private function request(string $url, string $accessKey): ?string
    {
        // 2026年5月の楽天API刷新でOrigin/Refererの一致が必須(申請時の「許可されたWebサイト」と揃える)
        $siteUrl = (string) config('app.url');
        $headers = [
            'Accept: application/json',
            'accessKey: ' . $accessKey,
            'Origin: ' . $siteUrl,
            'Referer: ' . $siteUrl,
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_USERAGENT      => (string) config('app.name', 'RyokanCatalog') . '/1.0',
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($body !== false && $status >= 200 && $status < 300) ? (string) $body : null;
        }

        $context = stream_context_create([
            'http' => ['timeout' => 8, 'header' => implode("\r\n", $headers) . "\r\n", 'ignore_errors' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        return $body !== false ? $body : null;
    }

    /** 直近リクエストからの最小間隔を空ける(キャッシュミス時のみ効く)。 */
    private function throttle(): void
    {
        static $lastAt = 0.0;
        $wait = ((int) config('rakuten.throttle_ms', 0)) / 1000;
        if ($wait <= 0) {
            return;
        }
        $elapsed = microtime(true) - $lastAt;
        if ($lastAt > 0.0 && $elapsed < $wait) {
            usleep((int) (($wait - $elapsed) * 1_000_000));
        }
        $lastAt = microtime(true);
    }
}
