/**
 * Playwright configuration for the AI block editing end-to-end test (design §12).
 *
 * Run it with `wp-scripts test-playwright` — @wordpress/scripts 28 ships that
 * command alongside @wordpress/e2e-test-utils-playwright. Because this file
 * exists at the project root, wp-scripts uses it instead of its own bundled
 * config, which matters for two reasons: its `testDir` is `./specs`, and its
 * `webServer` block runs `npm run wp-env start`. This project has no wp-env —
 * a live WordPress multisite already runs under DDEV — so there is no
 * `webServer` here. Start DDEV before running the suite.
 *
 * Environment:
 *   WP_BASE_URL   site under test          (default https://swishfolio.ddev.site)
 *   WP_USERNAME   an administrator login   (default admin)
 *   WP_PASSWORD   that account's password  (default password)
 *
 * The defaults are wp-env's, not DDEV's, so WP_USERNAME and WP_PASSWORD must be
 * supplied for a DDEV run. Set PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 to stop
 * `wp-scripts test-playwright` shelling out to `npx playwright install` when the
 * browsers are already present.
 */

const path = require( 'node:path' );
const { defineConfig, devices } = require( '@playwright/test' );

process.env.WP_ARTIFACTS_PATH ??= path.join( __dirname, 'artifacts' );
process.env.STORAGE_STATE_PATH ??= path.join(
	process.env.WP_ARTIFACTS_PATH,
	'storage-states',
	'admin.json'
);

module.exports = defineConfig( {
	reporter: process.env.CI ? [ [ 'github' ] ] : [ [ 'list' ] ],
	forbidOnly: !! process.env.CI,
	// One WordPress install, one worker: these tests share editor state.
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 2 : 0,
	timeout: parseInt( process.env.TIMEOUT || '', 10 ) || 100_000,
	reportSlowTests: null,
	testDir: path.join( __dirname, 'tests', 'e2e', 'specs' ),
	outputDir: path.join( process.env.WP_ARTIFACTS_PATH, 'test-results' ),
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	use: {
		baseURL: process.env.WP_BASE_URL || 'https://swishfolio.ddev.site',
		headless: true,
		viewport: { width: 1280, height: 800 },
		// DDEV serves a locally-signed certificate.
		ignoreHTTPSErrors: true,
		locale: 'en-US',
		contextOptions: {
			reducedMotion: 'reduce',
			strictSelectors: true,
		},
		storageState: process.env.STORAGE_STATE_PATH,
		actionTimeout: 15_000,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'on-first-retry',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
