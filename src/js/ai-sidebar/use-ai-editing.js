/**
 * The one hook that owns an AI edit from prompt to applied change.
 *
 * It lives in React on purpose. `registry.batch()` is the only way to collapse
 * several dispatches into a single undo step, and in this build the registry is
 * reachable only through the `useRegistry()` hook — `@wordpress/data` exports
 * neither `batch` nor a default registry. A plain module-level apply function
 * would silently lose batching and turn one AI change into five Ctrl+Z presses.
 */

import { useCallback, useMemo, useState } from '@wordpress/element';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as editorStore } from '@wordpress/editor';
import { store as noticesStore } from '@wordpress/notices';
import { createBlock, getBlockType } from '@wordpress/blocks';
import { __, _n, sprintf } from '@wordpress/i18n';

import {
	countFields,
	getCapability,
	getContentAttribute,
} from '../ai/capabilities';
import {
	buildSelectionContext,
	detectStyleVariation,
} from '../ai/selection-context';
import {
	describeError,
	localError,
	reportApplied,
	requestEdit,
} from '../ai/rest';
import { buildActionRows } from '../ai/preview';
import {
	buildApplyPlan,
	executePlan,
	findMissingBlocks,
	findUnknownTargets,
} from '../ai/apply';
import { createColourResolver } from '../ai/tokens';

/**
 * @param {number}  count   How many actions were applied.
 * @param {boolean} batched Whether the dispatches shared one undo step.
 *
 * @return {string} Snackbar copy.
 */
function appliedMessage( count, batched ) {
	if ( batched ) {
		return sprintf(
			/* translators: %d: number of changes applied. */
			_n(
				'Applied %d AI change.',
				'Applied %d AI changes.',
				count,
				'swishfolio-core'
			),
			count
		);
	}

	return sprintf(
		/* translators: %d: number of changes applied. */
		_n(
			'Applied %d AI change. Undo may take more than one step.',
			'Applied %d AI changes. Undo may take more than one step.',
			count,
			'swishfolio-core'
		),
		count
	);
}

export const STATUS_IDLE = 'idle';
export const STATUS_WORKING = 'working';
export const STATUS_PROPOSED = 'proposed';
export const STATUS_ERROR = 'error';

