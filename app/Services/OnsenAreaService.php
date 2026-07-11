<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 温泉地ガイド(コンテンツ増強①)。
 * 宿名・住所から温泉地を判定し、辞書(app/Config/onsen_areas.php)の解説を返す。
 *
 * マッチング仕様:
 *  - needle が 宿名 または 住所 に含まれること
 *  - pref 指定がある項目は宿の都道府県が一致すること
 *    (草津=滋賀県草津市、白浜=千葉県南房総 のような同名地名の誤マッチを防ぐ)
 *  - 複数該当時は needle が長い(=より具体的な)ものを優先
 */
final class OnsenAreaService
{
    /** @var array<string,array{pref:string,needle:string,desc:string}> */
    private array $areas;

    public function __construct(?array $areas = null)
    {
        $this->areas = $areas ?? (array) config('onsen_areas', []);
    }

    /**
     * @param array<string,mixed> $hotel 正規化済み $hotel
     * @return ?array{name:string,desc:string,searchQuery:string}
     */
    public function forHotel(array $hotel): ?array
    {
        $name = (string) ($hotel['hotelName'] ?? '');
        $address = (string) ($hotel['address'] ?? '');
        $pref = (string) ($hotel['area'] ?? '');
        if ($name === '' && $address === '') {
            return null;
        }
        $haystack = $name . ' ' . $address;

        $best = null;
        $bestLen = 0;
        foreach ($this->areas as $areaName => $def) {
            $needle = (string) ($def['needle'] ?? '');
            if ($needle === '' || mb_strpos($haystack, $needle) === false) {
                continue;
            }
            $reqPref = (string) ($def['pref'] ?? '');
            if ($reqPref !== '' && $pref !== '' && $pref !== $reqPref) {
                continue; // 同名地名の誤マッチ防止
            }
            $len = mb_strlen($needle);
            if ($len > $bestLen) {
                $bestLen = $len;
                $best = [
                    'key'         => (string) ($def['key'] ?? ''),
                    'name'        => (string) $areaName,
                    'desc'        => (string) ($def['desc'] ?? ''),
                    // 同じ温泉地の宿をさがす内部リンク用(needleで検索すると再現率が高い)
                    'searchQuery' => $needle,
                ];
            }
        }
        return $best;
    }

    /**
     * URLキー(romaji)から温泉地を引く(エリアページ用)。
     * @return ?array{key:string,name:string,pref:string,desc:string}
     */
    public function byKey(string $key): ?array
    {
        if ($key === '') {
            return null;
        }
        foreach ($this->areas as $name => $def) {
            if ((string) ($def['key'] ?? '') === $key) {
                return [
                    'key'  => $key,
                    'name' => (string) $name,
                    'pref' => (string) ($def['pref'] ?? ''),
                    'desc' => (string) ($def['desc'] ?? ''),
                ];
            }
        }
        return null;
    }

    /** @return array<int,array{key:string,name:string,pref:string}> 全温泉地(sitemap用) */
    public function all(): array
    {
        $out = [];
        foreach ($this->areas as $name => $def) {
            $out[] = [
                'key'  => (string) ($def['key'] ?? ''),
                'name' => (string) $name,
                'pref' => (string) ($def['pref'] ?? ''),
            ];
        }
        return $out;
    }
}
