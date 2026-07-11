/** 詳細ページのエントリ。必要なコンポーネントJSを読み込んで初期化する。 */
import { History } from '../core/storage.js';
import { initFavoriteButtons, updateFavBadge } from '../components/favorite-button.js';
import { initHeroScroller } from '../components/hero-scroller.js';
import { initReveal } from '../components/reveal.js';
import { initShare } from '../components/share.js';
import { renderRecentRail } from '../components/recent-rail.js';
import { initInstaCarousel } from '../components/instagram-carousel.js';
import { initHotelMap, initReviewRadar } from '../components/detail-widgets.js';

function currentHotel() {
  const el = document.querySelector('[data-fav-toggle]');
  if (!el) return null;
  return {
    hotelNo: el.dataset.hotelNo,
    hotelName: el.dataset.hotelName,
    imageUrl: el.dataset.hotelImage,
    area: el.dataset.hotelArea,
    reviewAverage: el.dataset.hotelRating,
    tags: el.dataset.hotelTags || '',
  };
}

function boot() {
  initHeroScroller();
  initReveal();
  initShare();
  initInstaCarousel();
  initHotelMap();
  initReviewRadar();
  initFavoriteButtons();
  updateFavBadge();

  const hotel = currentHotel();
  if (hotel?.hotelNo) {
    // 詳細表示時に履歴へ自動追加 → その後「最近見た宿」を描画(自分は除外)
    History.push(hotel);
    renderRecentRail(hotel.hotelNo);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
