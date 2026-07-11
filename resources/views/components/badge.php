<?php
/**
 * バッジ(チップ)群の描画。カード・詳細ページ共通。
 * @var array<int,array{label:string,tone:string}> $badges
 * @var string $variant 'card'|'hero'  余白/サイズの出し分け
 */
$badges = $badges ?? [];
if (empty($badges)) { return; }
$variant = $variant ?? 'card';
?>
<div class="badges badges-<?= e($variant) ?>">
  <?php foreach ($badges as $b): ?>
    <span class="badge badge-<?= e($b['tone'] ?? 'amber') ?>"><?= e($b['label'] ?? '') ?></span>
  <?php endforeach; ?>
</div>
