<?php
declare(strict_types=1);

/**
 * PHP組み込みサーバー(`php -S`)用のルーター。
 * Apacheの .htaccess と同じ挙動 ―
 * 実在するファイルはそのまま返し、それ以外は public/index.php へ回す。
 * Render等、Apacheが使えない環境向け。
 */

$publicDir = __DIR__ . '/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = $publicDir . $uri;

if ($uri !== '/' && is_file($file)) {
    return false; // 静的ファイル(assets等)はサーバーにそのまま返させる
}

chdir($publicDir);
require $publicDir . '/index.php';
