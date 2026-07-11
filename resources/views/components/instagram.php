<?php
/**
 * Instagram導線(機能追加メモ1: ハッシュタグ検索へのディープリンクボタン)。
 * @var ?string $instagramUrl InstagramService::hashtagUrl() の結果。nullなら何も出さない。
 *
 * メインCTA(空室・料金を見る)より視覚的に控えめなアウトラインボタン。
 * 投稿が0件のタグもあり得る点は割り切り(Instagram側の通常の検索体験として許容)。
 */
$instagramUrl = $instagramUrl ?? null;
if (empty($instagramUrl)) { return; }
?>
<div class="insta-link-wrap">
  <a class="insta-link-btn" href="<?= e($instagramUrl) ?>" target="_blank" rel="nofollow noopener"
     data-track="instagram_link">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
      <rect x="3" y="3" width="18" height="18" rx="5"/>
      <circle cx="12" cy="12" r="4"/>
      <circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/>
    </svg>
    Instagramでこの宿の写真を探す
    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
  </a>
  <p class="insta-link-note">Instagramのハッシュタグ検索が開きます(投稿が無い場合もあります)</p>
</div>
