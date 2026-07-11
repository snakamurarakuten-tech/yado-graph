/**
 * お気に入り一覧。ガワ(サーバー)＋中身(JSがLocalStorageから描画)。
 * 改修4-4: 共有リンク(?share=hotelNoのCSV)の生成と受け取り表示。
 */
import { Favorites } from '../core/storage.js';
import { updateFavBadge } from '../components/favorite-button.js';
import { cardHtml } from '../core/dom.js';
import { loadRecommendRail } from '../components/recommend-rail.js';
import { tagAffinity, seenHotelNos } from '../core/prefs.js';
import { toast } from '../core/toast.js';

/** 共有リンクで開いたとき: /api/hotels からカードを引いて描画(自分のリストは触らない) */
async function renderSharedView(grid, nos) {
  const title = document.querySelector('[data-fav-title]');
  const note = document.querySelector('[data-shared-note]');
  const countEl = document.querySelector('[data-collection-count]');
  if (title) title.textContent = '共有されたお気に入り';
  if (note) note.hidden = false;

  // スケルトン(改修4-3)
  grid.innerHTML = Array.from({ length: Math.min(nos.length, 6) })
    .map(() => '<div class="card-poster card card-skel" aria-hidden="true"><div class="thumb"></div><div class="skel-line"></div><div class="skel-line short"></div></div>')
    .join('');

  try {
    const res = await fetch(`/api/hotels?nos=${encodeURIComponent(nos.join(','))}`);
    if (!res.ok) throw new Error('bad');
    const data = await res.json();
    const items = Array.isArray(data.items) ? data.items : [];
    if (countEl) countEl.textContent = items.length ? `${items.length}軒` : '';
    if (items.length === 0) {
      grid.innerHTML = '<p class="shared-empty">共有された宿の情報を取得できませんでした。</p>';
      return;
    }
    grid.innerHTML = items.map((it) => cardHtml(it, 'poster', 'shared')).join('');
  } catch {
    grid.innerHTML = '<p class="shared-empty">読み込みに失敗しました。時間をおいてお試しください。</p>';
  }
}

/** 共有ボタン: navigator.share があればシェアシート、無ければクリップボードへ */
function initShareButton(items) {
  const wrap = document.querySelector('[data-fav-actions]');
  const btn = document.querySelector('[data-share-favs]');
  if (!wrap || !btn || items.length === 0) return;
  wrap.hidden = false;

  btn.addEventListener('click', async () => {
    const nos = items.map((h) => String(h.hotelNo)).filter(Boolean).slice(0, 30);
    const url = `${location.origin}/favorites?share=${nos.join(',')}`;
    const text = `気になっている宿のリストです(${nos.length}軒)`;
    if (navigator.share) {
      try {
        await navigator.share({ title: 'お気に入りの宿リスト', text, url });
        return;
      } catch {
        /* キャンセル時はフォールバックせず終了 */
        return;
      }
    }
    try {
      await navigator.clipboard.writeText(url);
      toast('共有リンクをコピーしました');
    } catch {
      toast('コピーできませんでした');
    }
  });
}

function boot() {
  updateFavBadge();
  const grid = document.querySelector('[data-favorites-grid]');
  const empty = document.querySelector('[data-empty]');
  const countEl = document.querySelector('[data-collection-count]');
  if (!grid) return;

  // 共有リンクで開いた場合は、そのリストを表示して終わり
  const shared = new URLSearchParams(location.search).get('share') || '';
  const sharedNos = shared.split(',').map((s) => s.replace(/\D/g, '')).filter(Boolean);
  if (sharedNos.length) {
    renderSharedView(grid, sharedNos.slice(0, 30));
    return;
  }

  const items = Favorites.list();
  if (countEl) countEl.textContent = items.length ? `${items.length}軒` : '';

  if (items.length === 0) {
    if (empty) empty.hidden = false;
    grid.hidden = true;
    return;
  }
  grid.innerHTML = items.map((it) => cardHtml(it, 'poster')).join('');
  initShareButton(items);

  // C-8: お気に入りのタグ傾向から、類似する未閲覧の宿を提案
  const favTags = tagAffinity()
    .slice(0, 4)
    .map((x) => x.tag);
  if (favTags.length) {
    loadRecommendRail('similarRail', '[data-similar-track]', {
      tags: favTags,
      exclude: seenHotelNos(),
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
