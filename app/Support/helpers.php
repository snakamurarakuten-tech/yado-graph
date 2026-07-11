<?php
declare(strict_types=1);

/**
 * View内で使う短縮ヘルパー。
 * Laravel移行時は e() はそのまま、view()/config() はLaravel標準に置き換わる。
 */

if (!function_exists('e')) {
    /** HTMLエスケープ */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('config')) {
    /** ドット記法で設定値を取得 例: config('rakuten.app_id') */
    function config(string $key, mixed $default = null): mixed
    {
        return \App\Support\Config::get($key, $default);
    }
}

if (!function_exists('component')) {
    /** resources/views/components/ 配下の部品を描画 */
    function component(string $name, array $props = []): void
    {
        \App\Support\View::component($name, $props);
    }
}

if (!function_exists('asset')) {
    /**
     * 静的アセットのURLに更新時刻のバージョンクエリを付ける(改修4-3: キャッシュバスティング)。
     * デプロイでファイルが変わると ?v= が変わり、古いCSS/JSが残らない。
     * 例: asset('/assets/css/base/reset.css') → /assets/css/base/reset.css?v=1720000000
     */
    function asset(string $path): string
    {
        $file = BASE_PATH . '/public/' . ltrim($path, '/');
        $v = is_file($file) ? (string) filemtime($file) : '1';
        return $path . '?v=' . $v;
    }
}
