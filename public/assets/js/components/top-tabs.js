/**
 * トップ最上部の固定タブ(スクロール連動)。
 *  - タップ → 対象レールへスムーズスクロール(固定タブの高さぶんオフセット)
 *  - スクロールに応じて現在地のタブをハイライト(scroll-spy)
 *  - タブの並びは実際のレール(#railZone)のDOM順に同期(パーソナライズ後のズレを解消)
 */
let tabsEl, scroller;
// タップによるジャンプ中フラグ。true の間は scroll-spy を止める
// (移動の途中で通過したレールに選択を奪われるのを防ぐ)
let isJumping = false;

function targetOf(tab) {
  return document.getElementById('rail-' + tab.dataset.tab);
}

/**
 * タブのハイライトを更新する。
 * @param {string} key 対象レールのキー
 * @param {boolean} center タブ自身を中央へ横スクロールするか
 *   (タップ時のみ true。scroll-spy 由来で毎回動かすとタブ列が暴れるため)
 */
function setActive(key, center = false) {
  if (!tabsEl) return;
  const tabs = tabsEl.querySelectorAll('.section-tab[data-tab]');
  tabs.forEach((t) => t.classList.toggle('active', t.dataset.tab === key));
  if (!center) return;
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

  // タップでスクロール。
  // iOS Safari は scrollIntoView({behavior:'smooth'}) を無視することがあるため、
  // 実スクロール要素(.scroll-area)の scrollTop を自前で計算して設定する。
  // なめらかさは CSS 側の scroll-behavior:smooth が担当する。
  tabEls.forEach((tab) => {
    tab.addEventListener('click', (e) => {
      const target = targetOf(tab);
      if (!target) return; // 対応レールが無ければ通常のアンカー挙動に任せる
      e.preventDefault();

      isJumping = true;                  // 移動中は spy を止める
      setActive(tab.dataset.tab, true);  // 押したタブを確定＋中央へ寄せる

      // scroller を基準にした target の相対位置(rect 差分が最も確実)
      const sRect = scroller.getBoundingClientRect();
      const tRect = target.getBoundingClientRect();
      const tabsH = tabsEl ? tabsEl.offsetHeight : 0;
      const dest = Math.max(0, scroller.scrollTop + (tRect.top - sRect.top) - tabsH - 8);
      const before = scroller.scrollTop;
      if (Math.abs(dest - before) < 2) { isJumping = false; return; } // 既にその位置

      // 移動が起きない/scrollイベントが来ない環境でも spy が止まったままに
      // ならないよう、保険で一定時間後に必ず解除する
      setTimeout(() => { isJumping = false; }, 1200);

      // iOS Safari は scrollTop 代入・scrollTo(smooth)・scrollIntoView(smooth) の
      // いずれも黙って無視することがある。確実に動かすため多段で試し、
      // 各段で実際に動いたかを計測して次の手に切り替える。
      const tryScroll = (fn, next) => {
        const from = scroller.scrollTop;
        try { fn(); } catch { /* 非対応環境は次の手へ */ }
        setTimeout(() => {
          if (Math.abs(scroller.scrollTop - from) < 2 && next) next();
        }, 100);
      };

      tryScroll(
        () => scroller.scrollTo({ top: dest, behavior: 'smooth' }),
        () => tryScroll(
          () => { scroller.scrollTop = dest; },                 // 即時代入
          () => { target.scrollIntoView({ block: 'start' }); }, // 最終手段: ネイティブ
        ),
      );
    });
  });

  // scroll-spy(手動スクロール時に現在地のタブをハイライト)
  const rails = tabEls.map((t) => targetOf(t)).filter(Boolean);
  if (rails.length === 0) return;
  const io = new IntersectionObserver(
    (entries) => {
      // タップによるジャンプ中は無視する。
      // 目的地まで移動する途中で通過したレールを検知してしまい、
      // 「押したタブと違うタブが選ばれる」状態になるのを防ぐ。
      if (isJumping) return;
      const visible = entries
        .filter((en) => en.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
      if (visible[0]) setActive(visible[0].target.id.replace(/^rail-/, ''));
    },
    { root: scroller, rootMargin: '-15% 0px -75% 0px', threshold: 0 },
  );
  rails.forEach((r) => io.observe(r));

  // ジャンプ後、スクロールが止まったら spy を再開する(スクロール停止の検知)。
  let settleTimer;
  scroller.addEventListener('scroll', () => {
    if (!isJumping) return;
    clearTimeout(settleTimer);
    settleTimer = setTimeout(() => { isJumping = false; }, 140);
  }, { passive: true });
}
