/******/ (() => { // webpackBootstrap
/*!******************************************!*\
  !*** ./blocks/swish-counter/src/view.js ***!
  \******************************************/
/**
 * Swish Counter - Frontend Animation.
 *
 * Each .sfcore-swish-counter-item__value element ships with data-target
 * (the final number, e.g. "12.5") and data-decimals (count of digits after
 * the decimal point, derived at save time from the user's input string).
 *
 * On first viewport entry, the rendered text tweens 0 → target over 2s
 * with ease-out cubic. Respects prefers-reduced-motion (snaps to final).
 */

const DURATION = 2000;
const easeOutCubic = t => 1 - Math.pow(1 - t, 3);
const formatNumber = (n, decimals) => {
  return n.toLocaleString(undefined, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });
};
const animate = (el, target, decimals) => {
  const start = performance.now();
  const tick = now => {
    const elapsed = now - start;
    const t = Math.min(elapsed / DURATION, 1);
    const eased = easeOutCubic(t);
    const current = eased * target;
    el.textContent = formatNumber(current, decimals);
    if (t < 1) {
      requestAnimationFrame(tick);
    } else {
      el.textContent = formatNumber(target, decimals);
    }
  };
  requestAnimationFrame(tick);
};
const init = () => {
  const items = document.querySelectorAll('.sfcore-swish-counter-item__value');
  if (!items.length) {
    return;
  }
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const snap = el => {
    const target = parseFloat(el.dataset.target);
    const decimals = parseInt(el.dataset.decimals, 10) || 0;
    if (Number.isFinite(target)) {
      el.textContent = formatNumber(target, decimals);
    }
  };
  if (prefersReducedMotion) {
    items.forEach(snap);
    return;
  }
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) {
        return;
      }
      const el = entry.target;
      const target = parseFloat(el.dataset.target);
      const decimals = parseInt(el.dataset.decimals, 10) || 0;
      if (Number.isFinite(target)) {
        animate(el, target, decimals);
      }
      observer.unobserve(el);
    });
  }, {
    threshold: 0.4
  });
  items.forEach(el => {
    el.textContent = formatNumber(0, parseInt(el.dataset.decimals, 10) || 0);
    observer.observe(el);
  });
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
/******/ })()
;
//# sourceMappingURL=view.js.map