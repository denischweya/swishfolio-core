/**
 * The request payload: what ContextAssembler::forSelection and
 * AIController::normaliseBlockTree actually read, and nothing more.
 */

import {
	buildSelectionContext,
	detectStyleVariation,
} from '../../../src/js/ai/selection-context';
import { createEditor, samplePage } from '../fixtures/editor';
import * as wpData from '../mocks/wp-data';

/**
 * The keys ContextAssembler reads off `selection`: `clientId`, `blockName` and
 * `attributes` in buildUserPrompt(), `postType`, `styleVariation`, `parent`,
 * `siblings` and `innerBlocks` in describeSurroundings().
 */
const SELECTION_KEYS = [
	'attributes',
	'blockName',
	'clientId',
	'innerBlocks',
	'parent',
	'postType',
	'siblings',
	'styleVariation',
];

/** The keys normaliseBlockTree() reads off each block-tree entry. */
const TREE_ENTRY_KEYS = [ 'attributes', 'name', 'parentClientId' ];

afterEach( () => {
	wpData.__reset();
} );

describe( 'buildSelectionContext', () => {
	it( 'selection context builder output shape matches what ContextAssembler expects', () => {
		const editor = createEditor( {
			blocks: samplePage(),
			selectedClientId: 'bento-1',
		} );

		const context = buildSelectionContext(
			editor.selectors,
			'bento-1',
			{ postType: 'sfcore_project', styleVariation: 'brutalist' }
		);

		expect( Object.keys( context ).sort() ).toEqual( [
			'blockTree',
			'selection',
		] );

		const { selection, blockTree } = context;

		expect( Object.keys( selection ).sort() ).toEqual( SELECTION_KEYS );
		expect( selection.clientId ).toBe( 'bento-1' );
		expect( selection.blockName ).toBe( 'swishfolio-core/bento-cards' );
		expect( selection.attributes ).toEqual( { accentColor: '' } );
		expect( selection.postType ).toBe( 'sfcore_project' );
		expect( selection.styleVariation ).toBe( 'brutalist' );

		// A top-level block has no parent, which describeSurroundings reads as
		// `empty( $selection['parent'] )`.
		expect( selection.parent ).toBeNull();

		expect( selection.innerBlocks ).toEqual( [
			{ clientId: 'card-1', blockName: 'swishfolio-core/bento-card' },
			{ clientId: 'card-2', blockName: 'swishfolio-core/bento-card' },
		] );

		expect( selection.siblings ).toEqual( [
			{ clientId: 'hero-1', blockName: 'swishfolio-core/hero' },
			{ clientId: 'para-1', blockName: 'core/paragraph' },
		] );

		// The tree is the selection subtree: the allowlist an action may target.
		expect( Object.keys( blockTree ).sort() ).toEqual( [
			'bento-1',
			'card-1',
			'card-2',
		] );

		Object.keys( blockTree ).forEach( ( clientId ) => {
			expect( Object.keys( blockTree[ clientId ] ).sort() ).toEqual(
				TREE_ENTRY_KEYS
			);
		} );

		expect( blockTree[ 'bento-1' ].name ).toBe(
			'swishfolio-core/bento-cards'
		);
		expect( blockTree[ 'bento-1' ].parentClientId ).toBeNull();
		expect( blockTree[ 'card-1' ].parentClientId ).toBe( 'bento-1' );
		expect( blockTree[ 'card-1' ].attributes.title ).toBe( 'First card' );
	} );

	it( 'sibling attributes are NEVER included in the payload', () => {
		const editor = createEditor( {
			blocks: samplePage(),
			selectedClientId: 'card-1',
		} );

		const context = buildSelectionContext( editor.selectors, 'card-1', {
			postType: 'page',
			styleVariation: 'default',
		} );

		const payload = {
			postId: 41,
			instruction: 'Tighten the copy.',
			selection: context.selection,
			blockTree: context.blockTree,
		};
		const serialised = JSON.stringify( payload );

		// card-2 is a sibling of the selection. Its clientId is disclosed (the
		// AI is told what is next to the selection); its attribute values are
		// not, and it is not in the tree, so an action targeting it is refused.
		expect( context.selection.siblings ).toEqual( [
			{ clientId: 'card-2', blockName: 'swishfolio-core/bento-card' },
		] );
		expect( context.selection.siblings[ 0 ] ).not.toHaveProperty(
			'attributes'
		);
		expect( Object.keys( context.blockTree ) ).not.toContain( 'card-2' );

		expect( serialised ).not.toContain( 'MODULE 02' );
		expect( serialised ).not.toContain( 'Second card' );
		expect( serialised ).not.toContain( 'Card two description.' );

		// Same guarantee one level up: the hero and the trailing paragraph are
		// siblings of the bento grid.
		const topLevel = buildSelectionContext(
			editor.selectors,
			'bento-1',
			{}
		);

		topLevel.selection.siblings.forEach( ( sibling ) => {
			expect( Object.keys( sibling ).sort() ).toEqual( [
				'blockName',
				'clientId',
			] );
		} );

		const topLevelSerialised = JSON.stringify( topLevel );

		expect( topLevelSerialised ).not.toContain(
			'Build a portfolio that lands the work'
		);
		expect( topLevelSerialised ).not.toContain( 'A trailing paragraph.' );
	} );

	it( 'includes ancestors without their attributes, so a parent can be targeted but not read', () => {
		const editor = createEditor( {
			blocks: samplePage(),
			selectedClientId: 'card-1',
		} );

		const { blockTree } = buildSelectionContext(
			editor.selectors,
			'card-1',
			{}
		);

		expect( Object.keys( blockTree ).sort() ).toEqual( [
			'bento-1',
			'card-1',
		] );
		expect( blockTree[ 'bento-1' ].attributes ).toEqual( {} );
		expect( blockTree[ 'card-1' ].attributes ).not.toEqual( {} );
	} );

	it( 'defaults postType and styleVariation the way the server does', () => {
		const editor = createEditor( {
			blocks: samplePage(),
			selectedClientId: 'hero-1',
		} );

		const { selection } = buildSelectionContext(
			editor.selectors,
			'hero-1',
			null
		);

		expect( selection.postType ).toBe( 'page' );
		expect( selection.styleVariation ).toBe( 'default' );
	} );

	it( 'returns null when the selected block is gone', () => {
		const editor = createEditor( {
			blocks: samplePage(),
			selectedClientId: 'hero-1',
		} );

		expect(
			buildSelectionContext( editor.selectors, 'deleted-1', {} )
		).toBeNull();
		expect( buildSelectionContext( editor.selectors, null, {} ) ).toBeNull();
	} );
} );

describe( 'detectStyleVariation', () => {
	it( 'reads the slug the theme embeds as --style-mode', () => {
		expect(
			detectStyleVariation( [
				{ css: 'body{margin:0}' },
				{ css: ':root{--style-mode: brutalist;}' },
			] )
		).toBe( 'brutalist' );
	} );

	it( 'falls back to default when no variation is active', () => {
		expect( detectStyleVariation( [ { css: 'body{margin:0}' } ] ) ).toBe(
			'default'
		);
		expect( detectStyleVariation( undefined ) ).toBe( 'default' );
	} );
} );
