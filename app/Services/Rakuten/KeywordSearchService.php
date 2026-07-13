<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

/**
 * KeywordHotelSearch / SimpleHotelSearch を叩き、カード配列に正規化する。
 * 「同じ温泉地」「この宿もおすすめ」の候補抽出に使用。
 */
final class KeywordSearchService
{
    private RakutenApiClient $client;

    public function __construct(?RakutenApiClient $client = null)
    {
        $this->client = $client ?? new RakutenApiClient();
    }

    /**
     * キーワード検索。カード用の軽量配列で返す。
     * @param int $hits 取得件数
     * @return array<int,array<string,mixed>>
     */
    public function searchByKeyword(string $keyword, int $hits = 12): array
    {
        return $this->search($keyword, ['hits' => $hits]);
    }

    /**
     * オプション付きキーワード検索(ページング・ソート対応)。
     * @param array{hits?:int,page?:int,sort?:string} $opts
     * @return array<int,array<string,mixed>>
     */
    public function search(string $keyword, array $opts = []): array
    {
        $hits = max(1, min((int) ($opts['hits'] ?? 12), 30));
        $page = max(1, min((int) ($opts['page'] ?? 1), 100));
        $sort = (string) ($opts['sort'] ?? 'standard');

        $params = [
            'keyword' => $keyword,
            'hits'    => $hits,
            'page'    => $page,
            'sort'    => $sort,
        ];
        // メモ3 TODO: 楽天APIの squeezeCondition(温泉等の施設絞り込み)フック。
        // 公式ドキュメントで KeywordHotelSearch での対応値を確認できたら
        // 環境変数 RAKUTEN_SQUEEZE_CONDITION に設定する(例: 'onsen')。
        // 未確認のため既定は未設定=挙動不変。使えればチェーン系除外フィルタ自体が軽くなる。
        $squeeze = (string) config('rakuten.squeeze_condition', '');
        if ($squeeze !== '') {
            $params['squeezeCondition'] = $squeeze;
        }

        $response = $this->client->get('keyword_search', $params);
        return HotelResponseNormalizer::toCards($response);
    }
}
