/**
 * The stubbed AI provider.
 *
 * `POST /wp-json/swishfolio/v1/ai/edit` is intercepted in the browser and
 * answered from a fixture, so no AI provider is contacted, no API key is needed
 * and the response is byte-identical on every run. Nothing outside this file
 * knows the difference: the editor still calls apiFetch, still gets the
 * `{ schemaVersion, summary, actions }` envelope, and still runs its own
 * clientId re-check before dispatching anything.
 *
 * The trade this makes is deliberate. Intercepting at the network boundary
 * means PHP's five validation stages do not run — they are covered exhaustively
 * by the PHPUnit suite, whereas what only a browser can prove is what this file
 * exists to set up: that an applied change is one undo step.
 *
 * The responder is handed the request body, so fixtures can be written against
 * the clientIds the editor actually sent — the only way to produce actions that
 * survive `findUnknownTargets()`, which rejects any target the client did not
 * itself submit.
 */

/** Matches both permalink shapes: /wp-json/<ns>/<route> and ?rest_route=<route>. */
function isAiEditRequest( url ) {
	const href = typeof url === 'string' ? url : url.href;

	return (
		href.includes( 'swishfolio/v1/ai/edit' ) ||
		href.includes( 'swishfolio%2Fv1%2Fai%2Fedit' )
	);
}

/**
 * @param {import('@playwright/test').Page} page    Editor page.
 * @param {Function}                        respond Request body → response body.
 *
 * @return {Promise<Object>} { calls } — every intercepted request body, in order.
 */
async function stubAiProvider( page, respond ) {
	const calls = [];

	await page.route( isAiEditRequest, async ( route ) => {
		let body = null;

		try {
			body = route.request().postDataJSON();
		} catch ( error ) {
			body = null;
		}

		calls.push( body );

		await route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( respond( body ) ),
		} );
	} );

	return { calls };
}

module.exports = { isAiEditRequest, stubAiProvider };
