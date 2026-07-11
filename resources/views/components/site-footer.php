<?php
/**
 * サイト共通フッター(依頼P3-9 / P3-10)。
 *  - 全カテゴリ一覧への導線
 *  - 楽天ウェブサービスのクレジット表記(利用規約で表示が求められる)
 * 文章生成はせず固定ラベルのみ。
 */
?>
<footer class="site-footer">
  <?php component('logo', ['variant' => 'full', 'size' => 26]); ?>
  <p class="affiliate-disclosure">本サイトはアフィリエイト広告（楽天トラベルアフィリエイト）を利用しています。</p>
  <nav class="footer-nav" aria-label="サイト情報">
    <a href="/about">運営者情報</a>
    <a href="/privacy">プライバシーポリシー</a>
  </nav>
  <p class="footer-credit">
    <a href="https://webservice.rakuten.co.jp/" target="_blank" rel="noopener nofollow">Supported by 楽天ウェブサービス</a>
  </p>
  <p class="footer-note">掲載情報・画像は楽天トラベルより取得しています。空室・料金は各宿の楽天トラベルページでご確認ください。</p>
</footer>
