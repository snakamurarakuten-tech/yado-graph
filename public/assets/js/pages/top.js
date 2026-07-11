/** トップページのエントリ。 */
import { initReveal } from '../components/reveal.js';
import { updateFavBadge } from '../components/favorite-button.js';
import { renderRecentRail } from '../components/recent-rail.js';
import { initOnboarding } from '../components/onboarding.js';
import { personalizeRails } from '../components/rail-personalize.js';
import { loadRecommendRail } from '../components/recommend-rail.js';
import { initSectionTabs, syncTabOrder } from '../components/top-tabs.js';
import { Prefs } from '../core/prefs.js';

/** 好み・履歴に応じたパーソナライズ(棚並べ替え＋あなた好みの宿) */
function personalize() {
  personalizeRails();
  syncTabOrder(); // レール並べ替え後にタブ順を同期(#4)
  loadRecommendRail('forYouRail', '[data-foryou-track]', { showCta: false });
}

/** 今日の1軒ボタンにスピン演出(遷移自体は /surprise へのリンクで実施) */
function initShuffle() {
  const btn = document.querySelector('[data-shuffle]');
  if (!btn) return;
  btn.addEventListener('click', () => btn.classList.add('is-spinning'));
}

function boot() {
  // どれか1つの初期化が失敗しても他(特にタブ)が死なないよう分離実行
  const safely = (fn) => { try { fn(); } catch (err) { console.error('[top] init failed:', err); } };
  safely(initReveal);
  safely(updateFavBadge);
  safely(renderRecentRail); // 「最近見た宿」を先頭付近に描画
  safely(initShuffle);
  safely(initSectionTabs); // 上部タブ(スクロール連動)

  // 初回訪問時はオンボーディングを開く。完了/スキップ後にパーソナライズを実行。
  safely(() => initOnboarding(personalize));
  // 2回目以降(オンボーディング済み)はすぐにパーソナライズ
  safely(() => { if (Prefs.isOnboarded()) personalize(); });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
