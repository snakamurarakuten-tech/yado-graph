<?php
/**
 * カテゴリ単体ページ(依頼P1-4/5)。
 * @var array  $category   ['key','label','cardType',...]
 * @var array  $items      初期表示カード
 * @var bool   $hasMore    次ページの有無
 * @var array  $priceBands 価格帯フィルタ定義
 * @var string $activePrice 選択中の価格帯キー
 */
$key = (string) ($category['key'] ?? '');
$label = (string) ($category['label'] ?? '');
$activePrice = $activePrice ?? '';
$activeSort = $activeSort ?? 'popular';
$hasMore = !empty($hasMore);
$sorts = ['popular' => '人気順', 'rating' => '評価順', 'reviews' => 'クチコミ数順'];
/** 現在の条件を保ちつつ一部だけ変えたURL(改修4-2) */
$buildUrl = function (array $overrides = []) use ($key, $activePrice, $activeSort): string {
    $params = array_filter([
        'price' => $overrides['price'] ?? $activePrice,
        'sort'  => $overrides['sort'] ?? $activeSort,
    ], static fn ($v) => $v !== '' && $v !== 'popular');
    return '/category/' . rawurlencode($key) . ($params !== [] ? '?' . http_build_query($params) : '');
};
?>
<div class="collection-head">
  <div class="eyebrow"><a href="/categories" class="crumb">カテゴリ</a></div>
  <h1><?= e($label) ?></h1>
  <p class="count" data-collection-count><?= count($items) ?>軒〜</p>
</div>

<?php // 絞り込み・並び替え(改修4-2): 価格帯とソートはサーバー側(リンク遷移)、エリアはJSチップ ?>
<div class="filter-bar" data-category="<?= e($key) ?>">
  <?php if (!empty($priceBands)): ?>
    <div class="filter-chips" role="group" aria-label="価格帯で絞り込み">
      <a class="chip<?= $activePrice === '' ? ' active' : '' ?>" href="<?= e($buildUrl(['price' => ''])) ?>">すべて</a>
      <?php foreach ($priceBands as $band): ?>
        <a class="chip<?= $activePrice === $band['key'] ? ' active' : '' ?>" href="<?= e($buildUrl(['price' => (string) $band['key']])) ?>"><?= e($band['label']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="filter-chips" role="group" aria-label="並び替え">
    <?php foreach ($sorts as $sk => $sl): ?>
      <a class="chip<?= $activeSort === $sk ? ' active' : '' ?>" href="<?= e($buildUrl(['sort' => $sk])) ?>"><?= e($sl) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="filter-chips filter-area" data-area-chips hidden role="group" aria-label="エリアで絞り込み"></div>
</div>

<div class="collection-grid" data-category-grid
     data-key="<?= e($key) ?>"
     data-price="<?= e($activePrice) ?>"
     data-sort="<?= e($activeSort) ?>"
     data-page="1"
     data-has-more="<?= $hasMore ? '1' : '0' ?>">
  <?php foreach ($items as $item): ?>
    <?php component('hotel-card', ['card' => $item, 'cardType' => 'poster', 'category' => $key]); ?>
  <?php endforeach; ?>
</div>

<?php if (empty($items)): ?>
  <div class="empty-state">
    <h2>該当する宿が見つかりませんでした</h2>
    <p>条件を変えるか、他のカテゴリもご覧ください。</p>
    <a class="btn-primary-lg" href="/categories">カテゴリ一覧へ</a>
  </div>
<?php endif; ?>

<div class="load-more-wrap"<?= $hasMore ? '' : ' hidden' ?> data-load-more-wrap>
  <button class="btn-secondary" data-load-more>もっと見る</button>
</div>
<div data-infinite-sentinel></div>

<?php component('site-footer'); ?>
