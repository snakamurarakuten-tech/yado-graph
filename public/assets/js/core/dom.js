/** 小さなDOMヘルパー。カード生成をサーバー側テンプレートと揃える。 */
export function cardHtml(item, cardType = 'poster', category = '', showCta = true) {
  const no = item.hotelNo ? String(item.hotelNo) : '';
  const href = no ? `/hotel/${encodeURIComponent(no)}` : '#';
  const cls = cardType === 'wide' ? 'card-wide' : 'card-poster';
  const name = item.hotelName || '';
  const rating = Number(item.reviewAverage) || 0;
  const reviewCount = Number(item.reviewCount) || 0;
  const tags = Array.isArray(item.tags) ? item.tags.join(',') : String(item.tags || '');
  const aff = item.affiliateUrl || '';
  const img = item.imageUrl
    ? `<span class="thumb-wrap"><img class="thumb" src="${escapeAttr(item.imageUrl)}" alt="" loading="lazy"></span>`
    : `<div class="thumb thumb-empty">No Image</div>`;
  const badges = Array.isArray(item.badges) && item.badges.length
    ? `<div class="badges badges-card">${item.badges
        .slice(0, 2)
        .map(
          (b) =>
            `<span class="badge badge-${escapeAttr(b.tone || 'amber')}">${escapeHtml(b.label || '')}</span>`,
        )
        .join('')}</div>`
    : '';
  const cta = aff && showCta
    ? `<a class="card-cta" href="${escapeAttr(aff)}" target="_blank" rel="sponsored nofollow noopener" data-track="card_cta" data-hotel="${escapeAttr(no)}" data-category="${escapeAttr(category)}" aria-label="${escapeAttr(name)}を楽天トラベルで見る">楽天トラベルで見る</a>`
    : '';
  const ratingHtml = reviewCount > 0 && rating > 0
    ? `<div class="cr">★ ${rating.toFixed(1)} <span class="muted">(${reviewCount})</span></div>`
    : `<div class="cr cr-none">レビューなし</div>`;
  return `<article class="${cls} card" data-tags="${escapeAttr(tags)}" data-area="${escapeAttr(item.area || '')}">
    <div class="card-thumb-wrap">${img}${badges}${cta}</div>
    <div class="cn">${escapeHtml(name)}</div>
    ${item.area ? `<div class="cd">${escapeHtml(item.area)}</div>` : ''}
    ${ratingHtml}
    <a class="card-link" href="${escapeAttr(href)}" data-track="card" data-hotel="${escapeAttr(no)}" data-category="${escapeAttr(category)}" aria-label="${escapeAttr(name)}の詳細"></a>
  </article>`;
}

export function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}
export function escapeAttr(s) {
  return escapeHtml(s);
}
