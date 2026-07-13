<?php
declare(strict_types=1);

/**
 * 夜間バッチ: 楽天API → 自前DB(SQLite) 取り込み(セクション2)。
 *
 * 使い方:
 *   php bin/fetch-hotels.php --enumerate           # カテゴリ横断で宿を列挙し軽量レコードを保存
 *   php bin/fetch-hotels.php --detail [--limit=500] # 各宿を HotelDetailSearch で詳細化(6軸評価・設備・周辺・全画像)
 *   php bin/fetch-hotels.php --all                  # enumerate → detail を通しで
 *
 * 楽天の制限(1 application_id につき 1秒1リクエスト以下)を守るため、
 * すべてのAPI呼び出しの間に sleep(1.1秒) を入れている。
 * 更新頻度は日次〜週次を想定(cron等で実行)。
 */

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

use App\Services\CategoryService;
use App\Services\Rakuten\HotelDetailService;
use App\Services\Rakuten\HotelExtractor;
use App\Services\Rakuten\KeywordSearchService;
use App\Services\Storage\HotelRepository;

const RATE_SLEEP = 1.1; // 秒/リクエスト(楽天制限順守)

function rate(): void
{
    usleep((int) (RATE_SLEEP * 1_000_000));
}
function logline(string $m): void
{
    fwrite(STDOUT, '[' . date('H:i:s') . '] ' . $m . "\n");
}

$args = array_slice($argv, 1);
$has = fn (string $f) => in_array($f, $args, true);
$opt = function (string $name, $default = null) use ($args) {
    foreach ($args as $a) {
        if (str_starts_with($a, "--{$name}=")) {
            return substr($a, strlen($name) + 3);
        }
    }
    return $default;
};

if ((string) config('rakuten.app_id') === '') {
    logline('ERROR: RAKUTEN_APP_ID が未設定です。.env を確認してください。');
    exit(1);
}

$repo = new HotelRepository();
$repo->migrate();
logline('DB migrated. 現在の件数: ' . $repo->count());

$doAll = $has('--all');

/* ---------- 1) 列挙(カテゴリ横断で宿を集めて軽量保存) ---------- */
if ($doAll || $has('--enumerate')) {
    $keyword = new KeywordSearchService();
    $filter = new \App\Services\HotelFilterService();
    $skipped = 0;
    $cats = (new CategoryService())->all();
    $maxPage = (int) $opt('pages', 5); // カテゴリあたり取得ページ数
    $saved = 0;

    foreach ($cats as $cat) {
        $def = (new CategoryService())->find($cat['key']);
        $query = (string) ($def['query'] ?? '');
        if ($query === '') {
            continue;
        }
        for ($page = 1; $page <= $maxPage; $page++) {
            $cards = $keyword->search($query, ['hits' => 30, 'page' => $page]);
            rate();
            if ($cards === []) {
                break;
            }
            foreach ($cards as $card) {
                // メモ2・3: クチコミ0件/チェーン系/異常低額は最初から入れない
                if (!$filter->isEligible($card)) {
                    $skipped++;
                    continue;
                }
                $repo->upsert($card); // 軽量レコード(後で詳細化で上書き)
                $saved++;
            }
            logline("enumerate [{$cat['key']}] page {$page}: +" . count($cards) . " (累計 {$saved})");
        }
    }
    logline("列挙完了。保存(延べ): {$saved} / 除外: {$skipped} / ユニーク: " . $repo->count());
}

/* ---------- 1b) エリア総当たり列挙(初期投入用。テーマキーワードに縛られず全国をカバー) ----------
 * --enumerate とは独立したオプション(--all には連動させない=日次cronは今まで通り軽いまま)。
 * 初回の情報投入時など、意図的に広く取り込みたいときだけ明示的に付けて使う。
 *   php bin/fetch-hotels.php --enumerate-area --area-pages=3
 */
