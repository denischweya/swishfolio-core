/**
 * Builds the request payload for one editor selection.
 *
 * Two things go to the server:
 *
 * 1. `selection` — the shape ContextAssembler::forSelection reads. The selected
 *	  block goes in whole; parent, siblings and inner blocks contribute a
 *	  clientId and a block name only. Their attributes are deliberately withheld:
 *	  they would multiply prompt size for no gain, and the server resolves each
 *	  neighbour's purpose from its manifest anyway.
 *
 * 2. `blockTree` — what AIController::normaliseBlockTree feeds
 *	  ValidationContext. It covers the selection subtree and its ancestors, which
 *	  is exactly the set of blocks an action may target. Ancestors carry no
 *	  attributes because no validation stage reads them.
 *
 * The keys of `blockTree` are also the allowlist the client re-checks every
 * returned clientId against before dispatching anything — the client half of
 * validation stage 2.
 */

/**
 * Active style variation slug, read the way the theme itself reads it.
 *
 * Each variation JSON embeds `:root { --style-mode: <slug>; }` in its
 * `styles.css`, which WordPress merges into the global stylesheet and passes to
 * the editor in `settings.styles`. No variation selected means the base
 * theme.json is active, which the server treats as "default".
 *
 * @param {Array} styles `getSettings().styles`.
 *
 * @return {string} Variation slug.
 */
export function detectStyleVariation( styles ) {
	const entries = Array.isArray( styles ) ? styles : [];

	for ( let index = 0; index < entries.length; index++ ) {
		const entry = entries[ index ];
		const css = entry && typeof entry.css === 'string' ? entry.css : '';
		const match = css.match( /--style-mode:\s*([A-Za-z0-9_-]+)/ );

		if ( match ) {
			return match[ 1 ];
		}
	}

	return 'default';
}

function summarise( getBlockName, clientId ) {
	const blockName = getBlockName( clientId );

	return blockName ? { clientId, blockName } : null;
}

/**
 * @param {Object} selectors `core/block-editor` selectors (bound).
 * @param {string} clientId  The selected block.
 * @param {Object} context   { postType, styleVariation }.
 *
 * @return {Object|null} { selection, blockTree }, or null when the block is gone.
 */
export function buildSelectionContext( selectors, clientId, context ) {
	const {
		getBlockName,
		getBlockAttributes,
		getBlockRootClientId,
		getBlockOrder,
		getBlockParents,
		getClientIdsOfDescendants,
	} = selectors;

	const blockName = clientId ? getBlockName( clientId ) : null;

	if ( ! blockName ) {
		return null;
	}

	const parentClientId = getBlockRootClientId( clientId );

	const innerBlocks = getBlockOrder( clientId )
		.map( ( childId ) => summarise( getBlockName, childId ) )
		.filter( Boolean );

	const siblings = getBlockOrder( parentClientId )
		.filter( ( siblingId ) => siblingId !== clientId )
		.map( ( siblingId ) => summarise( getBlockName, siblingId ) )
		.filter( Boolean );

	const selection = {
		clientId,
		blockName,
		attributes: getBlockAttributes( clientId ) || {},
		innerBlocks,
		parent: parentClientId
			? summarise( getBlockName, parentClientId )
			: null,
		siblings,
		postType: context && context.postType ? context.postType : 'page',
		styleVariation:
			context && context.styleVariation
				? context.styleVariation
				: 'default',
	};

	const blockTree = {};

	const addToTree = ( id, withAttributes ) => {
		const name = getBlockName( id );

		if ( ! name ) {
			return;
		}

		blockTree[ id ] = {
			name,
			attributes: withAttributes ? getBlockAttributes( id ) || {} : {},
			parentClientId: getBlockRootClientId( id ) || null,
		};
	};

	getBlockParents( clientId ).forEach( ( ancestorId ) =>
		addToTree( ancestorId, false )
	);

	addToTree( clientId, true );

	getClientIdsOfDescendants( clientId ).forEach( ( descendantId ) =>
		addToTree( descendantId, true )
	);

	return { selection, blockTree };
}
