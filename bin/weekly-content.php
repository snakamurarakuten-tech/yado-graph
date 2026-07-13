<?php
declare(strict_types=1);

/**
 * コンテンツ自動生成の週次オーケストレーター(cron登録用)。
 *
 *   0 4 * * 1  cd /path/to/yado-graph && php bin/weekly-content.php >> storage/logs/content.log 2>&1
 *
 * やること(すべて無料枠内で自動調整):
 *  1. 特集を1本、テーマプールから自動生成して公開(--auto --publish)
 *  2. 「この宿のこだわり」を最大 OFFICIAL_LIMIT 軒ぶん生成
 *     (Custom Search の日次カウンターが上限に達したら自動停止)
 *  3. 結果サマリーを通知(NOTIFY_EMAIL 設定時はメール、常に notify.log)
 *
 * 特集とこだわりの無料枠調整: 特集はGeminiのみ(検索クォータを消費しない)ため
 * 同日実行で競合しない。Geminiの無料RPDに対しても計30回程度で余裕がある。
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Support/helpers.php';
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'App\\')) {
        $p = BASE_PATH . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
        if (is_file($p)) { require $p; }
    }
});
if (is_file(BASE_PATH . '/.env')) { \App\Support\Env::load(BASE_PATH . '/.env'); }
\App\Support\Config::init(require BASE_PATH . '/app/Config/config.php');

// 多重起動防止(cronの実行が前回分と重なった場合は静かに終了)
$__lock = \App\Support\Lock::acquire('weekly-content');
if ($__lock === null) {
    fwrite(STDERR, "[skip] weekly-content は既に実行中のため終了します\n");
    exit(0);
}

$officialLimit = max(0, (int) (getenv('OFFICIAL_LIMIT') ?: 25));
$php = PHP_BINARY;
$summary = [];

echo '===== weekly-content ' . date('Y-m-d H:i') . " =====\n";

/* 1) 特集(全自動・公開) */
passthru("{$php} " . escapeshellarg(BASE_PATH . '/bin/generate-feature.php') . ' --auto --publish', $code1);
$summary[] = '特集: ' . ($code1 === 0 ? '公開しました' : 'スキップ/失敗(詳細は個別通知)');

/* 2) こだわり(無料枠の範囲で) */
if ($officialLimit > 0) {
    passthru("{$php} " . escapeshellarg(BASE_PATH . '/bin/generate-official-summary.php') . " --limit={$officialLimit}", $code2);
    $summary[] = "こだわり: --limit={$officialLimit} で実行(結果は上記ログ)";
}

/* 3) サマリー通知 */
$total = count(glob(BASE_PATH . '/content/official/*.json') ?: []);
$features = count((new \App\Services\ContentStore())->publishedFeatures());
(new \App\Services\Ai\Notifier())->send(
    '週次コンテンツ生成レポート',
    implode("\n", $summary) . "\n\n累計: こだわり {$total} 軒 / 公開特集 {$features} 本\n"
    . "※ mixhost等の永続ディスク環境では content/ がそのまま公開領域です。\n"
    . "  Render(揮発FS)で運用中の場合は content/ を git commit & push してください。"
);
echo "done\n";
