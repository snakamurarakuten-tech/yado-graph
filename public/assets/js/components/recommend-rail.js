/**
 * タグ嗜好から /api/recommend を叩き、指定レールに描画する共通処理。
 * トップの「あなた好みの宿」(C-7)、お気に入りの「好みが近い宿」(C-8)で共用。
 */
import { cardHtml } from '../core/dom.js';
import { topTags, seenHotelNos } from '../core/prefs.js';

/**
 * @param {string} railId       セクションのid
 * @param {string} trackSel     カードを流し込むトラックのセレクタ
 * @param {object} opts         { tags?:string[], exclude?:string[], limit?:number }
 */
export async function loadRecommendRail(railId, trackSel, opts = {}) {
  const rail = document.getElementById(railId);
  const track = rail?.querySelector(trackSel);
  if (!rail || !track) return;

  const tags = (opts.tags && opts.tags.length ? opts.tags : topTags(4)).filter(Boolean);
  if (tags.length === 0) return; // 手がかりが無ければ出さない

  // Phase 2-3: サーバー描画済みレールの宿(window.__seenHotelNos)と、
  // 履歴由来の既視宿をマージして除外。ページ内の重複表示を防ぐ。
  const serverSeen = Array.isArray(window.__seenHotelNos) ? window.__seenHotelNos.map(String) : [];
  const exclude = [...new Set([...(opts.exclude || seenHotelNos()), ...serverSeen])];
  const limit = opts.limit || 12;

  const params = new URLSearchParams();
  params.set('tags', tags.join(','));
  if (exclude.length) params.set('exclude', exclude.join(','));
  params.set('limit', String(limit));

  // 改修4-3: フェッチ完了までスケルトンを表示(体感速度・レイアウト安定)
  const skeleton = '<div class="card-poster card card-skel" aria-hidden="true"><div class="thumb"></div><div class="skel-line"></div><div class="skel-line short"></div></div>';
  track.innerHTML = skeleton.repeat(4);
  rail.hidden = false;

  try {
    const res = await fetch(`/api/recommend?${params.toString()}`, {
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error('bad');
    const data = await res.json();
    const items = Array.isArray(data.items) ? data.items : [];
    if (items.length === 0) {
      rail.hidden = true;
      track.innerHTML = '';
      return;
    }

    track.innerHTML = items.map((it) => cardHtml(it, 'poster', '', opts.showCta !== false)).join('');
  } catch {
    // ネットワーク失敗時はスケルトンごと片付けて非表示に戻す
    rail.hidden = true;
    track.innerHTML = '';
  }
}
