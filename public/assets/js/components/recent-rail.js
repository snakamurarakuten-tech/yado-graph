/** 「最近見た宿」レールをHistoryから描画する(現在表示中の宿は除く)。 */
import { History } from '../core/storage.js';
import { cardHtml } from '../core/dom.js';

export function renderRecentRail(excludeHotelNo = '') {
  const rail = document.getElementById('recentRail');
  const track = rail?.querySelector('[data-recent-track]');
  if (!rail || !track) return;

  const items = History.list().filter(
    (h) => String(h.hotelNo) !== String(excludeHotelNo),
  );
  if (items.length === 0) return;

  track.innerHTML = items.map((it) => cardHtml(it, 'poster', '', false)).join('');
  rail.hidden = false;
}
