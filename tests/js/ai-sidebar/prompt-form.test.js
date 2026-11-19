/**
 * The prompt field and the chips above it.
 */

import { fireEvent, render, screen } from '@testing-library/react';

import PromptForm from '../../../src/js/ai-sidebar/prompt-form';
import { getCapability } from '../../../src/js/ai/capabilities';
import { MAX_INSTRUCTION_LENGTH } from '../../../src/js/ai/constants';
import {
	clearManifests,
	installBlockTypes,
	installManifests,
} from '../fixtures/manifests';
import { isDisabled } from '../helpers/dom';
import * as wpBlocks from '../mocks/wp-blocks';

/**
 * @param {Object} overrides Props to override.
 *
 * @return {Object} { props, rendered }
 */
function renderForm( overrides = {} ) {
	const props = {
		capability: getCapability( 'swishfolio-core/hero' ),
		instruction: '',
		setInstruction: jest.fn(),
		onSubmit: jest.fn(),
		isWorking: false,
		...overrides,
	};

	return { props, rendered: render( <PromptForm { ...props } /> ) };
}

const chipNames = () =>
	screen
		.getAllByRole( 'button' )
		.map( ( button ) => button.textContent )
		.filter( ( text ) => text !== 'Ask AI' && text !== 'Asking AI…' );

beforeEach( () => {
	installManifests();
	installBlockTypes();
} );

afterEach( () => {
	clearManifests();
	wpBlocks.__reset();
} );

describe( 'PromptForm', () => {
	it( 'suggestion chips are filtered by block capability', () => {
		const { rendered } = renderForm();

		const heroChips = chipNames();

		// The hero manifest carries colour tokens, a layout enum, a numeric
		// layout field, a boolean and CTA text.
		expect( heroChips ).toContain( 'Tighten the headline' );
		expect( heroChips ).toContain( 'Sharpen the call to action' );
		expect( heroChips ).toContain( 'Raise the contrast' );
		expect( heroChips ).toContain( 'Try a warmer palette' );

		rendered.unmount();

		renderForm( { capability: getCapability( 'core/paragraph' ) } );

		// core/paragraph is tier `content`: one richtext field. Copy work only.
		expect( chipNames() ).toEqual( [
			'Rewrite for a professional audience',
		] );
		expect( screen.queryByText( 'Raise the contrast' ) ).toBeNull();
		expect( screen.queryByText( 'Try a different layout' ) ).toBeNull();
		expect( screen.queryByText( 'Hide the non-essentials' ) ).toBeNull();
	} );

	it( 'names the child block in the chip for a parent that accepts children', () => {
		renderForm( {
			capability: getCapability( 'swishfolio-core/bento-cards' ),
		} );

		expect( chipNames() ).toContain( 'Add another Bento Card' );
	} );

	it( 'loads a chip into the prompt field rather than submitting straight away', () => {
		const { props } = renderForm();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Raise the contrast' } )
		);

		expect( props.setInstruction ).toHaveBeenCalledWith(
			'Raise the contrast between text and background using the theme palette.'
		);
		expect( props.onSubmit ).not.toHaveBeenCalled();
	} );

	it( 'refuses to submit an empty prompt', () => {
		const { props } = renderForm();

		const submit = screen.getByRole( 'button', { name: 'Ask AI' } );

		expect( isDisabled( submit ) ).toBe( true );

		fireEvent.click( submit );

		expect( props.onSubmit ).not.toHaveBeenCalled();
	} );

	it( 'submits a non-empty prompt, and on Cmd/Ctrl+Enter', () => {
		const { props, rendered } = renderForm( {
			instruction: 'Tighten the headline.',
		} );

		fireEvent.click( screen.getByRole( 'button', { name: 'Ask AI' } ) );

		expect( props.onSubmit ).toHaveBeenCalledTimes( 1 );

		const textarea = screen.getByLabelText( 'What should change?' );

		fireEvent.keyDown( textarea, { key: 'Enter' } );

		expect( props.onSubmit ).toHaveBeenCalledTimes( 1 );

		fireEvent.keyDown( textarea, { key: 'Enter', metaKey: true } );

		expect( props.onSubmit ).toHaveBeenCalledTimes( 2 );

		fireEvent.keyDown( textarea, { key: 'Enter', ctrlKey: true } );

		expect( props.onSubmit ).toHaveBeenCalledTimes( 3 );

		rendered.unmount();
	} );

	it( 'caps the prompt length rather than letting the field grow without bound', () => {
		const { props } = renderForm();

		fireEvent.change( screen.getByLabelText( 'What should change?' ), {
			target: { value: 'x'.repeat( MAX_INSTRUCTION_LENGTH + 50 ) },
		} );

		expect( props.setInstruction ).toHaveBeenCalledTimes( 1 );
		expect( props.setInstruction.mock.calls[ 0 ][ 0 ] ).toHaveLength(
			MAX_INSTRUCTION_LENGTH
		);
	} );

	it( 'locks the field and every chip while a request is in flight', () => {
		renderForm( { instruction: 'Tighten the headline.', isWorking: true } );

		expect(
			screen.getByLabelText( 'What should change?' ).disabled
		).toBe( true );
		expect(
			isDisabled( screen.getByRole( 'button', { name: 'Asking AI…' } ) )
		).toBe( true );

		screen
			.getAllByRole( 'button' )
			.filter( ( button ) => button.textContent !== 'Asking AI…' )
			.forEach( ( chip ) => {
				expect( isDisabled( chip ) ).toBe( true );
			} );
	} );
} );
