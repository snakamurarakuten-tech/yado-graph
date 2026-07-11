/** Heroの横スワイプに応じてdotsを切り替える。 */
export function initHeroScroller() {
  const scroller = document.getElementById('heroScroller');
  const dotsWrap = document.getElementById('dots');
  if (!scroller || !dotsWrap) return;
  const dots = dotsWrap.querySelectorAll('.dot');
  if (dots.length <= 1) return;

  let ticking = false;
  scroller.addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const idx = Math.round(scroller.scrollLeft / scroller.clientWidth);
      dots.forEach((d, i) => d.classList.toggle('active', i === idx));
      ticking = false;
    });
  });
}
