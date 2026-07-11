<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

use App\Services\BadgeService;
use App\Services\HotelTagger;

/**
 * 楽天 HotelDetailSearch(responseType=large)のブロック群を、
 * DB保存・詳細表示の双方で使う「リッチな1レコード」に正規化する(セクション2)。
 *
 * 施設/客室の画像(通常＋サムネイル)、6軸評価、風呂情報、設備、周辺レジャー、
 * 緯度経度などを漏れなく拾う。存在しないフィールドは空のまま(表示側で条件分岐)。
 */
final class HotelExtractor
{
    /** 6軸評価のキー: 表示名 と APIフィールド名 */
    private const AXES = [
        'service'   => ['label' => 'サービス',       'field' => 'serviceAverage'],
        'location'  => ['label' => '立地',           'field' => 'locationAverage'],
        'room'      => ['label' => '部屋',           'field' => 'roomAverage'],
        'equipment' => ['label' => '設備・アメニティ', 'field' => 'equipmentAverage'],
        'bath'      => ['label' => '風呂',           'field' => 'bathAverage'],
        'meal'      => ['label' => '食事',           'field' => 'mealAverage'],
    ];

    /**
     * @param array<int,mixed> $blocks hotels[0]['hotel'] のブロック配列
     * @return array<string,mixed>
     */
    public function fromBlocks(array $blocks): array
    {
        $b = $this->findBlock($blocks, 'hotelBasicInfo');
        if ($b === []) {
            return [];
        }

        [$hotelImages, $roomImages] = $this->collectImages($blocks);
        if ($hotelImages === []) {
            $hotelImages = $this->uniqueUrls([$b['hotelImageUrl'] ?? null, $b['hotelThumbnailUrl'] ?? null]);
        }

        $address = trim((string) ($b['address1'] ?? '') . (string) ($b['address2'] ?? ''));
        $special = trim((string) ($b['hotelSpecial'] ?? ''));

        $hotel = [
            'hotelNo'            => (string) ($b['hotelNo'] ?? ''),
            'hotelName'          => (string) ($b['hotelName'] ?? ''),
            'hotelSpecial'       => $special,
            'catchCopy'          => $special,
            'hotelCaption'       => '',
            'aboutLeisure'       => trim((string) $this->findFirst($blocks, ['aboutLeisure'])),
            'hotelImageUrls'     => $hotelImages,
            'roomImageUrls'      => $roomImages,
            'reviewAverage'      => (float) ($b['reviewAverage'] ?? 0),
            'reviewCount'        => (int) ($b['reviewCount'] ?? 0),
            'axis'               => $this->axisRatings($blocks),
            'bathType'           => trim((string) $this->findFirst($blocks, ['bathType', 'aboutBath'])),
            'bathQuality'        => trim((string) $this->findFirst($blocks, ['bathQuality', 'springQuality', 'onsenQuality'])),
            'bathBenefits'       => trim((string) $this->findFirst($blocks, ['bathBenefits', 'springBenefit', 'effect'])),
            // ホテル種別コード(メモ3-②: 値と種別の対応表づくり用に保存だけしておく)
            'hotelClassCode'     => trim((string) $this->findFirst($blocks, ['hotelClassCode'])),
            // お客様の声(メモ2: hotelBasicInfo/hotelDetailInfoに代表1件のみ)
            'latestReview'       => $this->latestReview($blocks),
            // クチコミ全件ページ(楽天側)へのURL。CTR用テキストリンクに使う
            'reviewUrl'          => trim((string) $this->findFirst($blocks, ['reviewUrl'])),
            'roomFacilities'     => $this->collectListText($blocks, ['roomFacilities', 'roomFacility']),
            'hotelFacilities'    => $this->collectListText($blocks, ['hotelFacilities', 'hotelFacility']),
            'address'            => $address,
            'area'               => trim((string) ($b['address1'] ?? '')),
            'access'             => trim((string) ($b['access'] ?? '')),
            'nearestStation'     => trim((string) ($b['nearestStation'] ?? '')),
            'parkingInformation' => trim((string) ($b['parkingInformation'] ?? '')),
            'checkinTime'        => (string) $this->findFirst($blocks, ['checkinTime']),
            'checkoutTime'       => (string) $this->findFirst($blocks, ['checkoutTime']),
            'minCharge'          => (int) ($b['hotelMinCharge'] ?? 0),
            'latitude'           => isset($b['latitude']) ? self::normalizeLat((float) $b['latitude'], (float) ($b['longitude'] ?? 0)) : null,
            'longitude'          => isset($b['longitude']) ? self::normalizeLng((float) ($b['latitude'] ?? 0), (float) $b['longitude']) : null,
            'affiliateUrl'       => AffiliateLinkBuilder::fromBasicInfo($b),
            'fetchedAt'          => time(),
        ];

        $hotel['facilities'] = $this->displayFacilities($hotel);
        // タグ・バッジ(設備・風呂・周辺テキストも判定材料に)
        $tagSource = $hotel + [
            'access' => implode(' ', array_filter([
                $hotel['access'], $hotel['bathType'], $hotel['bathQuality'],
                implode(' ', $hotel['hotelFacilities']), implode(' ', $hotel['roomFacilities']),
                $hotel['aboutLeisure'],
            ])),
        ];
        $hotel['tags']   = HotelTagger::tag($tagSource);
        $hotel['badges'] = (new BadgeService())->forHotel($hotel);

        return $hotel;
    }

