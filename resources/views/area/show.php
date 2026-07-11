<?php
/**
 * 温泉地ページ(増強②)。/area/{key} と /area/{key}/{tag} の共用ビュー。
 * @var array  $area     ['key','name','pref','desc']
 * @var string $tag      テーマタグ(''ならエリアトップ)
 * @var string $tagLabel テーマ名(露天風呂 等)
 * @var string $h1
 * @var array  $items    宿カード(クチコミ数順)
 * @var array  $stats    ['cnt','avgReview','medCharge']
 * @var array  $tagChips [['key','label','count'],...]
 * @var array  $faq      [['q','a'],...]
 */
?>
<div class="collection-head">
  <nav class="breadcrumb" aria-label="パンくず">
    <a href="/">ホーム</a><span class="sep">›</span>
    <?php if ($tag !== ''): ?>
      <a href="/area/<?= e(rawurlencode($area['key'])) ?>"><?= e($area['name']) ?></a><span class="sep">›</span>
      <span><?= e($tagLabel) ?></span>
    <?php else: ?>
      <span><?= e($area['pref']) ?></span><span class="sep">›</span>
      <span><?= e($area['name']) ?></span>
    <?php endif; ?>
  </nav>
  <h1><?= e($h1) ?></h1>
</div>

<?php if ($tag === ''): ?>
  <p class="onsen-area-desc"><?= e($area['desc']) ?></p>
<?php else: ?>
  <p class="area-tag-lead"><?= e($area['name']) ?>で「<?= e($tagLabel) ?>」の評判が高い宿を、クチコミ数順に紹介します。</p>
<?php endif; ?>

<div class="area-stats">
  <div class="area-stat"><span class="k">掲載</span><span class="v"><?= e((string) $stats['cnt']) ?>軒</span></div>
  <?php if ($stats['avgReview'] > 0): ?>
    <div class="area-stat"><span class="k">平均評価</span><span class="v">★<?= e(number_format($stats['avgReview'], 1)) ?></span></div>
  <?php endif; ?>
  <?php if ($stats['medCharge'] > 0): ?>
    <div class="area-stat"><span class="k">料金中央値</span><span class="v"><?= e(number_format($stats['medCharge'])) ?>円〜</span></div>
  <?php endif; ?>
</div>

<?php if (!empty($tagChips)): ?>
  <div class="filter-chips" role="group" aria-label="テーマで絞り込み">
    <a class="chip<?= $tag === '' ? ' active' : '' ?>" href="/area/<?= e(rawurlencode($area['key'])) ?>">すべて</a>
    <?php foreach ($tagChips as $c): ?>
      <a class="chip<?= $tag === $c['key'] ? ' active' : '' ?>"
         href="/area/<?= e(rawurlencode($area['key'])) ?>/<?= e(rawurlencode($c['key'])) ?>"><?= e($c['label']) ?> <?= e((string) $c['count']) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php // ランキング形式の宿一覧(1件目=クチコミ数1位) ?>
<ol class="area-ranking">
  <?php foreach ($items as $i => $it): ?>
    <?php
    // ハイライト1行(追加クエリなしのルール生成)
    $hl = '';
    if ((float) ($it['bathValue'] ?? 0) >= 4.5) {
        $hl = '風呂の評価' . number_format((float) $it['bathValue'], 1) . 'が特に高い一軒';
    } elseif ($i === 0) {
        $hl = $area['name'] . 'で最もクチコミが集まっている宿';
    } elseif ((float) $it['reviewAverage'] >= max(4.0, $stats['avgReview'] + 0.2)) {
        $hl = '総合評価' . number_format((float) $it['reviewAverage'], 1) . 'はエリア平均を上回る水準';
    } elseif ((int) ($it['minCharge'] ?? 0) > 0 && $stats['medCharge'] > 0 && (int) $it['minCharge'] <= (int) round($stats['medCharge'] * 0.8)) {
        $hl = '1泊' . number_format((int) $it['minCharge']) . '円〜と手が届きやすい価格帯';
    }
    ?>
    <li class="rank-item">
      <a class="rank-link" href="/hotel/<?= e(rawurlencode((string) $it['hotelNo'])) ?>">
        <span class="rank-no<?= $i < 3 ? ' top' : '' ?>"><?= e((string) ($i + 1)) ?></span>
        <span class="rank-thumb">
          <?php if (!empty($it['imageUrl'])): ?>
            <img src="<?= e($it['imageUrl']) ?>" alt="<?= e($it['hotelName']) ?>" loading="lazy">
          <?php else: ?>
            <span class="thumb-empty">No Image</span>
          <?php endif; ?>
        </span>
        <span class="rank-body">
          <span class="rank-name"><?= e($it['hotelName']) ?></span>
          <span class="rank-meta">
            <?php if ((float) $it['reviewAverage'] > 0): ?>★<?= e(number_format((float) $it['reviewAverage'], 1)) ?> · <?php endif; ?>クチコミ<?= e(number_format((int) $it['reviewCount'])) ?>件<?php if ((int) ($it['minCharge'] ?? 0) > 0): ?> · <?= e(number_format((int) $it['minCharge'])) ?>円〜<?php endif; ?>
          </span>
          <?php if ($hl !== ''): ?><span class="rank-hl"><?= e($hl) ?></span><?php endif; ?>
        </span>
      </a>
    </li>
  <?php endforeach; ?>
</ol>

<?php if (!empty($faq)): ?>
  <section class="block reveal" style="border-bottom:none;">
    <h2 class="section-title"><span class="bar"></span><?= e($area['name']) ?>のよくある質問</h2>
    <div class="faq-list">
      <?php foreach ($faq as $f): ?>
        <details class="faq-item">
          <summary><?= e($f['q']) ?></summary>
          <p><?= e($f['a']) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php component('site-footer'); ?>
