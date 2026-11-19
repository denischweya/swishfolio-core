/**
 * A fake editor: the three stores the AI UI talks to, plus the registry.
 *
 * The selectors reproduce the real ones closely enough that the code under test
 * cannot tell the difference. `getClientIdsOfDescendants` in particular copies
 * WordPress's own breadth-then-splice order (wp-includes/js/dist/block-editor.js
 * ~line 11598), and `getBlockRootClientId` returns '' for a top-level block,
 * which is what makes `selection.parent` null rather than a bogus entry.
 *
 * The undo model is the load-bearing part, and it is a model, not the real
 * thing. In the editor every store change notifies `useBlockSync`'s subscriber,
 * which calls `onChange` once and lands one entry in the undo manager;
 * `registry.batch()` pauses those emitters, so however many dispatches happen
 * inside one batch, the subscriber fires once afterwards. `undoEntries` counts
 * exactly that — one entry per un-batched dispatch, one entry per batch — so
 * "applying N changes produces one undo entry" is a real assertion about the
 * production code's use of registry.batch(), not about WordPress internals.
 */

import * as wpData from '../mocks/wp-data';

/**
 * @param {Object} options { blocks, selectedClientId, postId, postType, settings }.
 *						   `blocks` is a nested tree of
 *						   { clientId, name, attributes, innerBlocks }.
 *
 * @return {Object} The fake environment.
 */
export function createEditor( options = {} ) {
	const {
		blocks = [],
		selectedClientId = null,
		postId = 41,
		postType = 'page',
		settings = {},
	} = options;

	const names = {};
	const attributes = {};
	const parents = {};
	const order = {};

	const ingest = ( list, parentClientId ) => {
		order[ parentClientId ] = list.map( ( block ) => block.clientId );

		list.forEach( ( block ) => {
			names[ block.clientId ] = block.name;
			attributes[ block.clientId ] = block.attributes || {};
			parents[ block.clientId ] = parentClientId;
			ingest( block.innerBlocks || [], block.clientId );
		} );
	};

	ingest( blocks, '' );

	const selectors = {
		getSelectedBlockClientId: () => selectedClientId,
		getBlockName: ( clientId ) => names[ clientId ],
		getBlockAttributes: ( clientId ) =>
			Object.prototype.hasOwnProperty.call( attributes, clientId )
				? attributes[ clientId ]
				: null,
		getBlockRootClientId: ( clientId ) =>
			Object.prototype.hasOwnProperty.call( parents, clientId )
				? parents[ clientId ]
				: null,
		getBlockOrder: ( rootClientId ) =>
			( order[ rootClientId || '' ] || [] ).slice(),
		getBlockParents: ( clientId ) => {
			const ancestors = [];
			let current = parents[ clientId ];

			while ( current ) {
				ancestors.push( current );
				current = parents[ current ];
			}

			return ancestors.reverse();
		},
		getClientIdsOfDescendants: ( rootClientIds ) => {
			const roots = Array.isArray( rootClientIds )
				? rootClientIds.slice()
				: [ rootClientIds ];
			const ids = [];

			roots.forEach( ( rootId ) => {
				ids.push( ...( order[ rootId ] || [] ) );
			} );

			let index = 0;

			while ( index < ids.length ) {
				const children = order[ ids[ index ] ];

				if ( children ) {
					ids.splice( index + 1, 0, ...children );
				}

				index += 1;
			}

			return ids;
		},
		getSettings: () => settings,
	};

	const calls = {
		updateBlockAttributes: [],
		insertBlocks: [],
		removeBlocks: [],
		undo: [],
		successNotices: [],
		errorNotices: [],
	};

	const undoEntries = [];

	let batchDepth = 0;
	let pendingFlush = false;

	const record = ( name, args ) => {
		calls[ name ].push( { args, insideBatch: batchDepth > 0 } );

		if ( batchDepth > 0 ) {
			pendingFlush = true;

			return;
		}

		undoEntries.push( name );
	};

	const blockEditorActions = {
		updateBlockAttributes: ( ...args ) =>
			record( 'updateBlockAttributes', args ),
		insertBlocks: ( ...args ) => record( 'insertBlocks', args ),
		removeBlocks: ( ...args ) => record( 'removeBlocks', args ),
	};

	const registry = {
		select: ( store ) => wpData.select( store ),
		dispatch: ( store ) => wpData.dispatch( store ),
		batch: jest.fn( ( callback ) => {
			batchDepth += 1;

			try {
				callback();
			} finally {
				batchDepth -= 1;

				if ( batchDepth === 0 && pendingFlush ) {
					pendingFlush = false;
					undoEntries.push( 'batch' );
				}
			}
		} ),
	};

	const install = () => {
		wpData.__setStore(
			'core/block-editor',
			selectors,
			blockEditorActions
		);
		wpData.__setStore(
			'core/editor',
			{
				getCurrentPostId: () => postId,
				getCurrentPostType: () => postType,
			},
			{ undo: ( ...args ) => calls.undo.push( args ) }
		);
		wpData.__setStore(
			'core/notices',
			{},
			{
				createSuccessNotice: ( ...args ) =>
					calls.successNotices.push( args ),
				createErrorNotice: ( ...args ) =>
					calls.errorNotices.push( args ),
			}
		);
		wpData.__setRegistry( registry );
	};

	install();

	return {
		selectors,
		registry,
		calls,
		undoEntries,
		install,
		/** @return {number} Total store dispatches across all three actions. */
		dispatchCount: () =>
			calls.updateBlockAttributes.length +
			calls.insertBlocks.length +
			calls.removeBlocks.length,
	};
}

/**
 * A three-block page: a hero with two bento cards inside a bento grid.
 *
 * @return {Array} Block tree for createEditor().
 */
export function samplePage() {
	return [
		{
			clientId: 'hero-1',
			name: 'swishfolio-core/hero',
			attributes: {
				heading: 'Build a portfolio that lands the work',
				subheading: 'Old subheading copy.',
				ctaText: 'Get started',
				minHeight: 600,
				backgroundColor: 'var(--wp--preset--color--accent-1)',
				textColor: 'var(--wp--preset--color--base)',
			},
		},
		{
			clientId: 'bento-1',
			name: 'swishfolio-core/bento-cards',
			attributes: { accentColor: '' },
			innerBlocks: [
				{
					clientId: 'card-1',
					name: 'swishfolio-core/bento-card',
					attributes: {
						subtitle: 'MODULE 01',
						title: 'First card',
						description: 'Card one description.',
					},
				},
				{
					clientId: 'card-2',
					name: 'swishfolio-core/bento-card',
					attributes: {
						subtitle: 'MODULE 02',
						title: 'Second card',
						description: 'Card two description.',
					},
				},
			],
		},
		{
			clientId: 'para-1',
			name: 'core/paragraph',
			attributes: { content: 'A trailing paragraph.' },
		},
	];
}

/**
 * Editor settings carrying a theme palette, as `getSettings()` returns them.
 *
 * @param {Array} styles Optional `styles` entries (style-variation detection).
 *
 * @return {Object} Settings object.
 */
export function sampleSettings( styles = [] ) {
	return {
		styles,
		__experimentalFeatures: {
			color: {
				palette: {
					theme: [
						{ slug: 'base', color: '#ffffff', name: 'Base' },
						{ slug: 'contrast', color: '#121212', name: 'Contrast' },
						{ slug: 'accent-1', color: '#FFD93D', name: 'Accent 1' },
						{ slug: 'accent-2', color: '#4ECDC4', name: 'Accent 2' },
					],
				},
			},
		},
	};
}
