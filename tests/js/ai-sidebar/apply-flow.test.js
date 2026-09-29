/**
 * The whole loop through the sidebar: prompt, request, preview, apply.
 *
 * These are the two invariants that matter most, exercised through the real
 * component tree rather than the modules underneath it: a returned clientId
 * that was never sent stops everything, and applying a whole proposal is one
 * undo step.
 */

import { fireEvent, render, screen, waitFor } from '@testing-library/react';

import apiFetch from '@wordpress/api-fetch';

import AiSidebar from '../../../src/js/ai-sidebar/sidebar';
import { GENERIC_FAILURE } from '../../../src/js/ai/rest';
import {
	createEditor,
	samplePage,
	sampleSettings,
} from '../fixtures/editor';
import {
	clearManifests,
	installBlockTypes,
	installManifests,
} from '../fixtures/manifests';
import * as wpBlocks from '../mocks/wp-blocks';
import * as wpData from '../mocks/wp-data';

/** Three valid actions inside the bento grid: two updates and one insertion. */
const THREE_VALID_ACTIONS = [
	{
		type: 'update_block_attributes',
		clientId: 'card-1',
		changes: { title: 'Sharper first card' },
		reason: 'Tighter title.',
	},
	{
		type: 'update_block_attributes',
		clientId: 'card-2',
		changes: { description: 'Sharper second description.' },
		reason: 'Plainer description.',
	},
	{
		type: 'insert_block',
		parentClientId: 'bento-1',
		blockName: 'swishfolio-core/bento-card',
		index: 2,
		attributes: { subtitle: 'MODULE 03', title: 'Third card' },
		reason: 'Balances the grid.',
	},
];

let editor;

/**
 * @param {Object} options createEditor() options, merged over the defaults.
 *
 * @return {Object} Testing Library render result.
 */
function renderSidebar( options = {} ) {
	editor = createEditor( {
		blocks: samplePage(),
		selectedClientId: 'bento-1',
		settings: sampleSettings(),
		...options,
	} );

	return render( <AiSidebar /> );
}

/**
 * Type a prompt and press Ask AI.
 *
 * @param {string} instruction What to ask for.
 */
async function ask( instruction ) {
	fireEvent.change( screen.getByLabelText( 'What should change?' ), {
		target: { value: instruction },
	} );

	fireEvent.click( screen.getByRole( 'button', { name: 'Ask AI' } ) );

	await waitFor( () => expect( apiFetch ).toHaveBeenCalledTimes( 1 ) );
}

beforeEach( () => {
	installManifests();
	installBlockTypes();
	apiFetch.mockReset();
} );

afterEach( () => {
	clearManifests();
	wpBlocks.__reset();
	wpData.__reset();
} );