if ($has('--enumerate-area')) {
    $areaSearch = new \App\Services\Rakuten\AreaHotelSearchService();
    $filter = new \App\Services\HotelFilterService();
    logline('エリア一覧を取得中(GetAreaClass)...');
    $areas = (new \App\Services\Rakuten\AreaClassService())->allSmallAreas();
    rate();
    logline('エリア一覧取得: ' . count($areas) . ' 件(都道府県×市区町村×小エリア)');
    if (count($areas) === 0) {
        logline('WARN: エリアが0件でした。AreaClassService のレスポンス形状を確認してください(実JSONダンプ推奨)');
    }

    $maxPage = (int) $opt('area-pages', 3); // 小エリアあたり取得ページ数
    $saved = 0;
    $skipped = 0;
    foreach ($areas as $i => $a) {
        for ($page = 1; $page <= $maxPage; $page++) {
            $cards = $areaSearch->search($a['large'], $a['middle'], $a['small'], ['hits' => 30, 'page' => $page]);
            rate();
            if ($cards === []) {
                break;
            }
            foreach ($cards as $card) {
                if (!$filter->isEligible($card)) {
                    $skipped++;
                    continue;
                }
                $repo->upsert($card);
                $saved++;
            }
        }
        if (($i + 1) % 20 === 0) {
            logline('エリア列挙: ' . ($i + 1) . '/' . count($areas) . " エリア済み(累計保存 {$saved})");
        }
    }
    logline("エリア列挙完了。保存(延べ): {$saved} / 除外: {$skipped} / ユニーク: " . $repo->count());
}

/* ---------- 2) 詳細化(1件ずつ HotelDetailSearch で6軸評価・設備・全画像を付与) ---------- */
if ($doAll || $has('--detail')) {
    $detail = new HotelDetailService();
    $extractor = new HotelExtractor();
    $surroundings = new \App\Services\SurroundingsService();
    $limit = (int) $opt('limit', 300);
    if ($limit <= 0) {
        $limit = PHP_INT_MAX; // 0 または未指定の負値は「無制限」扱い(初期投入向け)
    }

    // 改修1-3: 詳細ページが積んだ再取得キュー(古いデータの宿)を最優先で処理
    $queueFile = BASE_PATH . '/storage/db/refresh-queue.txt';
    $queued = [];
    if (is_file($queueFile)) {
        $queued = array_values(array_unique(array_filter(array_map('trim', file($queueFile) ?: []))));
        @unlink($queueFile); // 読んだら消す(処理漏れは次回fetchedAt順で拾われる)
        if ($queued !== []) {
            logline('refresh-queue: ' . count($queued) . ' 件を優先詳細化');
        }
    }

    // キュー → 未詳細化(新規・bathTypeキー無し)を優先 → 詳細化済みは fetchedAt が古い順
    // (旧ロジックは fetchedAt 昇順のみだったため、新しく列挙されたばかりの宿(fetchedAtが直近)が
    //  既存の詳細化済み宿(fetchedAtが古い)より後回しになっていた。網羅目的の初期投入では逆効果なので修正)
    $pdo = \App\Support\Database::pdo();
    $rows = $pdo->query('SELECT hotelNo, data, fetchedAt FROM hotels ORDER BY fetchedAt ASC')->fetchAll();
    $neverDetailed = [];
    $staleDetailed = [];
    foreach ($rows as $r) {
        $d = json_decode((string) $r['data'], true);
        $isDetailed = is_array($d) && array_key_exists('bathType', $d);
        if ($isDetailed) {
            $staleDetailed[] = (string) $r['hotelNo'];
        } else {
            $neverDetailed[] = (string) $r['hotelNo'];
        }
    }
    if ($neverDetailed !== []) {
        logline('未詳細化(新規)の宿: ' . count($neverDetailed) . ' 件を優先詳細化');
    }
    $targets = array_values(array_unique(array_merge($queued, $neverDetailed, $staleDetailed)));

    $count = 0;
    foreach ($targets as $no) {
        if ($count >= $limit) {
            break;
        }
        $blocks = $detail->fetchBlocks($no);
        rate();
        if ($blocks === null) {
            continue;
        }
        $rich = $extractor->fromBlocks($blocks);
        if ($rich !== []) {
            // 詳細情報(風呂の有無等)を踏まえて再判定。落ちた宿はDBからも消す
            $judge = (new \App\Services\HotelFilterService())->judge($rich);
            if (!$judge['ok']) {
                \App\Support\Database::pdo()
                    ->prepare('DELETE FROM hotels WHERE hotelNo = :no')
                    ->execute([':no' => $no]);
                logline("filtered out [{$no}] {$rich['hotelName']} ({$judge['reason']})");
                continue;
            }
            // 改修1-4: 周辺情報(Overpass)もバッチ側で取得してDBに同梱。
            // リクエスト時の外部通信をゼロにする。失敗しても詳細化は続行。
            try {
                $sur = $surroundings->forHotel($rich);
                if (!empty($sur)) {
                    $rich['surroundings'] = $sur;
                }
            } catch (\Throwable $e) {
                logline("surroundings skip [{$no}]: " . $e->getMessage());
            }
            $repo->upsert($rich);
            $count++;
            if ($count % 25 === 0) {
                logline("detail: {$count} / {$limit} 件を詳細化");
            }
        }
    }
    logline("詳細化完了: {$count} 件");
}

