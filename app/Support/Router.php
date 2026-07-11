<?php
declare(strict_types=1);

namespace App\Support;

/**
 * 簡易ルーター。
 * ルート定義: [メソッド, パスパターン, [Controller::class, 'method']]
 * {hotelNo} のようなプレースホルダは名前付きキャプチャに変換して抽出する。
 * Laravel移行時は routes/web.php の Route::get(...) に1対1で移し替えられる。
 */
final class Router
{
    /** @var array<int, array{0:string,1:string,2:array{0:class-string,1:string}}> */
    private array $routes;

    /** @param array<int, array{0:string,1:string,2:array{0:class-string,1:string}}> $routes */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(string $method, string $path): void
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if (strcasecmp($routeMethod, $method) !== 0) {
                continue;
            }
            $regex = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern) . '$#u';
            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = $handler;
                (new $class())->{$action}(...array_map('urldecode', $params));
                return;
            }
        }

        throw new HttpNotFoundException('お探しのページは見つかりませんでした。');
    }
}
