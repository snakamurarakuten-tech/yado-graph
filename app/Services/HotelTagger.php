<?php
declare(strict_types=1);

namespace App\Services;

/**
 * 施設データからタグ(温泉/露天/サウナ/絶景…)を推定する。
 *
 * 楽天APIは設備フラグを常には返さないため、hotelName・hotelSpecial・access 等の
 * テキストに対して taxonomy.tags のキーワードを部分一致させてタグを立てる。
 * 露天風呂フラグ等が構造化データで取れる場合は $flags で明示上書きできる。
 */
final class HotelTagger
{
    /**
     * @param array<string,mixed> $source 施設テキスト源 ['hotelName','hotelSpecial','area','access',...]
     * @param array<string,bool>  $flags  明示フラグ(あれば優先) ['sauna'=>true, ...]
     * @return array<int,string> タグキー配列(例: ['onsen','rotenburo'])
     */
    public static function tag(array $source, array $flags = []): array
    {
        $haystack = self::haystack($source);
        $tags = [];

        foreach ((array) config('taxonomy.tags', []) as $key => $def) {
            // 明示フラグが来ていればそれを尊重
            if (array_key_exists($key, $flags)) {
                if ($flags[$key]) {
                    $tags[] = $key;
                }
                continue;
            }
            foreach ((array) ($def['keywords'] ?? []) as $needle) {
                if ($needle !== '' && mb_stripos($haystack, (string) $needle) !== false) {
                    $tags[] = $key;
                    break;
                }
            }
        }

        return array_values(array_unique($tags));
    }

    /** タグ一致に使うテキストを1本に連結する。 */
    private static function haystack(array $source): string
    {
        $parts = [];
        foreach (['hotelName', 'hotelSpecial', 'catchCopy', 'area', 'address', 'access'] as $k) {
            $v = $source[$k] ?? '';
            if (is_string($v) && $v !== '') {
                $parts[] = $v;
            }
        }
        return implode(' ', $parts);
    }
}
