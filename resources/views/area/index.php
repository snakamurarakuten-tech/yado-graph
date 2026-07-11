<?php /** @var array<string,array<int,array{key:string,name:string,count:int}>> $byPref */ ?>
<div class="collection-head">
  <div class="eyebrow">Areas</div>
  <h1>温泉地から宿をさがす</h1>
</div>
<?php if (empty($byPref)): ?>
  <div class="empty-state"><h2>温泉地ページは準備中です</h2><p>データの取り込みが進むと、ここに温泉地の一覧が表示されます。</p><a class="btn-primary-lg" href="/">ホームへ戻る</a></div>
<?php else: ?>
  <?php foreach ($byPref as $pref => $areas): ?>
    <section class="area-group">
      <h2 class="section-subtitle"><?= e($pref) ?></h2>
      <div class="filter-chips">
        <?php foreach ($areas as $a): ?>
          <a class="chip" href="/area/<?= e(rawurlencode($a['key'])) ?>"><?= e($a['name']) ?> <?= e((string) $a['count']) ?>軒</a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
<?php component('site-footer'); ?>
