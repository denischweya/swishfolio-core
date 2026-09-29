/**
 * The review step. Nothing here has touched the post: the only thing that does
 * is the buttons at the bottom.
 */

import { fireEvent, render, screen } from '@testing-library/react';

import ChangePreview from '../../../src/js/ai-sidebar/change-preview';
import { buildActionRows } from '../../../src/js/ai/preview';
import { getCapability } from '../../../src/js/ai/capabilities';
import { createColourResolver } from '../../../src/js/ai/tokens';
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
import { isDisabled } from '../helpers/dom';
import * as wpBlocks from '../mocks/wp-blocks';
import * as wpData from '../mocks/wp-data';

const ACTIONS = [
	{
		type: 'update_block_attributes',
		clientId: 'hero-1',
		changes: {
			heading: 'Portfolios that get you hired',
			backgroundColor: 'var(--wp--preset--color--accent-2)',
		},
		reason: 'Shorter headline, higher contrast.',
	},
	{
		type: 'insert_block',
		parentClientId: 'bento-1',
		blockName: 'swishfolio-core/bento-card',
		index: 2,
		attributes: { title: 'Third card' },
	},
	{ type: 'remove_block', clientId: 'card-2' },
];

let editor;
let handlers;

/**
 * @param {Array}  actions		   Validated actions.
 * @param {Array}  selectedActions Indexes currently ticked.
 * @param {string} summary		   Response summary.
 *
 * @return {Object} Testing Library render result.
 */
function renderPreview( actions = ACTIONS, selectedActions = [ 0, 1, 2 ], summary = 'Tightened the hero and refreshed the grid.' ) {
	const rows = buildActionRows( actions, {
		getBlockName: editor.selectors.getBlockName,
		getBlockAttributes: editor.selectors.getBlockAttributes,
		getCapability,
		resolveColour: createColourResolver( sampleSettings() ),
	} );

	return render(
		<ChangePreview
			proposal={ { summary, actions, rows } }
			selectedActions={ selectedActions }
			onToggle={ handlers.onToggle }
			onApplyAll={ handlers.onApplyAll }
			onApplySelected={ handlers.onApplySelected }
			onDiscard={ handlers.onDiscard }
		/>
	);
}

beforeEach( () => {
	installManifests();
	installBlockTypes();

	editor = createEditor( {
		blocks: samplePage(),
		selectedClientId: 'hero-1',
		settings: sampleSettings(),
	} );

	handlers = {
		onToggle: jest.fn(),
		onApplyAll: jest.fn(),
		onApplySelected: jest.fn(),
		onDiscard: jest.fn(),
	};
} );

afterEach( () => {
	clearManifests();
	wpBlocks.__reset();
	wpData.__reset();
} );

