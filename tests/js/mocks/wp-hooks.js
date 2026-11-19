/**
 * @wordpress/hooks stand-in that records registrations so a test can assert
 * which filter a module hooked, and run the registered callback itself.
 */

const registered = {};

export const addFilter = jest.fn( ( hookName, namespace, callback, priority ) => {
	registered[ hookName ] = registered[ hookName ] || [];
	registered[ hookName ].push( { namespace, callback, priority } );
} );

export const removeFilter = jest.fn();

export function applyFilters( hookName, value, ...args ) {
	return ( registered[ hookName ] || [] ).reduce(
		( carry, entry ) => entry.callback( carry, ...args ),
		value
	);
}

/** @return {Array} Registrations for one hook, in registration order. */
export function __getFilters( hookName ) {
	return ( registered[ hookName ] || [] ).slice();
}

export function __reset() {
	Object.keys( registered ).forEach( ( key ) => delete registered[ key ] );
	addFilter.mockClear();
}
