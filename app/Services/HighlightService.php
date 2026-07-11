<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Storage\HotelRepository;

/**
 * おすすめポイント(改修 Phase 3-2)。
 *
 * DBの県別統計と比較した「相対評価」を最大3点生成する。
 * 楽天ページには存在しない独自コンテンツになり、詳細ページの情報量と
 * description の一意性(SEO)の両方に効く。
 *
 * 文言は数値の事実のみを述べる(口コミの存在を示唆しない=ステマ規制対応)。
 */
final class HighlightService
{
    private HotelRepository $repo;

    public function __construct(?HotelRepository $repo = null)
    {
        $this->repo = $repo ?? new HotelRepository();
    }

    /**
     * @param array<string,mixed> $hotel 正規化済み $hotel
     * @return array<int,array{icon:string,text:string}> 最大3点(材料が無ければ空)
     */
    public function forHotel(array $hotel): array
    {
        $pref = (string) ($hotel['area'] ?? '');
        $stats = $this->repo->prefStats($pref);
        $out = [];

        // 1) 総合評価が県平均を上回る(+0.2以上・母数10軒以上のときのみ)
        $avg = (float) ($hotel['reviewAverage'] ?? 0);
        if ($avg > 0 && $stats['cnt'] >= 10 && $stats['avgReview'] > 0 && $avg >= $stats['avgReview'] + 0.2) {
            $out[] = [
                'icon' => 'star',
                'text' => sprintf(
                    '総合評価 %.1f は、%sの旅館平均(%.1f)を上回る水準です',
                    $avg, $pref, $stats['avgReview']
                ),
            ];
        }

        // 2) クチコミ数が県内上位10%
        $cnt = (int) ($hotel['reviewCount'] ?? 0);
        if ($cnt > 0 && $stats['topReviewCount'] > 0 && $cnt >= $stats['topReviewCount'] && $stats['cnt'] >= 20) {
            $out[] = [
                'icon' => 'chat',
                'text' => sprintf('%sで最も評価が集まっている宿のひとつです(%s件)', $pref, number_format($cnt)),
            ];
        }

        // 3) 価格帯の位置づけ(県内中央値との比較)
        $charge = (int) ($hotel['minCharge'] ?? 0);
        if ($charge > 0 && $stats['medCharge'] > 0) {
            if ($charge <= (int) round($stats['medCharge'] * 0.8)) {
                $out[] = [
                    'icon' => 'yen',
                    'text' => sprintf('1泊 %s円〜は、県内では手が届きやすい価格帯です', number_format($charge)),
                ];
            } elseif ($charge >= (int) round($stats['medCharge'] * 1.5)) {
                $out[] = [
                    'icon' => 'yen',
                    'text' => sprintf('1泊 %s円〜の、県内でも上質な価格帯の宿です', number_format($charge)),
                ];
            }
        }

        // 4) 軸評価の突出(風呂・食事など)。1〜3で3点に満たないときの補完。
        if (count($out) < 3) {
            $axis = (array) ($hotel['axis'] ?? []);
            $best = null;
            foreach ($axis as $a) {
                $v = (float) ($a['value'] ?? 0);
                if ($v >= 4.5 && ($best === null || $v > $best['value'])) {
                    $best = ['label' => (string) $a['label'], 'value' => $v];
                }
            }
            if ($best !== null) {
                $out[] = [
                    'icon' => 'bath',
                    'text' => sprintf('軸別評価では「%s」が %.1f と特に高い評価です', $best['label'], $best['value']),
                ];
            }
        }

        // 5) タグからの定型(それでも足りない場合の最後の補完)
        if (count($out) < 2) {
            $tags = array_map('strval', (array) ($hotel['tags'] ?? []));
            if (in_array('onsen', $tags, true) && in_array('rotenburo', $tags, true)) {
                $out[] = ['icon' => 'bath', 'text' => '露天風呂のある温泉宿です'];
            }
        }

        return array_slice($out, 0, 3);
    }

    /**
     * 「こんな人におすすめ」チップ(コンテンツ増強4)。
     * 軸評価・タグ・価格帯からルール生成。最大3つ。
     *
     * @param array<string,mixed> $hotel
     * @return array<int,string>
     */
    public function audiences(array $hotel): array
    {
        $axis = (array) ($hotel['axis'] ?? []);
        $val = static fn (string $k): float => (float) ($axis[$k]['value'] ?? 0);
        $tags = array_map('strval', (array) ($hotel['tags'] ?? []));
        $out = [];

        if ($val('bath') >= 4.5) {
            $out[] = in_array('rotenburo', $tags, true)
                ? '露天風呂・お風呂を最優先したい人'
                : 'お風呂を最優先したい人';
        }
        if ($val('meal') >= 4.5) {
            $out[] = '食事を目当てに宿を選ぶ人';
        }
        if ($val('service') >= 4.5) {
            $out[] = 'おもてなし重視の記念日・特別な旅';
        }
        if ($val('room') >= 4.5 && count($out) < 3) {
            $out[] = '部屋でゆっくり過ごしたい人';
        }
        if ($val('location') >= 4.5 && count($out) < 3) {
            $out[] = '観光の拠点にしたい人';
        }

        // 価格の文脈(県内中央値と比較)
        if (count($out) < 3) {
            $stats = $this->repo->prefStats((string) ($hotel['area'] ?? ''));
            $charge = (int) ($hotel['minCharge'] ?? 0);
            if ($charge > 0 && $stats['medCharge'] > 0) {
                if ($charge <= (int) round($stats['medCharge'] * 0.8)) {
                    $out[] = 'コスパよく温泉を楽しみたい人';
                } elseif ($charge >= (int) round($stats['medCharge'] * 1.5)) {
                    $out[] = 'ワンランク上の滞在をしたい人';
                }
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 3);
    }

    /**
     * 「評価から見る強みと注意点」(コンテンツ増強・追加分)。
     * 軸データの高低から正直なプラス/マイナスを生成する。
     * マイナス面も書くことで比較サイトとしての信頼性(E-E-A-T)に寄与する。
     *
     * @param array<string,mixed> $hotel
     * @return array{pros:array<int,string>,cons:array<int,string>}
     */
    public function prosCons(array $hotel): array
    {
        $axis = (array) ($hotel['axis'] ?? []);
        $rows = [];
        foreach ($axis as $a) {
            $v = (float) ($a['value'] ?? 0);
            if ($v > 0) {
                $rows[] = ['label' => (string) ($a['label'] ?? ''), 'value' => $v];
            }
        }
        if (count($rows) < 3) {
            return ['pros' => [], 'cons' => []];
        }
        usort($rows, static fn ($a, $b) => $b['value'] <=> $a['value']);

        $pros = [];
        foreach (array_slice($rows, 0, 2) as $r) {
            if ($r['value'] >= 4.3) {
                $pros[] = sprintf('%sの評価が%.1fと高く、この宿の柱になっています', $r['label'], $r['value']);
            }
        }

        $cons = [];
        $worst = end($rows);
        // 最上位との差が0.5以上あり、かつ3.9以下のときだけ「注意点」として正直に書く
        if ($worst !== false && $worst['value'] <= 3.9 && ($rows[0]['value'] - $worst['value']) >= 0.5) {
            $cons[] = sprintf('%sの評価は%.1fと控えめ。重視する場合は事前にプランや設備を確認するのがおすすめです', $worst['label'], $worst['value']);
        }

        return ['pros' => $pros, 'cons' => $cons];
    }
}
