/**
 * クリック計測(依頼P0-2)。
 * data-track 属性を持つ要素のクリックを拾い、
 *   1) /api/track へ sendBeacon(簡易ファイルログ。カテゴリ別・宿別に後から集計可)
 *   2) GA4(gtag)があればカスタムイベントも送信
 * の2系統で記録する。計測失敗は無視して遷移を妨げない。
 *
 * data-track     … イベント名(card / card_cta / detail_cta / rail_more ...)
 * data-hotel     … 宿番号(任意)
 * data-category  … カテゴリ/レールのキー(任意)
 */
function send(event, category, hotelNo) {
  const payload = { event, category: category || '', hotelNo: hotelNo || '' };

  // 1) 簡易ファイルログ(sendBeaconは離脱時も送れる)
  try {
    const body = new Blob([JSON.stringify(payload)], { type: 'text/plain' });
    if (navigator.sendBeacon) {
      navigator.sendBeacon('/api/track', body);
    } else {
      fetch('/api/track', { method: 'POST', body: JSON.stringify(payload), keepalive: true });
    }
  } catch {
    /* noop */
  }

  // 2) GA4 カスタムイベント
  try {
    if (typeof window.gtag === 'function') {
      window.gtag('event', event, {
        category_key: category || '',
        hotel_no: hotelNo || '',
      });
    }
  } catch {
    /* noop */
  }
}

document.addEventListener(
  'click',
  (e) => {
    const el = e.target.closest('[data-track]');
    if (!el) return;
    send(el.dataset.track, el.dataset.category, el.dataset.hotel);
  },
  { capture: true },
);

// 他モジュールからも使えるよう公開
window.yadoTrack = send;
