<?php
/**
 * 特集本文。
 * @var array $f       特集データ(title/lead/outro/publishedAt)
 * @var array $entries [['card'=>宿カード,'heading','body'],...]
 */
?>
<div class="collection-head">
  <nav class="breadcrumb" aria-label="パンくず">
    <a href="/">ホーム</a><span class="sep">›</span>
    <a href="/features">特集</a><span class="sep">›</span>
    <span><?= e((string) $f['title']) ?></span>
  </nav>
  <h1 class="serif"><?= e((string) $f['title']) ?></h1>
  <p class="feature-meta"><?= e((string) ($f['publishedAt'] ?? '')) ?> 公開 · <?= e((string) count($entries)) ?>軒</p>
</div>

<p class="feature-lead-full"><?= e((string) $f['lead']) ?></p>
<p class="feature-note">※ 掲載データと各宿の公開情報をもとに編集部が構成しています。評価・クチコミ数は最新のデータを表示しています。</p>
<?php if (!empty($isStale)): ?><p class="feature-stale">この特集は公開から時間が経過しています。最新の空室・料金は各宿のページでご確認ください。</p><?php endif; ?>

<div class="feature-entries">
  <?php foreach ($entries as $i => $e2): $c = $e2['card']; ?>
    <article class="feature-entry">
      <a class="fe-link" href="/hotel/<?= e(rawurlencode((string) $c['hotelNo'])) ?>">
        <span class="fe-no"><?= e(sprintf('%02d', $i + 1)) ?></span>
        <span class="fe-thumb">
          <?php if (!empty($c['imageUrl'])): ?><img src="<?= e($c['imageUrl']) ?>" alt="<?= e($c['hotelName']) ?>" loading="lazy"><?php endif; ?>
        </span>
        <span class="fe-body">
          <span class="fe-heading serif"><?= e($e2['heading']) ?></span>
          <span class="fe-name"><?= e($c['hotelName']) ?><?php if (!empty($c['area'])): ?><span class="fe-area"> / <?= e($c['area']) ?></span><?php endif; ?></span>
          <?php if ((float) $c['reviewAverage'] > 0): ?>
            <span class="fe-meta">★<?= e(number_format((float) $c['reviewAverage'], 1)) ?> · クチコミ<?= e(number_format((int) $c['reviewCount'])) ?>件</span>
          <?php endif; ?>
          <span class="fe-text"><?= e($e2['body']) ?></span>
        </span>
      </a>
    </article>
  <?php endforeach; ?>
</div>

<?php if (!empty($f['outro'])): ?>
  <p class="feature-outro"><?= e((string) $f['outro']) ?></p>
<?php endif; ?>
<?php component('site-footer'); ?>
