/**
 * The AI editing loop, end to end (design §12).
 *
 * Open the editor → select a block → Ask AI → review the preview → apply →
 * Ctrl+Z. Two tests, and the second is the one that matters.
 *
 * The first walks the loop on a hero and proves the pieces fit: the toolbar
 * button opens the sidebar, the request carries the real selection, the preview
 * describes the change without making it, applying makes it, and undo unmakes
 * it.
 *
 * The second exists because the first cannot fail the way batching fails. An
 * attribute-only change is a single `updateBlockAttributes()` dispatch, so it
 * lands in one undo step whether or not `registry.batch()` wrapped it —
 * `apply.js` could lose its batch entirely and the hero test would still pass.
 * So the second test proposes a change that cannot collapse into one dispatch:
 * attributes on a parent *and* a removal from it, which `executePlan()` must
 * issue as two. Without the batch those are two `onChange` notifications and
 * therefore two undo records, and the single Ctrl+Z asserted here would leave
 * the attribute change standing.
 *
 * Both tests answer from a stubbed provider (tests/e2e/utils/stub-provider.js).
 * No AI provider is contacted and no API key is required.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const {
	editBlockAttributes,
	ensureAiEditorLoaded,
	focusBlockInCanvas,
	getBlockAttributes,
	getChildClientIds,
	selectBlockByName,
} = require( '../utils/ai-editor' );
const { stubAiProvider } = require( '../utils/stub-provider' );

const HERO = 'swishfolio-core/hero';
const TESTIMONIALS = 'swishfolio-core/testimonials';
const TESTIMONIAL_CARD = 'swishfolio-core/testimonial-card';

const HERO_BEFORE = {
	heading: 'Original headline',
	subheading: 'Original subheading.',
	headingSize: 'large',
};

const HERO_AFTER = {
	heading: 'Design that ships',
	subheading: 'Portfolio sites that go live the same week.',
	headingSize: 'xlarge',
};

const HERO_SUMMARY = 'Tightened the headline and made it larger.';

test.describe( 'AI block editing', () => {
	test.beforeEach( async ( { admin, editor, page }, testInfo ) => {
		/*
		 * Fullscreen mode is not cosmetic here: this site's admin chrome is a
		 * fixed header that overlaps the block toolbar and swallows clicks on
		 * it. Fullscreen is how a real author gets it out of the way.
		 */
		await admin.createNewPost( { fullscreenMode: true } );

		// A fixed toolbar keeps "Ask AI" in the same place all run long.
		await editor.setIsFixedToolbar( true );

		const wiring = await ensureAiEditorLoaded( page );

		testInfo.annotations.push( {
			type: 'ai-editor-wiring',
			description: wiring.enqueuedByPlugin
				? 'enqueued by the plugin'
				: 'bundle injected by the test (PHP enqueue not present)',
		} );
	} );

	test( 'previews an AI edit, applies it, and reverses it with one undo', async ( {
		editor,
		page,
		pageUtils,
	} ) => {
		const stub = await stubAiProvider( page, ( request ) => ( {
			schemaVersion: '1.0',
			summary: HERO_SUMMARY,
			actions: [
				{
					type: 'update_block_attributes',
					clientId: request.selection.clientId,
					changes: { ...HERO_AFTER },
					reason: 'A shorter headline reads faster.',
				},
			],
		} ) );

		await editor.insertBlock( {
			name: HERO,
			attributes: { ...HERO_BEFORE },
		} );

		const heroClientId = await selectBlockByName( page, HERO );

		// --- Ask AI -------------------------------------------------------
		await editor.clickBlockToolbarButton( 'Ask AI' );

		const sidebar = page.locator( '.sfcore-ai-sidebar__body' );

		await expect( sidebar ).toBeVisible();
		await expect(
			sidebar.locator( '.sfcore-ai-sidebar__selection-block' )
		).toHaveText( 'Hero' );

		await sidebar
			.getByLabel( 'What should change?' )
			.fill( 'Make the headline punchier and larger' );

		await sidebar
			.getByRole( 'button', { name: 'Ask AI', exact: true } )
			.click();

		// --- The preview --------------------------------------------------
		await expect(
			sidebar.locator( '.sfcore-ai-sidebar__summary' )
		).toHaveText( HERO_SUMMARY );
		await expect(
			sidebar.locator( '.sfcore-ai-sidebar__count' )
		).toHaveText( '1 proposed change' );

		const headlineField = sidebar
			.locator( '.sfcore-ai-sidebar__field', { hasText: 'Headline' } )
			.first();

		await expect( headlineField ).toContainText( HERO_BEFORE.heading );
		await expect( headlineField ).toContainText( HERO_AFTER.heading );

		// The editor sent the selection it actually holds.
		expect( stub.calls ).toHaveLength( 1 );
		expect( stub.calls[ 0 ].selection.blockName ).toBe( HERO );
		expect( stub.calls[ 0 ].selection.clientId ).toBe( heroClientId );
		expect( stub.calls[ 0 ].selection.attributes.heading ).toBe(
			HERO_BEFORE.heading
		);
		expect( Object.keys( stub.calls[ 0 ].blockTree ) ).toContain(
			heroClientId
		);

		// Reviewing is not applying: the block is untouched at this point.
		expect( await getBlockAttributes( page, heroClientId ) ).toMatchObject(
			HERO_BEFORE
		);

		// --- Apply ---------------------------------------------------------
		await sidebar.getByRole( 'button', { name: 'Apply all' } ).click();

		await expect
			.poll( async () =>
				( await getBlockAttributes( page, heroClientId ) ).heading
			)
			.toBe( HERO_AFTER.heading );

		expect( await getBlockAttributes( page, heroClientId ) ).toMatchObject(
			HERO_AFTER
		);

		const snackbar = page.locator( '.components-snackbar__content' );

		await expect( snackbar ).toContainText( 'Applied 1 AI change.' );
		// The wording the hook uses when `registry.batch()` was unavailable.
		await expect( snackbar ).not.toContainText( 'more than one step' );

		// --- One undo ------------------------------------------------------
		await focusBlockInCanvas( editor, heroClientId );
		await pageUtils.pressKeys( 'primary+z' );

		await expect
			.poll( async () =>
				( await getBlockAttributes( page, heroClientId ) ).heading
			)
			.toBe( HERO_BEFORE.heading );

		expect( await getBlockAttributes( page, heroClientId ) ).toMatchObject(
			HERO_BEFORE
		);
	} );

	test( 'reverses a multi-dispatch AI change in a single undo step', async ( {
		editor,
		page,
		pageUtils,
	} ) => {
		const quoteBefore = [
			'They shipped the site in four days.',
			'The rebuild paid for itself in a month.',
			'Best money we spent all year.',
		];

		const rewrittenQuote = 'They shipped the whole site in four days flat.';
		const handEditedName = 'Edited by hand';

		await editor.insertBlock( {
			name: TESTIMONIALS,
			attributes: { columns: 3, cardShadow: 'none' },
			innerBlocks: quoteBefore.map( ( quote, index ) => ( {
				name: TESTIMONIAL_CARD,
				attributes: {
					quote,
					name: 'Author ' + ( index + 1 ),
					role: 'Role ' + ( index + 1 ),
				},
			} ) ),
		} );

		const parentClientId = await selectBlockByName( page, TESTIMONIALS );
		const cardsBefore = await getChildClientIds( page, parentClientId );

		expect( cardsBefore ).toHaveLength( 3 );

		/*
		 * One ordinary edit before the AI change, so the single undo below can
		 * be shown to have stopped at the AI change rather than run past it.
		 * It is on a card the AI does not touch, so nothing can coalesce it
		 * into the AI change's own undo record.
		 */
		await editBlockAttributes( page, cardsBefore[ 1 ], {
			name: handEditedName,
		} );

		const stub = await stubAiProvider( page, ( request ) => ( {
			schemaVersion: '1.0',
			summary: 'Two columns, a stronger lead quote, one card fewer.',
			actions: [
				{
					type: 'update_block_attributes',
					clientId: request.selection.clientId,
					changes: { columns: 2, cardShadow: 'large' },
					reason: 'Two columns suit three short quotes better.',
				},
				{
					type: 'update_block_content',
					clientId: cardsBefore[ 0 ],
					content: rewrittenQuote,
					reason: 'Leads with the concrete result.',
				},
				{
					type: 'remove_block',
					clientId: cardsBefore[ 2 ],
					reason: 'The weakest of the three.',
				},
			],
		} ) );

		await editor.clickBlockToolbarButton( 'Ask AI' );

		const sidebar = page.locator( '.sfcore-ai-sidebar__body' );

		await sidebar
			.getByLabel( 'What should change?' )
			.fill( 'Two columns, drop the weakest quote' );
		await sidebar
			.getByRole( 'button', { name: 'Ask AI', exact: true } )
			.click();

		await expect(
			sidebar.locator( '.sfcore-ai-sidebar__count' )
		).toHaveText( '3 proposed changes' );

		expect( stub.calls ).toHaveLength( 1 );
		// Every target the response names was in the tree the client sent.
		expect( Object.keys( stub.calls[ 0 ].blockTree ) ).toEqual(
			expect.arrayContaining( [ parentClientId, ...cardsBefore ] )
		);

		await sidebar.getByRole( 'button', { name: 'Apply all' } ).click();

		// --- Applied: two dispatches' worth of change ------------------------
		await expect
			.poll( async () =>
				( await getChildClientIds( page, parentClientId ) ).length
			)
			.toBe( 2 );

		expect(
			await getBlockAttributes( page, parentClientId )
		).toMatchObject( { columns: 2, cardShadow: 'large' } );
		expect(
			( await getBlockAttributes( page, cardsBefore[ 0 ] ) ).quote
		).toBe( rewrittenQuote );

		const snackbar = page.locator( '.components-snackbar__content' );

		await expect( snackbar ).toContainText( 'Applied 3 AI changes.' );
		await expect( snackbar ).not.toContainText( 'more than one step' );

		// --- One undo puts all of it back ------------------------------------
		await focusBlockInCanvas( editor, parentClientId );
		await pageUtils.pressKeys( 'primary+z' );

		await expect
			.poll( async () =>
				( await getChildClientIds( page, parentClientId ) ).length
			)
			.toBe( 3 );

		// The dispatch that ran *first* must be back too. If the batch is lost
		// this is where it shows: the removal would be undone on its own and
		// these attributes would still read 2 / 'large'.
		expect(
			await getBlockAttributes( page, parentClientId )
		).toMatchObject( { columns: 3, cardShadow: 'none' } );

		const cardsAfterUndo = await getChildClientIds( page, parentClientId );

		expect(
			( await getBlockAttributes( page, cardsAfterUndo[ 0 ] ) ).quote
		).toBe( quoteBefore[ 0 ] );

		// ...and the undo stopped there, rather than swallowing the edit the
		// user made before asking.
		expect(
			( await getBlockAttributes( page, cardsAfterUndo[ 1 ] ) ).name
		).toBe( handEditedName );
	} );
} );
