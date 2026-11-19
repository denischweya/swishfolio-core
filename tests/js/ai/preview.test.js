/**
 * Preview rows: one per action, with the value the block holds now beside the
 * value the AI proposes.
 */

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
import * as wpBlocks from '../mocks/wp-blocks';
import * as wpData from '../mocks/wp-data';

let editor;
let deps;

beforeEach( () => {
	installManifests();
	installBlockTypes();

	editor = createEditor( {
		blocks: samplePage(),
		selectedClientId: 'hero-1',
		settings: sampleSettings(),
	} );

	deps = {
		getBlockName: editor.selectors.getBlockName,
		getBlockAttributes: editor.selectors.getBlockAttributes,
		getCapability,
		resolveColour: createColourResolver( sampleSettings() ),
	};
} );

afterEach( () => {
	clearManifests();
	wpBlocks.__reset();
	wpData.__reset();
} );

describe( 'buildActionRows', () => {
	it( 'pairs the current value with the proposed value for every field', () => {
		const rows = buildActionRows(
			[
				{
					type: 'update_block_attributes',
					clientId: 'hero-1',
					changes: {
						heading: 'Portfolios that get you hired',
						minHeight: 480,
					},
					reason: 'Shorter headline reads faster.',
				},
			],
			deps
		);

		expect( rows ).toHaveLength( 1 );

		const row = rows[ 0 ];

		expect( row.index ).toBe( 0 );
		expect( row.typeLabel ).toBe( 'Update' );
		expect( row.clientId ).toBe( 'hero-1' );
		expect( row.blockLabel ).toBe( 'Hero' );
		expect( row.reason ).toBe( 'Shorter headline reads faster.' );

		const byAttribute = {};

		row.fields.forEach( ( field ) => {
			byAttribute[ field.attribute ] = field;
		} );

		// `heading` carries role `headline`, so the role label wins over the
		// humanised attribute name.
		expect( byAttribute.heading.label ).toBe( 'Headline' );
		expect( byAttribute.heading.before.text ).toBe(
			'Build a portfolio that lands the work'
		);
		expect( byAttribute.heading.after.text ).toBe(
			'Portfolios that get you hired'
		);

		// A number field renders its unit when the manifest supplies one.
		expect( byAttribute.minHeight.label ).toBe( 'Min height' );
		expect( byAttribute.minHeight.before.text ).toBe( '600px' );
		expect( byAttribute.minHeight.after.text ).toBe( '480px' );
	} );

	it( 'shows colour tokens as a slug and a resolved swatch, never as a custom property', () => {
		const rows = buildActionRows(
			[
				{
					type: 'update_block_attributes',
					clientId: 'hero-1',
					changes: {
						backgroundColor: 'var(--wp--preset--color--accent-2)',
					},
				},
			],
			deps
		);

		const field = rows[ 0 ].fields[ 0 ];

		expect( field.before.text ).toBe( 'accent-1' );
		expect( field.after.text ).toBe( 'accent-2' );
		expect( field.before.colour ).toEqual( {
			css: '#FFD93D',
			label: 'Accent 1',
			slug: 'accent-1',
		} );
		expect( field.after.colour ).toEqual( {
			css: '#4ECDC4',
			label: 'Accent 2',
			slug: 'accent-2',
		} );
		expect( field.after.text ).not.toContain( 'var(' );
	} );

	it( 'targets the first richtext field for a content rewrite, mirroring AllowlistStage', () => {
		const rows = buildActionRows(
			[
				{
					type: 'update_block_content',
					clientId: 'card-1',
					content: 'Rewritten eyebrow',
				},
			],
			deps
		);

		expect( rows[ 0 ].typeLabel ).toBe( 'Rewrite' );

		// bento-card's manifest lists `subtitle` first, so that is the field
		// PHP writes — and therefore the field the preview must show.
		expect( rows[ 0 ].fields ).toHaveLength( 1 );
		expect( rows[ 0 ].fields[ 0 ].attribute ).toBe( 'subtitle' );
		expect( rows[ 0 ].fields[ 0 ].label ).toBe( 'Eyebrow' );
		expect( rows[ 0 ].fields[ 0 ].before.text ).toBe( 'MODULE 01' );
		expect( rows[ 0 ].fields[ 0 ].after.text ).toBe( 'Rewritten eyebrow' );
	} );

	it( 'notes a block with no editable text instead of inventing a field', () => {
		const rows = buildActionRows(
			[
				{
					type: 'update_block_content',
					clientId: 'hero-1',
					content: 'Nowhere to put this',
				},
			],
			deps
		);

		expect( rows[ 0 ].fields ).toEqual( [] );
		expect( rows[ 0 ].note ).toBe(
			'This block has no editable text content.'
		);
	} );

	it( 'describes an insertion with no before side, labelled by its parent', () => {
		const rows = buildActionRows(
			[
				{
					type: 'insert_block',
					parentClientId: 'bento-1',
					blockName: 'swishfolio-core/bento-card',
					index: 2,
					attributes: { title: 'Third card' },
				},
			],
			deps
		);

		const row = rows[ 0 ];

		expect( row.typeLabel ).toBe( 'Add block' );
		expect( row.blockLabel ).toBe( 'Bento Card' );
		expect( row.parentLabel ).toBe( 'Bento Cards' );
		expect( row.note ).toBe( 'Added as a new child block.' );
		expect( row.fields ).toHaveLength( 1 );
		expect( row.fields[ 0 ].before ).toBeNull();
		expect( row.fields[ 0 ].after.text ).toBe( 'Third card' );
	} );

	it( 'describes a removal', () => {
		const rows = buildActionRows(
			[ { type: 'remove_block', clientId: 'card-2' } ],
			deps
		);

		expect( rows[ 0 ].typeLabel ).toBe( 'Remove block' );
		expect( rows[ 0 ].blockLabel ).toBe( 'Bento Card' );
		expect( rows[ 0 ].note ).toBe( 'Removed from the selection.' );
		expect( rows[ 0 ].fields ).toEqual( [] );
	} );

	it( 'keeps one row per action, in the order the response listed them', () => {
		const rows = buildActionRows(
			[
				{
					type: 'update_block_attributes',
					clientId: 'hero-1',
					changes: { heading: 'One' },
				},
				{ type: 'remove_block', clientId: 'card-2' },
				{
					type: 'update_block_content',
					clientId: 'card-1',
					content: 'Two',
				},
			],
			deps
		);

		expect( rows.map( ( row ) => row.index ) ).toEqual( [ 0, 1, 2 ] );
		expect( rows.map( ( row ) => row.type ) ).toEqual( [
			'update_block_attributes',
			'remove_block',
			'update_block_content',
		] );
	} );
} );
