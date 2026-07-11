<?php
/**
 * フリーワード検索ページ(改修 Phase 4-1)。
 * @var string $q          検索キーワード
 * @var string $pref       選択中の都道府県
 * @var string $price      選択中の価格帯キー
 * @var string $sort       並び替え('popular'|'rating'|'reviews')
 * @var array  $items      検索結果カード
 * @var int    $total      総ヒット数(DB時のみ正確)
 * @var bool   $hasMore    次ページの有無
 * @var array  $prefList   DBに存在する都道府県(件数順)
 * @var array  $priceBands 価格帯定義
 */
$hasQuery = $q !== '' || $pref !== '' || $price !== '';
$sorts = [
    'popular' => '人気順',
    'rating'  => '評価順',
    'reviews' => 'クチコミ数順',
];
/** 現在の条件を保ちつつ一部だけ変えたURLを作る */
$buildUrl = function (array $overrides = []) use ($q, $pref, $price, $sort): string {
    $params = array_filter([
        'q'     => $overrides['q'] ?? $q,
        'pref'  => $overrides['pref'] ?? $pref,
        'price' => $overrides['price'] ?? $price,
        'sort'  => $overrides['sort'] ?? $sort,
    ], static fn ($v) => $v !== '' && $v !== 'popular'); // 既定値はURLに含めない
    return '/search' . ($params !== [] ? '?' . http_build_query($params) : '');
};
?>
<div class="collection-head">
  <h1>宿をさがす</h1>
  <?php if ($hasQuery): ?>
    <p class="count" data-collection-count><?= e(number_format($total)) ?>軒</p>
  <?php endif; ?>
</div>

<?php // 検索フォーム(GET)。エリア・価格帯はプルダウン/チップで選ぶ ?>
<form class="search-bar" action="/search" method="get" role="search">
  <div class="search-input-wrap">
    <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="宿名・温泉地・キーワード"
           maxlength="60" autocomplete="off" <?= $q === '' ? 'autofocus' : '' ?>>
  </div>
  <select name="pref" aria-label="都道府県で絞り込み">
    <option value="">すべてのエリア</option>
    <?php foreach ($prefList as $p): ?>
      <option value="<?= e($p) ?>"<?= $p === $pref ? ' selected' : '' ?>><?= e($p) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($sort !== 'popular'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
  <?php if ($price !== ''): ?><input type="hidden" name="price" value="<?= e($price) ?>"><?php endif; ?>
  <button type="submit" class="btn-primary-lg search-submit">検索</button>
</form>

<?php // 価格帯チップ + 並び替えチップ(リンク遷移・条件は維持) ?>
<div class="filter-bar">
  <div class="filter-chips" role="group" aria-label="価格帯で絞り込み">
    <a class="chip<?= $price === '' ? ' active' : '' ?>" href="<?= e($buildUrl(['price' => ''])) ?>">すべて</a>
    <?php foreach ($priceBands as $band): ?>
      <a class="chip<?= $price === $band['key'] ? ' active' : '' ?>" href="<?= e($buildUrl(['price' => (string) $band['key']])) ?>"><?= e($band['label']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($hasQuery): ?>
    <div class="filter-chips" role="group" aria-label="並び替え">
      <?php foreach ($sorts as $key => $label): ?>
        <a class="chip<?= $sort === $key ? ' active' : '' ?>" href="<?= e($buildUrl(['sort' => $key])) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($hasQuery): ?>
  <div class="collection-grid" data-search-grid
       data-q="<?= e($q) ?>" data-pref="<?= e($pref) ?>" data-price="<?= e($price) ?>" data-sort="<?= e($sort) ?>"
       data-page="1" data-has-more="<?= $hasMore ? '1' : '0' ?>">
    <?php foreach ($items as $item): ?>
      <?php component('hotel-card', ['card' => $item, 'cardType' => 'poster', 'category' => 'search']); ?>
    <?php endforeach; ?>
  </div>

  <?php if (empty($items)): ?>
    <div class="empty-state">
      <h2>該当する宿が見つかりませんでした</h2>
      <p>キーワードを短くするか、エリア・価格帯の条件をゆるめてみてください。</p>
      <a class="btn-primary-lg" href="/categories">カテゴリからさがす</a>
    </div>
  <?php endif; ?>

  <div class="load-more-wrap"<?= $hasMore ? '' : ' hidden' ?> data-load-more-wrap>
    <button class="btn-secondary" data-load-more>もっと見る</button>
  </div>
  <div data-infinite-sentinel></div>
<?php else: ?>
  <?php // 初期表示: 検索のヒントとしてカテゴリへの導線を出す ?>
  <div class="search-hints">
    <h2 class="section-title"><span class="bar"></span>キーワードのヒント</h2>
    <div class="filter-chips">
      <?php foreach (['露天風呂', '離れ', 'オーシャンビュー', 'サウナ', '会席', '一人旅'] as $hint): ?>
        <a class="chip" href="/search?q=<?= e(rawurlencode($hint)) ?>"><?= e($hint) ?></a>
      <?php endforeach; ?>
    </div>
    <p class="search-hint-note">気分からさがすなら<a href="/categories">カテゴリ一覧</a>、地名からなら<a href="/areas">温泉地一覧</a>もどうぞ。</p>
  </div>
<?php endif; ?>

<?php component('site-footer'); ?>