    /** 6軸評価。値が全く無ければ空配列(レーダー非表示)。 */
    /**
     * お客様の声(代表1件・メモ2)。APIのフィールド名は環境差があるため候補を順に探す。
     * 見つからなければ null(セクション自体を非表示にする)。
     * @return ?array{comment:string,meta:string}
     */
    private function latestReview(array $blocks): ?array
    {
        $comment = trim((string) $this->findFirst($blocks, ['userReview', 'review', 'customerComment', 'reviewComment']));
        if ($comment === '') {
            return null;
        }
        // 実データ対応: APIの口コミには「つづきはこちら」のHTMLリンクや投稿日時が
        // 埋め込まれている(例: 2026-07-02 01:25:55投稿 <a href=...>つづきはこちら</a>)。
        // タグ・日時・リンク文言・生URLをすべて除去して本文だけにする。
        $comment = strip_tags($comment);
        $comment = (string) preg_replace('/\d{4}-\d{2}-\d{2}[ 　]?\d{2}:\d{2}(:\d{2})?\s*投稿.*/us', '', $comment);
        $comment = (string) preg_replace('/(つづきはこちら|続きはこちら)/u', '', $comment);
        $comment = (string) preg_replace('~https?://\S+~u', '', $comment);
        $comment = trim((string) preg_replace('/\s+/u', ' ', $comment));
        if ($comment === '') {
            return null;
        }
        // 長すぎる場合は文の切れ目で丸める(表示崩れ・転載過多の防止)
        if (mb_strlen($comment) > 220) {
            $cut = mb_substr($comment, 0, 220);
            $pos = mb_strrpos($cut, '。');
            $comment = ($pos !== false && $pos > 80 ? mb_substr($cut, 0, $pos + 1) : $cut . '…');
        }
        return ['comment' => $comment, 'meta' => '楽天トラベル「お客様の声」より(抜粋・1件)'];
    }

    /**
     * 楽天APIの座標を WGS84 の10進度に正規化する(地図バグの根本対応)。
     *
     * APIは datumType=1 を指定すれば10進度で返すが、指定漏れ・古いキャッシュ・
     * 旧DBデータでは「日本測地系(Tokyo Datum)の秒単位」(例: 133166.3)が来る。
     * 値が度としてあり得ない大きさ(>90/>180)なら 秒→度 変換し、
     * さらに 日本測地系→世界測地系 の簡易変換式を適用する。
     */
    public static function normalizeLat(float $lat, float $lng): float
    {
        if (abs($lat) <= 90.0) {
            return $lat; // すでに度(WGS84想定)
        }
        $latD = $lat / 3600.0;
        $lngD = $lng / 3600.0;
        // Tokyo Datum → WGS84(国土地理院の簡易式・誤差数m程度)
        return round($latD - $latD * 0.00010695 + $lngD * 0.000017464 + 0.0046017, 6);
    }

    /** @see normalizeLat */
    public static function normalizeLng(float $lat, float $lng): float
    {
        if (abs($lng) <= 180.0) {
            return $lng;
        }
        $latD = $lat / 3600.0;
        $lngD = $lng / 3600.0;
        return round($lngD - $latD * 0.000046038 - $lngD * 0.000083043 + 0.010040, 6);
    }

