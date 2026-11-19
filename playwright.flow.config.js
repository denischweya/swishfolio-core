/**
 * Runs the real AI editing flow spec against a pre-minted admin session.
 *
 * Same intent as playwright.config.js, minus its globalSetup: that logs in with
 * WP_USERNAME/WP_PASSWORD, which this run does not have. The storage state is
 * generated server-side instead — no password, no test user.
 */
const path = require( 'node:path' );
const { defineConfig, devices } = require( '@playwright/test' );

process.env.WP_ARTIFACTS_PATH ??= path.join( __dirname, 'artifacts' );

module.exports = defineConfig( {
	reporter: [ [ 'list' ] ],
	fullyParallel: false,
	workers: 1,
	timeout: 120_000,
	testDir: path.join( __dirname, 'tests', 'e2e', 'specs' ),
	outputDir: path.join( __dirname, 'artifacts', 'flow-results' ),
	use: {
		baseURL: process.env.WP_BASE_URL || 'https://swishfolio.ddev.site',
		headless: true,
		viewport: { width: 1400, height: 900 },
		ignoreHTTPSErrors: true,
		storageState: path.join( __dirname, 'artifacts', 'storage-states', 'admin.json' ),
		actionTimeout: 25_000,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [ { name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } } ],
} );
