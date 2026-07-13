<?php
declare(strict_types=1);

namespace App\Services\Rakuten;

/**
 * SimpleHotelSearch をエリアコード(large/middle/small)指定で叩く。
 * KeywordHotelSearch がテーマキーワードにヒットした宿しか拾えないのに対し、
 * こちらは「そのエリアに存在する宿」を機械的に総当たりで拾える(初期投入向け)。
 */
final class AreaHotelSearchService
{
    private RakutenApiClient $client;

    public function __construct(?RakutenApiClient $client = null)
    {
        $this->client = $client ?? new RakutenApiClient();
    }

    /**
     * @param array{hits?:int,page?:int} $opts
     * @return array<int,array<string,mixed>>
     */
    public function search(string $largeClassCode, string $middleClassCode, string $smallClassCode, array $opts = []): array
    {
        $hits = max(1, min((int) ($opts['hits'] ?? 30), 30));
        $page = max(1, min((int) ($opts['page'] ?? 1), 100));

        $params = [
            'largeClassCode'  => $largeClassCode,
            'middleClassCode' => $middleClassCode,
            'smallClassCode'  => $smallClassCode,
            'hits'            => $hits,
            'page'            => $page,
        ];
        $response = $this->client->get('simple_search', $params);
        return HotelResponseNormalizer::toCards($response);
    }
}
