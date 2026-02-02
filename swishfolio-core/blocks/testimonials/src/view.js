/**
 * Testimonials Block - View Script
 *
 * Handles carousel functionality on the frontend.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const carousels = document.querySelectorAll( '.sfcore-testimonials--carousel' );

	carousels.forEach( ( carousel ) => {
		const container = carousel.querySelector( '.sfcore-testimonials__container' );
		const items = carousel.querySelectorAll( '.sfcore-testimonials__item' );
		const prevBtn = carousel.querySelector( '.sfcore-testimonials__prev' );
		const nextBtn = carousel.querySelector( '.sfcore-testimonials__next' );

		if ( items.length <= 1 ) {
			return;
		}

		let currentIndex = 0;
		const totalItems = items.length;
		const autoplay = carousel.dataset.autoplay === 'true';
		const autoplaySpeed = parseInt( carousel.dataset.autoplaySpeed, 10 ) || 5000;
		let autoplayInterval = null;

		const goToSlide = ( index ) => {
			currentIndex = index;
			if ( currentIndex < 0 ) {
				currentIndex = totalItems - 1;
			}
			if ( currentIndex >= totalItems ) {
				currentIndex = 0;
			}
			container.style.transform = `translateX(-${ currentIndex * 100 }%)`;
		};

		const nextSlide = () => {
			goToSlide( currentIndex + 1 );
		};

		const prevSlide = () => {
			goToSlide( currentIndex - 1 );
		};

		const startAutoplay = () => {
			if ( autoplay && ! autoplayInterval ) {
				autoplayInterval = setInterval( nextSlide, autoplaySpeed );
			}
		};

		const stopAutoplay = () => {
			if ( autoplayInterval ) {
				clearInterval( autoplayInterval );
				autoplayInterval = null;
			}
		};

		// Event listeners
		if ( nextBtn ) {
			nextBtn.addEventListener( 'click', () => {
				nextSlide();
				stopAutoplay();
				startAutoplay();
			} );
		}

		if ( prevBtn ) {
			prevBtn.addEventListener( 'click', () => {
				prevSlide();
				stopAutoplay();
				startAutoplay();
			} );
		}

		// Pause on hover
		carousel.addEventListener( 'mouseenter', stopAutoplay );
		carousel.addEventListener( 'mouseleave', startAutoplay );

		// Touch support
		let touchStartX = 0;
		let touchEndX = 0;

		container.addEventListener( 'touchstart', ( e ) => {
			touchStartX = e.changedTouches[ 0 ].screenX;
			stopAutoplay();
		}, { passive: true } );

		container.addEventListener( 'touchend', ( e ) => {
			touchEndX = e.changedTouches[ 0 ].screenX;
			const diff = touchStartX - touchEndX;

			if ( Math.abs( diff ) > 50 ) {
				if ( diff > 0 ) {
					nextSlide();
				} else {
					prevSlide();
				}
			}
			startAutoplay();
		}, { passive: true } );

		// Initialize
		startAutoplay();
	} );
} );
