<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ContentStore;
use App\Services\Storage\HotelRepository;
use App\Support\HttpNotFoundException;
use App\Support\View;

/**
 * 特集ページ(AI下書き+人間レビューで公開)。
 *  - /features        公開済み特集の一覧
 *  - /features/{slug} 特集本文(宿カードはDBから解決。DBに無い宿はスキップ)
 */
final class FeatureController
{
    private ContentStore $store;
    private HotelRepository $repo;

    public function __construct(?ContentStore $store = null, ?HotelRepository $repo = null)
    {
        $this->store = $store ?? new ContentStore();
        $this->repo = $repo ?? new HotelRepository();
    }

    public function index(): void
    {
        $features = $this->store->publishedFeatures();
        View::render('features/index', [
            'features' => $features,
            'seo' => [
                'title'       => '特集一覧 - ' . config('app.name'),
                'description' => '季節やテーマで選ぶ、編集部の旅館特集。',
                'canonical'   => (string) config('app.url') . '/features',
                'image'       => '',
                'jsonLd'      => null,
            ],
            'pageCss'   => 'category',
            'pageJs'    => 'search',
            'activeTab' => 'home',
        ]);
    }

    public function show(string $slug): void
    {
        $f = $this->store->feature($slug);
        if ($f === null) {
            throw new HttpNotFoundException('この特集は見つかりませんでした。');
        }

        // 宿カードをDBから解決(掲載終了した宿は自動で消える)
        $entries = [];
        foreach ((array) $f['items'] as $it) {
            $card = null;
            foreach ($this->repo->findMany([(string) $it['hotelNo']]) as $c) {
                $card = $c;
            }
            if ($card !== null) {
                $entries[] = ['card' => $card, 'heading' => (string) $it['heading'], 'body' => (string) $it['body']];
            }
        }
        if (count($entries) < 3) {
            throw new HttpNotFoundException('この特集は現在準備中です。');
        }

        $base = rtrim((string) config('app.url'), '/');
        View::render('features/show', [
            'f'       => $f,
            'entries' => $entries,
            'seo' => [
                'title'       => $f['title'] . ' - ' . config('app.name'),
                'description' => mb_substr((string) $f['lead'], 0, 110),
                'canonical'   => $base . '/features/' . rawurlencode((string) $f['slug']),
                'image'       => (string) ($entries[0]['card']['imageUrl'] ?? ''),
                'jsonLd'      => [
                    '@context' => 'https://schema.org',
                    '@type'    => 'ItemList',
                    'name'     => (string) $f['title'],
                    'itemListElement' => array_map(static fn (array $e, int $i): array => [
                        '@type'    => 'ListItem',
                        'position' => $i + 1,
                        'name'     => (string) $e['card']['hotelName'],
                        'url'      => $base . '/hotel/' . rawurlencode((string) $e['card']['hotelNo']),
                    ], $entries, array_keys($entries)),
                ],
            ],
            'pageCss'   => 'category',
            'pageJs'    => 'search',
            'activeTab' => 'home',
        ]);
    }
}
