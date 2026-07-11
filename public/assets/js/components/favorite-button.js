/**
 * data-fav-toggle を持つ全ボタン(Hero内・sticky内・ユーティリティ)を
 * Favoritesストレージと同期する。同じ宿のボタン同士は状態を共有する。
 */
import { Favorites } from '../core/storage.js';
import { toast } from '../core/toast.js';

export function initFavoriteButtons() {
  const buttons = Array.from(document.querySelectorAll('[data-fav-toggle]'));
  if (buttons.length === 0) return;

  const hotelNo = buttons[0].dataset.hotelNo;
  const sync = (active) => {
    buttons.forEach((btn) => {
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-pressed', String(active));
      const label = btn.querySelector('[data-fav-label]');
      if (label) label.textContent = active ? 'お気に入り済み' : 'お気に入りに追加';
    });
    // sticky CTA脇のハート(idベース)も同期
    document.getElementById('stickyFav')?.classList.toggle('active', active);
  };

  // 初期状態
  sync(Favorites.has(hotelNo));

  const payload = (btn) => ({
    hotelNo: btn.dataset.hotelNo,
    hotelName: btn.dataset.hotelName,
    imageUrl: btn.dataset.hotelImage,
    area: btn.dataset.hotelArea,
    reviewAverage: btn.dataset.hotelRating,
    tags: btn.dataset.hotelTags || '',
  });

  const onToggle = (btn) => {
    const nowActive = Favorites.toggle(payload(btn));
    sync(nowActive);
    toast(nowActive ? 'お気に入りに追加しました' : 'お気に入りから外しました');
    updateFavBadge();
  };

  buttons.forEach((btn) => btn.addEventListener('click', () => onToggle(btn)));

  // sticky CTA脇のハートは data-fav-toggle を持たないので個別に配線
  const stickyFav = document.getElementById('stickyFav');
  if (stickyFav && buttons[0]) {
    stickyFav.addEventListener('click', () => onToggle(buttons[0]));
  }
}

/** タブバーのお気に入り件数バッジを更新 */
export function updateFavBadge() {
  const badge = document.querySelector('[data-fav-badge]');
  if (!badge) return;
  const n = Favorites.count();
  badge.textContent = String(n);
  badge.hidden = n === 0;
}
