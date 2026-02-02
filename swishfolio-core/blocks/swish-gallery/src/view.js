/**
 * Swish Gallery Block - View Script
 *
 * Handles lightbox and carousel functionality on the frontend.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	// Initialize all galleries
	const galleries = document.querySelectorAll( '.sfcore-swish-gallery' );

	galleries.forEach( ( gallery ) => {
		const isCarousel = gallery.classList.contains( 'sfcore-swish-gallery--layout-carousel' );
		const enableLightbox = gallery.dataset.lightbox === 'true';

		if ( isCarousel ) {
			initCarousel( gallery );
		}

		if ( enableLightbox ) {
			initLightbox( gallery );
		}
	} );

	/**
	 * Initialize carousel functionality
	 */
	function initCarousel( gallery ) {
		const container = gallery.querySelector( '.sfcore-swish-gallery__container' );
		const items = gallery.querySelectorAll( '.sfcore-swish-gallery__item' );
		const prevBtn = gallery.querySelector( '.sfcore-swish-gallery__prev' );
		const nextBtn = gallery.querySelector( '.sfcore-swish-gallery__next' );
		const dots = gallery.querySelectorAll( '.sfcore-swish-gallery__dot' );

		if ( items.length <= 1 ) {
			return;
		}

		let currentIndex = 0;
		const totalItems = items.length;
		const autoplay = gallery.dataset.autoplay === 'true';
		const autoplaySpeed = parseInt( gallery.dataset.autoplaySpeed, 10 ) || 5000;
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

			// Update dots
			dots.forEach( ( dot, i ) => {
				dot.classList.toggle( 'is-active', i === currentIndex );
			} );
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

		// Navigation buttons
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

		// Dots navigation
		dots.forEach( ( dot ) => {
			dot.addEventListener( 'click', () => {
				const index = parseInt( dot.dataset.index, 10 );
				goToSlide( index );
				stopAutoplay();
				startAutoplay();
			} );
		} );

		// Pause on hover
		gallery.addEventListener( 'mouseenter', stopAutoplay );
		gallery.addEventListener( 'mouseleave', startAutoplay );

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

		// Keyboard navigation
		gallery.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowLeft' ) {
				prevSlide();
				stopAutoplay();
				startAutoplay();
			} else if ( e.key === 'ArrowRight' ) {
				nextSlide();
				stopAutoplay();
				startAutoplay();
			}
		} );

		// Initialize
		startAutoplay();
	}

	/**
	 * Initialize lightbox functionality
	 */
	function initLightbox( gallery ) {
		const triggers = gallery.querySelectorAll( '.sfcore-swish-gallery__lightbox-trigger' );
		const images = gallery.querySelectorAll( '.sfcore-swish-gallery__image' );
		const showCaption = gallery.dataset.lightboxCaption === 'true';

		// Collect image data
		const imageData = Array.from( images ).map( ( img ) => ( {
			url: img.dataset.fullUrl || img.src,
			alt: img.alt || '',
			caption: img.closest( '.sfcore-swish-gallery__item' )?.querySelector( '.sfcore-swish-gallery__caption' )?.textContent || '',
		} ) );

		// Create lightbox elements
		let lightbox = document.querySelector( '.sfcore-lightbox' );
		if ( ! lightbox ) {
			lightbox = createLightboxElement();
			document.body.appendChild( lightbox );
		}

		const lightboxImage = lightbox.querySelector( '.sfcore-lightbox__image' );
		const lightboxCaption = lightbox.querySelector( '.sfcore-lightbox__caption' );
		const lightboxClose = lightbox.querySelector( '.sfcore-lightbox__close' );
		const lightboxPrev = lightbox.querySelector( '.sfcore-lightbox__prev' );
		const lightboxNext = lightbox.querySelector( '.sfcore-lightbox__next' );
		const lightboxCounter = lightbox.querySelector( '.sfcore-lightbox__counter' );

		let currentImageIndex = 0;
		let currentGalleryImages = [];

		const openLightbox = ( index, galleryImages ) => {
			currentImageIndex = index;
			currentGalleryImages = galleryImages;
			updateLightboxImage();
			lightbox.classList.add( 'is-open' );
			document.body.style.overflow = 'hidden';
			lightboxImage.focus();
		};

		const closeLightbox = () => {
			lightbox.classList.remove( 'is-open' );
			document.body.style.overflow = '';
		};

		const updateLightboxImage = () => {
			const image = currentGalleryImages[ currentImageIndex ];
			lightboxImage.src = image.url;
			lightboxImage.alt = image.alt;

			if ( showCaption && image.caption ) {
				lightboxCaption.textContent = image.caption;
				lightboxCaption.style.display = 'block';
			} else {
				lightboxCaption.style.display = 'none';
			}

			lightboxCounter.textContent = `${ currentImageIndex + 1 } / ${ currentGalleryImages.length }`;

			// Update navigation visibility
			lightboxPrev.style.display = currentGalleryImages.length > 1 ? 'flex' : 'none';
			lightboxNext.style.display = currentGalleryImages.length > 1 ? 'flex' : 'none';
		};

		const nextImage = () => {
			currentImageIndex = ( currentImageIndex + 1 ) % currentGalleryImages.length;
			updateLightboxImage();
		};

		const prevImage = () => {
			currentImageIndex = ( currentImageIndex - 1 + currentGalleryImages.length ) % currentGalleryImages.length;
			updateLightboxImage();
		};

		// Event listeners for triggers
		triggers.forEach( ( trigger ) => {
			trigger.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				const index = parseInt( trigger.dataset.index, 10 );
				openLightbox( index, imageData );
			} );
		} );

		// Also allow clicking on images directly
		images.forEach( ( img ) => {
			img.style.cursor = 'pointer';
			img.addEventListener( 'click', ( e ) => {
				const trigger = img.closest( '.sfcore-swish-gallery__image-wrapper' )?.querySelector( '.sfcore-swish-gallery__lightbox-trigger' );
				if ( trigger ) {
					trigger.click();
				}
			} );
		} );

		// Lightbox controls
		lightboxClose.addEventListener( 'click', closeLightbox );
		lightboxPrev.addEventListener( 'click', prevImage );
		lightboxNext.addEventListener( 'click', nextImage );

		// Click outside to close
		lightbox.addEventListener( 'click', ( e ) => {
			if ( e.target === lightbox || e.target.classList.contains( 'sfcore-lightbox__content' ) ) {
				closeLightbox();
			}
		} );

		// Keyboard navigation
		document.addEventListener( 'keydown', ( e ) => {
			if ( ! lightbox.classList.contains( 'is-open' ) ) {
				return;
			}

			switch ( e.key ) {
				case 'Escape':
					closeLightbox();
					break;
				case 'ArrowLeft':
					prevImage();
					break;
				case 'ArrowRight':
					nextImage();
					break;
			}
		} );
	}

	/**
	 * Create lightbox DOM element
	 */
	function createLightboxElement() {
		const lightbox = document.createElement( 'div' );
		lightbox.className = 'sfcore-lightbox';
		lightbox.setAttribute( 'role', 'dialog' );
		lightbox.setAttribute( 'aria-modal', 'true' );
		lightbox.setAttribute( 'aria-label', 'Image lightbox' );

		lightbox.innerHTML = `
			<div class="sfcore-lightbox__content">
				<button type="button" class="sfcore-lightbox__close" aria-label="Close lightbox">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
						<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
					</svg>
				</button>
				<button type="button" class="sfcore-lightbox__prev" aria-label="Previous image">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
						<path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
					</svg>
				</button>
				<div class="sfcore-lightbox__image-container">
					<img class="sfcore-lightbox__image" src="" alt="" tabindex="0" />
				</div>
				<button type="button" class="sfcore-lightbox__next" aria-label="Next image">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">
						<path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
					</svg>
				</button>
				<div class="sfcore-lightbox__footer">
					<span class="sfcore-lightbox__counter"></span>
					<figcaption class="sfcore-lightbox__caption"></figcaption>
				</div>
			</div>
		`;

		return lightbox;
	}
} );
