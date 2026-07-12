<?php
declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Rakuten\InstagramService;

/**
 * 公式HPの発見と検証(「この宿のこだわり」の材料集め)。
 *
 * 誤爆(別の宿・予約サイト・閉業した旧サイト)が最悪の失敗なので、
 * 三段構えで守る:
 *  1. 予約サイト・SNS等の既知ドメインを候補から機械的に除外
 *  2. ページを実際に取得し、正規化した宿名がページ内に存在するか照合
 *  3. DBの住所から市区町村を取り、ページ内に存在するか照合
 * どれか一つでも通らなければ「書かない」(skip) — 常に安全側に倒す。
 */
final class OfficialSiteFinder
{
    /** 公式HPではないドメイン(部分一致・小文字) */
    private const EXCLUDED_HOSTS = [
        'rakuten.co.jp', 'r10s.jp', 'jalan.net', 'ikyu.com', 'rurubu', 'jtb.co.jp',
        'booking.com', 'agoda.com', 'expedia', 'hotels.com', 'trip.com', 'tripadvisor',
        'yahoo.co.jp', 'google.', 'instagram.com', 'facebook.com', 'x.com', 'twitter.com',
        'youtube.com', 'wikipedia.org', 'retty.me', 'tabelog.com', 'hotpepper',
        'relux.jp', 'yadoken', 'biglobe', 'mytrip', 'ozmall', 'asoview',
        'navitime', 'mapion', 'ekiten', 'goo.ne.jp', 'line.me', 'note.com', 'ameblo',
    ];

    private CustomSearchClient $search;
    private GeminiClient $gemini;

    public function __construct(?CustomSearchClient $search = null, ?GeminiClient $gemini = null)
    {
        $this->search = $search ?? new CustomSearchClient();
        $this->gemini = $gemini ?? new GeminiClient();
    }

    /**
     * URL候補の発見。プロバイダは env SEARCH_PROVIDER で切替:
     *  - gemini(既定): Gemini検索グラウンディングでURL候補を取得
     *    ※ Custom Search JSON API が新規顧客に閉鎖されたための代替。
     *      グラウンディングは「発見」のみに使い、本文はここで返さない
     *  - cse: 従来の Custom Search JSON API(閉鎖前からアクセスがあるアカウント用)
     *
     * @return array<int,string> URL候補(優先順)
     */
    private function discoverCandidates(string $name, string $city, string $pref): array
    {
        if ((string) config('ai.search_provider', 'gemini') === 'cse') {
            $results = $this->search->search("{$name} {$city} 公式サイト", 6);
            return array_values(array_filter(array_map(
                static fn (array $r): string => (string) ($r['link'] ?? ''),
                $results
            )));
        }
        $res = $this->gemini->generateGrounded(
            "{$pref}{$city}にある宿泊施設「{$name}」の公式サイト(施設自身が運営するホームページ)のトップページURLを調べてください。" .
            '予約サイト(楽天トラベル・じゃらん・一休・Booking.com等)やSNS、まとめサイトは公式サイトではありません。' .
            "見つかった場合はそのURLを、公式サイトが存在しない・確信が持てない場合は「不明」とだけ答えてください。"
        );
        return $res !== null ? $res['links'] : [];
    }

    /**
     * 公式HPを探して検証し、本文テキストまで返す。
     *
     * @param array<string,mixed> $hotel 正規化済み $hotel(hotelName/address/area 必須)
     * @return ?array{url:string,text:string} 検証を通らなければ null(=書かない)
     */
    public function findVerified(array $hotel): ?array
    {
        $name = (string) ($hotel['hotelName'] ?? '');
        $address = (string) ($hotel['address'] ?? '');
        $pref = (string) ($hotel['area'] ?? '');
        if ($name === '' || $address === '') {
            return null;
        }
        $city = \App\Services\Storage\HotelRepository::cityFromAddress($address, $pref);

        // 1) URL候補の発見(Geminiグラウンディング or CSE)
        $candidates = array_slice($this->discoverCandidates($name, $city, $pref), 0, 6);
        foreach ($candidates as $url) {
            $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
            if ($host === '') {
                continue;
            }
            // グラウンディングのURLはGoogleのリダイレクトプロキシ経由のことがあるため、
            // 除外判定は「取得後の最終URL」で行う(プロキシ自体は素通しする)
            $isProxy = str_contains($host, 'vertexaisearch') || str_contains($host, 'grounding-api');
            if (!$isProxy && $this->isExcludedHost($host)) {
                continue;
            }

            // 2) ページ取得(リダイレクトを解決し、最終URLで再度除外判定)
            $fetched = $this->fetch($url);
            if ($fetched === null) {
                continue;
            }
            [$html, $finalUrl] = $fetched;
            $finalHost = strtolower((string) (parse_url($finalUrl, PHP_URL_HOST) ?: ''));
            if ($finalHost === '' || $this->isExcludedHost($finalHost)) {
                continue;
            }
            $url = $finalUrl;
            $text = $this->htmlToText($html);
            if (mb_strlen($text) < 300) {
                continue; // 中身が薄すぎる(パーキングページ等)
            }

            // 3) 宿名 + 市区町村の照合(正規化して比較)
            $normPage = InstagramService::normalizeTag($text);
            $normName = InstagramService::normalizeTag($name);
            if ($normName === '' || mb_strpos($normPage, $normName) === false) {
                continue;
            }
            if ($city !== '' && mb_strpos($text, $city) === false) {
                continue;
            }

            return ['url' => $url, 'text' => mb_substr($text, 0, 9000)];
        }
        return null;
    }

    private function isExcludedHost(string $host): bool
    {
        foreach (self::EXCLUDED_HOSTS as $ex) {
            if (str_contains($host, $ex)) {
                return true;
            }
        }
        return false;
    }

    /** @return ?array{0:string,1:string} [HTML, リダイレクト解決後の最終URL] */
    private function fetch(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; YadoGraphBot/1.0)',
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $finalUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        if (!is_string($res) || $code >= 400 || strlen($res) > 3_000_000) {
            return null;
        }
        return [$res, $finalUrl !== '' ? $finalUrl : $url];
    }

    /** HTML → プレーンテキスト(script/style除去・タグ除去・空白圧縮)。 */
    private function htmlToText(string $html): string
    {
        // 文字コードをUTF-8へ寄せる(公式HPはSJIS/EUCも残っている)
        $enc = mb_detect_encoding($html, ['UTF-8', 'SJIS-win', 'EUC-JP', 'ISO-2022-JP'], true);
        if ($enc !== false && $enc !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $enc);
        }
        $html = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html);
        $html = (string) preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
