<?php
declare(strict_types=1);

/**
 * テーマ特集ページの生成バッチ(検索なし・材料は自DBのみ)。
 *
 * 使い方(ローカルで実行 → ドラフトを目視レビュー → published を true にして git push):
 *   php bin/generate-feature.php --theme="雪見露天が楽しめる宿" --tags=rotenburo
 *   php bin/generate-feature.php --theme="カニの季節に泊まりたい北陸の宿" --pref=石川県 --tags=food
 *   php bin/generate-feature.php   # テーマ未指定なら季節からGeminiに3案を提案させ、対話で選ぶ
 *   php bin/generate-feature.php --auto --publish   # 全自動(cron用): テーマプールから
 *       未使用テーマを選び、生成→バリデーション→published:true で保存→メール通知。
 *       バリデーション不合格・候補不足は保存せず通知のみ(安全側)。
 *
 * 焼き直しにならない設計:
 *   - 宿の選定はDB(タグ・エリア・評価順の候補20軒)、LLMはその中から選ぶだけ
 *   - 紹介文の素材は候補データ+こだわり文(content/official があれば)のみ
 *   - バリデーション: hotelNoが候補内か / 数値が素材内か / 禁止表現なし
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
use App\Services\Ai\GeminiClient;
use App\Services\ContentStore;
use App\Services\Storage\HotelRepository;

$argvStr = implode(' ', array_slice($argv, 1));
$opt = function (string $name, string $default = '') use ($argvStr): string {
    return preg_match('/--' . $name . '=("([^"]*)"|(\S+))/u', $argvStr, $m) ? ($m[2] !== '' ? $m[2] : $m[3]) : $default;
};

$repo = new HotelRepository();
$gemini = new GeminiClient();
$validator = new AiOutputValidator();
$store = new ContentStore();

$theme = $opt('theme');
$tags = array_filter(explode(',', $opt('tags')));
$pref = $opt('pref');
$auto = str_contains($argvStr, '--auto');
$publish = str_contains($argvStr, '--publish');
$notifier = new \App\Services\Ai\Notifier();
$hint = '';

/* ---------- 全自動モード: テーマプールから未使用テーマを選ぶ ---------- */
if ($auto && $theme === '') {
    $pool = (array) config('feature_themes', []);
    $month = (int) date('n');
    $stateFile = BASE_PATH . '/content/features/_state.json';
    $state = is_file($stateFile) ? (array) json_decode((string) file_get_contents($stateFile), true) : [];
    $used = (array) ($state['used_titles'] ?? []);

    // 今月のプール → 前後の月 → 全月、の順で未使用テーマを探す
    $order = array_unique([$month, ($month % 12) + 1, (($month + 10) % 12) + 1]);
    $candidatesPool = [];
    foreach ($order as $m) { foreach ((array) ($pool[$m] ?? []) as $t) { $candidatesPool[] = $t; } }
    foreach ($pool as $themes) { foreach ((array) $themes as $t) { $candidatesPool[] = $t; } }
    foreach ($candidatesPool as $t) {
        if (!in_array((string) $t['title'], $used, true)) {
            $theme = (string) $t['title'];
            $tags = $tags !== [] ? $tags : array_map('strval', (array) ($t['tags'] ?? []));
            $pref = $pref !== '' ? $pref : (string) ($t['pref'] ?? '');
            $hint = (string) ($t['hint'] ?? '');
            break;
        }
    }
    if ($theme !== '') {
        $state['used_titles'] = array_merge($used, [$theme]);
        file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo "[auto] テーマ: {$theme} (tags: " . implode(',', $tags) . ")\n";
    }
    // プール全消化時は従来どおりGemini提案へ(下のブロックが対話なしで先頭案を採用)
}

/* ---------- テーマ未指定なら季節から提案させる ---------- */
if ($theme === '') {
    $month = (int) date('n');
    $json = $gemini->generateJson(
        "いまは{$month}月です。日本の温泉旅館サイトの特集テーマ案を3つ、" .
        '{"themes":[{"title":"特集タイトル(15〜25字)","tags":["rotenburo|food|view|quiet|solo のいずれか"]}]} のJSONだけで出力してください。' .
        '季節感があり、検索されそうなテーマにしてください。'
    );
    $themes = (array) ($json['themes'] ?? []);
    if ($themes === []) { fwrite(STDERR, "テーマ提案に失敗しました\n"); exit(1); }
    echo "テーマ案:\n";
    foreach ($themes as $i => $t) {
        echo '  ' . ($i + 1) . ') ' . ($t['title'] ?? '') . ' [tags: ' . implode(',', (array) ($t['tags'] ?? [])) . "]\n";
    }
    if ($auto) {
        $chosen = $themes[0] ?? null; // 全自動時は先頭案を採用
    } else {
        echo "番号を選んでください > ";
        $sel = (int) trim((string) fgets(STDIN));
        $chosen = $themes[$sel - 1] ?? null;
    }
    if ($chosen === null) { exit(1); }
    $theme = (string) $chosen['title'];
    $tags = $tags !== [] ? $tags : array_map('strval', (array) ($chosen['tags'] ?? []));
    sleep(5);
}

