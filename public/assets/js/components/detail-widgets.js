/**
 * 詳細ページのウィジェット。
 * 地図はGoogleマップのembed(iframe)に移行、レーダーチャートは軸別比較バーに
 * 一本化したため、Leaflet/Chart.js依存の実装は廃止した。
 * 互換のため空実装をexportしておく(呼び出し側の変更漏れがあっても壊れない)。
 */
export function initHotelMap() {}
export function initReviewRadar() {}
