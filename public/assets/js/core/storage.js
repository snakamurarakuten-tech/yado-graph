/**
 * LocalStorage操作を集約する。
 * favorites / history をアプリ全体で唯一ここから読み書きする。
 */
const KEY_FAV = 'favorites';
const KEY_HIST = 'history';
const HIST_MAX = 20;

function read(key) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function write(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* 容量超過等は握りつぶす(閲覧体験を止めない) */
  }
}

/** 宿の識別に使う最小フィールドへ整形 */
function toItem(data) {
  const tags = Array.isArray(data.tags)
    ? data.tags
    : String(data.tags ?? '')
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);
  return {
    hotelNo: String(data.hotelNo ?? ''),
    hotelName: data.hotelName ?? '',
    imageUrl: data.imageUrl ?? data.hotelImage ?? '',
    area: data.area ?? '',
    reviewAverage: Number(data.reviewAverage ?? 0) || 0,
    tags,
  };
}

export const Favorites = {
  list() {
    return read(KEY_FAV);
  },
  has(hotelNo) {
    const no = String(hotelNo);
    return read(KEY_FAV).some((h) => String(h.hotelNo) === no);
  },
  add(data) {
    const item = toItem(data);
    if (!item.hotelNo || this.has(item.hotelNo)) return;
    write(KEY_FAV, [item, ...read(KEY_FAV)]);
  },
  remove(hotelNo) {
    const no = String(hotelNo);
    write(KEY_FAV, read(KEY_FAV).filter((h) => String(h.hotelNo) !== no));
  },
  toggle(data) {
    const no = String(data.hotelNo);
    if (this.has(no)) {
      this.remove(no);
      return false;
    }
    this.add(data);
    return true;
  },
  count() {
    return read(KEY_FAV).length;
  },
};

export const History = {
  list() {
    return read(KEY_HIST);
  },
  /** 詳細ページ表示時に自動で呼ぶ。重複は先頭へ更新、最大20件。 */
  push(data) {
    const item = { ...toItem(data), viewedAt: Date.now() };
    if (!item.hotelNo) return;
    const rest = read(KEY_HIST).filter((h) => String(h.hotelNo) !== item.hotelNo);
    write(KEY_HIST, [item, ...rest].slice(0, HIST_MAX));
  },
};
