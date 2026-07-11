<?php
/**
 * @var array  $card     ['hotelNo','hotelName','area','reviewAverage','reviewCount','imageUrl','badges','fetchedAt','tags','affiliateUrl']
 * @var string $cardType 'poster'|'wide'
 * @var string $category この宿が属するレールのキー(計測用・任意)
 * @var ?bool  $showCta  サムネ上の楽天CTAを出すか(トップでは非表示)
 *
 * 構造: カード全体は詳細への stretched link。サムネ上のCTAだけが楽天へ直接遷移(P0-1)。
 * ネストアンカーを避けるため、card-link(absolute inset:0)とcard-cta(z-index上)を分離。
 */
$cardType = $cardType ?? 'poster';
$class = $cardType === 'wide' ? 'card-wide' : 'card-poster';
$category = $category ?? '';
$showCta = $showCta ?? true;
$no   = (string) ($card['hotelNo'] ?? '');
$href = $no !== '' ? '/hotel/' . rawurlencode($no) : '#';
$img  = $card['imageUrl'] ?? '';
$name = (string) ($card['hotelName'] ?? '');
$rating = (float) ($card['reviewAverage'] ?? 0);
$reviewCount = (int) ($card['reviewCount'] ?? 0);
$badges = array_slice((array) ($card['badges'] ?? []), 0, 2);
$tags = implode(',', array_map('strval', (array) ($card['tags'] ?? [])));
$aff  = (string) ($card['affiliateUrl'] ?? '');
// D-12改め改修5-2: 取得日時はカード上ではノイズになるため非表示に変更。
// 情報の鮮度は詳細ページ(CTAブロック下)でのみ表示する。
?>
<article class="<?= $class ?> card" data-tags="<?= e($tags) ?>" data-area="<?= e((string) ($card['area'] ?? '')) ?>">
  <div class="card-thumb-wrap">
    <?php // P2-6: 宿名の重複を防ぐため画像altは装飾扱い(空)。宿名はテキスト側で1回だけ出す。 ?>
    <?php if ($img): ?>
      <span class="thumb-wrap"><img class="thumb" src="<?= e($img) ?>" alt="" loading="lazy"></span>
    <?php else: ?>
      <div class="thumb thumb-empty">No Image</div>
    <?php endif; ?>
    <?php if ($badges): component('badge', ['badges' => $badges, 'variant' => 'card']); endif; ?>
    <?php // P0-1: サムネ上に楽天への直接CTA(1タップ送客・Cookie付与)。ホバーで強調。 ?>
    <?php if ($showCta && $aff !== ''): ?>
      <a class="card-cta" href="<?= e($aff) ?>" target="_blank" rel="sponsored nofollow noopener"
         data-track="card_cta" data-hotel="<?= e($no) ?>" data-category="<?= e($category) ?>"
         aria-label="<?= e($name) ?>を楽天トラベルで見る">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
        楽天トラベルで見る
      </a>
    <?php endif; ?>
  </div>
  <div class="cn"><?= e($name) ?></div>
  <?php if (!empty($card['area'])): ?><div class="cd"><?= e($card['area']) ?></div><?php endif; ?>
  <?php // P2-7: レビュー欠損時は「レビューなし」を機械的に表示(統一) ?>
  <?php if ($reviewCount > 0 && $rating > 0): ?>
    <div class="cr">★ <?= e(number_format($rating, 1)) ?> <span class="muted">(<?= e((string) $reviewCount) ?>)</span></div>
  <?php else: ?>
    <div class="cr cr-none">レビューなし</div>
  <?php endif; ?>
  <?php // カード全体を詳細ページへ(stretched link)。CTAより下のレイヤー。 ?>
  <a class="card-link" href="<?= e($href) ?>" data-track="card" data-hotel="<?= e($no) ?>" data-category="<?= e($category) ?>" aria-label="<?= e($name) ?>の詳細"></a>
</article>
