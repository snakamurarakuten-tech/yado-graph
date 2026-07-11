/** 全カテゴリ一覧ページのエントリ。 */
import { updateFavBadge } from '../components/favorite-button.js';
import { initReveal } from '../components/reveal.js';

function boot() {
  initReveal();
  updateFavBadge();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
