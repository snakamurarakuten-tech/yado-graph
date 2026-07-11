<?php /** @var array $features 公開済み特集 */ ?>
<div class="collection-head">
  <div class="eyebrow">Features</div>
  <h1>特集</h1>
</div>
<?php if (empty($features)): ?>
  <div class="empty-state"><h2>特集は準備中です</h2><p>季節やテーマで選ぶ特集を順次公開していきます。</p><a class="btn-primary-lg" href="/">ホームへ戻る</a></div>
<?php else: ?>
  <div class="feature-list">
    <?php foreach ($features as $f): ?>
      <a class="feature-card" href="/features/<?= e(rawurlencode((string) $f['slug'])) ?>">
        <span class="feature-date"><?= e((string) ($f['publishedAt'] ?? '')) ?></span>
        <span class="feature-title serif"><?= e((string) $f['title']) ?></span>
        <span class="feature-lead"><?= e(mb_substr((string) ($f['lead'] ?? ''), 0, 60)) ?>…</span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php component('site-footer'); ?>
