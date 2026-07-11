<?php
declare(strict_types=1);

/**
 * フロントコントローラー
 * 全リクエストは .htaccess によってこのファイルへ書き換えられる。
 * Laravel移行時は Laravel 標準の public/index.php に置き換える。
 */

define('BASE_PATH', dirname(__DIR__));

// ---- オートロード -----------------------------------------------------------
// composer install 済みならそれを使い、無ければ PSR-4 互換の簡易オートローダーで動かす。
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'App\\';
        if (str_starts_with($class, $prefix)) {
            $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    });
}

// ---- ヘルパー ---------------------------------------------------------------
require BASE_PATH . '/app/Support/helpers.php';

// ---- 環境変数・設定 ---------------------------------------------------------
\App\Support\Env::load(BASE_PATH . '/.env');
$config = require BASE_PATH . '/app/Config/config.php';
\App\Support\Config::init($config);

error_reporting(E_ALL);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');

// ---- ルーティング -----------------------------------------------------------
$routes = require BASE_PATH . '/app/Config/routes.php';
$router = new \App\Support\Router($routes);

try {
    $router->dispatch(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
    );
} catch (\App\Support\HttpNotFoundException $e) {
    http_response_code(404);
    \App\Support\View::render('errors/404', [
        'message' => $e->getMessage(),
        'seo'     => ['title' => 'ページが見つかりません｜' . config('app.name', 'YADO GRAPH')],
        'activeTab' => 'home',
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    if ($config['app']['debug']) {
        echo '<pre style="padding:20px;color:#f88;background:#111;">'
            . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        \App\Support\View::render('errors/500', [
            'message'   => 'ページの表示中に問題が発生しました。',
            'seo'       => ['title' => 'エラー｜' . config('app.name', 'YADO GRAPH')],
            'activeTab' => 'home',
        ]);
    }
}
