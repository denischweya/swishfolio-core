/**
 * The safety spine in a real WordPress (spec §Testing).
 *
 * One flow: change the live front page through the tool routes, find the change
 * in the audit log, click Undo, and confirm the site is back.
 *
 * The PHPUnit suite proves the journal's arithmetic against stubs — the inverse
 * for every target type, best-effort partial reverts, the append-only
 * guarantee. What it cannot prove is any of the things only a real install has:
 * that the journal table was actually created, that a forced revision on a real
 * post really does restore, that the admin screen renders the rows, and that
 * its nonce-protected Undo button reaches the tool. That is what this is for.
 *
 * Nothing is stubbed. No AI provider is contacted, because the tool routes do
 * not call one — the model's job is choosing the calls, and here the test makes
 * that choice itself.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const { getRest, postRest } = require( '../utils/rest' );

const AUDIT_URL = '/wp-admin/admin.php?page=swish-ai-audit';

/**
 * @param {import('@playwright/test').Page} page Page.
 *
 * @return {Promise<number>} ID of a published page to point the front page at.
 */
async function publishedPageId( page ) {
	const nonce = await page.evaluate( () => window.wpApiSettings.nonce );

	const created = await page.evaluate(
		async ( wpNonce ) => {
			const response = await fetch( '/wp-json/wp/v2/pages', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': wpNonce,
				},
				credentials: 'same-origin',
				body: JSON.stringify( {
					title: 'Tool layer front page',
					status: 'publish',
					content: '<!-- wp:paragraph --><p>Front.</p><!-- /wp:paragraph -->',
				} ),
			} );

			return { status: response.status, body: await response.json() };
		},
		nonce
	);

	expect( created.status, JSON.stringify( created.body ) ).toBe( 201 );

	return created.body.id;
}

test.describe( 'AI tool layer: audit and rollback', () => {
	test( 'a front-page change is journalled, listed and reversible', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'index.php' );

		const pageId = await publishedPageId( page );
		const calls = [
			{ name: 'swishfolio/set-front-page', input: { postId: pageId } },
		];

		// Without a token the gate refuses, and nothing is written. This is the
		// assertion that would catch a confirmation check that had quietly
		// stopped running.
		const unconfirmed = await postRest( page, 'ai/tools/run', { calls } );

		expect( unconfirmed.status ).toBe( 409 );
		expect( unconfirmed.body.data.code ).toBe( 'confirm_required' );

		const preview = await postRest( page, 'ai/tools/preview', { calls } );

		expect( preview.status ).toBe( 200 );
		expect( preview.body.requiresConfirmation ).toBe( true );
		expect( preview.body.previews[ 0 ].summary ).toContain(
			'Tool layer front page'
		);

		const run = await postRest( page, 'ai/tools/run', {
			calls,
			confirmToken: preview.body.confirmToken,
		} );

		expect( run.status, JSON.stringify( run.body ) ).toBe( 200 );
		expect( run.body.results[ 0 ].result.applied ).toBe( true );

		// The live site really did change.
		await admin.visitAdminPage( 'options-reading.php' );
		await expect(
			page.locator( 'input[name="show_on_front"][value="page"]' )
		).toBeChecked();

		// The audit screen lists the run, in words rather than as a payload.
		await page.goto( AUDIT_URL );
		await expect(
			page.getByRole( 'heading', { name: 'AI Audit Log' } )
		).toBeVisible();

		const runCard = page
			.locator( '.card' )
			.filter( { hasText: run.body.runId } );

		await expect( runCard ).toBeVisible();
		await expect( runCard ).toContainText( 'Set the front page' );
		await expect( runCard ).toContainText( 'Tool layer front page' );
		await expect( runCard ).toContainText( 'show_on_front' );

		// Undo, from the page that listed it.
		await runCard
			.getByRole( 'button', { name: 'Undo this run' } )
			.click();

		await expect(
			page.locator( '.notice' ).filter( { hasText: 'has been undone' } )
		).toBeVisible();

		// And the setting is back where it started.
		await admin.visitAdminPage( 'options-reading.php' );
		await expect(
			page.locator( 'input[name="show_on_front"][value="posts"]' )
		).toBeChecked();

		// A second undo must not undo anything twice.
		await page.goto( AUDIT_URL );
		await expect(
			page
				.locator( '.card' )
				.filter( { hasText: run.body.runId } )
				.getByRole( 'button', { name: 'Undo this run' } )
		).toHaveCount( 0 );
	} );

	test( 'a read needs no confirmation and is left out of the audit log', async ( {
		page,
		admin,
	} ) => {
		await admin.visitAdminPage( 'index.php' );

		const before = await getRest( page, 'ai/audit' );

		const read = await postRest( page, 'ai/tools/run', {
			calls: [ { name: 'swishfolio/site-info', input: {} } ],
		} );

		expect( read.status, JSON.stringify( read.body ) ).toBe( 200 );
		expect( read.body.results[ 0 ].result.name ).toBeTruthy();

		const after = await getRest( page, 'ai/audit' );

		expect( after.body.runs.length ).toBe( before.body.runs.length );
	} );
} );
