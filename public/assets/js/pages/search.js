/** 検索ページ(改修4-1)のエントリ。もっと見る/無限スクロールで /api/search を追記。 */
import { cardHtml } from '../core/dom.js';
import { initReveal } from '../components/reveal.js';
import { updateFavBadge } from '../components/favorite-button.js';

function initSearchFeed() {
  const grid = document.querySelector('[data-search-grid]');
  if (!grid) return;

  let page = Number(grid.dataset.page || 1);
  let hasMore = grid.dataset.hasMore === '1';
  let loading = false;

  const moreWrap = document.querySelector('[data-load-more-wrap]');
  const moreBtn = document.querySelector('[data-load-more]');
  const sentinel = document.querySelector('[data-infinite-sentinel]');

  const load = async () => {
    if (loading || !hasMore) return;
    loading = true;
    if (moreBtn) moreBtn.textContent = '読み込み中…';

    const params = new URLSearchParams({
      page: String(page + 1),
      q: grid.dataset.q || '',
      pref: grid.dataset.pref || '',
      price: grid.dataset.price || '',
      sort: grid.dataset.sort || '',
    });
    try {
      const res = await fetch(`/api/search?${params.toString()}`);
      if (!res.ok) throw new Error('bad');
      const data = await res.json();
      const items = Array.isArray(data.items) ? data.items : [];
      if (items.length) {
        grid.insertAdjacentHTML('beforeend', items.map((it) => cardHtml(it, 'poster', 'search')).join(''));
        page += 1;
      }
      hasMore = !!data.hasMore && items.length > 0;
    } catch {
      hasMore = false;
    } finally {
      loading = false;
      if (moreBtn) moreBtn.textContent = 'もっと見る';
      if (!hasMore && moreWrap) moreWrap.hidden = true;
    }
  };

  moreBtn?.addEventListener('click', load);
  if (sentinel && 'IntersectionObserver' in window) {
    new IntersectionObserver(
      (entries) => entries.forEach((e) => e.isIntersecting && load()),
      { rootMargin: '400px' },
    ).observe(sentinel);
  }
}

function boot() {
  initReveal();
  updateFavBadge();
  initSearchFeed();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
