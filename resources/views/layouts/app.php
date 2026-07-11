<?php
/**
 * 共通レイアウト。
 * $content   ... ページ本文HTML
 * $seo       ... ['title','description','canonical','image','jsonLd']
 * $pageCss   ... assets/css/pages/{$pageCss}.css を追加読込
 * $pageJs    ... assets/js/pages/{$pageJs}.js をエントリとして読込
 * $activeTab ... 'home' | 'favorites' | 'history'
 */
$seo = $seo ?? [];
$title = $seo['title'] ?? config('app.name');
$description = $seo['description'] ?? '';
$canonical = $seo['canonical'] ?? '';
$ogImage = ($seo['image'] ?? '') !== ''
    ? $seo['image']
    // 写真の無いページはブランドロゴのOGP画像にフォールバック(SNS共有時の見栄え)
    : (string) config('app.url') . '/assets/img/ogp-logo.png';
$jsonLd = $seo['jsonLd'] ?? null;
$activeTab = $activeTab ?? 'home';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<?php if (!empty($seo['robots'])): ?><meta name="robots" content="<?= e($seo['robots']) ?>"><?php endif; ?>
<?php if (($_SERVER['REQUEST_URI'] ?? '/') === '/'): ?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    'name'     => config('app.name'),
    'url'      => config('app.url'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php if ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>

<!-- OGP -->
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<?php if ($canonical): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $ogImage ? 'summary_large_image' : 'summary' ?>">

<!-- ブランド(改修: ロゴ・favicon) -->
<link rel="icon" type="image/svg+xml" href="<?= e(asset('/assets/img/favicon.svg')) ?>">
<meta name="theme-color" content="#0d0f18">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;500;700&family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">

<!-- base層 -->
<?php // CSS: ビルド済み app.css があれば1リクエストに集約(bin/build-css.php が生成)。無ければ個別読込(開発時)。 ?>
<?php if (is_file(BASE_PATH . '/public/assets/css/app.css')): ?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/base/variables.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/base/reset.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/base/typography.css')) ?>">
<!-- components層 -->
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/brand.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/topnav.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/hero.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/tags.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/scroller.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/gallery.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/info.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/review.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/cta.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/bottom-nav.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/collection.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/badge.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/instagram.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/onboarding.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/card-cta.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/section-tabs.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/footer.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('/assets/css/components/detail-extras.css')) ?>">
<?php endif; ?>
<?php if (!empty($pageCss)): ?>
<link rel="stylesheet" href="<?= e(asset('/assets/css/pages/' . $pageCss . '.css')) ?>">
<?php endif; ?>

<?php if ($jsonLd): ?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
<?php // P0-2: GA4(測定ID設定時のみ)。カスタムイベントは track.js が gtag() で送る ?>
<?php $ga4 = (string) config('tracking.ga4_id', ''); ?>
<?php if ($ga4 !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga4) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga4) ?>');</script>
<?php endif; ?>
<?php if (($pageJs ?? '') === 'detail'): ?>
<?php endif; ?>
</head>
<body<?= ($pageCss ?? '') === 'collection' ? ' class="collection-page"' : '' ?>>

<div class="stage">
  <div class="frame">
    <div class="scroll-area" id="scrollArea">
      <?= $content ?>
    </div>

    <div class="bottom-stack">
      <?php if (($showStickyCta ?? false)): ?>
        <div class="sticky-cta">
          <button class="sticky-fav" id="stickyFav" aria-label="お気に入りに追加" aria-pressed="false">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-10-9.2C.4 8.6 2 5 5.6 5c2 0 3.4 1.1 4.4 2.6C11 6.1 12.4 5 14.4 5 18 5 19.6 8.6 22 11.8 19.5 16.4 12 21 12 21z"/></svg>
          </button>
          <a class="sticky-btn" id="stickyCta" href="<?= e($stickyCtaUrl ?? '#') ?>" target="_blank" rel="sponsored nofollow noopener" data-track="sticky_cta">楽天トラベルで空室・料金を見る</a>
        </div>
      <?php endif; ?>

      <?php component('bottom-nav', ['active' => $activeTab]); ?>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>
  </div>
</div>

<!-- coreモジュール -->
<?php if (($pageJs ?? '') === 'detail'): ?>
<?php endif; ?>
<script type="module" src="<?= e(asset('/assets/js/core/track.js')) ?>"></script>
<script type="module" src="<?= e(asset('/assets/js/pages/' . ($pageJs ?? 'top') . '.js')) ?>"></script>
</body>
</html>
