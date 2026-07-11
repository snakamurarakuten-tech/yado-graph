<?php
declare(strict_types=1);

namespace App\Services;

/**
 * よくある質問(改修 Phase 3-5)。
 * 既存の構造化フィールドから Q&A を機械生成する。データがある質問だけ返す。
 * 2問以上あるとき SeoService が FAQPage の JSON-LD を出力する。
 */
final class FaqService
{
    /**
     * @param array<string,mixed> $hotel 正規化済み $hotel
     * @return array<int,array{q:string,a:string}>
     */
    public function forHotel(array $hotel): array
    {
        $name = (string) ($hotel['hotelName'] ?? '');
        $faq = [];

        $in  = trim((string) ($hotel['checkinTime'] ?? ''));
        $out = trim((string) ($hotel['checkoutTime'] ?? ''));
        if ($in !== '' || $out !== '') {
            $parts = [];
            if ($in !== '') {
                $parts[] = 'チェックインは' . $in;
            }
            if ($out !== '') {
                $parts[] = 'チェックアウトは' . $out;
            }
            $faq[] = [
                'q' => 'チェックイン・チェックアウトの時間は?',
                'a' => implode('、', $parts) . 'です。プランにより異なる場合があるため、予約時にご確認ください。',
            ];
        }

        $parking = trim((string) ($hotel['parkingInformation'] ?? ''));
        if ($parking !== '') {
            $faq[] = [
                'q' => '駐車場はありますか?',
                'a' => $parking,
            ];
        }

        $station = trim((string) ($hotel['nearestStation'] ?? ''));
        $access  = trim((string) ($hotel['access'] ?? ''));
        if ($station !== '' || $access !== '') {
            $a = '';
            if ($station !== '') {
                $a .= '最寄り駅は' . $station . 'です。';
            }
            if ($access !== '') {
                $a .= $access;
            }
            $faq[] = [
                'q' => 'アクセス方法を教えてください',
                'a' => $a,
            ];
        }

        $quality = trim((string) ($hotel['bathQuality'] ?? ''));
        if ($quality !== '') {
            $faq[] = [
                'q' => '温泉の泉質は?',
                'a' => $quality,
            ];
        }

        $charge = (int) ($hotel['minCharge'] ?? 0);
        if ($charge > 0) {
            $faq[] = [
                'q' => '宿泊料金の目安は?',
                'a' => '1泊 ' . number_format($charge) . '円〜が目安です(時期・プランにより変動します)。最新の料金は楽天トラベルでご確認ください。',
            ];
        }

        return $faq;
    }
}
