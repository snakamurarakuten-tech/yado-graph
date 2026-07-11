<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

use App\Services\BadgeService;
use App\Services\HotelTagger;

/**
 * hotelBasicInfo を横スクロールカード用の共通配列へ変換する小さなマッパー。
 * KeywordSearch / Ranking の両方から使い、カードの形を統一する。
 *
 * 追加(依頼B-5/D-12): タグ・バッジ・料金帯・取得日時(最終更新)を付与。
 * 追加(依頼A-1): affiliateUrl は AffiliateLinkBuilder で正しい hb.afl リンクに。
 */
final class HotelCardMapper
{
    /**
     * @return array<string,mixed>
     */
    public static function fromBasicInfo(array $b): array
    {
        $image = '';
        // hotelThumbnailUrl を最後のフォールバックに追加(NO IMAGE削減)
        foreach (['hotelImageUrl', 'hotelSpecialImageUrl', 'roomImageUrl', 'hotelThumbnailUrl'] as $key) {
            $v = $b[$key] ?? null;
            if (is_array($v)) {
                $v = $v[0] ?? null;
            }
            if (is_string($v) && $v !== '') {
                $image = $v;
                break;
            }
        }

        $card = [
            'hotelNo'       => (string) ($b['hotelNo'] ?? ''),
            'hotelName'     => (string) ($b['hotelName'] ?? ''),
            'area'          => trim((string) ($b['address1'] ?? '')),
            'hotelSpecial'  => trim((string) ($b['hotelSpecial'] ?? '')),
            'reviewAverage' => (float) ($b['reviewAverage'] ?? 0),
            'reviewCount'   => (int) ($b['reviewCount'] ?? 0),
            'minCharge'     => (int) ($b['hotelMinCharge'] ?? 0),
            'imageUrl'      => $image,
            // A-1: 画像APIのURLを排除し、正しい楽天トラベルのアフィリエイトリンクを生成
            'affiliateUrl'  => AffiliateLinkBuilder::fromBasicInfo($b),
            // D-12: 楽天API取得日時(=このカードを組み立てた日時)。ビュー側で「最終更新」に整形
            'fetchedAt'     => time(),
        ];

        // タグ → バッジ(設定テーブル駆動)
        $card['tags']   = HotelTagger::tag($card);
        $card['badges'] = (new BadgeService())->forHotel($card);

        return $card;
    }
}
