/** 閲覧履歴。ガワ＋JSがLocalStorageから描画。 */
import { History } from '../core/storage.js';
import { updateFavBadge } from '../components/favorite-button.js';
import { cardHtml } from '../core/dom.js';

function boot() {
  updateFavBadge();
  const grid = document.querySelector('[data-history-grid]');
  const empty = document.querySelector('[data-empty]');
  const countEl = document.querySelector('[data-collection-count]');

  const items = History.list();
  if (countEl) countEl.textContent = items.length ? `${items.length}軒` : '';

  if (items.length === 0) {
    if (empty) empty.hidden = false;
    if (grid) grid.hidden = true;
    return;
  }
  grid.innerHTML = items.map((it) => cardHtml(it, 'poster')).join('');
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
