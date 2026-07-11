/**
 * トップ最上部の固定タブ(スクロール連動)。
 *  - タップ → 対象レールへスムーズスクロール(固定タブの高さぶんオフセット)
 *  - スクロールに応じて現在地のタブをハイライト(scroll-spy)
 *  - タブの並びは実際のレール(#railZone)のDOM順に同期(パーソナライズ後のズレを解消)
 */
let tabsEl, scroller;

function targetOf(tab) {
  return document.getElementById('rail-' + tab.dataset.tab);
}

function setActive(key) {
  if (!tabsEl) return;
  const tabs = tabsEl.querySelectorAll('.section-tab[data-tab]');
  tabs.forEach((t) => t.classList.toggle('active', t.dataset.tab === key));
  const active = Array.from(tabs).find((t) => t.dataset.tab === key);
  if (active) active.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
}

/** タブの並びをレールのDOM順に合わせる(#4 順番のズレ解消)。 */
export function syncTabOrder() {
  const track = tabsEl?.querySelector('.section-tabs-track');
  const zone = document.getElementById('railZone');
  if (!track || !zone) return;

  const railOrder = Array.from(zone.querySelectorAll('[data-rail][data-key]'))
    .map((r) => r.dataset.key)
    .filter(Boolean);

  const allBtn = track.querySelector('.section-tab-all');
  railOrder.forEach((key) => {
    const tab = track.querySelector(`.section-tab[data-tab="${CSS.escape(key)}"]`);
    if (tab) track.insertBefore(tab, allBtn || null); // 「すべて」の手前に順番どおり並べ直す
  });
}

export function initSectionTabs() {
  tabsEl = document.getElementById('sectionTabs');
  scroller = document.getElementById('scrollArea')
    || document.scrollingElement
    || document.documentElement; // スクロール構造が変わってもタブが死なないように
  if (!tabsEl) return;

  syncTabOrder();

  const tabEls = Array.from(tabsEl.querySelectorAll('.section-tab[data-tab]'));
  if (tabEls.length === 0) return;

  // タップでスムーズスクロール(固定タブ高さぶん差し引く)
  tabEls.forEach((tab) => {
    tab.addEventListener('click', (e) => {
      const target = targetOf(tab);
      if (!target) return; // 対応レールが無ければ通常のアンカー挙動に任せる
      e.preventDefault();
      const scrollerRect = scroller.getBoundingClientRect
        ? scroller.getBoundingClientRect()
        : { top: 0 };
      const targetRect = target.getBoundingClientRect();
      const top = scroller.scrollTop + (targetRect.top - scrollerRect.top) - tabsEl.offsetHeight - 8;

      if (!Number.isFinite(top)) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } else {
        const dest = Math.max(0, top);
        const before = scroller.scrollTop;
        try {
          scroller.scrollTo({ top: dest, behavior: 'smooth' });
        } catch {
          scroller.scrollTop = dest; // ScrollToOptions非対応(旧iOS Safari等)
        }
        // 旧iOS Safariは scrollTo(options) を「エラーも出さず無視」するため、
        // 実際に動いたか計測して、動いていなければ直接 scrollTop を書く。
        setTimeout(() => {
          if (Math.abs(scroller.scrollTop - before) < 2 && Math.abs(dest - before) > 2) {
            scroller.scrollTop = dest;
          }
        }, 80);
      }
      setActive(tab.dataset.tab);
    });
  });

  // scroll-spy
  const rails = tabEls.map((t) => targetOf(t)).filter(Boolean);
  if (rails.length === 0) return;
  const io = new IntersectionObserver(
    (entries) => {
      const visible = entries
        .filter((en) => en.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (visible[0]) setActive(visible[0].target.id.replace(/^rail-/, ''));
    },
    { root: scroller, rootMargin: '-15% 0px -75% 0px', threshold: 0 },
  );
  rails.forEach((r) => io.observe(r));
}
