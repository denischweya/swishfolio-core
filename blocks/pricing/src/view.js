/**
 * Pricing Table - View Script
 *
 * The billing toggle flips the wrapper's `data-billing` attribute; CSS
 * decides which price row is visible in each plan.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const pricingBlocks = document.querySelectorAll( '.sfcore-pricing' );

	pricingBlocks.forEach( ( block ) => {
		const toggleBtns = block.querySelectorAll( '.sfcore-pricing__toggle-btn' );

		if ( toggleBtns.length === 0 ) {
			return;
		}

		toggleBtns.forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const billing = btn.dataset.billing;
				block.dataset.billing = billing;

				toggleBtns.forEach( ( b ) => {
					const isActive = b.dataset.billing === billing;
					b.classList.toggle( 'is-active', isActive );
					b.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				} );
			} );
		} );
	} );
} );
