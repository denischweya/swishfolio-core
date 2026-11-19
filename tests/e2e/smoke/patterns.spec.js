/**
 * Do the theme's section patterns actually survive insertion?
 *
 * A pattern is block markup plus attribute JSON. If a block's save() cannot
 * reproduce the serialized markup from those attributes, Gutenberg marks the
 * block invalid the moment the pattern is inserted and the user sees "this
 * block contains unexpected or invalid content".
 *
 * Static analysis already caught one of these (section-stats set "align" on a
 * block that declares both attributes.align and supports.align). This runs the
 * real parser with the real block registry over every pattern, which is what
 * Gutenberg itself does, so nothing of that class can hide.
 */

const { test, expect } = require( '@playwright/test' );

test.describe( 'theme section patterns', () => {
	test( 'every section pattern parses to valid, known blocks', async ( { page } ) => {
		await page.goto( '/wp-admin/post-new.php?post_type=page' );

		const close = page.locator( '.components-modal__header button[aria-label="Close"]' );
		if ( await close.isVisible().catch( () => false ) ) {
			await close.click();
		}

		// Wait until the block registry is populated, or "unknown block" would
		// be indistinguishable from "not loaded yet".
		await page.waitForFunction(
			() => ( window.wp?.blocks?.getBlockTypes() || [] ).length > 40,
			{ timeout: 60_000 }
		);

		const report = await page.evaluate( async () => {
			const { parse, getBlockType } = window.wp.blocks;

			const patterns = await window.wp.apiFetch( {
				path: '/wp/v2/block-patterns/patterns',
			} );

			const sections = patterns.filter(
				( p ) => typeof p.name === 'string' && p.name.startsWith( 'swishfolio/section-' )
			);

			/** Walk a parsed tree, collecting anything Gutenberg would complain about. */
			const inspect = ( blocks, patternName, out ) => {
				blocks.forEach( ( block ) => {
					if ( block.name === 'core/missing' ) {
						out.push( {
							pattern: patternName,
							block: block.attributes?.originalName || 'unknown',
							problem: 'unregistered block',
						} );
					} else if ( ! getBlockType( block.name ) ) {
						out.push( { pattern: patternName, block: block.name, problem: 'no block type' } );
					} else if ( block.isValid === false ) {
						out.push( {
							pattern: patternName,
							block: block.name,
							problem: 'invalid content',
						} );
					}

					inspect( block.innerBlocks || [], patternName, out );
				} );
			};

			const problems = [];
			const summary = [];

			sections.forEach( ( pattern ) => {
				const parsed = parse( pattern.content );
				const before = problems.length;

				inspect( parsed, pattern.name, problems );

				const count = ( function tally( list ) {
					return list.reduce( ( n, b ) => n + 1 + tally( b.innerBlocks || [] ), 0 );
				} )( parsed );

				summary.push( {
					name: pattern.name,
					blocks: count,
					bytes: pattern.content.length,
					problems: problems.length - before,
				} );
			} );

			return { found: sections.length, summary, problems };
		} );

		// eslint-disable-next-line no-console
		console.log( '--- SECTION PATTERNS ---' );
		report.summary.forEach( ( s ) => {
			// eslint-disable-next-line no-console
			console.log(
				`  ${ s.problems ? 'FAIL' : ' ok ' }  ${ s.name.padEnd( 38 ) } ${ String( s.blocks ).padStart( 3 ) } blocks  ${ String( s.bytes ).padStart( 5 ) } bytes`
			);
		} );

		expect( report.found, 'no section patterns were registered' ).toBeGreaterThanOrEqual( 25 );
		expect( report.problems, JSON.stringify( report.problems, null, 2 ) ).toEqual( [] );
	} );
} );
