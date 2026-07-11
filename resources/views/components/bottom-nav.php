<?php /** @var string $active 'home'|'search'|'favorites'|'history' */ ?>
<nav class="tabbar" aria-label="メインナビゲーション">
  <a class="tab-item <?= $active === 'home' ? 'active' : '' ?>" href="/" <?= $active === 'home' ? 'aria-current="page"' : '' ?>>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></svg>
    <span>ホーム</span>
  </a>
  <a class="tab-item <?= $active === 'search' ? 'active' : '' ?>" href="/search" <?= $active === 'search' ? 'aria-current="page"' : '' ?>>
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    <span>さがす</span>
  </a>
  <a class="tab-item <?= $active === 'favorites' ? 'active' : '' ?>" href="/favorites" <?= $active === 'favorites' ? 'aria-current="page"' : '' ?>>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-10-9.2C.4 8.6 2 5 5.6 5c2 0 3.4 1.1 4.4 2.6C11 6.1 12.4 5 14.4 5 18 5 19.6 8.6 22 11.8 19.5 16.4 12 21 12 21z"/></svg>
    <span>お気に入り</span>
    <span class="tab-badge" data-fav-badge hidden>0</span>
  </a>
  <a class="tab-item <?= $active === 'history' ? 'active' : '' ?>" href="/history" <?= $active === 'history' ? 'aria-current="page"' : '' ?>>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v4l3 2M3 12a9 9 0 109-9 9 9 0 00-8 5M3 4v4h4"/></svg>
    <span>閲覧履歴</span>
  </a>
</nav>
