/** 共有ボタン。Web Share APIがあれば使い、無ければURLコピー。 */
import { toast } from '../core/toast.js';

export function initShare() {
  const btn = document.getElementById('shareBtn');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    const shareData = { title: document.title, url: location.href };
    try {
      if (navigator.share) {
        await navigator.share(shareData);
        return;
      }
      await navigator.clipboard.writeText(location.href);
      toast('リンクをコピーしました');
    } catch {
      /* ユーザーキャンセル等は無視 */
    }
  });
}
