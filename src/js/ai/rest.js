/**
 * The `/ai/edit` client.
 *
 * apiFetch already carries the REST nonce (WordPress installs the nonce
 * middleware inline after the `wp-api-fetch` handle), so there is no nonce
 * plumbing here — declaring `wp-api-fetch` as a script dependency is the whole
 * requirement.
 *
 * Every failure reaches the user as one sentence. The machine detail — the
 * validation stage, the offending path, the provider's own message — goes behind
 * a disclosure, because it is useful when something is wrong and noise when it
 * is not.
 */

import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

import { REST_EDIT_PATH } from './constants';

/** The one sentence every failure shows. */
export const GENERIC_FAILURE = __(
	"AI couldn't complete this request. Nothing was changed.",
	'swishfolio-core'
);

/**
 * Codes AIController actually returns, and the one actionable hint each one
 * carries. The headline copy never varies.
 */
const HINTS = {
	sfcore_ai_not_configured: () =>
		__(
			'No AI provider is configured yet — add a key under Swish Forms → AI.',
			'swishfolio-core'
		),
	sfcore_ai_invalid_response: () =>
		__(
			'The response was rejected before anything was applied.',
			'swishfolio-core'
		),
	sfcore_ai_provider_error: () =>
		__( 'The AI provider could not be reached.', 'swishfolio-core' ),
	sfcore_ai_rate_limited: () =>
		__(
			'The hourly AI request limit has been reached. Try again later.',
			'swishfolio-core'
		),
	sfcore_ai_forbidden: () =>
		__(
			'You do not have permission to use AI editing on this post.',
			'swishfolio-core'
		),
	sfcore_ai_bad_selection: () =>
		__( 'The editor did not send a usable selection.', 'swishfolio-core' ),
};

/**
 * @param {Object} payload { postId, instruction, selection, blockTree }.
 *
 * @return {Promise<Object>} The validated response: schemaVersion, summary, actions.
 */
export function requestEdit( payload ) {
	return apiFetch( {
		path: REST_EDIT_PATH,
		method: 'POST',
		data: payload,
	} );
}

function collectDetail( error ) {
	const data = error && error.data ? error.data : {};
	const parts = [];

	if ( data.stage ) {
		parts.push(
			sprintf(
				/* translators: %s: validation stage that rejected the response. */
				__( 'Stage: %s', 'swishfolio-core' ),
				data.stage
			)
		);
	}

	if ( data.path ) {
		parts.push(
			sprintf(
				/* translators: %s: path within the AI response, e.g. actions.0.changes.heading. */
				__( 'Path: %s', 'swishfolio-core' ),
				data.path
			)
		);
	}

	if ( data.detail ) {
		parts.push( String( data.detail ) );
	} else if ( error && error.message ) {
		parts.push( String( error.message ) );
	}

	if ( error && error.code ) {
		parts.push(
			sprintf(
				/* translators: %s: machine error code. */
				__( 'Code: %s', 'swishfolio-core' ),
				error.code
			)
		);
	}

	return parts.length ? parts.join( '\n' ) : null;
}

/**
 * Normalise anything apiFetch can reject with into one display shape.
 *
 * @param {*} error Rejection value.
 *
 * @return {Object} { code, message, hint, detail, stage, path, status }.
 */
export function describeError( error ) {
	const code =
		error && error.code ? String( error.code ) : 'sfcore_ai_unknown';
	const data = error && error.data ? error.data : {};
	const hint = HINTS[ code ] ? HINTS[ code ]() : null;

	return {
		code,
		message: GENERIC_FAILURE,
		hint,
		detail: collectDetail( error ),
		stage: data.stage || null,
		path: data.path || null,
		status: data.status || null,
	};
}

/**
 * A client-side refusal, shaped like a server error so one panel renders both.
 *
 * @param {string} code   Machine code.
 * @param {string} hint   One sentence of context.
 * @param {string} detail Machine detail, or empty.
 *
 * @return {Object} Error description.
 */
export function localError( code, hint, detail ) {
	return {
		code,
		message: GENERIC_FAILURE,
		hint: hint || null,
		detail: detail || null,
		stage: null,
		path: null,
		status: null,
	};
}
