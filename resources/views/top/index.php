<?php
/**
 * トップページ。Netflix風レールを複数並べる。
 * @var array  $rails      [['key','title','items','cardType','tag','moreHref'], ...]
 * @var ?array $hero       今日の一軒(日付シードで決定的に選出・P2-8)
 * @var array  $sections   上部タブ用 [['key','title'], ...]
 * @var array  $onboarding オンボーディング設定
 */
$appName = config('app.name');
$sections = $sections ?? [];
?>

<?php // === 上部タブ(最上部に固定・スクロール連動)。タップで対象レールへスライド === ?>
<?php if (!empty($sections)): ?>
  <nav class="section-tabs" id="sectionTabs" aria-label="カテゴリ">
    <div class="section-tabs-track">
      <?php foreach ($sections as $i => $s): ?>
        <a class="section-tab<?= $i === 0 ? ' active' : '' ?>" href="#rail-<?= e($s['key']) ?>" data-tab="<?= e($s['key']) ?>"><?= e($s['title']) ?></a>
      <?php endforeach; ?>
      <a class="section-tab section-tab-all" href="/search">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" style="vertical-align:-1px;margin-right:3px;"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>検索</a>
      <a class="section-tab section-tab-all" href="/features">特集</a>
      <a class="section-tab section-tab-all" href="/categories">すべて</a>
    </div>
  </nav>
<?php endif; ?>

<?php if ($hero): ?>
  <div class="top-hero">
    <div class="brand"><?php component('logo', ['variant' => 'full', 'size' => 30]); ?></div>
    <?php if (!empty($hero['imageUrl'])): ?>
      <img src="<?= e($hero['imageUrl']) ?>" alt="<?= e((string) ($hero['hotelName'] ?? '')) ?>">
    <?php endif; ?>
    <div class="hero-gradient"></div>
    <div class="hero-content">
      <div class="eyebrow">今日の一軒</div>
      <h1 class="hotel-name"><?= e($hero['hotelName']) ?></h1>
      <?php if (!empty($hero['area'])): ?>
        <div class="hotel-sub"><span><?= e($hero['area']) ?></span>
          <?php if (!empty($hero['reviewAverage'])): ?><span class="dot2">·</span><span class="rating-badge">★ <?= e(number_format((float) $hero['reviewAverage'], 1)) ?></span><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="hero-actions">
        <a class="hero-cta" href="<?= e(($hero['hotelNo'] ?? '') !== '' ? '/hotel/' . rawurlencode((string) $hero['hotelNo']) : '#') ?>" data-track="hero" data-hotel="<?= e((string) ($hero['hotelNo'] ?? '')) ?>">
          この宿を見る
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </a>
        <?php // 気まぐれ提案。条件を問わずランダム1軒へ(「今日の一軒」= 決定的とは別機能) ?>
        <a class="shuffle-btn" href="/surprise" data-shuffle aria-label="ランダムに1軒えらぶ" data-track="shuffle">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
          気まぐれで選ぶ
        </a>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="top-hero" style="display:flex;align-items:flex-end;">
    <div class="brand"><?php component('logo', ['variant' => 'full', 'size' => 30]); ?></div>
    <div class="hero-content"><h1 class="hotel-name">泊まりたい宿を、<br>眺めて見つける。</h1></div>
  </div>
<?php endif; ?>

<?php // JSレールがサーバー描画済みの宿を除外できるように共有(Phase 2-3) ?>
<script>window.__seenHotelNos = <?= json_encode(array_values(array_map('strval', $seenHotelNos ?? [])), JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>

<?php // === 上部タブは最上部へ移動済み === ?>

<?php // === 最近見た宿(JSがLocalStorageから描画) === ?>
<section class="rail reveal" id="recentRail" hidden>
  <div class="rail-head"><h2><span class="bar"></span>最近見た宿</h2></div>
  <div class="rail-track" data-recent-track></div>
</section>

<?php // === あなた好みの宿(オンボーディング/履歴の好みからJSが描画) === ?>
<section class="rail reveal" id="forYouRail" hidden>
  <div class="rail-head">
    <h2><span class="bar"></span>あなた好みの宿</h2>
    <a class="rail-more" href="/categories" data-track="rail_more" data-category="foryou">一覧はこちら
      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
    </a>
  </div>
  <div class="rail-track" data-foryou-track></div>
</section>

<?php // === レール群。JS(C-7)が data-tag を見て並べ替える === ?>
<div id="railZone">
<?php if (!empty($rails)): ?>
  <?php foreach ($rails as $rail): ?>
    <?php component('horizontal-rail', [
        'title'    => $rail['title'],
        'items'    => $rail['items'],
        'cardType' => $rail['cardType'] ?? 'poster',
        'tag'      => $rail['tag'] ?? '',
        'key'      => $rail['key'] ?? '',
        'moreHref' => $rail['moreHref'] ?? '',
        'mood'     => $rail['mood'] ?? false,
        'showCta'  => false,
    ]); ?>
  <?php endforeach; ?>
<?php else: ?>
  <div class="top-empty-rail">
    ただいまおすすめを準備中です。<br>
    しばらくしてから、もう一度おたずねください。
  </div>
<?php endif; ?>
</div>

<?php component('site-footer'); ?>

<?php // === 初回オンボーディング(Netflix風・初回訪問時のみJSが開く) === ?>
<?php component('onboarding', ['onboarding' => $onboarding]); ?>
