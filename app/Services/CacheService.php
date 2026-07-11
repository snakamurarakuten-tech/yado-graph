<?php
declare(strict_types=1);

namespace App\Services;

/**
 * APIレスポンスの薄いファイルキャッシュ。
 * 楽天APIは同一リクエストの短時間連続アクセスで一時利用不可になるため、その緩衝材。
 * TTLは config('cache.ttl_minutes') の一律値。0で無効化。
 * Laravel移行時は Cache::remember() にそのまま置き換えられる形にしてある。
 */
final class CacheService
{
    private string $path;
    private int $ttlSeconds;

    public function __construct()
    {
        $this->path = (string) config('cache.path');
        $this->ttlSeconds = ((int) config('cache.ttl_minutes', 0)) * 60;
        if (!is_dir($this->path)) {
            @mkdir($this->path, 0775, true);
        }
    }

    /**
     * キャッシュがあれば返し、無ければ $callback を実行して保存する。
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function remember(string $key, callable $callback): mixed
    {
        if ($this->ttlSeconds <= 0) {
            return $callback();
        }

        $file = $this->fileFor($key);
        if (is_file($file) && (time() - filemtime($file)) < $this->ttlSeconds) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if ($decoded !== null || $raw === 'null') {
                    return $decoded;
                }
            }
        }

        $value = $callback();
        @file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $value;
    }

    private function fileFor(string $key): string
    {
        return $this->path . '/' . sha1($key) . '.json';
    }
}