    private function axisRatings(array $blocks): array
    {
        $out = [];
        foreach (self::AXES as $key => $def) {
            $v = (float) $this->findFirst($blocks, [$def['field']]);
            if ($v > 0) {
                $out[$key] = ['label' => $def['label'], 'value' => round($v, 2)];
            }
        }
        return count($out) >= 3 ? $out : []; // 3軸未満は不完全としてチャート非表示
    }

    /** 指定キーのブロックを返す。 */
    private function findBlock(array $blocks, string $key): array
    {
        foreach ($blocks as $block) {
            if (is_array($block) && isset($block[$key]) && is_array($block[$key])) {
                return $block[$key];
            }
        }
        return [];
    }

    /** ツリー全体から、いずれかのキーに一致する最初のスカラー値を返す。 */
    private function findFirst(array $tree, array $keys): mixed
    {
        $found = null;
        $walk = function ($node) use (&$walk, &$found, $keys): void {
            if ($found !== null || !is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (in_array((string) $k, $keys, true) && (is_string($v) || is_numeric($v)) && (string) $v !== '') {
                    $found = $v;
                    return;
                }
                if (is_array($v)) {
                    $walk($v);
                    if ($found !== null) {
                        return;
                    }
                }
            }
        };
        $walk($tree);
        return $found ?? '';
    }

    /** 指定キー配下の文字列を集めてリスト化(設備など)。 */
    private function collectListText(array $tree, array $keys): array
    {
        $out = [];
        $walk = function ($node) use (&$walk, &$out, $keys): void {
            if (!is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (in_array((string) $k, $keys, true)) {
                    $list = $v === null ? [] : (array) $v;
                    array_walk_recursive($list, static function ($leaf) use (&$out): void {
                        if (is_string($leaf) && trim($leaf) !== '') {
                            $out[] = trim($leaf);
                        }
                    });
                } elseif (is_array($v)) {
                    $walk($v);
                }
            }
        };
        $walk($tree);
        return array_values(array_unique($out));
    }

    /** 施設/客室画像を通常＋サムネイルまで漏れなく収集(地図除外・サイズ違い重複排除)。 */
    private function collectImages(mixed $tree): array
    {
        $hotel = [];
        $room  = [];
        $seen  = [];
        $walk = function ($node) use (&$walk, &$hotel, &$room, &$seen): void {
            if (!is_array($node)) {
                return;
            }
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $walk($value);
                    continue;
                }
                if (!is_string($value) || $value === '' || !preg_match('#^https?://#', $value)) {
                    continue;
                }
                $k = (string) $key;
                if (!preg_match('/image ?url/i', $k) || stripos($k, 'map') !== false) {
                    continue;
                }
                $isRoom = stripos($k, 'room') !== false;
                $canon = ($isRoom ? 'room:' : 'hotel:')
                    . strtolower((string) preg_replace('/_[a-z0-9]+(?=\.(jpg|jpeg|png|gif|webp)$)/i', '', $value));
                if (isset($seen[$canon])) {
                    continue;
                }
                $seen[$canon] = true;
                if ($isRoom) {
                    $room[] = $value;
                } else {
                    $hotel[] = $value;
                }
            }
        };
        $walk($tree);
        return [array_values($hotel), array_values($room)];
    }

    private function displayFacilities(array $h): array
    {
        $candidates = [
            'チェックイン'   => $h['checkinTime'] ?? '',
            'チェックアウト' => $h['checkoutTime'] ?? '',
            '駐車場'         => $h['parkingInformation'] ?? '',
            '最寄り駅'       => $h['nearestStation'] ?? '',
            '風呂'           => $h['bathType'] ?? '',
            '泉質'           => $h['bathQuality'] ?? '',
        ];
        $list = [];
        foreach ($candidates as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $list[] = ['k' => $label, 'v' => $value];
            }
        }
        return $list;
    }

    private function uniqueUrls(array $urls): array
    {
        $flat = [];
        array_walk_recursive($urls, static function ($u) use (&$flat): void {
            if (is_string($u) && $u !== '') {
                $flat[] = $u;
            }
        });
        return array_values(array_unique($flat));
    }
}
