/**
 * Portfolio Block - View Script
 *
 * Handles filter functionality on the frontend.
 */

document.addEventListener( 'DOMContentLoaded', () => {
	const portfolios = document.querySelectorAll( '.wp-block-swishfolio-core-portfolio' );

	portfolios.forEach( ( portfolio ) => {
		const filters = portfolio.querySelectorAll( '.sfcore-portfolio__filter' );
		const items = portfolio.querySelectorAll( '.sfcore-portfolio__item' );

		if ( filters.length === 0 ) {
			return;
		}

		filters.forEach( ( filter ) => {
			filter.addEventListener( 'click', () => {
				const filterValue = filter.dataset.filter;

				// Update active state
				filters.forEach( ( f ) => f.classList.remove( 'is-active' ) );
				filter.classList.add( 'is-active' );

				// Filter items
				items.forEach( ( item ) => {
					if ( filterValue === '*' ) {
						item.style.display = '';
						item.style.opacity = '1';
						item.style.transform = '';
					} else if ( item.classList.contains( `filter-${ filterValue }` ) ) {
						item.style.display = '';
						item.style.opacity = '1';
						item.style.transform = '';
					} else {
						item.style.opacity = '0';
						item.style.transform = 'scale(0.8)';
						setTimeout( () => {
							if ( ! item.classList.contains( `filter-${ filterValue }` ) && filterValue !== '*' ) {
								item.style.display = 'none';
							}
						}, 300 );
					}
				} );
			} );
		} );
	} );
} );
