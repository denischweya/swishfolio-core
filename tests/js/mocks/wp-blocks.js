/**
 * @wordpress/blocks stand-in.
 *
 * `getBlockType` returning undefined for an unregistered name is load-bearing:
 * it is how apply.js refuses to insert a block type the site does not have.
 */

const types = {};

let created = 0;

export const getBlockType = jest.fn( ( name ) => types[ name ] );

export const createBlock = jest.fn( ( name, attributes ) => {
	created += 1;

	return {
		clientId: 'created-' + created,
		name,
		attributes: attributes || {},
		innerBlocks: [],
	};
} );

/**
 * @param {Object} map Block name => { title } (anything the UI reads).
 */
export function __setBlockTypes( map ) {
	Object.keys( map ).forEach( ( name ) => {
		types[ name ] = { name, ...map[ name ] };
	} );
}

export function __reset() {
	Object.keys( types ).forEach( ( key ) => delete types[ key ] );
	created = 0;
	getBlockType.mockClear();
	createBlock.mockClear();
}
