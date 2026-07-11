<?php
declare(strict_types=1);

/**
 * DBの状態を1行で出力する(docker-entrypoint.sh の判定用)。
 * 出力: "<件数> <最古データの経過日数>"
 * 例:   "342 5"  … 342軒、いちばん古い宿の取得から5日
 * DB未作成・エラー時は "0 9999"。
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Support/helpers.php';
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
if (is_file(BASE_PATH . '/.env')) {
    \App\Support\Env::load(BASE_PATH . '/.env');
}
\App\Support\Config::init(require BASE_PATH . '/app/Config/config.php');

try {
    $pdo = \App\Support\Database::pdo();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM hotels')->fetchColumn();
    if ($count === 0) {
        echo "0 9999\n";
        exit;
    }
    $oldest = (int) $pdo->query('SELECT MIN(fetchedAt) FROM hotels')->fetchColumn();
    $ageDays = $oldest > 0 ? (int) floor((time() - $oldest) / 86400) : 9999;
    echo "{$count} {$ageDays}\n";
} catch (\Throwable) {
    echo "0 9999\n";
}
