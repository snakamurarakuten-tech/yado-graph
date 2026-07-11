/**
 * C-7: 履歴・お気に入り・好みのタグ傾向に応じて、トップの棚(レール)を並べ替える。
 * data-tag が嗜好スコア上位のレールを上位へ。人気の宿(tag無し)は先頭固定。
 */
import { tagAffinity } from '../core/prefs.js';

export function personalizeRails() {
  const zone = document.getElementById('railZone');
  if (!zone) return;

  const affinity = tagAffinity();
  if (affinity.length === 0) return; // 手がかりが無ければ既定順のまま

  const scoreOf = (tag) => {
    if (!tag) return 0;
    const hit = affinity.find((a) => a.tag === tag);
    return hit ? hit.score : 0;
  };

  const rails = Array.from(zone.querySelectorAll('[data-rail]'));
  // 元の並びを保持しつつ、スコア降順で安定ソート
  const decorated = rails.map((el, i) => ({
    el,
    i,
    score: scoreOf(el.dataset.tag),
    mood: el.dataset.mood === '1',
  }));

  decorated.sort((a, b) => {
    // 感情訴求棚(mood)でスコアの付くものを優先的に上げる。同点は元の順序維持。
    if (b.score !== a.score) return b.score - a.score;
    return a.i - b.i;
  });

  // 並べ替えを反映(先頭の「人気の宿」= tag無し・score0 は自然と上位付近に留まる)
  decorated.forEach(({ el }) => zone.appendChild(el));
}
