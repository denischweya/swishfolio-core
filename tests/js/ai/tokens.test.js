/**
 * Design tokens. PHP's ValueStage expands a slug into the custom property the
 * block stores; the preview has to walk that back and paint a real swatch.
 */

import {
	createColourResolver,
	isLiteralColour,
	slugToStorageValue,
	storageValueToSlug,
	tokenSlugFor,
} from '../../../src/js/ai/tokens';
import { sampleSettings } from '../fixtures/editor';

describe( 'slug and storage value', () => {
	it( 'round-trips every token set TokenResolver knows about', () => {
		expect( slugToStorageValue( 'color', 'accent-2' ) ).toBe(
			'var(--wp--preset--color--accent-2)'
		);
		expect( slugToStorageValue( 'spacing', '40' ) ).toBe(
			'var(--wp--preset--spacing--40)'
		);
		expect( slugToStorageValue( 'fontSize', 'x-large' ) ).toBe(
			'var(--wp--preset--font-size--x-large)'
		);

		[
			[ 'color', 'accent-2' ],
			[ 'spacing', '40' ],
			[ 'fontSize', 'x-large' ],
		].forEach( ( [ set, slug ] ) => {
			expect(
				storageValueToSlug( set, slugToStorageValue( set, slug ) )
			).toBe( slug );
		} );
	} );

	it( 'refuses to read a slug out of anything that is not a token reference', () => {
		expect( storageValueToSlug( 'color', '#FFD93D' ) ).toBeNull();
		expect( storageValueToSlug( 'color', 'accent-2' ) ).toBeNull();
		expect(
			storageValueToSlug( 'color', 'var(--wp--preset--spacing--40)' )
		).toBeNull();
		expect( storageValueToSlug( 'nonsense', 'x' ) ).toBeNull();
		expect( storageValueToSlug( 'color', 42 ) ).toBeNull();
	} );

	it( 'recognises a paintable literal colour', () => {
		[
			'#fff',
			'#FFD93D',
			'#ffd93dcc',
			'rgb(1,2,3)',
			'rgba(1,2,3,.5)',
			'  hsl(1 2% 3%)',
			'color-mix(in srgb, red, blue)',
			'oklch(0.7 0.1 200)',
		].forEach( ( value ) =>
			expect( isLiteralColour( value ) ).toBe( true )
		);

		[ 'accent-2', 'var(--wp--preset--color--accent-2)', '', 4, null ].forEach(
			( value ) => expect( isLiteralColour( value ) ).toBe( false )
		);
	} );

	it( 'only reads a slug for a token field of the matching set', () => {
		const colourField = { type: 'token', tokenSet: 'color' };

		expect(
			tokenSlugFor( colourField, 'var(--wp--preset--color--accent-2)' )
		).toBe( 'accent-2' );
		expect(
			tokenSlugFor(
				{ type: 'text' },
				'var(--wp--preset--color--accent-2)'
			)
		).toBeNull();
		expect( tokenSlugFor( null, 'x' ) ).toBeNull();
	} );
} );

describe( 'createColourResolver', () => {
	it( 'resolves a token reference to a swatch from the editor palette', () => {
		const resolve = createColourResolver( sampleSettings() );

		expect( resolve( 'var(--wp--preset--color--accent-1)' ) ).toEqual( {
			css: '#FFD93D',
			label: 'Accent 1',
			slug: 'accent-1',
		} );
	} );

	it( 'labels an unknown slug without inventing a colour for it', () => {
		const resolve = createColourResolver( sampleSettings() );

		expect( resolve( 'var(--wp--preset--color--accent-9)' ) ).toEqual( {
			css: null,
			label: 'accent-9',
			slug: 'accent-9',
		} );
	} );

	it( 'lets a later origin win, matching TokenResolver::ORIGINS', () => {
		const resolve = createColourResolver( {
			__experimentalFeatures: {
				color: {
					palette: {
						default: [
							{ slug: 'accent-1', color: '#000000', name: 'Core' },
						],
						theme: [
							{ slug: 'accent-1', color: '#FFD93D', name: 'Theme' },
						],
						custom: [
							{
								slug: 'accent-1',
								color: '#123456',
								name: 'Custom',
							},
						],
					},
				},
			},
		} );

		expect( resolve( 'var(--wp--preset--color--accent-1)' ).css ).toBe(
			'#123456'
		);
		expect( resolve( 'var(--wp--preset--color--accent-1)' ).label ).toBe(
			'Custom'
		);
	} );

	it( 'reads the flat colors list too, which is what most settings carry', () => {
		const resolve = createColourResolver( {
			colors: [ { slug: 'brand', color: '#abcdef', name: 'Brand' } ],
		} );

		expect( resolve( 'var(--wp--preset--color--brand)' ).css ).toBe(
			'#abcdef'
		);
	} );

	it( 'passes a literal colour through and refuses anything else', () => {
		const resolve = createColourResolver( sampleSettings() );

		expect( resolve( '	#FFD93D ' ) ).toEqual( {
			css: '#FFD93D',
			label: '#FFD93D',
			slug: null,
		} );
		expect( resolve( 'large' ) ).toBeNull();
		expect( resolve( '' ) ).toBeNull();
		expect( resolve( 600 ) ).toBeNull();
	} );
} );
