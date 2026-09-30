/**
 * Does the AI editor UI mount inside Gutenberg?
 *
 * The recon agent verified which @wordpress/* APIs exist by reading the dist
 * files; this proves the bundle actually loads and mounts against them. No AI
 * provider is called, so none of this needs API credit.
 */

const { test, expect } = require( '@playwright/test' );

/**
 * Console noise that predates the AI work and is NOT caused by it.
 *
 * Listing these explicitly is the point: a bare "ignore console errors" would
 * hide the next real regression. Anything not matching one of these fails.
 *
 *  - The text-color format claims bare <span> with className null, which is a
 *    greedy claim another format then collides with. Fixing it means changing
 *    the className, which changes how existing saved content parses — a content
 *    migration, not a one-line edit.
 *  - Two blocks in this site's existing content no longer match their save()
 *    output, so Gutenberg reports invalid content on load.
 */
const KNOWN_PRE_EXISTING = [
	/already registered to handle bare tag name/i,
	/Block validation: Block validation failed/i,
];

/**
 * @param {string[]} messages Collected console errors.
 *
 * @return {string[]} Only the messages that are not known pre-existing noise.
 */
function unexpected( messages ) {
	return messages.filter(
		( message ) => ! KNOWN_PRE_EXISTING.some( ( pattern ) => pattern.test( message ) )
	);
}

/**
 * Open a fresh page in the block editor with the welcome guide out of the way.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 */
async function openEditor( page ) {
	await page.goto( '/wp-admin/post-new.php?post_type=page' );

	// The welcome modal steals focus and blocks every later click.
	const modalClose = page.locator( '.components-modal__header button[aria-label="Close"]' );
	if ( await modalClose.isVisible().catch( () => false ) ) {
		await modalClose.click();
	}

	await expect( page.locator( 'iframe[name="editor-canvas"], .block-editor-writing-flow' ).first() )
		.toBeVisible( { timeout: 60_000 } );
}

/** The canvas is iframed in WP 7.0; blocks live inside it. */
function canvas( page ) {
	return page.frameLocator( 'iframe[name="editor-canvas"]' );
}

test.describe( 'AI editor UI', () => {
	test( 'the bundle loads with no console errors and exposes its capability payload', async ( { page } ) => {
		const consoleErrors = [];
		const pageErrors = [];
		page.on( 'console', ( m ) => m.type() === 'error' && consoleErrors.push( m.text() ) );
		page.on( 'pageerror', ( e ) => pageErrors.push( e.message ) );

		await openEditor( page );

		// Localised before the bundle: without it the UI cannot know any block.
		const payload = await page.evaluate( () => {
			const data = window.sfcoreAiEditor;
			if ( ! data ) {
				return null;
			}
			return {
				blocks: Object.keys( data.capabilities || {} ).length,
				maxActions: data.maxActions,
				heroFields: Object.keys(
					( data.capabilities[ 'swishfolio-core/hero' ] || {} ).fields || {}
				).length,
			};
		} );

		expect( payload, 'window.sfcoreAiEditor was never set' ).not.toBeNull();
		expect( payload.blocks ).toBeGreaterThanOrEqual( 28 );
		expect( payload.heroFields ).toBe( 21 );
		expect( payload.maxActions ).toBe( 25 );

		// An uncaught exception is never acceptable: it stops the bundle dead.
		expect( pageErrors, pageErrors.join( '\n' ) ).toEqual( [] );

		const newProblems = unexpected( consoleErrors );
		expect( newProblems, newProblems.join( '\n' ) ).toEqual( [] );
	} );

	test( 'the AI sidebar is registered as an editor plugin', async ( { page } ) => {
		await openEditor( page );

		// registerPlugin() with no scope registers in the DEFAULT registry, not
		// under 'core' — getPlugins('core') returns an unrelated, empty set.
		const registered = await page.evaluate( () => {
			const plugins = window.wp && window.wp.plugins;
			if ( ! plugins || ! plugins.getPlugins ) {
				return [];
			}
			return plugins.getPlugins().map( ( plugin ) => plugin.name );
		} );

		expect( registered ).toContain( 'sfcore-ai' );
	} );

	test( 'the Ask AI toolbar control is registered against editor.BlockEdit', async ( { page } ) => {
		await openEditor( page );

		// What is verifiable without driving the iframed canvas: the filter that
		// injects the toolbar control is registered under its own namespace.
		// Whether the control is VISIBLE on selection is not asserted here — see
		// the note at the bottom of this file.
		const registered = await page.evaluate( () => {
			const hooks = window.wp && window.wp.hooks;
			if ( ! hooks || ! hooks.hasFilter ) {
				return null;
			}
			return hooks.hasFilter( 'editor.BlockEdit', 'sfcore/ai-ask-controls' );
		} );

		expect( registered, 'editor.BlockEdit filter not registered' ).toBeTruthy();
	} );

} );

/*
 * NOT VERIFIED HERE, and deliberately not faked.
 *
 * Clicking Ask AI and reading the sidebar panel is not asserted. Two concrete
 * obstacles, both WordPress mechanics rather than plugin behaviour, and both
 * worth knowing before anyone extends this:
 *
 *  1. This theme's page editor loads a template wrapper, so the block tree is
 *     [template-part, group[...post-content], template-part]. Post content lives
 *     in core/post-content's CONTROLLED inner blocks, which have their own
 *     block-editor registry scope — a root-level insertBlocks() lands in the
 *     template and never reaches the post.
 *  2. Programmatic selectBlock() does not stick while the iframed canvas owns
 *     selection, and clicking inside the canvas did not resolve in time here.
 *
 * What IS established above: the bundle loads with no uncaught errors, the
 * capability payload is correct (28 blocks, hero at 21 fields), the sidebar
 * plugin registers as sfcore-ai, and the toolbar filter is registered. The Ask
 * AI button was also observed present in the DOM during investigation.
 *
 * Still needs a human to look, or a harness built on
 * @wordpress/e2e-test-utils-playwright's editor fixture, which resolves the
 * post-content scope and canvas selection properly.
 */
