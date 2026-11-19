/**
 * The apply layer: the clientId re-check, the dispatch plan, and the single
 * undo step.
 */

import {
	buildApplyPlan,
	collectTargetClientIds,
	countPlanTargets,
	executePlan,
	findMissingBlocks,
	findUnknownTargets,
} from '../../../src/js/ai/apply';
import {
	getCapability,
	getContentAttribute,
} from '../../../src/js/ai/capabilities';
import { createEditor, samplePage } from '../fixtures/editor';
import {
	clearManifests,
	installBlockTypes,
	installManifests,
} from '../fixtures/manifests';
import * as wpBlocks from '../mocks/wp-blocks';
import * as wpData from '../mocks/wp-data';

let editor;

const planDeps = () => ( {
	getBlockName: editor.selectors.getBlockName,
	getBlockOrder: editor.selectors.getBlockOrder,
	getContentAttributeFor: ( name ) =>
		getContentAttribute( getCapability( name ) ),
} );

const executeDeps = () => {
	const actions = wpData.dispatch( 'core/block-editor' );

	return {
		registry: editor.registry,
		updateBlockAttributes: actions.updateBlockAttributes,
		insertBlocks: actions.insertBlocks,
		removeBlocks: actions.removeBlocks,
		createBlock: wpBlocks.createBlock,
	};
};

beforeEach( () => {
	installManifests();
	installBlockTypes();

	editor = createEditor( {
		blocks: samplePage(),
		selectedClientId: 'bento-1',
	} );
} );

afterEach( () => {
	clearManifests();
	wpBlocks.__reset();
	wpData.__reset();
} );

describe( 'findUnknownTargets', () => {
	it( 'a returned clientId that was not sent is refused and never dispatched', () => {
		const sentIds = [ 'bento-1', 'card-1', 'card-2' ];
		const actions = [
			{
				type: 'update_block_attributes',
				clientId: 'card-1',
				changes: { title: 'Fine' },
			},
			{
				type: 'update_block_attributes',
				clientId: 'hero-1',
				changes: { heading: 'Not part of this request' },
			},
		];

		expect( findUnknownTargets( actions, sentIds ) ).toEqual( [ 'hero-1' ] );

		// All-or-nothing: the refusal covers the whole response, so nothing is
		// dispatched even though the first action was legitimate.
		expect( editor.dispatchCount() ).toBe( 0 );
		expect( editor.undoEntries ).toEqual( [] );
	} );

	it( 'checks the insertion parent, not just update targets', () => {
		const actions = [
			{
				type: 'insert_block',
				parentClientId: 'somewhere-else',
				blockName: 'swishfolio-core/bento-card',
				index: 0,
			},
		];

		expect( findUnknownTargets( actions, [ 'bento-1' ] ) ).toEqual( [
			'somewhere-else',
		] );
	} );

	it( 'passes a response that stays inside the tree that was sent', () => {
		const actions = [
			{ type: 'remove_block', clientId: 'card-2' },
			{
				type: 'insert_block',
				parentClientId: 'bento-1',
				blockName: 'swishfolio-core/bento-card',
				index: 0,
			},
		];

		expect(
			findUnknownTargets( actions, new Set( [ 'bento-1', 'card-2' ] ) )
		).toEqual( [] );
	} );

	it( 'collects every target exactly once', () => {
		expect(
			collectTargetClientIds( [
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: {},
				},
				{
					type: 'update_block_content',
					clientId: 'card-1',
					content: 'x',
				},
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'swishfolio-core/bento-card',
				},
			] )
		).toEqual( [ 'card-1', 'bento-1' ] );
	} );
} );

describe( 'findMissingBlocks', () => {
	it( 'catches a block deleted while the request was in flight', () => {
		expect(
			findMissingBlocks(
				[ { type: 'remove_block', clientId: 'card-9' } ],
				editor.selectors.getBlockName
			)
		).toEqual( [ 'card-9' ] );

		expect(
			findMissingBlocks(
				[ { type: 'remove_block', clientId: 'card-1' } ],
				editor.selectors.getBlockName
			)
		).toEqual( [] );
	} );
} );

