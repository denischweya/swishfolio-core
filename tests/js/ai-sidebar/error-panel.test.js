/**
 * Every failure shows the same sentence. What changes is the one actionable
 * hint under it and the machine detail behind the disclosure.
 */

import { render, screen } from '@testing-library/react';

import ErrorPanel from '../../../src/js/ai-sidebar/error-panel';
import { GENERIC_FAILURE, describeError } from '../../../src/js/ai/rest';

/**
 * The four codes AIController returns, shaped the way apiFetch rejects: a
 * WP_Error serialised to { code, message, data }.
 */
const REST_ERRORS = [
	{
		code: 'sfcore_ai_not_configured',
		status: 409,
		rejection: {
			code: 'sfcore_ai_not_configured',
			message: 'No AI provider is configured.',
			data: { status: 409 },
		},
		hint: 'No AI provider is configured yet — add a key under Swish Forms → AI.',
	},
	{
		code: 'sfcore_ai_invalid_response',
		status: 422,
		rejection: {
			code: 'sfcore_ai_invalid_response',
			message: 'AI could not complete this request. Nothing was changed.',
			data: {
				status: 422,
				stage: 'allowlist',
				path: 'actions.0.changes.heroImage',
				detail: 'Attribute "heroImage" is not editable on swishfolio-core/hero.',
			},
		},
		hint: 'The response was rejected before anything was applied.',
	},
	{
		code: 'sfcore_ai_provider_error',
		status: 502,
		rejection: {
			code: 'sfcore_ai_provider_error',
			message: 'AI could not complete this request. Nothing was changed.',
			data: { status: 502, detail: 'cURL error 28: Operation timed out' },
		},
		hint: 'The AI provider could not be reached.',
	},
	{
		code: 'sfcore_ai_rate_limited',
		status: 429,
		rejection: {
			code: 'sfcore_ai_rate_limited',
			message: 'Too many AI requests. Try again later.',
			data: { status: 429 },
		},
		hint: 'The hourly AI request limit has been reached. Try again later.',
	},
];

describe( 'ErrorPanel', () => {
	REST_ERRORS.forEach( ( { code, status, rejection, hint } ) => {
		it( `renders the right message for ${ code } (HTTP ${ status })`, () => {
			const described = describeError( rejection );

			expect( described.code ).toBe( code );
			expect( described.status ).toBe( status );

			render( <ErrorPanel error={ described } /> );

			// The headline is identical for all four: nothing was changed.
			expect( screen.getByText( GENERIC_FAILURE ) ).toBeTruthy();

			// The hint is what differs, and it is the only actionable line.
			expect( screen.getByText( hint ) ).toBeTruthy();

			// The machine detail is present but behind a disclosure.
			const details = screen.getByText( 'Details' ).closest( 'details' );

			expect( details ).not.toBeNull();
			expect( details.open ).toBe( false );
			expect( details.textContent ).toContain( `Code: ${ code }` );
		} );
	} );

	it( 'puts the validation stage and path in the disclosure, and only for a validation failure', () => {
		const invalid = REST_ERRORS.find(
			( entry ) => entry.code === 'sfcore_ai_invalid_response'
		);

		const { unmount } = render(
			<ErrorPanel error={ describeError( invalid.rejection ) } />
		);

		const details = screen.getByText( 'Details' ).closest( 'details' );

		expect( details.textContent ).toContain( 'Stage: allowlist' );
		expect( details.textContent ).toContain(
			'Path: actions.0.changes.heroImage'
		);
		expect( details.textContent ).toContain( 'heroImage' );

		unmount();

		const rateLimited = REST_ERRORS.find(
			( entry ) => entry.code === 'sfcore_ai_rate_limited'
		);

		render( <ErrorPanel error={ describeError( rateLimited.rejection ) } /> );

		const plain = screen.getByText( 'Details' ).closest( 'details' );

		expect( plain.textContent ).not.toContain( 'Stage:' );
		expect( plain.textContent ).not.toContain( 'Path:' );
	} );

	it( 'omits the hint and the disclosure when there is nothing to say', () => {
		render(
			<ErrorPanel
				error={ {
					code: 'sfcore_ai_unknown',
					message: GENERIC_FAILURE,
					hint: null,
					detail: null,
				} }
			/>
		);

		expect( screen.getByText( GENERIC_FAILURE ) ).toBeTruthy();
		expect( screen.queryByText( 'Details' ) ).toBeNull();
	} );
} );
