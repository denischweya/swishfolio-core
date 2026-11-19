/**
 * Labels. Manifests carry machine names; the preview has to read as English.
 */

import {
	blockLabel,
	emptyValueLabel,
	fieldLabel,
	formatValue,
	humanise,
} from '../../../src/js/ai/labels';
import { installBlockTypes } from '../fixtures/manifests';
import * as wpBlocks from '../mocks/wp-blocks';

afterEach( () => {
	wpBlocks.__reset();
} );

describe( 'humanise', () => {
	it( 'splits camelCase and keeps known acronyms upper-case', () => {
		expect( humanise( 'backgroundColor' ) ).toBe( 'Background color' );
		expect( humanise( 'minHeight' ) ).toBe( 'Min height' );
		expect( humanise( 'ctaText' ) ).toBe( 'CTA text' );
		expect( humanise( 'ctaUrl' ) ).toBe( 'CTA URL' );
		expect( humanise( 'swish-cv' ) ).toBe( 'Swish CV' );
		expect( humanise( 'faq' ) ).toBe( 'FAQ' );
		expect( humanise( '' ) ).toBe( '' );
		expect( humanise( undefined ) ).toBe( '' );
	} );
} );

describe( 'blockLabel', () => {
	it( 'uses the registered block title when the block is registered', () => {
		installBlockTypes();

		expect( blockLabel( 'swishfolio-core/bento-card' ) ).toBe(
			'Bento Card'
		);
	} );

	it( 'humanises the slug when the block type is unknown', () => {
		expect( blockLabel( 'swishfolio-core/swish-counter-item' ) ).toBe(
			'Swish counter item'
		);
		expect( blockLabel( '' ) ).toBe( 'Block' );
	} );
} );

describe( 'fieldLabel', () => {
	it( 'prefers the manifest role over the attribute name', () => {
		expect( fieldLabel( 'subtitle', { role: 'eyebrow' } ) ).toBe(
			'Eyebrow'
		);
		expect( fieldLabel( 'heading', { role: 'headline' } ) ).toBe(
			'Headline'
		);
		expect( fieldLabel( 'ctaText', { role: 'primary-cta' } ) ).toBe(
			'Primary button'
		);
		expect( fieldLabel( 'overlayOpacity', { role: 'made-up' } ) ).toBe(
			'Overlay opacity'
		);
		expect( fieldLabel( 'overlayOpacity', null ) ).toBe(
			'Overlay opacity'
		);
	} );
} );

describe( 'formatValue', () => {
	it( 'marks an absent value rather than rendering an empty gap', () => {
		[ undefined, null, '', '   ', '<em></em>' ].forEach( ( value ) =>
			expect( formatValue( value, null ) ).toBe( emptyValueLabel() )
		);

		expect( emptyValueLabel() ).toBe( 'not set' );
	} );

	it( 'renders booleans as words', () => {
		expect( formatValue( true, null ) ).toBe( 'Yes' );
		expect( formatValue( false, null ) ).toBe( 'No' );
	} );

	it( 'appends a real unit and suppresses a bookkeeping one', () => {
		expect( formatValue( 480, { unit: 'px' } ) ).toBe( '480px' );
		expect( formatValue( 50, { unit: '%' } ) ).toBe( '50%' );
		expect( formatValue( 3, { unit: 'count' } ) ).toBe( '3' );
		expect( formatValue( 2, { unit: 'level' } ) ).toBe( '2' );
		expect( formatValue( 600, null ) ).toBe( '600' );
		expect( formatValue( 0, { unit: 'px' } ) ).toBe( '0px' );
	} );

	it( 'strips markup and decodes entities out of rich text', () => {
		expect(
			formatValue( '<strong>Bold</strong> &amp; brave', null )
		).toBe( 'Bold & brave' );
		expect( formatValue( 'Tight&nbsp;copy', null ) ).toBe( 'Tight copy' );
		expect( formatValue( '&lt;script&gt;', null ) ).toBe( '<script>' );
	} );

	it( 'truncates a long value to a reviewable length', () => {
		const formatted = formatValue( 'x'.repeat( 400 ), null );

		expect( formatted ).toHaveLength( 140 );
		expect( formatted.endsWith( '…' ) ).toBe( true );
	} );

	it( 'falls back to JSON for a structured value', () => {
		expect( formatValue( { url: 'https://example.com' }, null ) ).toBe(
			'{"url":"https://example.com"}'
		);
	} );
} );