/* ---------- 3) パージ(掲載条件を満たさなくなった宿の削除・メモ2/3) ---------- */
if ($doAll || $has('--purge')) {
    $purged = $repo->purgeIneligible(new \App\Services\HotelFilterService());
    if ($purged > 0) {
        logline("purge: {$purged} 件を掲載対象外として削除");
    }
}

/* ---------- 4) hotelClassCode の分布レポート(メモ3-② 対応表づくり用) ----------
 * 使い方: php bin/fetch-hotels.php --class-report
 * 詳細化済みデータに保存された hotelClassCode を集計し、コード値ごとに宿名の例を表示する。
 * 温泉旅館とビジネスホテルでコード値がどう違うかを見て hotel_filters.php を育てる。 */
if ($has('--class-report')) {
    $pdo = \App\Support\Database::pdo();
    $byCode = [];
    foreach ($pdo->query('SELECT data FROM hotels') as $row) {
        $d = json_decode((string) $row['data'], true);
        if (!is_array($d)) {
            continue;
        }
        $code = (string) ($d['hotelClassCode'] ?? '');
        $code = $code === '' ? '(なし)' : $code;
        $byCode[$code] ??= ['count' => 0, 'samples' => []];
        $byCode[$code]['count']++;
        if (count($byCode[$code]['samples']) < 3) {
            $byCode[$code]['samples'][] = (string) ($d['hotelName'] ?? '');
        }
    }
    ksort($byCode);
    logline('--- hotelClassCode 分布 ---');
    foreach ($byCode as $code => $info) {
        logline(sprintf('%-8s %4d件  例: %s', $code, $info['count'], implode(' / ', $info['samples'])));
    }
}

/* ---------- 5) キャッシュGC(改修1-5: 24時間より古いAPIキャッシュを削除) ---------- */
$cacheDir = (string) config('cache.path', BASE_PATH . '/storage/cache');
$purged = 0;
foreach (glob($cacheDir . '/*.json') ?: [] as $f) {
    if (time() - (int) filemtime($f) > 86400) {
        @unlink($f);
        $purged++;
    }
}
if ($purged > 0) {
    logline("cache GC: {$purged} ファイルを削除");
}
// ログの掃除(共用サーバーでの肥大防止): クリックログ60日・検索カウンター7日・通知ログ5MB
foreach (glob(BASE_PATH . '/storage/logs/clicks-*.jsonl') ?: [] as $f) {
    if (time() - (int) filemtime($f) > 60 * 86400) { @unlink($f); }
}
foreach (glob(BASE_PATH . '/storage/logs/cse-usage-*.txt') ?: [] as $f) {
    if (time() - (int) filemtime($f) > 7 * 86400) { @unlink($f); }
}
$nl = BASE_PATH . '/storage/logs/notify.log';
if (is_file($nl) && filesize($nl) > 5 * 1024 * 1024) { @rename($nl, $nl . '.old'); }

logline('DONE. 総件数: ' . $repo->count());
