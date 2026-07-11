<?php
/**
 * サイトロゴ(ブランド指定デザイン)。
 * 円のなかに「太陽 + 折れ線グラフ(山なみ) + 水面の線」を描いたマーク + ワードマーク。
 *
 * @var ?string $variant 'full'(マーク+文字・既定) | 'mark'(マークのみ)
 * @var ?int    $size    マークの一辺(px)。既定 34
 * @var ?bool   $link    トップへのリンクにするか。既定 true
 */
$variant = $variant ?? 'full';
$size = (int) ($size ?? 34);
$link = $link ?? true;
$tag = $link ? 'a' : 'span';
$attrs = $link ? ' href="/" aria-label="' . e(config('app.name')) . ' ホームへ"' : '';
?>
<<?= $tag ?> class="site-logo site-logo--<?= e($variant) ?>"<?= $attrs ?>>
  <svg class="site-logo-mark" width="<?= $size ?>" height="<?= $size ?>" viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <!-- 外周円 -->
    <circle cx="24" cy="24" r="21.5" stroke="currentColor" stroke-width="2"/>
    <!-- 太陽 -->
    <circle cx="29.5" cy="12.5" r="3.6" fill="currentColor"/>
    <!-- 折れ線グラフ(山なみ)。左の縁から昇る -->
    <path d="M4.5 30 L14 25 L20.5 28.5 L33 17.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="14" cy="25" r="2.1" fill="currentColor"/>
    <circle cx="20.5" cy="28.5" r="2.1" fill="currentColor"/>
    <circle cx="33" cy="17.5" r="2.1" fill="currentColor"/>
    <!-- 水面の線 -->
    <path d="M13 35.5 H35" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    <path d="M16 39 H32" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
  </svg>
  <?php if ($variant === 'full'): ?>
    <span class="site-logo-word">YADO GRAPH<span class="site-logo-dot">.</span></span>
  <?php endif; ?>
</<?= $tag ?>>