export function useAiEditing() {
	const registry = useRegistry();

	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );
	const { updateBlockAttributes, insertBlocks, removeBlocks } =
		useDispatch( blockEditorStore );
	const { undo } = useDispatch( editorStore );

	const { clientId, blockName, postId, postType } = useSelect( ( select ) => {
		const { getSelectedBlockClientId, getBlockName } =
			select( blockEditorStore );
		const { getCurrentPostId, getCurrentPostType } = select( editorStore );
		const selected = getSelectedBlockClientId();

		return {
			clientId: selected || null,
			blockName: selected ? getBlockName( selected ) : null,
			postId: getCurrentPostId(),
			postType: getCurrentPostType(),
		};
	}, [] );

	const capability = useMemo(
		() => getCapability( blockName ),
		[ blockName ]
	);
	const editableCount = useMemo(
		() => countFields( capability ),
		[ capability ]
	);

	const [ instruction, setInstruction ] = useState( '' );
	const [ status, setStatus ] = useState( STATUS_IDLE );
	const [ error, setError ] = useState( null );
	const [ proposal, setProposal ] = useState( null );
	const [ selectedActions, setSelectedActions ] = useState( [] );

	const reset = useCallback( () => {
		setStatus( STATUS_IDLE );
		setError( null );
		setProposal( null );
		setSelectedActions( [] );
	}, [] );

	const submit = useCallback( async () => {
		const trimmed = instruction.trim();

		if ( ! trimmed || ! clientId || ! capability || ! postId ) {
			return;
		}

		const blockEditor = registry.select( blockEditorStore );
		const context = buildSelectionContext( blockEditor, clientId, {
			postType,
			styleVariation: detectStyleVariation(
				blockEditor.getSettings().styles
			),
		} );

		if ( ! context ) {
			setError(
				localError(
					'sfcore_ai_bad_selection',
					__(
						'The selected block could not be read.',
						'swishfolio-core'
					),
					null
				)
			);
			setStatus( STATUS_ERROR );

			return;
		}

		setStatus( STATUS_WORKING );
		setError( null );
		setProposal( null );
		setSelectedActions( [] );

		try {
			const response = await requestEdit( {
				postId,
				instruction: trimmed,
				selection: context.selection,
				blockTree: context.blockTree,
			} );

			const actions =
				response && Array.isArray( response.actions )
					? response.actions
					: [];
			const sentIds = Object.keys( context.blockTree );
			const unknown = findUnknownTargets( actions, sentIds );

			if ( unknown.length ) {
				setError(
					localError(
						'sfcore_ai_unknown_target',
						__(
							'The response referred to a block that was not part of this request, so none of it was applied.',
							'swishfolio-core'
						),
						sprintf(
							/* translators: %s: comma-separated client IDs. */
							__( 'Unrecognised targets: %s', 'swishfolio-core' ),
							unknown.join( ', ' )
						)
					)
				);
				setStatus( STATUS_ERROR );

				return;
			}

			const resolveColour = createColourResolver(
				blockEditor.getSettings()
			);

			setProposal( {
				summary:
					response && response.summary
						? String( response.summary )
						: '',
				actions,
				rows: buildActionRows( actions, {
					getBlockName: blockEditor.getBlockName,
					getBlockAttributes: blockEditor.getBlockAttributes,
					getCapability,
					resolveColour,
				} ),
				sentIds,
				requestedClientId: clientId,
				requestedBlockName: blockName,
				instruction: trimmed,
			} );
			setSelectedActions( actions.map( ( action, index ) => index ) );
			setStatus( STATUS_PROPOSED );
		} catch ( requestError ) {
			setError( describeError( requestError ) );
			setStatus( STATUS_ERROR );
		}
	}, [
		instruction,
		clientId,
		capability,
		postId,
		postType,
		blockName,
		registry,
	] );

	const apply = useCallback(
		( indexes ) => {
			if ( ! proposal ) {
				return;
			}

			const chosen = ( indexes || [] )
				.slice()
				.sort( ( a, b ) => a - b )
				.map( ( index ) => proposal.actions[ index ] )
				.filter( Boolean );

			if ( ! chosen.length ) {
				return;
			}

			// The client half of validation stage 2, run again at the last
			// possible moment.
			const unknown = findUnknownTargets( chosen, proposal.sentIds );

			if ( unknown.length ) {
				setError(
					localError(
						'sfcore_ai_unknown_target',
						__(
							'A change pointed at a block that was not part of this request. Nothing was applied.',
							'swishfolio-core'
						),
						sprintf(
							/* translators: %s: comma-separated client IDs. */
							__( 'Unrecognised targets: %s', 'swishfolio-core' ),
							unknown.join( ', ' )
						)
					)
				);
				setStatus( STATUS_ERROR );

				return;
			}

			const blockEditor = registry.select( blockEditorStore );
			const missing = findMissingBlocks(
				chosen,
				blockEditor.getBlockName
			);

			if ( missing.length ) {
				setError(
					localError(
						'sfcore_ai_target_gone',
						__(
							'One of the blocks has changed since you asked. Nothing was applied — try again.',
							'swishfolio-core'
						),
						sprintf(
							/* translators: %s: comma-separated client IDs. */
							__( 'Missing blocks: %s', 'swishfolio-core' ),
							missing.join( ', ' )
						)
					)
				);
				setStatus( STATUS_ERROR );

				return;
			}

			const plan = buildApplyPlan( chosen, {
				getBlockName: blockEditor.getBlockName,
				getBlockOrder: blockEditor.getBlockOrder,
				getContentAttributeFor: ( name ) =>
					getContentAttribute( getCapability( name ) ),
			} );

			if ( plan.unsupported.length ) {
				setError(
					localError(
						'sfcore_ai_unsupported_action',
						__(
							'Part of the response could not be applied, so none of it was.',
							'swishfolio-core'
						),
						plan.unsupported
							.map( ( entry ) =>
								sprintf(
									'%1$s: %2$s',
									entry.type || '?',
									entry.reason
								)
							)
							.join( '\n' )
					)
				);
				setStatus( STATUS_ERROR );

				return;
			}

			const unregistered = plan.inserts
				.map( ( insert ) => insert.blockName )
				.filter( ( name ) => ! getBlockType( name ) );

			if ( unregistered.length ) {
				setError(
					localError(
						'sfcore_ai_unregistered_block',
						__(
							'The response wanted to add a block type this site does not have. Nothing was applied.',
							'swishfolio-core'
						),
						unregistered.join( ', ' )
					)
				);
				setStatus( STATUS_ERROR );

				return;
			}

			try {
				const result = executePlan( plan, {
					registry,
					updateBlockAttributes,
					insertBlocks,
					removeBlocks,
					createBlock,
				} );

				// Applying happens entirely client-side, so the server only
				// learns the outcome if we tell it. Fire-and-forget: a failed
				// metric must never disturb an edit that already succeeded.
				reportApplied( chosen.length );

				createSuccessNotice(
					appliedMessage( chosen.length, result.batched ),
					{
						type: 'snackbar',
						actions: [
							{
								label: __( 'Undo', 'swishfolio-core' ),
								onClick: () => undo(),
							},
						],
					}
				);
			} catch ( applyError ) {
				createErrorNotice(
					__(
						'The AI change could not be applied.',
						'swishfolio-core'
					),
					{ type: 'snackbar' }
				);
				setError(
					localError(
						'sfcore_ai_apply_failed',
						__(
							'The editor refused the change.',
							'swishfolio-core'
						),
						applyError && applyError.message
							? String( applyError.message )
							: null
					)
				);
				setStatus( STATUS_ERROR );

				return;
			}

			setInstruction( '' );
			reset();
		},
		[
			proposal,
			registry,
			updateBlockAttributes,
			insertBlocks,
			removeBlocks,
			createSuccessNotice,
			createErrorNotice,
			undo,
			reset,
		]
	);

	const applyAll = useCallback( () => {
		if ( proposal ) {
			apply( proposal.actions.map( ( action, index ) => index ) );
		}
	}, [ apply, proposal ] );

	const applySelected = useCallback( () => {
		apply( selectedActions );
	}, [ apply, selectedActions ] );

	const toggleAction = useCallback( ( index ) => {
		setSelectedActions( ( current ) =>
			current.indexOf( index ) === -1
				? current.concat( [ index ] )
				: current.filter( ( entry ) => entry !== index )
		);
	}, [] );

	return {
		clientId,
		blockName,
		capability,
		editableCount,
		instruction,
		setInstruction,
		status,
		error,
		proposal,
		selectedActions,
		submit,
		applyAll,
		applySelected,
		toggleAction,
		discard: reset,
	};
}
