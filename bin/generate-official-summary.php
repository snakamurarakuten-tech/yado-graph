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

// 対象: クチコミ数の多い順(価値の高いページから)。処理済み・スキップ済みは除外
$targets = [];
if ($onlyHotel !== '') {
    $targets = [$onlyHotel];
} else {
    foreach ($repo->search(['sort' => 'reviews', 'excludeNoReview' => true, 'perPage' => 500])['items'] as $c) {
        $no = (string) $c['hotelNo'];
        if (!isset($state[$no]) && !is_file(BASE_PATH . "/content/official/{$no}.json")) {
            $targets[] = $no;
        }
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
    if ($hotel === null) { $state[$no] = 'not_in_db'; continue; }
    $name = (string) $hotel['hotelName'];
    echo "--- [{$no}] {$name}\n";

    // 1) 公式HP発見+検証
    $site = $finder->findVerified($hotel);
    if ($site === null) {
        echo "    skip: 公式HPを検証できず(書かない)\n";
        $state[$no] = 'no_verified_site';
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

    $json = $gemini->generateJson($prompt, $system);
    sleep(5); // 無料枠のレート配慮
    $points = array_values(array_filter(
        (array) ($json['points'] ?? []),
        static fn ($p) => is_array($p) && trim((string) ($p['title'] ?? '')) !== '' && trim((string) ($p['body'] ?? '')) !== ''
    ));
    if ($json === null || $points === []) {
        echo "    skip: 生成失敗\n";
        $state[$no] = 'generate_failed';
        $saveState();
        continue;
    }

    // 3) バリデーション(通らなければ破棄)
    $joined = (string) ($json['catch'] ?? '');
    foreach ($points as $p) { $joined .= ' ' . $p['title'] . ' ' . $p['body']; }
    $check = $validator->validate($joined, $site['text'] . ' ' . $facts);
    if (!$check['ok']) {
        echo "    skip: バリデーション不合格({$check['reason']})\n";
        $state[$no] = 'validation:' . $check['reason'];
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
    $state[$no] = 'ok';
    $saveState();
    $done++;
    echo "    saved: content/official/{$no}.json\n";
}

echo "[done] 生成 {$done} 件。中身を確認してから git push してください\n";
