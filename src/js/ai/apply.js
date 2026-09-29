/**
 * Applying validated actions to the block editor store.
 *
 * Two invariants live here.
 *
 * **Every returned clientId is re-checked against the set the client sent.**
 * The server cannot prove a clientId is real — it only knows what the editor
 * submitted (see TargetStage's own note) — so the side that *can* verify does,
 * before anything is dispatched. A single unrecognised target rejects the whole
 * response, matching the all-or-nothing rule the server validator follows.
 *
 * **The whole change is one undo step.** Every dispatch happens inside
 * `registry.batch()`, which pauses the registry and store emitters for the
 * duration of the callback. `useBlockSync`'s subscriber therefore fires once,
 * calls `onChange` once, and one record lands in the undo manager — one Ctrl+Z.
 * Attribute changes go further and collapse into a *single* Redux action via
 * `updateBlockAttributes( clientIds, attrsByClientId, { uniqueByBlock: true } )`,
 * which also makes the typing-coalescing rule in the block-editor reducer
 * unreachable: were the last action in the batch judged non-persistent,
 * `useBlockSync` would call `onInput` instead and the change would be staged into
 * whatever the user did before it, so one Ctrl+Z would undo both.
 */

import {
	ACTION_INSERT,
	ACTION_REMOVE,
	ACTION_UPDATE_ATTRIBUTES,
	ACTION_UPDATE_CONTENT,
} from './constants';

function unique( values ) {
	return values.filter(
		( value, index ) => values.indexOf( value ) === index
	);
}

/**
 * Every clientId an action set points at, target or insertion parent.
 *
 * @param {Array} actions Actions.
 *
 * @return {string[]} Unique clientIds.
 */
export function collectTargetClientIds( actions ) {
	const ids = [];

	( Array.isArray( actions ) ? actions : [] ).forEach( ( action ) => {
		if ( ! action ) {
			return;
		}

		if ( action.type === ACTION_INSERT ) {
			if ( action.parentClientId ) {
				ids.push( String( action.parentClientId ) );
			}

			return;
		}

		if ( action.clientId ) {
			ids.push( String( action.clientId ) );
		}
	} );

	return unique( ids );
}

/**
 * The client half of validation stage 2.
 *
 * @param {Array}        actions  Actions.
 * @param {string[]|Set} knownIds The clientIds the client sent.
 *
 * @return {string[]} Targets that were never sent. Empty means safe to apply.
 */
export function findUnknownTargets( actions, knownIds ) {
	const known =
		knownIds instanceof Set ? knownIds : new Set( knownIds || [] );

	return collectTargetClientIds( actions ).filter(
		( id ) => ! known.has( id )
	);
}

/**
 * Targets that were sent but no longer exist — the user deleted the block while
 * the request was in flight.
 *
 * @param {Array}    actions      Actions.
 * @param {Function} getBlockName `core/block-editor` selector.
 *
 * @return {string[]} Missing clientIds.
 */
export function findMissingBlocks( actions, getBlockName ) {
	return collectTargetClientIds( actions ).filter(
		( id ) => ! getBlockName( id )
	);
}

/**
 * Group the actions into the smallest set of dispatches that expresses them.
 *
 * @param {Array}  actions Actions to apply.
 * @param {Object} deps    { getBlockName, getBlockOrder, getContentAttributeFor }.
 *
 * @return {Object} { clientIds, attributesByClientId, inserts, removals, unsupported }.
 */
