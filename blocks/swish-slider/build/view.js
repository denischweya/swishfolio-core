/******/ (() => { // webpackBootstrap
/*!*****************************************!*\
  !*** ./blocks/swish-slider/src/view.js ***!
  \*****************************************/
/**
 * Swish Slider - View Script
 *
 * Builds the seamless marquee loop on the frontend:
 * 1. Measures the original slide set.
 * 2. Clones it (aria-hidden, links untabbable) until the track is wide
 *    enough to loop without gaps.
 * 3. Sets --marquee-distance / --marquee-duration and starts the animation
 *    by adding `is-running`.
 *
 * Duration = slideCount × --seconds-per-slide, so per-slide pace stays
 * constant regardless of how many slides the strip holds.
 */

document.addEventListener('DOMContentLoaded', () => {
  const sliders = document.querySelectorAll('.sfcore-swish-slider');
  sliders.forEach(slider => {
    const track = slider.querySelector('.sfcore-swish-slider__track');
    if (!track) {
      return;
    }
    const slides = Array.from(track.children);
    if (!slides.length) {
      return;
    }
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    // Width of one full slide set, including the gap that follows it.
    const setWidth = slides.reduce((width, slide) => width + slide.offsetWidth, 0) + gap * slides.length;
    if (!setWidth) {
      return;
    }

    // The animation shifts the track by one set width, so the track must
    // hold enough copies to cover the viewport plus one full set.
    const targetWidth = Math.max(setWidth * 2, slider.offsetWidth + setWidth);
    let trackWidth = setWidth;
    while (trackWidth < targetWidth) {
      slides.forEach(slide => {
        const clone = slide.cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        clone.querySelectorAll('a').forEach(link => link.setAttribute('tabindex', '-1'));
        track.appendChild(clone);
      });
      trackWidth += setWidth;
    }
    const secondsPerSlide = parseFloat(getComputedStyle(slider).getPropertyValue('--seconds-per-slide')) || 6;
    track.style.setProperty('--marquee-distance', `${setWidth}px`);
    track.style.setProperty('--marquee-duration', `${slides.length * secondsPerSlide}s`);
    slider.classList.add('is-running');
  });
});
/******/ })()
;
//# sourceMappingURL=view.js.map