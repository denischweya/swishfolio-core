/**
 * The `/ai/agent` client.
 *
 * apiFetch carries the REST nonce already, so there is no nonce plumbing here —
 * declaring `wp-api-fetch` as a script dependency is the whole requirement.
 *
 * Unlike the block-editing client, a failure here is not always "nothing was
 * changed": the loop may have completed several tool calls before the provider
 * dropped the connection, and those calls are journalled and real. So the copy
 * says what is actually true and points at the audit log, which is the only
 * place that can answer "what did it already do".
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

import { REST_AGENT_PATH, REST_JOB_PATH } from './constants';

/** Shown when the request itself failed. */
export const GENERIC_FAILURE = __(
	"The AI couldn't finish that request.",
	'swishfolio-core'
);

const HINTS = {
	sfcore_ai_not_configured: () =>
		__(
			'No AI provider is connected yet — connect one under Swish AI → Connections.',
			'swishfolio-core'
		),
	sfcore_ai_disabled: () =>
		__(
			'AI is turned off for this site — enable it under Swish AI → Connections.',
			'swishfolio-core'
		),
	sfcore_ai_provider_error: () =>
		__(
			'The AI provider could not be reached. Anything it had already changed is listed under Swish AI → Audit Log.',
			'swishfolio-core'
		),
	sfcore_ai_rate_limited: () =>
		__(
			'The hourly AI request limit has been reached. Try again later.',
			'swishfolio-core'
		),
	sfcore_ai_forbidden: () =>
		__(
			'You do not have permission to manage this site with AI.',
			'swishfolio-core'
		),
	sfcore_ai_bad_request: () =>
		__( 'Say what you would like changed.', 'swishfolio-core' ),
	sfcore_job_failed: () =>
		__(
			'That request stopped unexpectedly. Anything it had already changed is listed under Swish AI → Audit Log.',
			'swishfolio-core'
		),
	sfcore_job_not_found: () =>
		__(
			'That request expired before it finished. Ask again.',
			'swishfolio-core'
		),
};

/**
 * Send one turn.
 *
 * @param {Object} payload { instruction, history, confirmToken }.
 *
 * @return {Promise<Object>} AgentRun as an object.
 */
export function sendTurn( payload ) {
	return apiFetch( {
		path: REST_AGENT_PATH,
		method: 'POST',
		data: payload,
	} );
}

/**
 * Ask a queued turn how far along it is.
 *
 * @param {string} jobId Job identifier.
 *
 * @return {Promise<Object>} The job status.
 */
export function fetchJob( jobId ) {
	return apiFetch( { path: REST_JOB_PATH + jobId } );
}

/**
 * Normalise anything apiFetch can reject with into one display shape.
 *
 * @param {*} error Rejection value.
 *
 * @return {Object} { code, message, hint, detail }.
 */
export function describeError( error ) {
	const code =
		error && error.code ? String( error.code ) : 'sfcore_ai_unknown';
	const data = error && error.data ? error.data : {};
	const detail = data.detail || ( error && error.message ) || null;

	return {
		code,
		message: GENERIC_FAILURE,
		hint: HINTS[ code ] ? HINTS[ code ]() : null,
		detail: detail ? String( detail ) : null,
	};
}
