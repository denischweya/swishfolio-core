/**
 * Do the section patterns look like anything on the front end?
 *
 * Parsing proves Gutenberg accepts them and do_blocks() proves PHP emits
 * markup, but neither proves a visitor sees a section rather than a collapsed
 * empty box. This loads a draft containing every pattern and measures each one.
 */

const { test, expect } = require( '@playwright/test' );

const PREVIEW = process.env.SFCORE_PREVIEW_URL;

test.describe( 'section patterns on the front end', () => {
	test.skip( ! PREVIEW, 'SFCORE_PREVIEW_URL not set' );

	test( 'every pattern renders with real height and no console errors', async ( { page } ) => {
		const consoleErrors = [];
		page.on( 'console', ( m ) => m.type() === 'error' && consoleErrors.push( m.text() ) );
		page.on( 'pageerror', ( e ) => consoleErrors.push( 'pageerror: ' + e.message ) );

		const failedAssets = [];
		page.on( 'response', ( r ) => {
			if ( r.status() >= 400 && /\.(png|jpe?g|webp|svg|css|js)(\?|$)/i.test( r.url() ) ) {
				failedAssets.push( r.status() + ' ' + r.url().split( '/' ).pop() );
			}
		} );

		await page.goto( PREVIEW, { waitUntil: 'networkidle' } );

		const body = await page.locator( 'body' ).innerText();
		for ( const marker of [ 'Fatal error', 'Parse error', 'Warning:', 'Notice:' ] ) {
			expect( body, 'PHP ' + marker ).not.toContain( marker );
		}

		// Each pattern sits under an h2 bearing its slug. Measure the vertical
		// gap to the next heading: that is the space the pattern occupies.
		const measured = await page.evaluate( () => {
			const marks = Array.from( document.querySelectorAll( 'h2[id^="section-"]' ) );
			const pageBottom = document.body.scrollHeight;

			return marks.map( ( mark, index ) => {
				const top = mark.getBoundingClientRect().top + window.scrollY;
				const next = marks[ index + 1 ];
				const bottom = next
					? next.getBoundingClientRect().top + window.scrollY
					: pageBottom;

				return {
					name: mark.id,
					height: Math.round( bottom - top ),
					images: 0,
				};
			} );
		} );

		// eslint-disable-next-line no-console
		console.log( '--- FRONT-END RENDER ---' );
		measured.forEach( ( m ) => {
			// eslint-disable-next-line no-console
			console.log( `  ${ m.height < 120 ? 'THIN' : ' ok ' }  ${ m.name.padEnd( 30 ) } ${ String( m.height ).padStart( 5 ) }px` );
		} );

		expect( measured.length, 'pattern markers not found on the page' ).toBeGreaterThanOrEqual( 25 );

		// A pattern shorter than its own heading has collapsed to nothing.
		const collapsed = measured.filter( ( m ) => m.height < 120 );
		expect( collapsed, JSON.stringify( collapsed ) ).toEqual( [] );

		expect( failedAssets, failedAssets.join( '\n' ) ).toEqual( [] );

		// Theme/pattern CSS problems would show here.
		expect( consoleErrors, consoleErrors.join( '\n' ) ).toEqual( [] );

		await page.screenshot( {
			path: 'artifacts/pattern-proof.png',
			fullPage: true,
		} );
	} );
} );
