/**
 * Render smoke checks, run against a pre-minted admin session.
 *
 * Separate from playwright.config.js on purpose: that config's globalSetup logs
 * in with WP_USERNAME/WP_PASSWORD, which this run does not have. Here the
 * storage state is generated server-side instead, so no password is needed and
 * no test user is created.
 *
 * These checks never call an AI provider — they prove the screens and the editor
 * integration render, which is verifiable with no API credit.
 */

const path = require( 'node:path' );
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	reporter: [ [ 'list' ] ],
	fullyParallel: false,
	workers: 1,
	timeout: 90_000,
	testDir: path.join( __dirname, 'tests', 'e2e', 'smoke' ),
	outputDir: path.join( __dirname, 'artifacts', 'smoke-results' ),
	use: {
		baseURL: process.env.WP_BASE_URL || 'https://swishfolio.ddev.site',
		headless: true,
		viewport: { width: 1400, height: 900 },
		ignoreHTTPSErrors: true,
		storageState: path.join( __dirname, 'artifacts', 'storage-states', 'admin.json' ),
		actionTimeout: 20_000,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [ { name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } } ],
} );