/* ---------- 候補をDBから抽出(選定はデータ、LLMは選ぶだけ) ---------- */
$query = ['sort' => 'rating', 'perPage' => 20, 'excludeNoReview' => true];
if ($tags !== []) { $query['tags'] = array_slice($tags, 0, 2); }
if ($pref !== '') { $query['pref'] = $pref; }
$candidates = $repo->search($query)['items'];
if (count($candidates) < 5) {
    $msg = "特集「{$theme}」: 候補が" . count($candidates) . '軒しかありません(5軒以上必要)。tags/prefを緩めてください';
    fwrite(STDERR, $msg . "\n");
    if ($auto) { $notifier->send('特集の自動生成をスキップ', $msg); }
    exit(1);
}

$material = '';
$candidateNos = [];
foreach ($candidates as $c) {
    $no = (string) $c['hotelNo'];
    $candidateNos[$no] = true;
    $official = $store->officialSummary($no);
    $officialText = $official !== null
        ? ' こだわり:' . $official['catch'] . ' ' . implode(' ', array_map(static fn ($p) => $p['title'] . ':' . $p['body'], $official['points']))
        : '';
    $material .= sprintf(
        "- hotelNo:%s 宿名:%s(%s) 評価:%.1f クチコミ:%d件 風呂評価:%.1f タグ:%s%s\n",
        $no, (string) $c['hotelName'], (string) $c['area'],
        (float) $c['reviewAverage'], (int) $c['reviewCount'],
        (float) ($c['bathValue'] ?? 0), implode(',', (array) $c['tags']), $officialText
    );
}

/* ---------- 執筆 ---------- */
$system = <<<SYS
あなたは旅行メディアの編集者です。渡された候補データにある事実だけを使います。
禁止: 候補データにない事実・数値・固有名詞 / 価格・キャンペーン / 効能や「日本一」等の断定。
文体: です・ます調。読者が行きたくなる具体性を、データの範囲内で。
SYS;
$prompt = <<<PROMPT
特集「{$theme}」を作ります。編集方針: {$hint}
以下の候補からテーマに合う宿を6〜8軒選び、このJSONだけで出力:
{"slug":"英小文字とハイフンのURLスラッグ","title":"特集タイトル(そのまま使える形)","lead":"導入文150〜200字。なぜ今このテーマか、選定基準に軽く触れる","items":[{"hotelNo":"候補のhotelNo","heading":"この宿を一言で(12〜20字)","body":"紹介文100〜150字"}],"outro":"締めの一文(50字以内)"}

# 候補データ
{$material}
PROMPT;

$json = $gemini->generateJson($prompt, $system);
if ($json === null || empty($json['items']) || empty($json['slug'])) {
    fwrite(STDERR, "生成に失敗しました\n");
    if ($auto) { $notifier->send('特集の自動生成に失敗', "テーマ「{$theme}」の生成に失敗しました(APIエラーの可能性)。notify.log/実行ログを確認してください"); }
    exit(1);
}

/* ---------- バリデーション ---------- */
$items = [];
$joined = (string) ($json['title'] ?? '') . ' ' . (string) ($json['lead'] ?? '');
foreach ((array) $json['items'] as $it) {
    $no = (string) ($it['hotelNo'] ?? '');
    if (!isset($candidateNos[$no])) {
        fwrite(STDERR, "破棄: 候補にない hotelNo({$no})が含まれています\n");
        exit(1);
    }
    $items[] = [
        'hotelNo' => $no,
        'heading' => trim((string) ($it['heading'] ?? '')),
        'body'    => trim((string) ($it['body'] ?? '')),
    ];
    $joined .= ' ' . $it['heading'] . ' ' . $it['body'];
}
$joined .= ' ' . (string) ($json['outro'] ?? '');
$check = $validator->validate($joined, $material . ' ' . $theme);
if (!$check['ok']) {
    fwrite(STDERR, "破棄: バリデーション不合格({$check['reason']})\n");
    exit(1);
}

/* ---------- ドラフト保存(published:false。レビュー後にtrueへ) ---------- */
$slug = preg_replace('/[^a-z0-9-]/', '-', strtolower((string) $json['slug']));
$slug = trim((string) preg_replace('/-+/', '-', $slug), '-') ?: 'feature-' . date('Ymd');
$out = [
    'slug'        => $slug,
    'title'       => trim((string) $json['title']),
    'lead'        => trim((string) $json['lead']),
    'items'       => $items,
    'outro'       => trim((string) ($json['outro'] ?? '')),
    'publishedAt' => date('Y-m-d'),
    'published'   => $publish, // --publish 時は即公開(cron自動運用)。手動時はレビュー後にtrueへ
];
$file = BASE_PATH . "/content/features/{$slug}.json";
file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo "ドラフト保存: content/features/{$slug}.json({$out['title']} / " . count($items) . "軒)\n";
if ($publish) {
    echo "公開済みとして保存しました(/features/{$slug})\n";
    $notifier->send(
        '特集を自動公開しました',
        "タイトル: {$out['title']}\nURL: " . rtrim((string) config('app.url'), '/') . "/features/{$slug}\n掲載: " . count($items) . "軒\n\n"
        . "導入文:\n{$out['lead']}\n\n内容に問題がある場合は content/features/{$slug}.json の \"published\" を false にしてください。"
    );
} else {
    echo "中身を確認し、\"published\": true に変更して git push すると公開されます\n";
}
