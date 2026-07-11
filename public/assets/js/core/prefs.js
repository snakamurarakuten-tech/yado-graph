/**
 * 好み(オンボーディング選択)の保存と、タグ嗜好(affinity)の集計。
 *
 * タグ嗜好 = オンボーディングで選んだタグ(重み大)
 *          + お気に入りの宿のタグ(重み中)
 *          + 閲覧履歴の宿のタグ(重み小)
 *
 * これを使って、トップの棚並び替え(C-7)、あなた好みの宿(C-7)、
 * お気に入り類似レコメンド(C-8)を駆動する。
 */
import { Favorites, History } from './storage.js';

const KEY_PREFS = 'prefs';

function read(key) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}
function write(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* 容量超過等は無視 */
  }
}

export const Prefs = {
  /** オンボーディング済みか(選択 or スキップ済みなら true) */
  isOnboarded() {
    const p = read(KEY_PREFS);
    return !!(p && p.onboarded);
  },
  /** 選択されたタグ配列(重複なし) */
  tags() {
    const p = read(KEY_PREFS);
    return Array.isArray(p?.tags) ? p.tags : [];
  },
  /** オンボーディング結果を保存 */
  save(tags) {
    const clean = Array.from(new Set((tags || []).map(String).filter(Boolean)));
    write(KEY_PREFS, { onboarded: true, tags: clean, at: Date.now() });
  },
  /** スキップ(タグなしでオンボーディング完了扱い) */
  skip() {
    write(KEY_PREFS, { onboarded: true, tags: [], at: Date.now() });
  },
};

/** 保存済みアイテム配列から tags を取り出して重み付き集計に足す */
function addTags(scores, items, weight) {
  items.forEach((it) => {
    const tags = Array.isArray(it.tags)
      ? it.tags
      : String(it.tags || '')
          .split(',')
          .map((t) => t.trim())
          .filter(Boolean);
    tags.forEach((t) => {
      scores[t] = (scores[t] || 0) + weight;
    });
  });
}

/**
 * タグ嗜好スコアを集計して返す。
 * @returns {{tag:string,score:number}[]} スコア降順
 */
export function tagAffinity() {
  const scores = {};
  // オンボーディング選択(重み3)
  Prefs.tags().forEach((t) => {
    scores[t] = (scores[t] || 0) + 3;
  });
  // お気に入り(重み2) / 履歴(重み1)
  addTags(scores, Favorites.list(), 2);
  addTags(scores, History.list(), 1);

  return Object.entries(scores)
    .map(([tag, score]) => ({ tag, score }))
    .sort((a, b) => b.score - a.score);
}

/** 上位N個のタグキーを返す */
export function topTags(n = 4) {
  return tagAffinity()
    .slice(0, n)
    .map((x) => x.tag);
}

/** 履歴＋お気に入りの hotelNo 集合(除外用) */
export function seenHotelNos() {
  const set = new Set();
  [...Favorites.list(), ...History.list()].forEach((it) => {
    if (it.hotelNo) set.add(String(it.hotelNo));
  });
  return Array.from(set);
}