export function buildApplyPlan( actions, deps ) {
	const { getBlockName, getBlockOrder, getContentAttributeFor } = deps;

	const attributesByClientId = {};
	const removals = [];
	const unsupported = [];
	const insertRequests = [];

	const mergeAttributes = ( clientId, values ) => {
		attributesByClientId[ clientId ] = {
			...( attributesByClientId[ clientId ] || {} ),
			...values,
		};
	};

	( Array.isArray( actions ) ? actions : [] ).forEach(
		( action, position ) => {
			if ( ! action || ! action.type ) {
				unsupported.push( {
					position,
					type: '',
					reason: 'missing-type',
				} );

				return;
			}

			if ( action.type === ACTION_UPDATE_ATTRIBUTES ) {
				const changes =
					action.changes && typeof action.changes === 'object'
						? action.changes
						: null;

				if ( ! changes || Object.keys( changes ).length === 0 ) {
					unsupported.push( {
						position,
						type: action.type,
						reason: 'no-changes',
					} );

					return;
				}

				mergeAttributes( String( action.clientId ), changes );

				return;
			}

			if ( action.type === ACTION_UPDATE_CONTENT ) {
				const blockName = getBlockName( action.clientId );
				const attribute = getContentAttributeFor( blockName );

				if ( ! attribute ) {
					unsupported.push( {
						position,
						type: action.type,
						reason: 'no-content-field',
					} );

					return;
				}

				mergeAttributes( String( action.clientId ), {
					[ attribute ]: action.content,
				} );

				return;
			}

			if ( action.type === ACTION_INSERT ) {
				const requestedIndex =
					Number.isFinite( Number( action.index ) ) &&
					action.index !== null
						? Math.max( 0, Math.floor( Number( action.index ) ) )
						: null;

				insertRequests.push( {
					position,
					parentClientId: String( action.parentClientId ),
					blockName: action.blockName,
					attributes:
						action.attributes &&
						typeof action.attributes === 'object'
							? action.attributes
							: {},
					requestedIndex,
				} );

				return;
			}

			if ( action.type === ACTION_REMOVE ) {
				removals.push( String( action.clientId ) );

				return;
			}

			unsupported.push( {
				position,
				type: action.type,
				reason: 'unknown-type',
			} );
		}
	);

	// A block about to be removed does not also need its attributes set.
	removals.forEach( ( clientId ) => {
		delete attributesByClientId[ clientId ];
	} );

	/*
	 * Insertion indices are read in the frame the AI saw: the tree as it was
	 * when the request went out. Each insertion into the same parent shifts the
	 * ones after it by one, so the nth insertion into a parent lands at its
	 * requested index plus n. Removals run after insertions for the same
	 * reason — removing first would shift indices out from under them.
	 */
	const byParent = {};

	insertRequests.forEach( ( request ) => {
		byParent[ request.parentClientId ] =
			byParent[ request.parentClientId ] || [];
		byParent[ request.parentClientId ].push( request );
	} );

	const inserts = [];

	Object.keys( byParent ).forEach( ( parentClientId ) => {
		const childCount = ( getBlockOrder( parentClientId ) || [] ).length;

		const resolve = ( request ) =>
			request.requestedIndex === null
				? childCount
				: Math.min( request.requestedIndex, childCount );

		byParent[ parentClientId ]
			.slice()
			.sort( ( a, b ) => {
				const difference = resolve( a ) - resolve( b );

				return difference === 0 ? a.position - b.position : difference;
			} )
			.forEach( ( request, offset ) => {
				inserts.push( {
					position: request.position,
					parentClientId,
					blockName: request.blockName,
					attributes: request.attributes,
					index: resolve( request ) + offset,
				} );
			} );
	} );

	return {
		clientIds: Object.keys( attributesByClientId ),
		attributesByClientId,
		inserts,
		removals: unique( removals ),
		unsupported,
	};
}

/**
 * Dispatch a plan as one undo step.
 *
 * @param {Object} plan Plan from buildApplyPlan().
 * @param {Object} deps { registry, updateBlockAttributes, insertBlocks, removeBlocks, createBlock }.
 *
 * @return {Object} { batched } — false when no registry batch was available, in
 *					which case undo will take more than one step.
 */
export function executePlan( plan, deps ) {
	const {
		registry,
		updateBlockAttributes,
		insertBlocks,
		removeBlocks,
		createBlock,
	} = deps;

	const run = () => {
		if ( plan.clientIds.length ) {
			updateBlockAttributes( plan.clientIds, plan.attributesByClientId, {
				uniqueByBlock: true,
			} );
		}

		plan.inserts.forEach( ( insert ) => {
			insertBlocks(
				[ createBlock( insert.blockName, insert.attributes ) ],
				insert.index,
				insert.parentClientId,
				false
			);
		} );

		if ( plan.removals.length ) {
			removeBlocks( plan.removals, false );
		}
	};

	const canBatch = !! registry && typeof registry.batch === 'function';

	if ( canBatch ) {
		registry.batch( run );
	} else {
		run();
	}

	return { batched: canBatch };
}

/**
 * @param {Object} plan Plan from buildApplyPlan().
 *
 * @return {number} How many blocks the plan touches.
 */
export function countPlanTargets( plan ) {
	return plan.clientIds.length + plan.inserts.length + plan.removals.length;
}
