/** IntersectionObserverでスクロール連動フェードイン。 */
export function initReveal() {
  const targets = document.querySelectorAll('.reveal');
  if (targets.length === 0) return;

  if (!('IntersectionObserver' in window)) {
    targets.forEach((el) => el.classList.add('in'));
    return;
  }

  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) {
        e.target.classList.add('in');
        io.unobserve(e.target);
      }
    });
  }, { root: document.getElementById('scrollArea'), threshold: 0.15 });

  targets.forEach((el) => io.observe(el));
}
