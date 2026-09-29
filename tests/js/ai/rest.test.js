/**
 * The REST client: one request shape, and one display shape for every failure.
 */

import apiFetch from '@wordpress/api-fetch';
import {
	GENERIC_FAILURE,
	describeError,
	localError,
	requestEdit,
} from '../../../src/js/ai/rest';
import { REST_EDIT_PATH } from '../../../src/js/ai/constants';

beforeEach( () => {
	apiFetch.mockReset();
} );

describe( 'requestEdit', () => {
	it( 'POSTs the payload to /swishfolio/v1/ai/edit', async () => {
		apiFetch.mockResolvedValue( {
			schemaVersion: '1.0',
			summary: 'Tightened the headline.',
			actions: [],
		} );

		const payload = {
			postId: 41,
			instruction: 'Tighten the headline.',
			selection: { clientId: 'hero-1' },
			blockTree: { 'hero-1': {} },
		};

		await requestEdit( payload );

		expect( REST_EDIT_PATH ).toBe( '/swishfolio/v1/ai/edit' );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/swishfolio/v1/ai/edit',
			method: 'POST',
			data: payload,
		} );
	} );
} );

describe( 'describeError', () => {
	it( 'shows one headline for every failure mode', () => {
		[
			'sfcore_ai_not_configured',
			'sfcore_ai_invalid_response',
			'sfcore_ai_provider_error',
			'sfcore_ai_rate_limited',
			'sfcore_ai_forbidden',
			'sfcore_ai_bad_selection',
			'something_unexpected',
		].forEach( ( code ) => {
			expect( describeError( { code } ).message ).toBe(
				GENERIC_FAILURE
			);
		} );

		expect( GENERIC_FAILURE ).toBe(
			"AI couldn't complete this request. Nothing was changed."
		);
	} );

	it( 'keeps the validation stage and path for the disclosure', () => {
		const described = describeError( {
			code: 'sfcore_ai_invalid_response',
			message: 'Attribute "heroImage" is not editable.',
			data: {
				status: 422,
				stage: 'allowlist',
				path: 'actions.0.changes.heroImage',
				detail: 'Attribute "heroImage" is not editable.',
			},
		} );

		expect( described.stage ).toBe( 'allowlist' );
		expect( described.path ).toBe( 'actions.0.changes.heroImage' );
		expect( described.status ).toBe( 422 );
		expect( described.detail ).toContain( 'Stage: allowlist' );
		expect( described.detail ).toContain(
			'Path: actions.0.changes.heroImage'
		);
		expect( described.detail ).toContain( 'Code: sfcore_ai_invalid_response' );
	} );

	it( 'falls back to an unknown code when apiFetch rejects with nothing useful', () => {
		const described = describeError( undefined );

		expect( described.code ).toBe( 'sfcore_ai_unknown' );
		expect( described.message ).toBe( GENERIC_FAILURE );
		expect( described.hint ).toBeNull();
		expect( described.detail ).toBeNull();
	} );
} );

describe( 'localError', () => {
	it( 'shapes a client-side refusal like a server error', () => {
		const error = localError( 'sfcore_ai_unknown_target', 'Hint.', 'Detail.' );

		expect( error ).toEqual( {
			code: 'sfcore_ai_unknown_target',
			message: GENERIC_FAILURE,
			hint: 'Hint.',
			detail: 'Detail.',
			stage: null,
			path: null,
			status: null,
		} );
	} );
} );
