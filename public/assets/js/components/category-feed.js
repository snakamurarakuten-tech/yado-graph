/**
 * カテゴリ単体ページ(依頼P1-4/5)。
 *  - 「もっと見る」ボタン＋無限スクロールで次ページを追記
 *  - 読み込み済みカードから都道府県チップを生成し、エリアで即時絞り込み(クライアント)
 * 価格帯フィルタはサーバー側(?price=)でリンク遷移するためここでは扱わない。
 */
import { cardHtml } from '../core/dom.js';

export function initCategoryPage() {
  const grid = document.querySelector('[data-category-grid]');
  if (!grid) return;

  const key = grid.dataset.key;
  const price = grid.dataset.price || '';
  const sort = grid.dataset.sort || '';
  let page = Number(grid.dataset.page || 1);
  let hasMore = grid.dataset.hasMore === '1';
  let loading = false;

  const moreWrap = document.querySelector('[data-load-more-wrap]');
  const moreBtn = document.querySelector('[data-load-more]');
  const sentinel = document.querySelector('[data-infinite-sentinel]');
  const areaChipsWrap = document.querySelector('[data-area-chips]');

  const buildAreaChips = () => {
    if (!areaChipsWrap) return;
    const areas = new Set();
    grid.querySelectorAll('.card').forEach((c) => {
      const a = (c.dataset.area || '').trim();
      if (a) areas.add(a);
    });
    if (areas.size <= 1) return;
    const current = areaChipsWrap.dataset.active || '';
    const chips = ['<button class="chip' + (current === '' ? ' active' : '') + '" data-area="">すべて</button>'];
    Array.from(areas)
      .sort()
      .forEach((a) => {
        chips.push(
          `<button class="chip${current === a ? ' active' : ''}" data-area="${a}">${a}</button>`,
        );
      });
    areaChipsWrap.innerHTML = chips.join('');
    areaChipsWrap.hidden = false;
  };

  const applyAreaFilter = (area) => {
    if (areaChipsWrap) areaChipsWrap.dataset.active = area;
    grid.querySelectorAll('.card').forEach((c) => {
      const match = !area || (c.dataset.area || '').includes(area);
      c.style.display = match ? '' : 'none';
    });
    areaChipsWrap?.querySelectorAll('.chip').forEach((ch) => {
      ch.classList.toggle('active', (ch.dataset.area || '') === area);
    });
  };

  areaChipsWrap?.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (!chip) return;
    applyAreaFilter(chip.dataset.area || '');
  });

  const load = async () => {
    if (loading || !hasMore) return;
    loading = true;
    moreBtn && (moreBtn.textContent = '読み込み中…');

    const params = new URLSearchParams({ page: String(page + 1), price, sort });
    try {
      const res = await fetch(`/api/category/${encodeURIComponent(key)}?${params.toString()}`);
      if (!res.ok) throw new Error('bad');
      const data = await res.json();
      const items = Array.isArray(data.items) ? data.items : [];
      if (items.length) {
        grid.insertAdjacentHTML('beforeend', items.map((it) => cardHtml(it, 'poster', key)).join(''));
        page += 1;
        buildAreaChips();
        // 現在のエリア絞り込みを新カードにも適用
        applyAreaFilter(areaChipsWrap?.dataset.active || '');
      }
      hasMore = !!data.hasMore && items.length > 0;
    } catch {
      hasMore = false;
    } finally {
      loading = false;
      moreBtn && (moreBtn.textContent = 'もっと見る');
      if (!hasMore && moreWrap) moreWrap.hidden = true;
    }
  };

  moreBtn?.addEventListener('click', load);

  // 無限スクロール
  if (sentinel && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver(
      (entries) => {
        if (entries.some((en) => en.isIntersecting)) load();
      },
      { root: document.getElementById('scrollArea'), rootMargin: '400px 0px' },
    );
    io.observe(sentinel);
  }

  buildAreaChips();
}