describe( 'ChangePreview', () => {
	it( 'preview renders before -> after per action', () => {
		const { container } = renderPreview();

		const rows = container.querySelectorAll( '.sfcore-ai-sidebar__row' );

		// One reviewable row per action, in response order.
		expect( rows ).toHaveLength( 3 );
		expect( screen.getByText( '3 proposed changes' ) ).toBeTruthy();

		// Row 1: two attribute changes, each showing the current value, an
		// arrow, and the proposed value — in that DOM order.
		const attributeFields = rows[ 0 ].querySelectorAll(
			'.sfcore-ai-sidebar__field'
		);

		expect( attributeFields ).toHaveLength( 2 );
		expect( rows[ 0 ].textContent ).toContain( 'Update' );
		expect( rows[ 0 ].textContent ).toContain( 'Hero' );

		const heading = attributeFields[ 0 ];

		expect(
			heading.querySelector( '.sfcore-ai-sidebar__field-label' )
				.textContent
		).toBe( 'Headline' );

		const headingValues = heading.querySelectorAll(
			'.sfcore-ai-sidebar__value'
		);

		expect( headingValues ).toHaveLength( 2 );
		expect( headingValues[ 0 ].className ).toContain( '--before' );
		expect( headingValues[ 0 ].textContent ).toBe(
			'Build a portfolio that lands the work'
		);
		expect( headingValues[ 1 ].className ).toContain( '--after' );
		expect( headingValues[ 1 ].textContent ).toBe(
			'Portfolios that get you hired'
		);
		expect(
			heading.querySelector( '.sfcore-ai-sidebar__arrow' ).textContent
		).toBe( '→' );

		// The arrow sits between the two values, and is decorative — screen
		// readers get the "changes to" text instead.
		expect(
			headingValues[ 0 ].compareDocumentPosition( headingValues[ 1 ] ) &
				Node.DOCUMENT_POSITION_FOLLOWING
		).toBeTruthy();
		expect(
			heading
				.querySelector( '.sfcore-ai-sidebar__arrow' )
				.getAttribute( 'aria-hidden' )
		).toBe( 'true' );
		expect( heading.textContent ).toContain( 'changes to' );

		// A colour change reads as a palette name with a painted swatch, not as
		// a raw custom property.
		const colour = attributeFields[ 1 ];
		const colourValues = colour.querySelectorAll(
			'.sfcore-ai-sidebar__value'
		);

		expect( colourValues[ 0 ].textContent ).toBe( 'Accent 1' );
		expect( colourValues[ 1 ].textContent ).toBe( 'Accent 2' );
		expect( colour.textContent ).not.toContain( 'var(' );
		expect(
			colourValues[ 1 ].querySelector( '.sfcore-ai-sidebar__swatch' )
				.style.backgroundColor
		).toBe( 'rgb(78, 205, 196)' );

		expect( rows[ 0 ].textContent ).toContain(
			'Shorter headline, higher contrast.'
		);

		// Row 2: an insertion has no before side at all.
		expect(
			rows[ 1 ].querySelectorAll( '.sfcore-ai-sidebar__value--before' )
		).toHaveLength( 0 );
		expect(
			rows[ 1 ].querySelectorAll( '.sfcore-ai-sidebar__value--after' )
		).toHaveLength( 1 );
		expect( rows[ 1 ].textContent ).toContain( 'Third card' );
		expect( rows[ 1 ].textContent ).toContain(
			'Added as a new child block.'
		);

		// Row 3: a removal has no fields, only a note.
		expect(
			rows[ 2 ].querySelectorAll( '.sfcore-ai-sidebar__field' )
		).toHaveLength( 0 );
		expect( rows[ 2 ].textContent ).toContain( 'Remove block' );
		expect( rows[ 2 ].textContent ).toContain(
			'Removed from the selection.'
		);

		expect(
			screen.getByText( 'Tightened the hero and refreshed the grid.' )
		).toBeTruthy();
	} );

	it( 'ticks every row by default and reports the selected count', () => {
		const { container } = renderPreview();

		const checkboxes = container.querySelectorAll(
			'input[type="checkbox"]'
		);

		expect( checkboxes ).toHaveLength( 3 );
		checkboxes.forEach( ( checkbox ) =>
			expect( checkbox.checked ).toBe( true )
		);

		expect(
			screen.getByRole( 'button', { name: 'Apply selected (3)' } )
		).toBeTruthy();
	} );

	it( 'reports a row toggle by its action index', () => {
		const { container } = renderPreview( ACTIONS, [ 0, 1, 2 ] );

		fireEvent.click(
			container.querySelectorAll( 'input[type="checkbox"]' )[ 1 ]
		);

		expect( handlers.onToggle ).toHaveBeenCalledWith( 1 );
	} );

	it( 'disables Apply selected when nothing is ticked, and never Apply all', () => {
		renderPreview( ACTIONS, [] );

		const applySelected = screen.getByRole( 'button', {
			name: 'Apply selected (0)',
		} );

		expect( isDisabled( applySelected ) ).toBe( true );

		fireEvent.click( applySelected );

		expect( handlers.onApplySelected ).not.toHaveBeenCalled();

		fireEvent.click( screen.getByRole( 'button', { name: 'Apply all' } ) );

		expect( handlers.onApplyAll ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'applies only the ticked rows when asked to', () => {
		renderPreview( ACTIONS, [ 0, 2 ] );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Apply selected (2)' } )
		);

		expect( handlers.onApplySelected ).toHaveBeenCalledTimes( 1 );
		expect( handlers.onApplyAll ).not.toHaveBeenCalled();
	} );

	it( 'discards without applying anything', () => {
		renderPreview();

		fireEvent.click( screen.getByRole( 'button', { name: 'Discard' } ) );

		expect( handlers.onDiscard ).toHaveBeenCalledTimes( 1 );
		expect( handlers.onApplyAll ).not.toHaveBeenCalled();
		expect( handlers.onApplySelected ).not.toHaveBeenCalled();
	} );

	it( 'says so plainly when the AI proposed nothing', () => {
		renderPreview( [], [], '' );

		expect(
			screen.getByText( 'No changes were proposed. Nothing was changed.' )
		).toBeTruthy();
		expect( screen.queryByText( 'Apply all' ) ).toBeNull();

		fireEvent.click( screen.getByRole( 'button', { name: 'Back' } ) );

		expect( handlers.onDiscard ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'uses the singular for one change', () => {
		renderPreview( [ ACTIONS[ 2 ] ], [ 0 ], '' );

		expect( screen.getByText( '1 proposed change' ) ).toBeTruthy();
	} );
} );
