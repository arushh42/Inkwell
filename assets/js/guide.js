/**
 * Turns the guide's list of steps into a story-style stepper:
 * one step at a time, progress bars, Back/Next, arrow keys, swipe,
 * and #step-N links. Without JavaScript every step is shown in order.
 */
(() => {
  const tour = document.querySelector('[data-tour]');
  if (!tour) return;

  const steps = [...tour.querySelectorAll('[data-step]')];
  const bars = [...tour.querySelectorAll('.tour__bar')];
  const cta = document.querySelector('[data-cta]');
  let current = 0;

  tour.classList.add('is-stepper');
  tour.querySelector('[data-progress]').hidden = false;
  tour.querySelectorAll('[data-nav], [data-hint]').forEach((el) => (el.hidden = false));
  if (cta) cta.hidden = true;

  const live = document.createElement('p');
  live.className = 'visually-hidden';
  live.setAttribute('aria-live', 'polite');
  tour.appendChild(live);

  const show = (index, { focus = false, scroll = true } = {}) => {
    current = Math.max(0, Math.min(steps.length - 1, index));
    steps.forEach((step, i) => {
      step.classList.toggle('is-active', i === current);
      step.setAttribute('aria-hidden', i === current ? 'false' : 'true');
    });
    bars.forEach((bar, i) => {
      bar.classList.toggle('is-done', i < current);
      bar.classList.toggle('is-current', i === current);
    });
    history.replaceState(null, '', `#step-${current + 1}`);
    live.textContent = `Step ${current + 1} of ${steps.length}: ${steps[current].querySelector('.tour__title').textContent}`;
    if (scroll && tour.getBoundingClientRect().top < 0) tour.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (focus) steps[current].querySelector('.tour__title').focus({ preventScroll: true });
  };

  steps.forEach((step) => step.querySelector('.tour__title').setAttribute('tabindex', '-1'));

  tour.addEventListener('click', (e) => {
    if (e.target.closest('[data-next]')) show(current + 1, { focus: true });
    if (e.target.closest('[data-prev]')) show(current - 1, { focus: true });
    const bar = e.target.closest('[data-go]');
    if (bar) show(Number(bar.dataset.go));
  });

  document.addEventListener('keydown', (e) => {
    if (e.target.closest('input, textarea, select, [contenteditable]')) return;
    if (e.key === 'ArrowRight') show(current + 1, { scroll: false });
    if (e.key === 'ArrowLeft') show(current - 1, { scroll: false });
  });

  let touchX = null;
  tour.addEventListener('touchstart', (e) => { touchX = e.touches[0].clientX; }, { passive: true });
  tour.addEventListener('touchend', (e) => {
    if (touchX === null) return;
    const dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 60) show(current + (dx < 0 ? 1 : -1), { scroll: false });
    touchX = null;
  });

  const fromHash = Number((location.hash.match(/^#step-(\d+)$/) || [])[1]);
  show(fromHash ? fromHash - 1 : 0, { scroll: false });
})();
