/**
 * Instagram「みんなの投稿」カルーセルの自動スクロール(依頼B-4)。
 * ゆっくり右へ流し、端で折り返す。ユーザー操作中とreduced-motion時は止める。
 */
export function initInstaCarousel() {
  const carousel = document.querySelector('[data-insta-carousel]');
  const track = carousel?.querySelector('[data-insta-track]');
  if (!carousel || !track) return;
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
  if (track.children.length <= 1) return;

  let dir = 1;
  let paused = false;
  const speed = 0.4; // px/frame

  const pause = () => { paused = true; };
  const resume = () => { paused = false; };
  ['pointerdown', 'touchstart', 'mouseenter'].forEach((ev) =>
    track.addEventListener(ev, pause, { passive: true }),
  );
  ['pointerup', 'touchend', 'mouseleave'].forEach((ev) =>
    track.addEventListener(ev, resume, { passive: true }),
  );

  let raf;
  const step = () => {
    if (!paused) {
      const max = track.scrollWidth - track.clientWidth;
      if (max > 0) {
        track.scrollLeft += speed * dir;
        if (track.scrollLeft >= max - 1) dir = -1;
        else if (track.scrollLeft <= 0) dir = 1;
      }
    }
    raf = requestAnimationFrame(step);
  };

  // 画面外では止める(省電力)
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        if (!raf) raf = requestAnimationFrame(step);
      } else if (raf) {
        cancelAnimationFrame(raf);
        raf = null;
      }
    });
  });
  io.observe(carousel);
}
