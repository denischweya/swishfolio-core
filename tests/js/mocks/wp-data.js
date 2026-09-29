/**
 * @wordpress/data stand-in.
 *
 * Stores are registered by name through `__setStore()`; `useSelect`,
 * `useDispatch` and `useRegistry` then read from that table exactly as the real
 * hooks read from the real registry. There is no subscription machinery: the
 * components under test re-render from their own `useState`, and every selector
 * here is a pure read of the fixture, so a re-render recomputes the same value.
 *
 * `useRegistry()` is the only route to `registry.batch()` in this WordPress
 * build, which is why the batch model lives in the fixture rather than here —
 * see tests/js/fixtures/editor.js.
 */

const stores = {};

let registry = null;

function keyFor( store ) {
	if ( ! store ) {
		return '';
	}

	return typeof store === 'string' ? store : store.name;
}

export function select( store ) {
	const entry = stores[ keyFor( store ) ];

	return entry ? entry.selectors : undefined;
}

export function dispatch( store ) {
	const entry = stores[ keyFor( store ) ];

	return entry ? entry.actions : undefined;
}

export function useSelect( mapSelect ) {
	return typeof mapSelect === 'function' ? mapSelect( select ) : select( mapSelect );
}

export function useDispatch( store ) {
	return dispatch( store ) || {};
}

export function useRegistry() {
	return registry;
}

/**
 * @param {string} name		 Store name, e.g. 'core/block-editor'.
 * @param {Object} selectors Bound selectors.
 * @param {Object} actions	 Bound action creators.
 */
export function __setStore( name, selectors, actions ) {
	stores[ name ] = { selectors: selectors || {}, actions: actions || {} };
}

export function __setRegistry( value ) {
	registry = value;
}

export function __reset() {
	Object.keys( stores ).forEach( ( key ) => delete stores[ key ] );
	registry = null;
}
