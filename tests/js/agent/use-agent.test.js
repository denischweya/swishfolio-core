/**
 * The conversation state machine.
 *
 * The approval round trip is the part worth testing hardest: a bug that loses
 * the token, or sends the instruction again alongside it, either silently fails
 * to apply an approved change or asks the model to redecide something the user
 * already approved.
 */

import { act, renderHook, waitFor } from '@testing-library/react';

import { useAgent } from '../../../src/js/agent/use-agent';
import { sendTurn } from '../../../src/js/agent/rest';

jest.mock( '../../../src/js/agent/rest', () => {
	const actual = jest.requireActual( '../../../src/js/agent/rest' );

	return { ...actual, sendTurn: jest.fn() };
} );

const doneRun = ( overrides = {} ) => ( {
	status: 'done',
	message: 'All set.',
	steps: [],
	history: [ { role: 'user', text: 'hello' } ],
	pending: [],
	confirmToken: '',
	...overrides,
} );

const pendingRun = () => ( {
	status: 'awaiting_confirmation',
	message: 'I will set the front page.',
	steps: [],
	history: [
		{ role: 'user', text: 'make Home the front page' },
		{
			role: 'assistant',
			toolCalls: [
				{
					id: 'c1',
					name: 'swishfolio/set-front-page',
					input: { postId: 60 },
				},
			],
		},
	],
	pending: [ { name: 'swishfolio/set-front-page', input: { postId: 60 } } ],
	confirmToken: '123.abc',
} );

describe( 'useAgent', () => {
	beforeEach( () => {
		sendTurn.mockReset();
	} );

	it( 'ignores an empty message rather than sending it', async () => {
		const { result } = renderHook( () => useAgent() );

		await act( async () => {
			await result.current.send( '   ' );
		} );

		expect( sendTurn ).not.toHaveBeenCalled();
	} );

	it( 'shows what the user said before the answer arrives', async () => {
		let resolve;
		sendTurn.mockReturnValue(
			new Promise( ( r ) => {
				resolve = r;
			} )
		);

		const { result } = renderHook( () => useAgent() );

		act( () => {
			result.current.send( 'how many pages?' );
		} );

		expect( result.current.transcript ).toHaveLength( 1 );
		expect( result.current.transcript[ 0 ] ).toMatchObject( {
			role: 'user',
			text: 'how many pages?',
		} );
		expect( result.current.isWorking ).toBe( true );

		await act( async () => {
			resolve( doneRun() );
		} );
	} );

	it( 'appends the answer and keeps the history for the next turn', async () => {
		sendTurn.mockResolvedValue( doneRun() );

		const { result } = renderHook( () => useAgent() );

		await act( async () => {
			await result.current.send( 'hello' );
		} );

		expect( result.current.transcript[ 1 ] ).toMatchObject( {
			role: 'assistant',
			text: 'All set.',
		} );
		expect( result.current.isWorking ).toBe( false );

		await act( async () => {
			await result.current.send( 'again' );
		} );

		expect( sendTurn ).toHaveBeenLastCalledWith( {
			instruction: 'again',
			history: [ { role: 'user', text: 'hello' } ],
		} );
	} );

	it( 'surfaces a proposal instead of applying it', async () => {
		sendTurn.mockResolvedValue( pendingRun() );

		const { result } = renderHook( () => useAgent() );

		await act( async () => {
			await result.current.send( 'make Home the front page' );
		} );

		expect( result.current.pending ).toMatchObject( {
			confirmToken: '123.abc',
		} );
		expect( result.current.pending.calls[ 0 ].name ).toBe(
			'swishfolio/set-front-page'
		);
	} );

	it( 'approving sends the token with the history and no new instruction', async () => {
		sendTurn.mockResolvedValueOnce( pendingRun() );

		const { result } = renderHook( () => useAgent() );

		await act( async () => {
			await result.current.send( 'make Home the front page' );
		} );

		sendTurn.mockResolvedValueOnce( doneRun( { message: 'Done.' } ) );

		await act( async () => {
			await result.current.approve();
		} );

		expect( sendTurn ).toHaveBeenLastCalledWith( {
			instruction: '',
			history: pendingRun().history,
			confirmToken: '123.abc',
		} );
		expect( result.current.pending ).toBeNull();
	} );

	it( 'declining sends nothing, because nothing ran', async () => {
		sendTurn.mockResolvedValue( pendingRun() );

		const { result } = renderHook( () => useAgent() );

		await act( async () => {
			await result.current.send( 'make Home the front page' );
		} );

		sendTurn.mockClear();

		act( () => {
			result.current.decline();
		} );

		expect( sendTurn ).not.toHaveBeenCalled();
		expect( result.current.pending ).toBeNull();
		expect(
			result.current.transcript[ result.current.transcript.length - 1 ]
				.text
		).toBe( 'Left as it was.' );
	} );

	it( 'a failure clears the pending proposal rather than leaving it clickable', async () => {
		sendTurn.mockResolvedValueOnce( pendingRun() );

		const { result } = renderHook( () => useAgent() );

		await act( async () => {
			await result.current.send( 'make Home the front page' );
		} );

		sendTurn.mockRejectedValueOnce( {
			code: 'sfcore_ai_provider_error',
			data: { detail: 'upstream died' },
		} );

		await act( async () => {
			await result.current.approve();
		} );

		await waitFor( () => expect( result.current.error ).not.toBeNull() );

		expect( result.current.pending ).toBeNull();
		expect( result.current.error.code ).toBe( 'sfcore_ai_provider_error' );
		expect( result.current.isWorking ).toBe( false );
	} );

	it( 'keeps the steps so the user can see what was done', async () => {
		sendTurn.mockResolvedValue(
			doneRun( {
				steps: [
					{ tool: 'swishfolio/site-info', summary: 'Site info', ok: true },
					{
						tool: 'swishfolio/read-page',
						summary: 'Read page — postId: "Home" (#10)',
						ok: false,
						errorCode: 'target_missing',
					},
				],
			} )
		);

		const { result } = renderHook( () => useAgent() );

		await act( async () => {
			await result.current.send( 'look around' );
		} );

		const answer = result.current.transcript[ 1 ];

		expect( answer.steps ).toHaveLength( 2 );
		expect( answer.steps[ 1 ].ok ).toBe( false );
	} );
} );