describe( 'AI sidebar', () => {
	it( 'a returned clientId that was not sent is refused and never dispatched', async () => {
		renderSidebar();

		apiFetch.mockResolvedValue( {
			schemaVersion: '1.0',
			summary: 'Tightened the grid and the hero.',
			actions: [
				{
					type: 'update_block_attributes',
					clientId: 'card-1',
					changes: { title: 'Legitimate change' },
				},
				{
					type: 'update_block_attributes',
					clientId: 'hero-1',
					changes: { heading: 'Never asked for this' },
				},
			],
		} );

		await ask( 'Tighten the cards.' );

		// hero-1 is a sibling of the selection: the AI was told it exists, but
		// it was never put in the block tree, so it may not be touched.
		const sent = apiFetch.mock.calls[ 0 ][ 0 ].data;

		expect( Object.keys( sent.blockTree ).sort() ).toEqual( [
			'bento-1',
			'card-1',
			'card-2',
		] );
		expect( Object.keys( sent.blockTree ) ).not.toContain( 'hero-1' );

		await waitFor( () =>
			expect( screen.getByText( GENERIC_FAILURE ) ).toBeTruthy()
		);

		expect(
			screen.getByText(
				'The response referred to a block that was not part of this request, so none of it was applied.'
			)
		).toBeTruthy();
		expect(
			screen.getByText( 'Details' ).closest( 'details' ).textContent
		).toContain( 'Unrecognised targets: hero-1' );

		// All-or-nothing: the legitimate first action was not applied either,
		// and the review step was never reached.
		expect( screen.queryByText( 'Apply all' ) ).toBeNull();
		expect( editor.dispatchCount() ).toBe( 0 );
		expect( editor.registry.batch ).not.toHaveBeenCalled();
		expect( editor.undoEntries ).toEqual( [] );
		expect( editor.calls.successNotices ).toEqual( [] );
	} );

	it( 'applying N changes produces exactly ONE undo entry', async () => {
		renderSidebar();

		apiFetch.mockResolvedValue( {
			schemaVersion: '1.0',
			summary: 'Sharpened both cards and added a third.',
			actions: THREE_VALID_ACTIONS,
		} );

		await ask( 'Sharpen the cards and add one more.' );

		await waitFor( () =>
			expect( screen.getByText( '3 proposed changes' ) ).toBeTruthy()
		);

		// Reviewing has dispatched nothing.
		expect( editor.dispatchCount() ).toBe( 0 );

		fireEvent.click( screen.getByRole( 'button', { name: 'Apply all' } ) );

		// Three actions, two dispatches, one batch — therefore one undo entry.
		expect( editor.registry.batch ).toHaveBeenCalledTimes( 1 );
		expect( editor.undoEntries ).toEqual( [ 'batch' ] );

		expect( editor.calls.updateBlockAttributes ).toHaveLength( 1 );
		expect( editor.calls.updateBlockAttributes[ 0 ].args ).toEqual( [
			[ 'card-1', 'card-2' ],
			{
				'card-1': { title: 'Sharper first card' },
				'card-2': { description: 'Sharper second description.' },
			},
			{ uniqueByBlock: true },
		] );
		expect( editor.calls.insertBlocks ).toHaveLength( 1 );
		expect( editor.calls.removeBlocks ).toHaveLength( 0 );

		editor.calls.updateBlockAttributes
			.concat( editor.calls.insertBlocks )
			.forEach( ( call ) => expect( call.insideBatch ).toBe( true ) );

		// The snackbar says how many, without the multi-step undo caveat, and
		// offers Undo.
		expect( editor.calls.successNotices ).toHaveLength( 1 );

		const [ message, options ] = editor.calls.successNotices[ 0 ];

		expect( message ).toBe( 'Applied 3 AI changes.' );
		expect( message ).not.toContain( 'more than one step' );
		expect( options.type ).toBe( 'snackbar' );
		expect( options.actions[ 0 ].label ).toBe( 'Undo' );

		options.actions[ 0 ].onClick();

		expect( editor.calls.undo ).toHaveLength( 1 );

		// Applying clears the proposal and returns to the prompt.
		await waitFor( () =>
			expect( screen.queryByText( 'Apply all' ) ).toBeNull()
		);
		expect( screen.getByLabelText( 'What should change?' ).value ).toBe(
			''
		);
	} );

	it( 'applies only the ticked rows, still as one undo entry', async () => {
		const { container } = renderSidebar();

		apiFetch.mockResolvedValue( {
			schemaVersion: '1.0',
			summary: 'Three changes.',
			actions: THREE_VALID_ACTIONS,
		} );

		await ask( 'Sharpen the cards and add one more.' );
		await waitFor( () =>
			expect( screen.getByText( '3 proposed changes' ) ).toBeTruthy()
		);

		// Untick the insertion.
		fireEvent.click(
			container.querySelectorAll( 'input[type="checkbox"]' )[ 2 ]
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply selected (2)' } )
		);

		expect( editor.calls.insertBlocks ).toHaveLength( 0 );
		expect( editor.calls.updateBlockAttributes ).toHaveLength( 1 );
		expect( editor.undoEntries ).toEqual( [ 'batch' ] );
		expect( editor.calls.successNotices[ 0 ][ 0 ] ).toBe(
			'Applied 2 AI changes.'
		);
	} );

	it( 'discarding a proposal changes nothing', async () => {
		renderSidebar();

		apiFetch.mockResolvedValue( {
			schemaVersion: '1.0',
			summary: 'Three changes.',
			actions: THREE_VALID_ACTIONS,
		} );

		await ask( 'Sharpen the cards and add one more.' );
		await waitFor( () =>
			expect( screen.getByText( '3 proposed changes' ) ).toBeTruthy()
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Discard' } ) );

		await waitFor( () =>
			expect( screen.queryByText( '3 proposed changes' ) ).toBeNull()
		);

		expect( editor.dispatchCount() ).toBe( 0 );
		expect( editor.undoEntries ).toEqual( [] );
		expect( editor.calls.successNotices ).toEqual( [] );
	} );

	it( 'refuses an insertion of a block type this site does not have', async () => {
		renderSidebar();

		apiFetch.mockResolvedValue( {
			schemaVersion: '1.0',
			summary: 'Adding something exotic.',
			actions: [
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'acme/not-installed',
					index: 0,
					attributes: {},
				},
			],
		} );

		await ask( 'Add a widget.' );
		await waitFor( () =>
			expect( screen.getByText( '1 proposed change' ) ).toBeTruthy()
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Apply all' } ) );

		await waitFor( () =>
			expect( screen.getByText( GENERIC_FAILURE ) ).toBeTruthy()
		);

		expect(
			screen.getByText(
				'The response wanted to add a block type this site does not have. Nothing was applied.'
			)
		).toBeTruthy();
		expect( editor.dispatchCount() ).toBe( 0 );
		expect( editor.undoEntries ).toEqual( [] );
	} );

	it( 'surfaces a REST failure without touching the post', async () => {
		renderSidebar();

		apiFetch.mockRejectedValue( {
			code: 'sfcore_ai_not_configured',
			message: 'No AI provider is configured.',
			data: { status: 409 },
		} );

		await ask( 'Tighten the cards.' );

		await waitFor( () =>
			expect( screen.getByText( GENERIC_FAILURE ) ).toBeTruthy()
		);

		expect(
			screen.getByText(
				'No AI provider is configured yet — add a key under Swish Forms → AI.'
			)
		).toBeTruthy();
		expect( editor.dispatchCount() ).toBe( 0 );
		expect( editor.undoEntries ).toEqual( [] );
	} );

	it( 'sends the post ID, the instruction and the selection the server reads', async () => {
		renderSidebar();

		apiFetch.mockResolvedValue( {
			schemaVersion: '1.0',
			summary: '',
			actions: [],
		} );

		await ask( '  Tighten the cards.  ' );

		const [ request ] = apiFetch.mock.calls[ 0 ];

		expect( request.path ).toBe( '/swishfolio/v1/ai/edit' );
		expect( request.method ).toBe( 'POST' );
		expect( Object.keys( request.data ).sort() ).toEqual( [
			'blockTree',
			'instruction',
			'postId',
			'selection',
		] );
		expect( request.data.postId ).toBe( 41 );
		expect( request.data.instruction ).toBe( 'Tighten the cards.' );
		expect( request.data.selection.clientId ).toBe( 'bento-1' );
		expect( request.data.selection.blockName ).toBe(
			'swishfolio-core/bento-cards'
		);
	} );

	it( 'summarises the selection and how much of it is editable', () => {
		renderSidebar( { selectedClientId: 'card-1' } );

		expect( screen.getByText( 'Bento Card' ) ).toBeTruthy();
		expect( screen.getByText( '4 editable properties' ) ).toBeTruthy();
		expect(
			screen.getByText(
				'One bento grid cell with a title, subtitle and description.'
			)
		).toBeTruthy();
	} );

	it( 'warns that a content-tier block has no editable styling', () => {
		renderSidebar( { selectedClientId: 'para-1' } );

		expect( screen.getByText( '1 editable property' ) ).toBeTruthy();
		expect(
			screen.getByText(
				'Text only on this block — its styling is not AI-editable.'
			)
		).toBeTruthy();
	} );

	it( 'asks for a selection when there is none', () => {
		renderSidebar( { selectedClientId: null } );

		expect(
			screen.getByText(
				'Select a block, then describe the change you want.'
			)
		).toBeTruthy();
		expect( screen.queryByLabelText( 'What should change?' ) ).toBeNull();
	} );

	it( 'explains itself on a block with no manifest', () => {
		renderSidebar( {
			blocks: [
				{
					clientId: 'spacer-1',
					name: 'core/spacer',
					attributes: { height: '40px' },
				},
			],
			selectedClientId: 'spacer-1',
		} );

		expect(
			screen.getByText( 'Spacer has no AI-editable properties yet.' )
		).toBeTruthy();
		expect( screen.queryByLabelText( 'What should change?' ) ).toBeNull();
	} );

	it( 'says AI editing is unavailable when PHP localised no manifests', () => {
		clearManifests();

		renderSidebar();

		expect(
			screen.getByText(
				'AI editing is unavailable: no block capability manifests were loaded.'
			)
		).toBeTruthy();
	} );
} );
