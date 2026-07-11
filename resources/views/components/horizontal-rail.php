<?php
/**
 * @var string  $title
 * @var array   $items    カード配列(HotelCardMapper形式)
 * @var string  $cardType 'poster'|'wide'
 * @var ?string $moreHref 「一覧はこちら」の遷移先(カテゴリページ等)
 * @var ?string $tag      この棚が対応するタグ(C-7 パーソナライズ用)
 * @var ?string $key      レール識別子(計測・タブ用)
 * @var ?bool   $mood     感情訴求棚か(並べ替え対象の目印)
 * @var ?bool   $showCta  サムネ上の楽天CTAを出すか(トップでは非表示)
 */
$cardType = $cardType ?? 'poster';
if (empty($items)) { return; }
$tag  = $tag ?? '';
$key  = $key ?? '';
$mood = !empty($mood);
$moreHref = $moreHref ?? '';
$showCta = $showCta ?? true;
?>
<section class="rail reveal" data-rail data-key="<?= e($key) ?>" data-tag="<?= e($tag) ?>"<?= $mood ? ' data-mood="1"' : '' ?><?= $key ? ' id="rail-' . e($key) . '"' : '' ?>>
  <div class="rail-head">
    <h2><span class="bar"></span><?= e($title) ?></h2>
    <?php if ($moreHref !== ''): ?>
      <a class="rail-more" href="<?= e($moreHref) ?>" data-track="rail_more" data-category="<?= e($key) ?>">一覧はこちら
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
      </a>
    <?php endif; ?>
  </div>
  <div class="rail-track">
    <?php foreach ($items as $item): ?>
      <?php component('hotel-card', ['card' => $item, 'cardType' => $cardType, 'category' => $key, 'showCta' => $showCta]); ?>
    <?php endforeach; ?>
  </div>
</section>
