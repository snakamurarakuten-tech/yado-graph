<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 周辺情報(セクション4)。
 *  1) 楽天の aboutLeisure があればそれを使う
 *  2) 無ければ Overpass API(OpenStreetMap)で周辺スポットを補完
 *     - 半径 radius_m 内の tourism=* / leisure=* を取得
 *     - Haversine距離でソート、名前付きのみ、max_spots件まで
 *     - スポットが min_spots 未満なら空(=セクション非表示)
 *  帰属表示「© OpenStreetMap contributors」は View 側で必須。
 *
 * Overpass結果は緯度経度グリッド(約1km)単位でキャッシュし、近隣宿で共有する。
 */
final class SurroundingsService
{
    private CacheService $cache;

    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
    }

    /**
     * @param array<string,mixed> $hotel
     * @return array{source:string,leisureText?:string,spots?:array<int,array{name:string,category:string,dist:int}>}|null
     */
    public function forHotel(array $hotel): ?array
    {
        if (!config('surroundings.enabled', true)) {
            return null;
        }

        // 1) aboutLeisure 優先
        $leisure = trim((string) ($hotel['aboutLeisure'] ?? ''));
        if ($leisure !== '') {
            return ['source' => 'rakuten', 'leisureText' => $leisure];
        }

        // 2) Overpass補完(緯度経度が必要)
        $lat = $hotel['latitude'] ?? null;
        $lng = $hotel['longitude'] ?? null;
        if ($lat === null || $lng === null) {
            return null;
        }

        $spots = $this->overpassSpots((float) $lat, (float) $lng);
        $min = (int) config('surroundings.min_spots', 3);
        if (count($spots) < $min) {
            return null; // 情報が薄いエリアはセクションごと非表示
        }
        return ['source' => 'osm', 'spots' => $spots];
    }

    /**
     * @return array<int,array{name:string,category:string,dist:int}>
     */
    private function overpassSpots(float $lat, float $lng): array
    {
        $radius = (int) config('surroundings.radius_m', 2500);
        $max    = (int) config('surroundings.max_spots', 8);

        // 約1kmグリッドでキャッシュ(近隣宿で共有)
        $gridKey = sprintf('osm:%.2f,%.2f:%d', $lat, $lng, $radius);
        $raw = $this->cache->remember($gridKey, function () use ($lat, $lng, $radius): array {
            return $this->queryOverpass($lat, $lng, $radius);
        });

        // 宿からの距離でソートし整形
        $spots = [];
        foreach ($raw as $el) {
            $name = trim((string) ($el['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $dist = $this->haversine($lat, $lng, (float) $el['lat'], (float) $el['lng']);
            $spots[] = ['name' => $name, 'category' => (string) ($el['category'] ?? ''), 'dist' => (int) round($dist)];
        }
        usort($spots, static fn ($a, $b) => $a['dist'] <=> $b['dist']);
        return array_slice($spots, 0, $max);
    }

    /**
     * Overpassへ問い合わせて素の要素配列を返す(name/lat/lng/category)。
     * 失敗時は空配列(=補完なし)。
     * @return array<int,array{name:string,lat:float,lng:float,category:string}>
     */
    private function queryOverpass(float $lat, float $lng, int $radius): array
    {
        $url = (string) config('surroundings.overpass_url');
        if ($url === '') {
            return [];
        }
        $q = "[out:json][timeout:15];("
            . "node[\"tourism\"](around:{$radius},{$lat},{$lng});"
            . "way[\"tourism\"](around:{$radius},{$lat},{$lng});"
            . "node[\"leisure\"](around:{$radius},{$lat},{$lng});"
            . ");out center 60;";

        // Overpassの公開インスタンスへの配慮(利用ポリシー上、連続アクセスは避ける)
        usleep(1_000_000);
        $body = $this->httpPost($url, 'data=' . rawurlencode($q));
        if ($body === null) {
            return [];
        }
        $json = json_decode($body, true);
        $els = is_array($json['elements'] ?? null) ? $json['elements'] : [];

        $out = [];
        foreach ($els as $el) {
            $tags = (array) ($el['tags'] ?? []);
            $name = (string) ($tags['name:ja'] ?? $tags['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $plat = $el['lat'] ?? ($el['center']['lat'] ?? null);
            $plng = $el['lon'] ?? ($el['center']['lon'] ?? null);
            if ($plat === null || $plng === null) {
                continue;
            }
            $category = (string) ($tags['tourism'] ?? $tags['leisure'] ?? '');
            $out[] = ['name' => $name, 'lat' => (float) $plat, 'lng' => (float) $plng, 'category' => $category];
        }
        return $out;
    }

    /** 2点間の距離(メートル)。 */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function httpPost(string $url, string $body): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => 18,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_USERAGENT      => (string) config('app.name', 'YadoGraph') . '/1.0 (+surroundings)',
            ]);
            $res = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($res !== false && $status >= 200 && $status < 300) ? (string) $res : null;
        }
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'timeout' => 18, 'ignore_errors' => true,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        return $res !== false ? (string) $res : null;
    }
}
