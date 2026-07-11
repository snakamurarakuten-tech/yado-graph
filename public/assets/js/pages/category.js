/** カテゴリ単体ページのエントリ。 */
import { initReveal } from '../components/reveal.js';
import { updateFavBadge } from '../components/favorite-button.js';
import { initCategoryPage } from '../components/category-feed.js';

function boot() {
  initReveal();
  updateFavBadge();
  initCategoryPage();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
