/**
 * The "Ask AI" block toolbar button.
 *
 * The fill is rendered directly here (see tests/js/mocks/wp-block-editor.js):
 * these tests assert what the fill contains and how it behaves, not that
 * BlockControls' slot places it in the real toolbar — that is a browser check.
 */

import { fireEvent, render, screen } from '@testing-library/react';

import withAskAiControl, {
	registerAskAiToolbarButton,
} from '../../../src/js/ai-sidebar/ask-ai-button';
import {
	SIDEBAR_IDENTIFIER,
	SIDEBAR_SCOPE,
} from '../../../src/js/ai/constants';
import {
	clearManifests,
	installBlockTypes,
	installManifests,
} from '../fixtures/manifests';
import { isDisabled } from '../helpers/dom';
import * as wpBlocks from '../mocks/wp-blocks';
import * as wpData from '../mocks/wp-data';
import * as wpHooks from '../mocks/wp-hooks';

const BlockEdit = ( { name } ) => (
	<div data-testid="block-edit">{ name }</div>
);

const Wrapped = withAskAiControl( BlockEdit );

let enableComplementaryArea;

/**
 * @param {string|null} activeArea Identifier of the open complementary area.
 */
function installInterfaceStore( activeArea = null ) {
	enableComplementaryArea = jest.fn();

	wpData.__setStore(
		'core/interface',
		{ getActiveComplementaryArea: () => activeArea },
		{ enableComplementaryArea }
	);
}

beforeEach( () => {
	installManifests();
	installBlockTypes();
	installInterfaceStore();
} );

afterEach( () => {
	clearManifests();
	wpBlocks.__reset();
	wpData.__reset();
	wpHooks.__reset();
} );

describe( 'Ask AI toolbar button', () => {
	it( 'toolbar button is disabled for a block with no manifest', () => {
		render( <Wrapped name="core/spacer" /> );

		// core/spacer has no ai.json, so there is nothing the AI could change.
		const button = screen.getByRole( 'button', {
			name: 'Spacer has no AI-editable properties yet.',
		} );

		expect( isDisabled( button ) ).toBe( true );

		// accessibleWhenDisabled: the control keeps the `disabled` attribute
		// off so it stays focusable and the explanation still reaches a
		// keyboard user, and reports its state through aria-disabled.
		expect( button.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect( button.disabled ).toBe( false );

		// Clicking it does nothing.
		fireEvent.click( button );

		expect( enableComplementaryArea ).not.toHaveBeenCalled();

		// The block's own edit UI is untouched.
		expect( screen.getByTestId( 'block-edit' ).textContent ).toBe(
			'core/spacer'
		);
	} );

	it( 'is enabled for a block with a manifest and opens the sidebar by name', () => {
		render( <Wrapped name="swishfolio-core/hero" /> );

		const button = screen.getByRole( 'button', { name: 'Ask AI' } );

		expect( isDisabled( button ) ).toBe( false );

		fireEvent.click( button );

		expect( enableComplementaryArea ).toHaveBeenCalledTimes( 1 );
		expect( enableComplementaryArea ).toHaveBeenCalledWith(
			SIDEBAR_SCOPE,
			SIDEBAR_IDENTIFIER
		);
		expect( SIDEBAR_IDENTIFIER ).toBe( 'sfcore-ai/sfcore-ai-sidebar' );
	} );

	it( 'shows as pressed while its sidebar is the open complementary area', () => {
		installInterfaceStore( SIDEBAR_IDENTIFIER );

		render( <Wrapped name="swishfolio-core/hero" /> );

		expect(
			screen
				.getByRole( 'button', { name: 'Ask AI' } )
				.getAttribute( 'aria-pressed' )
		).toBe( 'true' );
	} );

	it( 'adds no toolbar control at all when PHP localised no manifests', () => {
		clearManifests();

		render( <Wrapped name="swishfolio-core/hero" /> );

		expect( screen.queryByRole( 'button' ) ).toBeNull();
		expect( screen.getByTestId( 'block-edit' ) ).toBeTruthy();
	} );

	it( 'registers itself on editor.BlockEdit under its own namespace', () => {
		registerAskAiToolbarButton();

		const filters = wpHooks.__getFilters( 'editor.BlockEdit' );

		expect( filters ).toHaveLength( 1 );
		expect( filters[ 0 ].namespace ).toBe( 'sfcore/ai-ask-controls' );
		expect( filters[ 0 ].callback ).toBe( withAskAiControl );
	} );
} );
