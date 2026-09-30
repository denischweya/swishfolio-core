/**
 * Does any of this actually render?
 *
 * Every other suite runs against stubs or in isolation. Nothing had ever loaded
 * these screens in a browser, so a missing asset, a PHP notice or a wrong
 * @wordpress/* import would have gone unnoticed.
 */

const { test, expect } = require( '@playwright/test' );

/** Fail loudly on console errors and PHP output — the point of a render check. */
function watchForErrors( page ) {
	const problems = [];

	page.on( 'console', ( message ) => {
		if ( message.type() === 'error' ) {
			problems.push( 'console: ' + message.text() );
		}
	} );
	page.on( 'pageerror', ( error ) => problems.push( 'pageerror: ' + error.message ) );

	return problems;
}

/** WordPress prints notices as text; catching them beats eyeballing a screenshot. */
async function expectNoPhpNotices( page ) {
	const body = await page.locator( 'body' ).innerText();

	for ( const marker of [ 'Fatal error', 'Parse error', 'Warning:', 'Notice:', 'Deprecated:' ] ) {
		expect( body, 'PHP ' + marker + ' on page' ).not.toContain( marker );
	}
}

test.describe( 'Swish AI admin', () => {
	test( 'the Connections screen renders provider cards', async ( { page } ) => {
		const problems = watchForErrors( page );

		await page.goto( '/wp-admin/admin.php?page=swish-ai' );

		await expect( page.getByRole( 'heading', { name: 'Swish AI', level: 1 } ) ).toBeVisible();

		// One card per registered provider.
		await expect( page.locator( '.sfcore-ai-card' ) ).toHaveCount( 3 );
		await expect( page.getByRole( 'heading', { name: 'Anthropic (Claude)' } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'OpenAI', exact: true } ) ).toBeVisible();

		await expectNoPhpNotices( page );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );

	test( 'each provider card offers only its own models', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=swish-ai' );

		const anthropic = page.locator( 'select.sfcore-ai-model[data-provider="anthropic"]' );
		const openai = page.locator( 'select.sfcore-ai-model[data-provider="openai"]' );

		await expect( anthropic ).toBeVisible();

		const claudeModels = await anthropic.locator( 'option' ).allTextContents();
		const gptModels = await openai.locator( 'option' ).allTextContents();

		expect( claudeModels.join( ' ' ) ).toContain( 'Claude Opus 5' );
		expect( claudeModels.join( ' ' ) ).not.toContain( 'GPT' );
		expect( gptModels.join( ' ' ) ).toContain( 'GPT-4o' );
		expect( gptModels.join( ' ' ) ).not.toContain( 'Claude' );
	} );

	test( 'the connected provider is marked, and no key is present in the HTML', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=swish-ai' );

		await expect( page.locator( '.sfcore-ai-badge--ok' ).first() ).toBeVisible();

		// The key field must be an empty password input, never pre-filled.
		const keyField = page.locator( '#key-anthropic' );
		await expect( keyField ).toHaveAttribute( 'type', 'password' );
		await expect( keyField ).toHaveValue( '' );

		const html = await page.content();
		expect( html ).not.toContain( 'sk-ant-api' );
	} );

	test( 'the Brand screen renders and counts characters', async ( { page } ) => {
		const problems = watchForErrors( page );

		await page.goto( '/wp-admin/admin.php?page=swish-ai-brand' );

		await expect( page.getByRole( 'heading', { name: 'Brand & Context', level: 1 } ) ).toBeVisible();

		const description = page.locator( '#siteDescription' );
		await description.fill( 'We help Nairobi SMEs grow online.' );

		await expect( page.locator( '#siteDescription-counter' ) ).toContainText( '33/500' );

		await expectNoPhpNotices( page );
		expect( problems, problems.join( '\n' ) ).toEqual( [] );
	} );

	test( 'the Usage screen renders', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=swish-ai-usage' );

		await expect( page.getByRole( 'heading', { name: 'AI Usage', level: 1 } ) ).toBeVisible();
		await expectNoPhpNotices( page );
	} );
} );
