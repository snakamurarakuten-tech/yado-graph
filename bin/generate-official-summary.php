<?php
declare(strict_types=1);

/**
 * 「この宿のこだわり(公式サイトより)」の生成バッチ。
 *
 * 使い方(ローカルで実行 → content/official/ を目視レビュー → git push で公開):
 *   php bin/generate-official-summary.php --limit=30
 *   php bin/generate-official-summary.php --hotel=40786   # 1軒だけ
 *
 * 完全無料の担保:
 *   - Custom Search は日次カウンターで90クエリ/日を超えたら自動停止
 *   - Gemini は無料枠モデル(GEMINI_MODEL)+呼び出し間に5秒スリープ
 *
 * フロー(1軒あたり): 公式HP発見 → 宿名+市区町村で検証 → 本文抽出
 *   → Gemini(検索なし・素材は渡したテキストのみ)→ バリデーション → JSON保存。
 * どこかで落ちたらその宿はスキップ(=書かない)。処理済み/スキップは
 * content/official/_state.json に記録し、再実行時に飛ばす。
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
$__lock = \App\Support\Lock::acquire('official-summary');
if ($__lock === null) {
    fwrite(STDERR, "[skip] official-summary は既に実行中のため終了します\n");
    exit(0);
}

use App\Services\Ai\AiOutputValidator;
use App\Services\Ai\CustomSearchClient;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\OfficialSiteFinder;
use App\Services\Storage\HotelRepository;

$argvStr = implode(' ', array_slice($argv, 1));
$opt = function (string $name, string $default = '') use ($argvStr): string {
    return preg_match('/--' . $name . '=(\S+)/', $argvStr, $m) ? $m[1] : $default;
};
$limit = max(1, (int) $opt('limit', '30'));
$onlyHotel = preg_replace('/\D/', '', $opt('hotel', '')) ?? '';

$repo = new HotelRepository();
$finder = new OfficialSiteFinder();
$gemini = new GeminiClient();
$validator = new AiOutputValidator();
$search = new CustomSearchClient();

$stateFile = BASE_PATH . '/content/official/_state.json';
$state = is_file($stateFile) ? (array) json_decode((string) file_get_contents($stateFile), true) : [];
$saveState = function () use (&$state, $stateFile): void {
    file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
};

$provider = (string) config('ai.search_provider', 'gemini');
$remaining = static fn (): int => $provider === 'cse' ? $search->remainingToday() : $gemini->groundingRemainingToday();
echo "[start] 発見プロバイダ: {$provider} / 本日の残り検索枠: " . $remaining() . "\n";

// 対象選定(クチコミ数の多い順=価値の高いページから)。
//  - 未挑戦: 最優先
//  - 過去に失敗(no_verified_site等): RETRY_COOLDOWN_DAYS 経過かつ RETRY_MAX 未満なら再挑戦(指摘5)
//  - 生成済み: REFRESH_DAYS 経過していれば公式HPを取り直して記事を更新(指摘6)
$retryCooldownDays = max(1, (int) (getenv('OFFICIAL_RETRY_COOLDOWN_DAYS') ?: 30));
$retryMax          = max(1, (int) (getenv('OFFICIAL_RETRY_MAX') ?: 3));
$refreshDays       = max(0, (int) (getenv('OFFICIAL_REFRESH_DAYS') ?: 0)); // 0=リフレッシュ無効
$nowTs = time();
$daysSince = static fn (string $ymd): float => $ymd === '' ? 9e9 : ($nowTs - (int) strtotime($ymd)) / 86400;

$targets = [];
if ($onlyHotel !== '') {
    $targets = [$onlyHotel];
} else {
    foreach ($repo->search(['sort' => 'reviews', 'excludeNoReview' => true, 'perPage' => 500])['items'] as $c) {
        $no = (string) $c['hotelNo'];
        $st = $state[$no] ?? null;

        // 生成済み: リフレッシュ対象かだけ判定
        if (is_file(BASE_PATH . "/content/official/{$no}.json")) {
            if ($refreshDays > 0) {
                $data = json_decode((string) file_get_contents(BASE_PATH . "/content/official/{$no}.json"), true);
                if (is_array($data) && $daysSince((string) ($data['fetchedAt'] ?? '')) >= $refreshDays) {
                    $targets[] = $no;
                }
            }
            continue;
        }

        // 未挑戦
        if ($st === null) { $targets[] = $no; continue; }

        // 過去に失敗 → クールダウン経過かつ試行上限未満なら再挑戦(指摘5)
        if (is_array($st)) {
            $attempts = (int) ($st['attempts'] ?? 1);
            $lastAt   = (string) ($st['lastAt'] ?? '');
            if ($attempts < $retryMax && $daysSince($lastAt) >= $retryCooldownDays) {
                $targets[] = $no;
            }
        }
        // 旧形式(文字列)の state はいったん確定失敗として扱い、次回の書き込みで新形式化される
    }
}

$done = 0;
foreach ($targets as $no) {
    if ($done >= $limit) { break; }
    if ($remaining() <= 0) {
        echo "[stop] 本日の検索枠を使い切りました。明日続きから再開できます\n";
        break;
    }

    $hotel = $repo->find($no);
    if ($hotel === null) { $state[$no] = ['reason' => 'not_in_db', 'attempts' => 99, 'lastAt' => date('Y-m-d')]; continue; }
    $name = (string) $hotel['hotelName'];
    echo "--- [{$no}] {$name}\n";

    // 1) 公式HP発見+検証(クォータ超過は state を汚さず停止=翌日再開可能)
    try {
        $site = $finder->findVerified($hotel, $onlyHotel !== '');
    } catch (\App\Services\Ai\AiQuotaException $e) {
        echo "[stop] Geminiのクォータ上限に達しました。翌日この宿から自動で再開されます\n";
        echo '      ' . mb_substr($e->getMessage(), 0, 200) . "\n";
        (new \App\Services\Ai\Notifier())->send(
            'こだわり生成: クォータ上限で停止',
            "処理 {$done} 件で停止(state未記録のため翌日同じ宿から再開)。\n" . mb_substr($e->getMessage(), 0, 400)
        );
        break;
    }
    sleep(4); // 無料枠のRPM対策(発見コールと次のコールの間隔)
    if ($site === null) {
        $prevAttempts = (int) (($state[$no]['attempts'] ?? 0));
        $state[$no] = ['reason' => 'no_verified_site', 'attempts' => $prevAttempts + 1, 'lastAt' => date('Y-m-d')];
        echo "    skip: 公式HPを検証できず(書かない・試行{$state[$no]['attempts']}回目)\n";
        $saveState();
        continue;
    }
    echo "    official: {$site['url']}\n";

    // 2) 執筆(素材=公式HP本文+DB事実のみ。検索ツールなし)
    $facts = sprintf(
        '宿名:%s / 所在地:%s / 総合評価:%.1f(クチコミ%d件) / タグ:%s',
        $name, (string) $hotel['address'],
        (float) $hotel['reviewAverage'], (int) $hotel['reviewCount'],
        implode(',', (array) $hotel['tags'])
    );
    $system = <<<SYS
あなたは旅行メディアの編集者です。渡された素材テキストに書かれている事実だけを使って文章を書きます。
禁止: 素材にない事実・数値・固有名詞 / 価格やキャンペーンへの言及 / 効能や「日本一」等の断定 / 営業状態への言及。
文体: です・ます調。誇張しない。素材が乏しい観点は書かない。
SYS;
    $prompt = <<<PROMPT
以下は旅館「{$name}」の公式サイト本文と基本データです。
この宿ならではの「こだわり」を、次のJSONだけで出力してください:
{"catch": "20〜35字のひとこと紹介", "points": [{"title": "8〜14字の見出し", "body": "80〜130字の本文"}]}
points は素材から書ける分だけ(最大3件・最低1件)。料理/風呂/客室/景観/歴史/おもてなし の観点を優先。

# 基本データ
{$facts}

# 公式サイト本文
{$site['text']}
PROMPT;

    try {
        $json = $gemini->generateJson($prompt, $system);
    } catch (\App\Services\Ai\AiQuotaException $e) {
        echo "[stop] Geminiのクォータ上限に達しました(執筆フェーズ)。翌日再開されます\n";
        break;
    }
    sleep(5); // 無料枠のレート配慮
    $points = array_values(array_filter(
        (array) ($json['points'] ?? []),
        static fn ($p) => is_array($p) && trim((string) ($p['title'] ?? '')) !== '' && trim((string) ($p['body'] ?? '')) !== ''
    ));
    if ($json === null || $points === []) {
        echo "    skip: 生成失敗\n";
        $state[$no] = ['reason' => 'generate_failed', 'attempts' => (int)(($state[$no]['attempts'] ?? 0)) + 1, 'lastAt' => date('Y-m-d')];
        $saveState();
        continue;
    }

    // 3) バリデーション(通らなければ破棄)
    $joined = (string) ($json['catch'] ?? '');
    foreach ($points as $p) { $joined .= ' ' . $p['title'] . ' ' . $p['body']; }
    $check = $validator->validate($joined, $site['text'] . ' ' . $facts);
    if (!$check['ok']) {
        echo "    skip: バリデーション不合格({$check['reason']})\n";
        $state[$no] = ['reason' => 'validation:' . $check['reason'], 'attempts' => (int)(($state[$no]['attempts'] ?? 0)) + 1, 'lastAt' => date('Y-m-d')];
        $saveState();
        continue;
    }

    // 4) 保存(git push が公開の承認行為)
    $out = [
        'hotelNo'   => $no,
        'hotelName' => $name,
        'catch'     => trim((string) ($json['catch'] ?? '')),
        'points'    => array_slice(array_map(static fn ($p) => [
            'title' => trim((string) $p['title']),
            'body'  => trim((string) $p['body']),
        ], $points), 0, 3),
        'sourceUrl' => $site['url'],
        'fetchedAt' => date('Y-m-d'),
    ];
    file_put_contents(
        BASE_PATH . "/content/official/{$no}.json",
        json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
    );
    unset($state[$no]); // 成功=生成物ファイルが真実。state からは除去
    $saveState();
    $done++;
    echo "    saved: content/official/{$no}.json\n";
}

echo "[done] 生成 {$done} 件。中身を確認してから git push してください\n";

// 実行結果を必ず通知する(--hotel での単体テスト時は送らない)
if ($onlyHotel === '') {
    $__total = count(glob(BASE_PATH . '/content/official/*.json') ?: []);
    $__reasons = [];
    foreach ($state as $__st) {
        if (is_array($__st)) {
            $__r = (string) ($__st['reason'] ?? '?');
            $__reasons[$__r] = ($__reasons[$__r] ?? 0) + 1;
        }
    }
    $__breakdown = '';
    foreach ($__reasons as $__r => $__c) {
        $__breakdown .= "  {$__r}: {$__c}件\n";
    }
    (new \App\Services\Ai\Notifier())->send(
        "こだわり文: {$done}件を生成(累計{$__total}件)",
        "今回の生成: {$done}件\n"
        . "累計: {$__total}件\n\n"
        . "--- スキップ内訳(累計) ---\n"
        . ($__breakdown !== '' ? $__breakdown : "  なし\n")
        . "\n※ no_verified_site は公式サイトを検証できなかった宿。\n"
        . "  30日後・最大3回まで自動で再挑戦します。"
    );
}
