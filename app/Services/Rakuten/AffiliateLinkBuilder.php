<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

/**
 * 楽天トラベルのアフィリエイトリンクを組み立てる。
 *
 * 【背景 / 修正依頼A-1】
 *  従来は hotelBasicInfo の URL 系フィールドをそのまま CTA に流用していたため、
 *  施設によっては画像API(img.travel.rakuten.co.jp/...)のURLが混入し、
 *  クリックしても楽天トラベル本体へ遷移せず＝Cookieが付与されず成果が発生しない、
 *  という不具合が起きていた。
 *
 * 【方針】
 *  - 遷移先(商品URL)は必ず楽天トラベルの「施設ページ」に正規化する。
 *    画像ホスト(img.travel.rakuten.co.jp 等)は遷移先として絶対に採用しない。
 *  - アフィリエイトIDがある場合は、楽天公式仕様に沿って
 *    https://hb.afl.rakuten.co.jp/hgc/{affiliateId}/?pc={商品URL}&m={商品URL}
 *    の形にラップする(商品URLはURLエンコード必須)。
 *  - APIがすでに hb.afl.rakuten.co.jp のURLを返している場合はそれを優先する。
 *
 * 参考: 楽天トラベル施設検索API 公式ドキュメント「アフィリエイトURLの作り方」
 *   http://hb.afl.rakuten.co.jp/hgc/[アフィリエイトID]/?pc=[商品URL]&m=[商品URL]
 */
final class AffiliateLinkBuilder
{
    /** アフィリエイトラッパのベース */
    private const HGC_BASE = 'https://hb.afl.rakuten.co.jp/hgc';

    /** 遷移先として不適格な(＝Cookieが付かない)ホスト。画像APIなど。 */
    private const REJECT_HOST_FRAGMENTS = [
        'img.travel.rakuten.co.jp',
        'image.travel.rakuten',
        'thumbnail.image.rakuten',
        '/image/tr/api/',
    ];

    /**
     * hotelBasicInfo からアフィリエイトURLを生成する。
     *
     * @param array<string,mixed> $b hotelBasicInfo
     * @return string 空を返すことはない(最低限 施設ページURLを組み立てて返す)
     */
    public static function fromBasicInfo(array $b): string
    {
        // (1) APIが既にアフィリエイトURL(hb.afl)を返しているなら最優先で使う
        $prewrapped = self::findPrewrappedAffiliateUrl($b);
        if ($prewrapped !== '') {
            return $prewrapped;
        }

        // (2) 遷移先(商品URL)を正規化して決定
        $target = self::resolveTargetUrl($b);
        if ($target === '') {
            return '';
        }

        // (3) アフィリエイトIDがあればラップ。無ければ素の施設ページURL。
        $affiliateId = (string) config('rakuten.affiliate_id');
        if ($affiliateId === '') {
            return $target;
        }

        $enc = rawurlencode($target);
        return self::HGC_BASE . '/' . $affiliateId . '/?pc=' . $enc . '&m=' . $enc;
    }

    /**
     * 任意の楽天URLをアフィリエイトURLにラップする(クチコミページ等・ホスト判定なし)。
     * すでに hb.afl のURLならそのまま返す。affiliate_id 未設定なら素のURLを返す。
     */
    public static function wrapUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === 'hb.afl.rakuten.co.jp') {
            return $url;
        }
        $affiliateId = (string) config('rakuten.affiliate_id');
        if ($affiliateId === '') {
            return $url;
        }
        $enc = rawurlencode($url);
        return self::HGC_BASE . '/' . $affiliateId . '/?pc=' . $enc . '&m=' . $enc;
    }

    /**
     * 明示的に施設ページURLと hotelNo を渡してアフィリエイトURLを作る(詳細ページ等から利用)。
     */
    public static function build(string $rawUrl, string $hotelNo): string
    {
        return self::fromBasicInfo([
            'hotelInformationUrl' => $rawUrl,
            'hotelNo'             => $hotelNo,
        ]);
    }

    /** hotelBasicInfo内に既存の hb.afl アフィリエイトURLがあれば返す。 */
    private static function findPrewrappedAffiliateUrl(array $b): string
    {
        foreach ($b as $value) {
            if (!is_string($value) || !str_contains($value, 'hb.afl.rakuten.co.jp')) {
                continue;
            }
            // 改修7-4: 部分一致だけだと「?rd=https://hb.afl...」のような紛れ込みも拾うため、
            // URLとしてパースしホストが hb.afl.rakuten.co.jp のときのみ採用する。
            $host = strtolower((string) (parse_url($value, PHP_URL_HOST) ?: ''));
            if ($host === 'hb.afl.rakuten.co.jp') {
                return $value;
            }
        }
        return '';
    }

    /**
     * 遷移先の商品URL(楽天トラベル施設ページ)を決定する。
     * 画像ホスト等の不適格URLは弾き、無ければ hotelNo から正規URLを組み立てる。
     */
    private static function resolveTargetUrl(array $b): string
    {
        // URL候補を優先度順に評価(施設ページとして妥当なものだけ採用)
        $candidates = [
            $b['hotelInformationUrl'] ?? null,
            $b['planListUrl'] ?? null,
            $b['dpPlanListUrl'] ?? null,
            $b['reviewUrl'] ?? null,
        ];
        foreach ($candidates as $url) {
            if (is_string($url) && self::isUsableHotelUrl($url)) {
                return $url;
            }
        }

        // どれも使えない場合は hotelNo から施設ページURLを組み立てる
        $hotelNo = trim((string) ($b['hotelNo'] ?? ''));
        if ($hotelNo !== '' && ctype_digit($hotelNo)) {
            return "https://travel.rakuten.co.jp/HOTEL/{$hotelNo}/{$hotelNo}.html";
        }

        return '';
    }

    /** 楽天トラベル施設ページとして遷移先に使えるURLか。画像APIなどは除外。 */
    private static function isUsableHotelUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !str_starts_with($url, 'http')) {
            return false;
        }
        foreach (self::REJECT_HOST_FRAGMENTS as $frag) {
            if (str_contains($url, $frag)) {
                return false;
            }
        }
        // 楽天トラベル(travel.rakuten.co.jp)配下であること
        return str_contains($url, 'travel.rakuten.co.jp');
    }
}
