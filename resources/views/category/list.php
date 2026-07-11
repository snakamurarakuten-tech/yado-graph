<?php
/**
 * 全カテゴリ一覧(依頼P1-4 / P3-10)。
 * @var array $categories [['key','label','href','feature'], ...]
 */
?>
<div class="collection-head">
  <div class="eyebrow">Categories</div>
  <h1>カテゴリから探す</h1>
  <p class="count"><?= count($categories) ?>カテゴリ</p>
</div>

<div class="cat-list">
  <?php foreach ($categories as $c): ?>
    <a class="cat-item" href="<?= e($c['href']) ?>" data-track="category_open" data-category="<?= e($c['key']) ?>">
      <span class="cat-label"><?= e($c['label']) ?></span>
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
    </a>
  <?php endforeach; ?>
</div>

<?php component('site-footer'); ?>
