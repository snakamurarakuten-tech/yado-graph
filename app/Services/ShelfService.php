<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Rakuten\KeywordSearchService;

/**
 * 感情訴求型の棚(ムード×タグ)を生成する(依頼B-6)。
 *
 * config/taxonomy.php の shelves 定義から、表示のたびにランダムに数本を選び、
 * 並び順もシャッフルして「毎回同じ並びにならない」ようにする。
 * 各棚には tag メタを持たせ、JS側の履歴/好みパーソナライズ(C-7)で並べ替えできる。
 */
final class ShelfService
{
    private KeywordSearchService $keyword;

    public function __construct(?KeywordSearchService $keyword = null)
    {
        $this->keyword = $keyword ?? new KeywordSearchService();
    }

    /**
     * ランダムに選んだ感情訴求棚を返す。取得できたものだけ。
     *
     * @param int $count 生成する棚の本数(上限)
     * @return array<int,array{title:string,items:array,cardType:string,tag:string,mood:bool}>
     */
    public function emotionalShelves(int $count = 3): array
    {
        $defs = (array) config('taxonomy.shelves', []);
        if ($defs === []) {
            return [];
        }

        shuffle($defs); // 毎回並びを変える
        $rails = [];
        foreach ($defs as $def) {
            if (count($rails) >= $count) {
                break;
            }
            $search = (string) ($def['search'] ?? '');
            if ($search === '') {
                continue;
            }
            $items = $this->keyword->searchByKeyword($search, 12);
            if ($items === []) {
                continue;
            }
            $rails[] = [
                'title'    => (string) ($def['title'] ?? ''),
                'items'    => $items,
                'cardType' => 'wide',
                'tag'      => (string) ($def['tag'] ?? ''),
                'mood'     => true, // 感情訴求棚の目印(JSパーソナライズ対象)
            ];
        }
        return $rails;
    }
}
