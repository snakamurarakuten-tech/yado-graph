
<div class="collection-head">
  <div class="eyebrow">Saved</div>
  <h1 data-fav-title>お気に入り</h1>
  <p class="count" data-collection-count></p>
</div>

<?php // 改修4-4: 共有。リンクを作ってLINE等で送れる(受け取り側は ?share= をJSが描画) ?>
<div class="fav-actions" data-fav-actions hidden>
  <button class="btn-secondary fav-share-btn" data-share-favs>
    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8.7 10.7L15.3 7M8.7 13.3l6.6 3.7M6 14a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM18 8a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM18 21a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/></svg>
    このリストを共有する
  </button>
</div>

<?php // 共有リンクで開いたときの注記(JSが表示) ?>
<p class="shared-note" data-shared-note hidden>
  共有されたお気に入りリストです。<a href="/favorites">自分のお気に入りを見る</a>
</p>

<div class="collection-grid" data-favorites-grid></div>

<?php // === C-8: お気に入りのタグ傾向から類似の未閲覧宿(JSが /api/recommend から描画) === ?>
<section class="rail reveal" id="similarRail" hidden>
  <div class="rail-head"><h2><span class="bar"></span>好みが近い宿</h2></div>
  <div class="rail-track" data-similar-track></div>
</section>

<div class="empty-state" data-empty hidden>
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-10-9.2C.4 8.6 2 5 5.6 5c2 0 3.4 1.1 4.4 2.6C11 6.1 12.4 5 14.4 5 18 5 19.6 8.6 22 11.8 19.5 16.4 12 21 12 21z"/></svg>
  <h2>まだお気に入りがありません</h2>
  <p>気になった宿の♡を押すと、ここにまとまります。</p>
  <a class="btn-primary-lg" href="/">宿を探しにいく</a>
</div>
