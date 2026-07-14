/**
 * 初回オンボーディング(Netflix風・好み選択)。
 * 初回訪問時(Prefs未設定)のみモーダルを開き、選んだタグを Prefs へ保存する。
 * 保存後は「あなた好みの宿」と棚並び替えを再実行できるよう onDone を呼ぶ。
 */
import { Prefs } from '../core/prefs.js';

export function initOnboarding(onDone) {
  const modal = document.getElementById('onboarding');
  if (!modal) return;

  // 既にオンボーディング済みなら何もしない
  if (Prefs.isOnboarded()) return;

  const grid = modal.querySelector('[data-onboarding-grid]');
  const doneBtn = modal.querySelector('[data-ob-done]');
  const skipBtn = modal.querySelector('[data-ob-skip]');
  const countEl = modal.querySelector('[data-ob-count]');
  const minSelect = Number(modal.dataset.minSelect || 2);
  const cards = Array.from(modal.querySelectorAll('[data-ob-card]'));

  const selected = new Set();

  const refresh = () => {
    if (countEl) countEl.textContent = String(selected.size);
    if (doneBtn) doneBtn.disabled = selected.size < minSelect;
  };

  cards.forEach((card) => {
    card.addEventListener('click', () => {
      const key = card.dataset.key;
      const active = card.getAttribute('aria-pressed') === 'true';
      card.setAttribute('aria-pressed', String(!active));
      if (active) selected.delete(key);
      else selected.add(key);
      refresh();
    });
  });

  const collectTags = () => {
    const tags = new Set();
    cards.forEach((card) => {
      if (card.getAttribute('aria-pressed') === 'true') {
        (card.dataset.tags || '')
          .split(',')
          .map((t) => t.trim())
          .filter(Boolean)
          .forEach((t) => tags.add(t));
      }
    });
    return Array.from(tags);
  };

  const close = () => {
    modal.classList.remove('is-open');
    document.body.classList.remove('ob-active');
    modal.setAttribute('aria-hidden', 'true');
  };

  doneBtn?.addEventListener('click', () => {
    Prefs.save(collectTags());
    close();
    if (typeof onDone === 'function') onDone();
  });

  skipBtn?.addEventListener('click', () => {
    Prefs.skip();
    close();
    if (typeof onDone === 'function') onDone();
  });

  // 開く(次フレームでトランジションを効かせる)
  modal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('ob-active'); // :has()非対応ブラウザ用の保険
  requestAnimationFrame(() => modal.classList.add('is-open'));
  refresh();
}
