<?php
declare(strict_types=1);

namespace App\Services\Storage;

use App\Support\Database;
use PDO;

/**
 * 宿データの永続化と検索(セクション2)。
 * バッチが upsert し、サイト表示はここ経由でDBだけを参照する。
 *
 * これにより「全宿を横断した絞り込み・並び替え」が可能になる
 * (従来のキーワード検索1ページ内だけ、という制約が外れる)。
 */
final class HotelRepository
{
    private PDO $db;

    /** プロセス内で migrate を一度だけ流すためのフラグ */
    private static bool $migrated = false;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::pdo();
        // 初回接続時にスキーマを保証(冪等・CREATE IF NOT EXISTS のみ)。
        // これにより write-through(APIフォールバック時の保存)がテーブル未作成で落ちない。
        if (!self::$migrated) {
            try {
                $this->migrate();
            } catch (\Throwable) {
                // 読み取り専用FS等で失敗しても表示系は止めない
            }
            self::$migrated = true;
        }
    }

    /** スキーマ初期化(冪等)。 */
    public function migrate(): void
    {
        $this->db->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS hotels (
                hotelNo        TEXT PRIMARY KEY,
                hotelName      TEXT NOT NULL DEFAULT '',
                pref           TEXT NOT NULL DEFAULT '',
                reviewAverage  REAL NOT NULL DEFAULT 0,
                reviewCount    INTEGER NOT NULL DEFAULT 0,
                minCharge      INTEGER NOT NULL DEFAULT 0,
                latitude       REAL,
                longitude      REAL,
                tags           TEXT NOT NULL DEFAULT '',
                searchtext     TEXT NOT NULL DEFAULT '',
                data           TEXT NOT NULL DEFAULT '{}',
                city           TEXT NOT NULL DEFAULT '',
                fetchedAt      INTEGER NOT NULL DEFAULT 0
            );
            SQL);
        // 旧スキーマのDBに city 列を後付け(冪等: 既にあれば例外を握りつぶす)
        try {
            $this->db->exec("ALTER TABLE hotels ADD COLUMN city TEXT NOT NULL DEFAULT ''");
        } catch (\Throwable) {
            // duplicate column = 追加済み
        }
        // エリアページ用: 温泉地名(増強②)
        try {
            $this->db->exec("ALTER TABLE hotels ADD COLUMN onsen_area TEXT NOT NULL DEFAULT ''");
        } catch (\Throwable) {
            // duplicate column = 追加済み
        }
        foreach ([
            'CREATE INDEX IF NOT EXISTS idx_pref ON hotels(pref)',
            'CREATE INDEX IF NOT EXISTS idx_review ON hotels(reviewAverage)',
            'CREATE INDEX IF NOT EXISTS idx_count ON hotels(reviewCount)',
            'CREATE INDEX IF NOT EXISTS idx_charge ON hotels(minCharge)',
            'CREATE INDEX IF NOT EXISTS idx_city ON hotels(city)',
            'CREATE INDEX IF NOT EXISTS idx_onsen_area ON hotels(onsen_area)',
        ] as $sql) {
            $this->db->exec($sql);
        }
    }

    /** 取り込み済み件数。0ならDB未構築とみなしAPIフォールバックする。 */
    public function count(): int
    {
        try {
            return (int) $this->db->query('SELECT COUNT(*) FROM hotels')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * 1件をupsert。$hotel は HotelExtractor の出力(rich配列)。
     */
    public function upsert(array $hotel): void
    {
        $no = (string) ($hotel['hotelNo'] ?? '');
        if ($no === '') {
            return;
        }
        $searchtext = mb_strtolower(implode(' ', array_filter([
            $hotel['hotelName'] ?? '', $hotel['hotelSpecial'] ?? '', $hotel['address'] ?? '',
            $hotel['aboutLeisure'] ?? '', $hotel['bathType'] ?? '', $hotel['bathQuality'] ?? '',
            is_array($hotel['hotelFacilities'] ?? null) ? implode(' ', $hotel['hotelFacilities']) : '',
            is_array($hotel['roomFacilities'] ?? null) ? implode(' ', $hotel['roomFacilities']) : '',
            implode(' ', (array) ($hotel['tags'] ?? [])),
        ])));

        // 市区町村を住所から導出(コンテンツ増強3: 「◯◯市の宿事情」・エリアページの材料)
        $city = self::cityFromAddress((string) ($hotel['address'] ?? ''), (string) ($hotel['area'] ?? ''));
        // 温泉地を判定(増強②: /area/{key} ページの一発クエリ用)
        $onsenArea = '';
        try {
            $onsenArea = (string) ((new \App\Services\OnsenAreaService())->forHotel($hotel)['name'] ?? '');
        } catch (\Throwable) {
            // 辞書未ロード等でも upsert は止めない
        }

        $stmt = $this->db->prepare(<<<SQL
            INSERT INTO hotels
                (hotelNo, hotelName, pref, reviewAverage, reviewCount, minCharge, latitude, longitude, tags, searchtext, data, city, onsen_area, fetchedAt)
            VALUES
                (:no, :name, :pref, :avg, :cnt, :charge, :lat, :lng, :tags, :stext, :data, :city, :oarea, :fetched)
            ON CONFLICT(hotelNo) DO UPDATE SET
                hotelName=excluded.hotelName, pref=excluded.pref,
                reviewAverage=excluded.reviewAverage, reviewCount=excluded.reviewCount,
                minCharge=excluded.minCharge, latitude=excluded.latitude, longitude=excluded.longitude,
                tags=excluded.tags, searchtext=excluded.searchtext, data=excluded.data,
                city=excluded.city, onsen_area=excluded.onsen_area, fetchedAt=excluded.fetchedAt
            SQL);
        $stmt->execute([
            ':no'      => $no,
            ':name'    => (string) ($hotel['hotelName'] ?? ''),
            ':pref'    => (string) ($hotel['area'] ?? $hotel['pref'] ?? ''),
            ':avg'     => (float) ($hotel['reviewAverage'] ?? 0),
            ':cnt'     => (int) ($hotel['reviewCount'] ?? 0),
            ':charge'  => (int) ($hotel['minCharge'] ?? 0),
            ':lat'     => isset($hotel['latitude']) ? (float) $hotel['latitude'] : null,
            ':lng'     => isset($hotel['longitude']) ? (float) $hotel['longitude'] : null,
            ':tags'    => implode(',', (array) ($hotel['tags'] ?? [])),
            ':stext'   => $searchtext,
            ':data'    => json_encode($hotel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':city'    => $city,
            ':oarea'   => $onsenArea,
            ':fetched' => (int) ($hotel['fetchedAt'] ?? time()),
        ]);
    }

    /** hotelNoで1件取得(詳細ページ用)。richデータを復元して返す。 */
    public function find(string $hotelNo): ?array
    {
        $stmt = $this->db->prepare('SELECT data FROM hotels WHERE hotelNo = :no');
        $stmt->execute([':no' => $hotelNo]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $data = json_decode((string) $row['data'], true);
        return is_array($data) ? $data : null;
    }

    /**
     * 横断検索(カード配列で返す)。全宿を対象に絞り込み＋並べ替え。
     * @param array{terms?:array<int,string>,tags?:array<int,string>,pref?:string,minCharge?:int,maxCharge?:int,minReview?:int,excludeNoReview?:bool,sort?:string,page?:int,perPage?:int,seed?:int} $q
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function search(array $q): array
    {
        $where = [];
        $args = [];

        foreach ((array) ($q['terms'] ?? []) as $i => $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            $where[] = "searchtext LIKE :t{$i}";
            $args[":t{$i}"] = '%' . mb_strtolower($t) . '%';
        }
        foreach (array_values((array) ($q['tags'] ?? [])) as $i => $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            $where[] = "(',' || tags || ',') LIKE :tag{$i}";
            $args[":tag{$i}"] = '%,' . $tag . ',%';
        }
        if (($q['onsenArea'] ?? '') !== '') {
            $where[] = 'onsen_area = :oarea';
            $args[':oarea'] = (string) $q['onsenArea'];
        }
        if (($q['pref'] ?? '') !== '') {
            $where[] = 'pref LIKE :pref';
            $args[':pref'] = '%' . $q['pref'] . '%';
        }
        if ((int) ($q['minCharge'] ?? 0) > 0) {
            $where[] = 'minCharge >= :minc AND minCharge > 0';
            $args[':minc'] = (int) $q['minCharge'];
        }
        if ((int) ($q['maxCharge'] ?? 0) > 0) {
            $where[] = 'minCharge <= :maxc AND minCharge > 0';
            $args[':maxc'] = (int) $q['maxCharge'];
        }
        if (!empty($q['excludeNoReview']) || (int) ($q['minReview'] ?? 0) > 0) {
            // 実データ対応: reviewCount>0 でも reviewAverage=0 の宿が存在し
            // カード上「レビューなし」と表示されてしまうため、評価0も除外する
            $where[] = 'reviewCount >= :minr AND reviewAverage > 0';
            $args[':minr'] = max(1, (int) ($q['minReview'] ?? 1));
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

        $order = match ((string) ($q['sort'] ?? 'popular')) {
            'rating'  => 'reviewAverage DESC, reviewCount DESC',
            'reviews' => 'reviewCount DESC',
            'random'  => 'RANDOM()',
            default   => 'reviewCount DESC, reviewAverage DESC', // popular
        };

        $perPage = max(1, min((int) ($q['perPage'] ?? 20), 60));
        $page    = max(1, (int) ($q['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        // total
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM hotels WHERE {$whereSql}");
        $countStmt->execute($args);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT data FROM hotels WHERE {$whereSql} ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $data = json_decode((string) $row['data'], true);
            if (is_array($data)) {
                $items[] = $this->toCard($data);
            }
        }
        return ['items' => $items, 'total' => $total];
    }

    /** 日付シードで決定的に1件(今日の一軒)。 */
    public function daily(int $seed): ?array
    {
        // 改修7-3: 「日付シード % 件数」だと同日中にDB件数が変わると別の宿になるため、
        // (hotelNo + 日付シード) のCRC32が最小の宿を選ぶ方式に変更。
        // 母集団の増減があっても、その宿が消えない限り同日中は同じ1軒を返す。
        // 対象はクチコミ上位200軒(品質担保)に限定。
        try {
            $stmt = $this->db->query('SELECT hotelNo, data FROM hotels WHERE reviewCount > 0 ORDER BY reviewCount DESC LIMIT 200');
            $best = null;
            $bestHash = PHP_INT_MAX;
            foreach ($stmt as $row) {
                $h = crc32((string) $row['hotelNo'] . ':' . $seed);
                if ($h < $bestHash) {
                    $bestHash = $h;
                    $best = (string) $row['data'];
                }
            }
            if ($best === null) {
                return null;
            }
            $data = json_decode($best, true);
            return is_array($data) ? $this->toCard($data) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 県別の統計(おすすめポイント生成・セクション3-2用)。
     * 集計はDBのみで完結する軽いクエリなのでキャッシュ不要(数千件規模まで)。
     *
     * @return array{avgReview:float,medCharge:int,cnt:int,topReviewCount:int}
     */
    public function prefStats(string $pref): array
    {
        $empty = ['avgReview' => 0.0, 'medCharge' => 0, 'cnt' => 0, 'topReviewCount' => 0];
        if ($pref === '') {
            return $empty;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT AVG(reviewAverage) a, COUNT(*) c FROM hotels WHERE pref = :p AND reviewCount > 0'
            );
            $stmt->execute([':p' => $pref]);
            $row = $stmt->fetch() ?: [];
            $cnt = (int) ($row['c'] ?? 0);
            if ($cnt === 0) {
                return $empty;
            }

            // minCharge の中央値(価格が入っている宿のみ)。OFFSETはPHP側で算出。
            $cc = $this->db->prepare('SELECT COUNT(*) FROM hotels WHERE pref = :p AND minCharge > 0');
            $cc->execute([':p' => $pref]);
            $chargeCnt = (int) $cc->fetchColumn();
            $medCharge = 0;
            if ($chargeCnt > 0) {
                $mid = $this->db->prepare(
                    'SELECT minCharge FROM hotels WHERE pref = :p AND minCharge > 0
                     ORDER BY minCharge LIMIT 1 OFFSET ' . intdiv($chargeCnt, 2)
                );
                $mid->execute([':p' => $pref]);
                $medCharge = (int) ($mid->fetchColumn() ?: 0);
            }

            // クチコミ数 上位10% の下限値(「県内で最も口コミが集まる宿」判定用)
            $top = $this->db->prepare(
                'SELECT reviewCount FROM hotels WHERE pref = :p AND reviewCount > 0
                 ORDER BY reviewCount DESC LIMIT 1 OFFSET ' . max(0, intdiv($cnt, 10))
            );
            $top->execute([':p' => $pref]);
            $topReviewCount = (int) ($top->fetchColumn() ?: 0);

            return [
                'avgReview'      => round((float) ($row['a'] ?? 0), 2),
                'medCharge'      => $medCharge,
                'cnt'            => $cnt,
                'topReviewCount' => $topReviewCount,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * 近隣の宿(セクション3-4)。緯度経度の矩形で粗く絞り、PHP側で正確な距離ソート。
     * @return array<int,array<string,mixed>> カード配列(distKm 付き)
     */
    public function nearby(float $lat, float $lng, string $excludeHotelNo = '', float $radiusKm = 10.0, int $limit = 10): array
    {
        try {
            // 緯度1度≒111km、経度は日本の緯度帯で≒91km として矩形化
            $dLat = $radiusKm / 111.0;
            $dLng = $radiusKm / 91.0;
            $stmt = $this->db->prepare(
                'SELECT data, latitude, longitude FROM hotels
                 WHERE latitude BETWEEN :lat1 AND :lat2
                   AND longitude BETWEEN :lng1 AND :lng2
                   AND hotelNo != :no AND reviewCount > 0
                 ORDER BY reviewCount DESC LIMIT 60'
            );
            $stmt->execute([
                ':lat1' => $lat - $dLat, ':lat2' => $lat + $dLat,
                ':lng1' => $lng - $dLng, ':lng2' => $lng + $dLng,
                ':no'   => $excludeHotelNo,
            ]);

            $cards = [];
            foreach ($stmt->fetchAll() as $row) {
                $d = json_decode((string) $row['data'], true);
                if (!is_array($d)) {
                    continue;
                }
                $km = $this->haversineKm($lat, $lng, (float) $row['latitude'], (float) $row['longitude']);
                if ($km > $radiusKm) {
                    continue;
                }
                $card = $this->toCard($d);
                $card['distKm'] = round($km, 1);
                $cards[] = $card;
            }
            usort($cards, static fn ($a, $b) => $a['distKm'] <=> $b['distKm']);
            return array_slice($cards, 0, $limit);
        } catch (\Throwable) {
            return [];
        }
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * DBに存在する都道府県の一覧(改修4-1/4-2: 検索・絞り込みのプルダウン用)。
     * @return array<int,string> 件数の多い順
     */
    public function prefList(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT pref, COUNT(*) c FROM hotels WHERE pref != '' GROUP BY pref ORDER BY c DESC"
            );
            return array_map(static fn ($r) => (string) $r['pref'], $stmt->fetchAll());
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * hotelNo の配列からカードを一括取得(改修4-4: お気に入り共有リンク用)。
     * 渡された順序を保って返す。存在しない番号は黙って落とす。
     *
     * @param array<int,string> $nos
     * @return array<int,array<string,mixed>>
     */
    public function findMany(array $nos): array
    {
        $nos = array_values(array_unique(array_filter(array_map(
            static fn ($n): string => preg_replace('/\D/', '', (string) $n) ?? '',
            $nos
        ), static fn (string $n): bool => $n !== '')));
        if ($nos === []) {
            return [];
        }
        $nos = array_slice($nos, 0, 30); // 上限(URL肥大・乱用防止)

        try {
            $ph = implode(',', array_fill(0, count($nos), '?'));
            $stmt = $this->db->prepare("SELECT hotelNo, data FROM hotels WHERE hotelNo IN ({$ph})");
            $stmt->execute($nos);

            $byNo = [];
            foreach ($stmt->fetchAll() as $row) {
                $d = json_decode((string) $row['data'], true);
                if (is_array($d)) {
                    $byNo[(string) $row['hotelNo']] = $this->toCard($d);
                }
            }
            // 渡された順序を維持
            $out = [];
            foreach ($nos as $n) {
                if (isset($byNo[$n])) {
                    $out[] = $byNo[$n];
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 住所から市区町村を導出(コンテンツ増強3)。
     * 例: 「石川県七尾市和倉町1」+ 県「石川県」→「七尾市」。取れなければ ''。
     */
    public static function cityFromAddress(string $address, string $pref): string
    {
        if ($address === '') {
            return '';
        }
        $rest = $pref !== '' && str_starts_with($address, $pref)
            ? mb_substr($address, mb_strlen($pref))
            : $address;
        // 「◯◯郡△△町」は郡込みで1単位。それ以外は最初の市/区/町/村まで。
        if (preg_match('/^(.{1,10}?郡.{1,10}?[町村])/u', $rest, $m)) {
            return $m[1];
        }
        if (preg_match('/^(.{1,12}?[市区町村])/u', $rest, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * 市区町村の統計と、この宿の順位(コンテンツ増強3: 「◯◯市の宿事情」)。
     * @return array{cnt:int,avgReview:float,rankByReviews:int,rankByRating:int}
     */
    public function cityStats(string $city, string $hotelNo): array
    {
        $empty = ['cnt' => 0, 'avgReview' => 0.0, 'rankByReviews' => 0, 'rankByRating' => 0];
        if ($city === '' || $hotelNo === '') {
            return $empty;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) c, AVG(reviewAverage) a FROM hotels WHERE city = :c AND reviewCount > 0'
            );
            $stmt->execute([':c' => $city]);
            $row = $stmt->fetch() ?: [];
            $cnt = (int) ($row['c'] ?? 0);
            if ($cnt === 0) {
                return $empty;
            }

            $rank = function (string $col) use ($city, $hotelNo): int {
                $q = $this->db->prepare(
                    "SELECT COUNT(*) + 1 FROM hotels
                     WHERE city = :c AND reviewCount > 0
                       AND {$col} > (SELECT {$col} FROM hotels WHERE hotelNo = :no)"
                );
                $q->execute([':c' => $city, ':no' => $hotelNo]);
                return (int) $q->fetchColumn();
            };

            return [
                'cnt'           => $cnt,
                'avgReview'     => round((float) ($row['a'] ?? 0), 2),
                'rankByReviews' => $rank('reviewCount'),
                'rankByRating'  => $rank('reviewAverage'),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * 県内の軸別平均(コンテンツ増強1: 軸別評価×県平均の比較)。
     * data JSON から json_extract で集計。JSON1が無い環境では空を返す。
     * @return array<string,float> 例 ['bath'=>4.12,'meal'=>4.05,...]
     */
    public function prefAxisStats(string $pref): array
    {
        if ($pref === '') {
            return [];
        }
        $axes = ['service', 'location', 'room', 'equipment', 'bath', 'meal'];
        try {
            $selects = [];
            foreach ($axes as $a) {
                $selects[] = "AVG(json_extract(data, '\$.axis.{$a}.value')) AS {$a}";
            }
            $stmt = $this->db->prepare(
                'SELECT ' . implode(', ', $selects) . ' FROM hotels WHERE pref = :p AND reviewCount > 0'
            );
            $stmt->execute([':p' => $pref]);
            $row = $stmt->fetch() ?: [];
            $out = [];
            foreach ($axes as $a) {
                $v = (float) ($row[$a] ?? 0);
                if ($v > 0) {
                    $out[$a] = round($v, 2);
                }
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 掲載条件を満たさなくなった宿の一括削除(メモ2・3のパージ)。
     * @return int 削除件数
     */
    public function purgeIneligible(\App\Services\HotelFilterService $filter): int
    {
        try {
            $rows = $this->db->query('SELECT hotelNo, data FROM hotels')->fetchAll();
        } catch (\Throwable) {
            return 0;
        }
        $purged = 0;
        $del = $this->db->prepare('DELETE FROM hotels WHERE hotelNo = :no');
        foreach ($rows as $row) {
            $d = json_decode((string) $row['data'], true);
            if (!is_array($d)) {
                continue;
            }
            if (!$filter->isEligible($d)) {
                $del->execute([':no' => (string) $row['hotelNo']]);
                $purged++;
            }
        }
        return $purged;
    }

    /**
     * 温泉地の統計(エリアページ用・増強②)。
     * @return array{cnt:int,avgReview:float,medCharge:int}
     */
    public function onsenAreaStats(string $areaName): array
    {
        $empty = ['cnt' => 0, 'avgReview' => 0.0, 'medCharge' => 0];
        if ($areaName === '') {
            return $empty;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) c, AVG(reviewAverage) a FROM hotels WHERE onsen_area = :o AND reviewCount > 0'
            );
            $stmt->execute([':o' => $areaName]);
            $row = $stmt->fetch() ?: [];
            $cnt = (int) ($row['c'] ?? 0);
            if ($cnt === 0) {
                return $empty;
            }
            $cc = $this->db->prepare('SELECT COUNT(*) FROM hotels WHERE onsen_area = :o AND minCharge > 0');
            $cc->execute([':o' => $areaName]);
            $chargeCnt = (int) $cc->fetchColumn();
            $medCharge = 0;
            if ($chargeCnt > 0) {
                $mid = $this->db->prepare(
                    'SELECT minCharge FROM hotels WHERE onsen_area = :o AND minCharge > 0
                     ORDER BY minCharge LIMIT 1 OFFSET ' . intdiv($chargeCnt, 2)
                );
                $mid->execute([':o' => $areaName]);
                $medCharge = (int) ($mid->fetchColumn() ?: 0);
            }
            return ['cnt' => $cnt, 'avgReview' => round((float) ($row['a'] ?? 0), 2), 'medCharge' => $medCharge];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * DBに宿が存在する温泉地の一覧(sitemap・一覧ページ用)。
     * @return array<string,int> 温泉地名 => 掲載軒数
     */
    public function onsenAreaCounts(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT onsen_area, COUNT(*) c FROM hotels
                 WHERE onsen_area != '' AND reviewCount > 0
                 GROUP BY onsen_area ORDER BY c DESC"
            );
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[(string) $row['onsen_area']] = (int) $row['c'];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** richデータ → カード用の軽量配列。 */
    private function toCard(array $d): array
    {
        return [
            'hotelNo'       => (string) ($d['hotelNo'] ?? ''),
            'hotelName'     => (string) ($d['hotelName'] ?? ''),
            'area'          => (string) ($d['area'] ?? $d['pref'] ?? ''),
            'imageUrl'      => (string) (($d['hotelImageUrls'][0] ?? '') ?: ($d['imageUrl'] ?? '')),
            'reviewAverage' => (float) ($d['reviewAverage'] ?? 0),
            'reviewCount'   => (int) ($d['reviewCount'] ?? 0),
            'minCharge'     => (int) ($d['minCharge'] ?? 0),
            // 風呂の軸評価(コンテンツ増強2: 近くの宿との比較テーブル用)
            'bathValue'     => round((float) ($d['axis']['bath']['value'] ?? 0), 1),
            'affiliateUrl'  => (string) ($d['affiliateUrl'] ?? ''),
            'tags'          => array_map('strval', (array) ($d['tags'] ?? [])),
            'badges'        => (array) ($d['badges'] ?? []),
            'fetchedAt'     => (int) ($d['fetchedAt'] ?? 0),
        ];
    }
}
