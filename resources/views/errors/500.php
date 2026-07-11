<?php /* 500エラー(改修7-6)。404の「見つかりません」文言と区別する。 */ ?>
<div class="empty-state" style="min-height:70vh;">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9L2 18a2 2 0 001.7 3h16.6a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
  <h2><?= e($message ?? 'ページの表示中に問題が発生しました') ?></h2>
  <p>一時的な不具合の可能性があります。時間をおいて再度お試しください。</p>
  <a class="btn-primary-lg" href="/">ホームへ戻る</a>
</div>
