/**
 * Reading the manifests PHP localises. This module never invents a field, and
 * it must agree with PHP about which attribute a content rewrite targets.
 */

import {
	countFields,
	getCapability,
	getCapabilityMap,
	getContentAttribute,
	getField,
	getFieldList,
	getMaxActions,
	hasCapability,
	isAvailable,
} from '../../../src/js/ai/capabilities';
import { DEFAULT_MAX_ACTIONS } from '../../../src/js/ai/constants';
import {
	BENTO_CARD,
	HERO,
	clearManifests,
	installManifests,
} from '../fixtures/manifests';

afterEach( () => {
	clearManifests();
} );

describe( 'getCapability', () => {
	it( 'returns null for a block with no manifest', () => {
		installManifests();

		expect( getCapability( 'core/spacer' ) ).toBeNull();
		expect( getCapability( '' ) ).toBeNull();
		expect( getCapability( undefined ) ).toBeNull();
		expect( hasCapability( 'swishfolio-core/hero' ) ).toBe( true );
		expect( hasCapability( 'core/spacer' ) ).toBe( false );
	} );

	it( 'preserves manifest field order across groups', () => {
		installManifests( { 'swishfolio-core/bento-card': BENTO_CARD } );

		expect(
			getFieldList( getCapability( 'swishfolio-core/bento-card' ) ).map(
				( field ) => field.name
			)
		).toEqual( [ 'subtitle', 'title', 'description', 'titleColor' ] );
	} );

	it( 'tags each field with its group and role', () => {
		installManifests( { 'swishfolio-core/hero': HERO } );

		const capability = getCapability( 'swishfolio-core/hero' );

		expect( getField( capability, 'heading' ) ).toEqual( {
			name: 'heading',
			type: 'text',
			group: 'content',
			role: 'headline',
			enum: [],
			tokenSet: null,
			min: null,
			max: null,
			maxLength: 120,
			unit: null,
		} );
		expect( getField( capability, 'layout' ).type ).toBe( 'enum' );
		expect( getField( capability, 'layout' ).enum ).toEqual( [
			'full',
			'split-right',
			'split-left',
		] );
		expect( getField( capability, 'backgroundColor' ).tokenSet ).toBe(
			'color'
		);
		expect( getField( capability, 'heroImage' ) ).toBeNull();
		expect( countFields( capability ) ).toBe( 10 );
	} );

	it( 'drops a field it cannot trust rather than guessing', () => {
		installManifests( {
			'test/block': {
				tier: 'full',
				capabilities: {
					style: {
						mystery: { type: 'colour' },
						emptyEnum: { enum: [] },
						tokenlessToken: { type: 'token' },
						notAnObject: 'nope',
						good: { type: 'text' },
					},
				},
			},
		} );

		expect(
			Object.keys( getCapability( 'test/block' ).fields )
		).toEqual( [ 'good' ] );
	} );

	it( 'infers an enum type from the values, the way the manifests are authored', () => {
		installManifests( {
			'test/block': {
				capabilities: { layout: { align: { enum: [ 'left', 'right' ] } } },
			},
		} );

		expect( getField( getCapability( 'test/block' ), 'align' ).type ).toBe(
			'enum'
		);
	} );

	it( 'defaults an unrecognised tier to full', () => {
		installManifests( {
			'test/block': { tier: 'partial', capabilities: {} },
		} );

		expect( getCapability( 'test/block' ).tier ).toBe( 'full' );
	} );
} );

describe( 'getContentAttribute', () => {
	it( 'picks the first richtext field, mirroring AllowlistStage and ValueStage', () => {
		installManifests();

		// bento-card lists subtitle (the eyebrow) before title, so that is what
		// PHP writes — the client must not diverge and pick `title` because it
		// reads better.
		expect(
			getContentAttribute( getCapability( 'swishfolio-core/bento-card' ) )
		).toBe( 'subtitle' );

		expect(
			getContentAttribute( getCapability( 'core/paragraph' ) )
		).toBe( 'content' );
	} );

	it( 'returns null for a block with no text content at all', () => {
		installManifests();

		// The hero's copy fields are `text`, not `richtext`.
		expect(
			getContentAttribute( getCapability( 'swishfolio-core/hero' ) )
		).toBeNull();
		expect( getContentAttribute( null ) ).toBeNull();
	} );
} );

describe( 'isAvailable', () => {
	it( 'is false when PHP localised nothing usable', () => {
		expect( isAvailable() ).toBe( false );

		installManifests( {} );

		expect( isAvailable() ).toBe( false );
		expect( getCapabilityMap() ).toEqual( {} );

		installManifests();

		expect( isAvailable() ).toBe( true );
	} );
} );

describe( 'getMaxActions', () => {
	it( 'falls back to the schema default when the payload is missing or nonsense', () => {
		expect( getMaxActions() ).toBe( DEFAULT_MAX_ACTIONS );

		installManifests( undefined, { maxActions: 'many' } );

		expect( getMaxActions() ).toBe( DEFAULT_MAX_ACTIONS );

		installManifests( undefined, { maxActions: 10 } );

		expect( getMaxActions() ).toBe( 10 );
	} );
} );
