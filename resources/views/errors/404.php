<?php /* $seo / $message は index.php から渡される */ ?>
<div class="empty-state" style="min-height:70vh;">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9L2 18a2 2 0 001.7 3h16.6a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
  <h2><?= e($message ?? 'ページが見つかりませんでした') ?></h2>
  <p>URLが変わったか、削除された可能性があります。</p>
  <a class="btn-primary-lg" href="/">ホームへ戻る</a>
</div>
