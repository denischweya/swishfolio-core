/**
 * Logs in once and saves the cookies every test reuses.
 *
 * This is @wordpress/scripts' own global setup with two changes: the request
 * context inherits `ignoreHTTPSErrors` (DDEV's certificate is locally signed),
 * and a failure is re-thrown with the environment variables that fix it. The
 * bundled setup fails with a bare "Not logged in" against any site that is not
 * wp-env, which is every site this project runs against.
 */

const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * @param {import('@playwright/test').FullConfig} config Resolved Playwright config.
 *
 * @return {Promise<void>} Resolves once the storage state is on disk.
 */
async function globalSetup( config ) {
	const { storageState, baseURL, ignoreHTTPSErrors } =
		config.projects[ 0 ].use;

	const storageStatePath =
		typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext( {
		baseURL,
		ignoreHTTPSErrors,
	} );

	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
		baseURL,
	} );

	try {
		await requestUtils.setupRest();
	} catch ( error ) {
		throw new Error(
			[
				'Could not sign in to ' + baseURL + ' as "' +
					( process.env.WP_USERNAME || 'admin' ) + '".',
				'',
				'Set the site and the credentials before running the suite:',
				'  WP_BASE_URL=https://swishfolio.ddev.site \\',
				'  WP_USERNAME=<administrator login> \\',
				'  WP_PASSWORD=<that account’s password> \\',
				'  npm run test:e2e',
				'',
				'Original error: ' + ( error && error.message ? error.message : error ),
			].join( '\n' )
		);
	} finally {
		await requestContext.dispose();
	}
}

module.exports = globalSetup;
