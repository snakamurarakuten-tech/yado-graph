/** 静的ページ(運営者情報・プライバシー)のエントリ。 */
import { updateFavBadge } from '../components/favorite-button.js';
import { initReveal } from '../components/reveal.js';
function boot() { initReveal(); updateFavBadge(); }
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