describe( 'buildApplyPlan', () => {
	it( 'merges every attribute change into one map keyed by clientId', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: { title: 'New title' },
				},
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: { description: 'New description' },
				},
				{
					type: 'update_block_attributes',
					clientId: 'card-2',
					changes: { title: 'Other title' },
				},
			],
			planDeps()
		);

		expect( plan.clientIds.sort() ).toEqual( [ 'card-1', 'card-2' ] );
		expect( plan.attributesByClientId ).toEqual( {
			'card-1': {
				title: 'New title',
				description: 'New description',
			},
			'card-2': { title: 'Other title' },
		} );
		expect( plan.unsupported ).toEqual( [] );
	} );

	it( 'routes a content rewrite to the first richtext field', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'update_block_content',
					clientId: 'card-1',
					content: 'Rewritten',
				},
			],
			planDeps()
		);

		expect( plan.attributesByClientId ).toEqual( {
			'card-1': { subtitle: 'Rewritten' },
		} );
	} );

	it( 'refuses a content rewrite on a block with no richtext field', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'update_block_content',
					clientId: 'hero-1',
					content: 'Nowhere to put this',
				},
			],
			planDeps()
		);

		expect( plan.unsupported ).toEqual( [
			{
				position: 0,
				type: 'update_block_content',
				reason: 'no-content-field',
			},
		] );
	} );

	it( 'does not set attributes on a block it is about to remove', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'update_block_attributes',
					clientId: 'card-2',
					changes: { title: 'Pointless' },
				},
				{ type: 'remove_block', clientId: 'card-2' },
			],
			planDeps()
		);

		expect( plan.clientIds ).toEqual( [] );
		expect( plan.removals ).toEqual( [ 'card-2' ] );
	} );

	it( 'offsets repeated insertions into one parent so they land in order', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'swishfolio-core/bento-card',
					index: 1,
					attributes: { title: 'A' },
				},
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'swishfolio-core/bento-card',
					index: 1,
					attributes: { title: 'B' },
				},
			],
			planDeps()
		);

		expect(
			plan.inserts.map( ( insert ) => [
				insert.index,
				insert.attributes.title,
			] )
		).toEqual( [
			[ 1, 'A' ],
			[ 2, 'B' ],
		] );
	} );

	it( 'clamps an insertion index to the number of children the parent has', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'swishfolio-core/bento-card',
					index: 99,
				},
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'swishfolio-core/bento-card',
					index: null,
				},
			],
			planDeps()
		);

		// bento-1 has two children, so both land at the end, in order.
		expect( plan.inserts.map( ( insert ) => insert.index ) ).toEqual( [
			2, 3,
		] );
	} );

	it( 'flags an unknown action type rather than silently dropping it', () => {
		const plan = buildApplyPlan(
			[ { type: 'reticulate_splines', clientId: 'card-1' } ],
			planDeps()
		);

		expect( plan.unsupported ).toEqual( [
			{ position: 0, type: 'reticulate_splines', reason: 'unknown-type' },
		] );
	} );
} );

describe( 'executePlan', () => {
	it( 'applying N changes produces exactly ONE undo entry', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: { title: 'First' },
				},
				{
					type: 'update_block_attributes',
					clientId: 'card-2',
					changes: { title: 'Second' },
				},
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'swishfolio-core/bento-card',
					index: 2,
					attributes: { title: 'Third' },
				},
				{ type: 'remove_block', clientId: 'card-2' },
			],
			planDeps()
		);

		const result = executePlan( plan, executeDeps() );

		expect( result.batched ).toBe( true );
		expect( editor.registry.batch ).toHaveBeenCalledTimes( 1 );

		// Four actions, more than one dispatch — but one flush, so one undo.
		expect( editor.dispatchCount() ).toBeGreaterThan( 1 );
		expect( editor.undoEntries ).toEqual( [ 'batch' ] );

		// Every dispatch happened inside the batch, or the count above would be
		// a coincidence.
		[
			...editor.calls.updateBlockAttributes,
			...editor.calls.insertBlocks,
			...editor.calls.removeBlocks,
		].forEach( ( call ) => {
			expect( call.insideBatch ).toBe( true );
		} );
	} );

	it( 'collapses all attribute changes into a single uniqueByBlock dispatch', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: { title: 'First' },
				},
				{
					type: 'update_block_attributes',
					clientId: 'card-2',
					changes: { title: 'Second' },
				},
			],
			planDeps()
		);

		executePlan( plan, executeDeps() );

		expect( editor.calls.updateBlockAttributes ).toHaveLength( 1 );
		expect( editor.calls.updateBlockAttributes[ 0 ].args ).toEqual( [
			[ 'card-1', 'card-2' ],
			{
				'card-1': { title: 'First' },
				'card-2': { title: 'Second' },
			},
			{ uniqueByBlock: true },
		] );
	} );

	it( 'inserts before it removes, and marks both as non-persistent-free dispatches', () => {
		const plan = buildApplyPlan(
			[
				{ type: 'remove_block', clientId: 'card-1' },
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'swishfolio-core/bento-card',
					index: 0,
					attributes: { title: 'Replacement' },
				},
			],
			planDeps()
		);

		executePlan( plan, executeDeps() );

		expect( editor.calls.insertBlocks ).toHaveLength( 1 );
		expect( editor.calls.removeBlocks ).toHaveLength( 1 );

		const [ blocks, index, parentClientId, updateSelection ] =
			editor.calls.insertBlocks[ 0 ].args;

		expect( blocks[ 0 ].name ).toBe( 'swishfolio-core/bento-card' );
		expect( blocks[ 0 ].attributes ).toEqual( { title: 'Replacement' } );
		expect( index ).toBe( 0 );
		expect( parentClientId ).toBe( 'bento-1' );
		expect( updateSelection ).toBe( false );

		expect( editor.calls.removeBlocks[ 0 ].args ).toEqual( [
			[ 'card-1' ],
			false,
		] );
		expect( editor.undoEntries ).toEqual( [ 'batch' ] );
	} );

	it( 'reports when no batch was available, because undo then takes more steps', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: { title: 'First' },
				},
				{ type: 'remove_block', clientId: 'card-2' },
			],
			planDeps()
		);

		const result = executePlan( plan, {
			...executeDeps(),
			registry: {},
		} );

		expect( result.batched ).toBe( false );
		expect( editor.undoEntries ).toEqual( [
			'updateBlockAttributes',
			'removeBlocks',
		] );
	} );
} );

describe( 'countPlanTargets', () => {
	it( 'counts blocks touched, not actions taken', () => {
		const plan = buildApplyPlan(
			[
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: { title: 'a' },
				},
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: { description: 'b' },
				},
				{ type: 'remove_block', clientId: 'card-2' },
			],
			planDeps()
		);

		expect( countPlanTargets( plan ) ).toBe( 2 );
	} );
} );
