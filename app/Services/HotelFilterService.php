<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 宿の掲載可否判定(機能追加メモ2・3)。
 *
 * 判定順序(先に該当したものが優先):
 *  1. reviewCount = 0            → 除外(レーダー不成立・thin content・稼働実績)
 *  2. 宿名がチェーン系キーワード  → 除外(温泉大浴場つきビジネスホテル対策で風呂判定より先)
 *  3. minCharge が異常な低額     → 除外(安全弁のみ。価格帯での除外はしない方針)
 *  4. 宿名/風呂情報に旅館らしさ  → 掲載
 *  5. どれにも該当しない中間層    → 設定 include_neutral に従う(既定: 掲載)
 *
 * バッチ取り込みと write-through の両方で使う。
 * 「掲載しない」= DBに入れない(=一覧・検索・sitemapに出ない)であって、
 * 詳細ページのアクセス自体は拒否しない(その場合は noindex で表示)。
 */
final class HotelFilterService
{
    /** @var array<string,mixed> */
    private array $conf;

    public function __construct(?array $conf = null)
    {
        $this->conf = $conf ?? (array) config('hotel_filters', []);
    }

    /**
     * @param array<string,mixed> $hotel HotelExtractor の出力
     */
    public function isEligible(array $hotel): bool
    {
        return $this->judge($hotel)['ok'];
    }

    /**
     * 判定と理由(バッチのログ用)。
     * @param array<string,mixed> $hotel
     * @return array{ok:bool,reason:string}
     */
    public function judge(array $hotel): array
    {
        // 1) クチコミ0件・評価0(メモ2)。レーダーも★も作れない宿は掲載しない
        if ((int) ($hotel['reviewCount'] ?? 0) <= 0 || (float) ($hotel['reviewAverage'] ?? 0) <= 0) {
            return ['ok' => false, 'reason' => 'no_review'];
        }

        // 1.5) 画像なし(写真主役サイトのため)。imageUrl / hotelImageUrls のどちらかにあればOK
        if (!empty($this->conf['exclude_no_image'])) {
            $hasImage = trim((string) ($hotel['imageUrl'] ?? '')) !== ''
                || !empty(array_filter((array) ($hotel['hotelImageUrls'] ?? [])));
            if (!$hasImage) {
                return ['ok' => false, 'reason' => 'no_image'];
            }
        }

        $name = (string) ($hotel['hotelName'] ?? '');

        // 2) チェーン系キーワード(メモ3-④。風呂判定より先)
        foreach ((array) ($this->conf['exclude_keywords'] ?? []) as $kw) {
            if ($kw !== '' && mb_stripos($name, (string) $kw) !== false) {
                return ['ok' => false, 'reason' => 'chain:' . $kw];
            }
        }

        // 3) 異常な低額の安全弁
        $floor = (int) ($this->conf['min_charge_floor'] ?? 0);
        $charge = (int) ($hotel['minCharge'] ?? 0);
        if ($floor > 0 && $charge > 0 && $charge < $floor) {
            return ['ok' => false, 'reason' => 'charge_floor'];
        }

        // 4) 旅館らしさの認定(宿名・風呂情報のいずれか)
        $haystack = $name . ' '
            . (string) ($hotel['bathType'] ?? '') . ' '
            . (string) ($hotel['bathQuality'] ?? '') . ' '
            . (string) ($hotel['bathBenefits'] ?? '');
        foreach ((array) ($this->conf['positive_keywords'] ?? []) as $kw) {
            if ($kw !== '' && mb_stripos($haystack, (string) $kw) !== false) {
                return ['ok' => true, 'reason' => 'positive:' . $kw];
            }
        }

        // 5) 中間層
        return !empty($this->conf['include_neutral'])
            ? ['ok' => true, 'reason' => 'neutral_include']
            : ['ok' => false, 'reason' => 'neutral_exclude'];
    }
}
