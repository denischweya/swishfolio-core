document.addEventListener( 'DOMContentLoaded', () => {
	const pricingBlocks = document.querySelectorAll( '.sfcore-pricing' );

	pricingBlocks.forEach( ( block ) => {
		const toggleBtns = block.querySelectorAll( '.sfcore-pricing__toggle-btn' );
		const priceAmounts = block.querySelectorAll( '.sfcore-pricing__price-amount' );
		const periodMonthly = block.querySelectorAll( '.sfcore-pricing__period-monthly' );
		const periodYearly = block.querySelectorAll( '.sfcore-pricing__period-yearly' );

		if ( toggleBtns.length === 0 ) {
			return;
		}

		const updatePrices = ( billing ) => {
			priceAmounts.forEach( ( priceEl ) => {
				const monthlyPrice = priceEl.dataset.monthly;
				const yearlyPrice = priceEl.dataset.yearly;
				priceEl.textContent = billing === 'monthly' ? monthlyPrice : yearlyPrice;
			} );

			periodMonthly.forEach( ( el ) => {
				el.style.display = billing === 'monthly' ? '' : 'none';
			} );

			periodYearly.forEach( ( el ) => {
				el.style.display = billing === 'yearly' ? '' : 'none';
			} );

			toggleBtns.forEach( ( btn ) => {
				const btnBilling = btn.dataset.billing;
				const isActive = btnBilling === billing;
				btn.classList.toggle( 'is-active', isActive );
				btn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
		};

		toggleBtns.forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const billing = btn.dataset.billing;
				updatePrices( billing );
			} );
		} );
	} );
} );
