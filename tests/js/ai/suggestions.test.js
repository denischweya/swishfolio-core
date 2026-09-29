/**
 * Suggestion chips are derived from the manifest, never from block knowledge
 * hardcoded in the UI. A chip offering a change the block cannot make would
 * teach the user that AI editing is unreliable.
 */

import { getSuggestions } from '../../../src/js/ai/suggestions';
import { getCapability } from '../../../src/js/ai/capabilities';
import {
	BENTO_CARDS,
	HERO,
	PARAGRAPH,
	clearManifests,
	installBlockTypes,
	installManifests,
} from '../fixtures/manifests';
import * as wpBlocks from '../mocks/wp-blocks';

/**
 * Chip ids for a block. `limit` defaults high so a capability assertion is not
 * confused by the six-chip display cap, which has its own test.
 *
 * @param {string} blockName Full block name.
 * @param {number} limit	 Maximum chips.
 *
 * @return {string[]} Chip ids.
 */
const idsFor = ( blockName, limit = 20 ) =>
	getSuggestions( getCapability( blockName ), limit ).map(
		( suggestion ) => suggestion.id
	);

beforeEach( () => {
	installManifests();
	installBlockTypes();
} );

afterEach( () => {
	clearManifests();
	wpBlocks.__reset();
} );

describe( 'getSuggestions', () => {
	it( 'suggestion chips are filtered by block capability', () => {
		const hero = idsFor( 'swishfolio-core/hero' );
		const paragraph = idsFor( 'core/paragraph' );

		// The hero manifest has colour tokens and a layout enum, so contrast
		// and layout work is offered.
		expect( hero ).toContain( 'raise-contrast' );
		expect( hero ).toContain( 'warmer-palette' );
		expect( hero ).toContain( 'different-layout' );
		expect( hero ).toContain( 'more-compact' );
		expect( hero ).toContain( 'hide-extras' );
		expect( hero ).toContain( 'sharpen-cta' );
		expect( hero ).toContain( 'tighten-headline' );

		// core/paragraph is tier `content`: one richtext field, no colours, no
		// layout, no booleans, no children. Only copy work makes sense.
		expect( paragraph ).toEqual( [ 'professional-voice' ] );
		expect( paragraph ).not.toContain( 'raise-contrast' );
		expect( paragraph ).not.toContain( 'different-layout' );
		expect( paragraph ).not.toContain( 'hide-extras' );
		expect( paragraph ).not.toContain( 'add-child' );

		// shorten-copy needs more than one text field; the paragraph has one.
		expect( paragraph ).not.toContain( 'shorten-copy' );
		expect( hero ).toContain( 'shorten-copy' );
	} );

	it( 'offers a child chip only when the manifest allows children, named after the child block', () => {
		const parent = getSuggestions(
			getCapability( 'swishfolio-core/bento-cards' )
		).find( ( suggestion ) => suggestion.id === 'add-child' );

		expect( parent ).toBeDefined();
		expect( parent.label ).toBe( 'Add another Bento Card' );
		expect( parent.prompt ).toBe(
			'Add one more Bento Card that fits the ones already here.'
		);

		expect( idsFor( 'swishfolio-core/bento-card' ) ).not.toContain(
			'add-child'
		);
		expect( BENTO_CARDS.children.allowed ).toEqual( [
			'swishfolio-core/bento-card',
		] );
	} );

	it( 'offers nothing at all without a capability', () => {
		expect( getSuggestions( null ) ).toEqual( [] );
		expect( getSuggestions( getCapability( 'core/spacer' ) ) ).toEqual( [] );
	} );

	it( 'caps the list at six chips by default and gives every chip a label and a prompt', () => {
		// The hero manifest satisfies more predicates than the sidebar shows.
		expect( idsFor( 'swishfolio-core/hero' ).length ).toBeGreaterThan( 6 );
		expect(
			getSuggestions( getCapability( 'swishfolio-core/hero' ) )
		).toHaveLength( 6 );

		const suggestions = getSuggestions(
			getCapability( 'swishfolio-core/hero' ),
			3
		);

		expect( suggestions ).toHaveLength( 3 );

		suggestions.forEach( ( suggestion ) => {
			expect( typeof suggestion.label ).toBe( 'string' );
			expect( suggestion.label.length ).toBeGreaterThan( 0 );
			expect( typeof suggestion.prompt ).toBe( 'string' );
			expect( suggestion.prompt.length ).toBeGreaterThan( 0 );
		} );
	} );

	it( 'reads both localised payload shapes', () => {
		// HERO arrives as the raw grouped manifest, PARAGRAPH as the flattened
		// BlockCapability shape. Both must produce fields.
		expect( HERO.capabilities ).toBeDefined();
		expect( PARAGRAPH.fields ).toBeDefined();

		expect(
			Object.keys( getCapability( 'swishfolio-core/hero' ).fields )
		).toContain( 'backgroundColor' );
		expect(
			Object.keys( getCapability( 'core/paragraph' ).fields )
		).toEqual( [ 'content' ] );
	} );

	it( 'drops chips for a block whose manifest lost its colour tokens', () => {
		installManifests( {
			'swishfolio-core/hero': {
				...HERO,
				capabilities: {
					content: HERO.capabilities.content,
				},
			},
		} );

		const hero = idsFor( 'swishfolio-core/hero' );

		expect( hero ).not.toContain( 'raise-contrast' );
		expect( hero ).not.toContain( 'different-layout' );
		expect( hero ).toContain( 'tighten-headline' );
	} );
} );
