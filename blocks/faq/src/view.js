/**
 * FAQ Block - View Script
 *
 * Handles accordion functionality on the frontend.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const faqBlocks = document.querySelectorAll( '.wp-block-swishfolio-core-faq' );

	faqBlocks.forEach( ( faqBlock ) => {
		const allowMultiple = faqBlock.dataset.allowMultiple === 'true';
		const items = faqBlock.querySelectorAll( '.sfcore-faq__item' );
		const headers = faqBlock.querySelectorAll( '.sfcore-faq__header' );

		headers.forEach( ( header, index ) => {
			header.addEventListener( 'click', () => {
				const item = items[ index ];
				const content = item.querySelector( '.sfcore-faq__content' );
				const isOpen = item.classList.contains( 'is-open' );

				// If not allowing multiple, close all others
				if ( ! allowMultiple ) {
					items.forEach( ( otherItem, otherIndex ) => {
						if ( otherIndex !== index && otherItem.classList.contains( 'is-open' ) ) {
							otherItem.classList.remove( 'is-open' );
							const otherContent = otherItem.querySelector( '.sfcore-faq__content' );
							const otherHeader = otherItem.querySelector( '.sfcore-faq__header' );
							otherContent.setAttribute( 'aria-hidden', 'true' );
							otherHeader.setAttribute( 'aria-expanded', 'false' );

							// Update plus icon if applicable
							const otherIcon = otherItem.querySelector( '.sfcore-faq__icon[data-icon="plus"]' );
							if ( otherIcon ) {
								otherIcon.textContent = '+';
							}
						}
					} );
				}

				// Toggle current item
				item.classList.toggle( 'is-open' );
				header.setAttribute( 'aria-expanded', ! isOpen ? 'true' : 'false' );
				content.setAttribute( 'aria-hidden', ! isOpen ? 'false' : 'true' );

				// Update plus icon if applicable
				const icon = item.querySelector( '.sfcore-faq__icon[data-icon="plus"]' );
				if ( icon ) {
					icon.textContent = ! isOpen ? '−' : '+';
				}
			} );

			// Keyboard support
			header.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					header.click();
				}
			} );
		} );
	} );
} );
