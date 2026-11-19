/**
 * Calling the plugin's REST routes from inside the logged-in browser session.
 *
 * The tool layer has no UI of its own in this slice — the chat panel that will
 * drive it is a later one — so the only honest way to exercise the real routes
 * end to end is from the page itself, with the session's own cookies and nonce.
 * Nothing here stubs anything: these calls hit the live PHP, the live validator
 * and the live journal table.
 */

/**
 * The REST nonce the current admin page was given.
 *
 * Every wp-admin screen that enqueues wp-api-fetch carries one, and without it
 * WordPress treats a cookie-authenticated REST call as anonymous.
 *
 * @param {import('@playwright/test').Page} page Page on a wp-admin screen.
 *
 * @return {Promise<string>} The nonce.
 */
async function restNonce( page ) {
	const nonce = await page.evaluate( () => {
		if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
			return window.wpApiSettings.nonce;
		}

		return null;
	} );

	if ( ! nonce ) {
		throw new Error(
			'No REST nonce on this page. Load a wp-admin screen that enqueues wp-api-fetch first.'
		);
	}

	return nonce;
}

/**
 * POSTs JSON to one of the plugin's routes and returns the parsed body.
 *
 * @param {import('@playwright/test').Page} page  Page on a wp-admin screen.
 * @param {string}                          route Route after the namespace, e.g. "ai/tools/run".
 * @param {Object}                          body  Request body.
 *
 * @return {Promise<{status: number, body: Object}>} Status and parsed body.
 */
async function postRest( page, route, body ) {
	const nonce = await restNonce( page );

	return page.evaluate(
		async ( [ path, payload, wpNonce ] ) => {
			const response = await fetch( `/wp-json/swishfolio/v1/${ path }`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': wpNonce,
				},
				credentials: 'same-origin',
				body: JSON.stringify( payload ),
			} );

			return { status: response.status, body: await response.json() };
		},
		[ route, body, nonce ]
	);
}

/**
 * GETs one of the plugin's routes and returns the parsed body.
 *
 * @param {import('@playwright/test').Page} page  Page on a wp-admin screen.
 * @param {string}                          route Route after the namespace, e.g. "ai/audit".
 *
 * @return {Promise<{status: number, body: Object}>} Status and parsed body.
 */
async function getRest( page, route ) {
	const nonce = await restNonce( page );

	return page.evaluate(
		async ( [ path, wpNonce ] ) => {
			const response = await fetch( `/wp-json/swishfolio/v1/${ path }`, {
				headers: { 'X-WP-Nonce': wpNonce },
				credentials: 'same-origin',
			} );

			return { status: response.status, body: await response.json() };
		},
		[ route, nonce ]
	);
}

module.exports = { getRest, postRest, restNonce };
