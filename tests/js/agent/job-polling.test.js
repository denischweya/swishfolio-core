/**
 * The background-job path.
 *
 * A turn makes up to eight provider calls, so it runs as a job and the panel
 * polls. The two things worth proving are that steps appear while the work is
 * still running — a progress record nobody can see is not progress — and that a
 * failed job surfaces as an error rather than a spinner that never stops.
 */

import { act, renderHook, waitFor } from '@testing-library/react';

import { useAgent } from '../../../src/js/agent/use-agent';
import { fetchJob, sendTurn } from '../../../src/js/agent/rest';

jest.mock( '../../../src/js/agent/rest', () => {
	const actual = jest.requireActual( '../../../src/js/agent/rest' );

	return { ...actual, sendTurn: jest.fn(), fetchJob: jest.fn() };
} );

const queued = { queued: true, jobId: 'job123', status: 'queued', steps: [] };

describe( 'useAgent background jobs', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		sendTurn.mockReset();
		fetchJob.mockReset();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	/*
	 * sendTurn resolves as a microtask, and only then does settle() register
	 * the first poll timeout. Advancing timers before that happens fires
	 * nothing, so microtasks are drained first.
	 */
	const drain = async () => {
		await act( async () => {} );
	};

	const tick = async () => {
		await drain();
		await act( async () => {
			jest.advanceTimersByTime( 2000 );
		} );
		await drain();
	};

	it( 'asks for the turn to run in the background', async () => {
		sendTurn.mockResolvedValue( queued );
		fetchJob.mockResolvedValue( {
			status: 'done',
			steps: [],
			result: {
				status: 'done',
				message: 'Done.',
				steps: [],
				history: [],
				pending: [],
				confirmToken: '',
			},
		} );

		const { result } = renderHook( () => useAgent() );

		act( () => {
			result.current.send( 'rebuild my site' );
		} );

		expect( sendTurn ).toHaveBeenCalledWith(
			expect.objectContaining( { async: true } )
		);

		await tick();
	} );

	it( 'shows each step as it lands rather than all at the end', async () => {
		sendTurn.mockResolvedValue( queued );
		fetchJob
			.mockResolvedValueOnce( {
				status: 'running',
				steps: [
					{
						tool: 'swishfolio/site-info',
						summary: 'Site info',
						ok: true,
					},
				],
			} )
			.mockResolvedValueOnce( {
				status: 'done',
				steps: [],
				result: {
					status: 'done',
					message: 'All done.',
					steps: [],
					history: [],
					pending: [],
					confirmToken: '',
				},
			} );

		const { result } = renderHook( () => useAgent() );

		act( () => {
			result.current.send( 'look around' );
		} );

		await tick();

		expect( result.current.liveSteps ).toHaveLength( 1 );
		expect( result.current.isWorking ).toBe( true );

		await tick();

		await waitFor( () =>
			expect( result.current.isWorking ).toBe( false )
		);
		expect( result.current.liveSteps ).toHaveLength( 0 );
	} );

	it( 'a failed job becomes an error, not an endless spinner', async () => {
		sendTurn.mockResolvedValue( queued );
		fetchJob.mockResolvedValue( {
			status: 'failed',
			steps: [],
			error: { code: 'sfcore_job_failed', detail: 'it exploded' },
		} );

		const { result } = renderHook( () => useAgent() );

		act( () => {
			result.current.send( 'do something' );
		} );

		await tick();

		expect( result.current.error ).not.toBeNull();
		expect( result.current.error.code ).toBe( 'sfcore_job_failed' );
		expect( result.current.isWorking ).toBe( false );
	} );

	it( 'a synchronous reply is still handled, so the job layer is optional', async () => {
		// A site where background jobs are unavailable gets the run back
		// directly, and the panel must not sit waiting for a job that does not
		// exist.
		sendTurn.mockResolvedValue( {
			status: 'done',
			message: 'Straight away.',
			steps: [],
			history: [],
			pending: [],
			confirmToken: '',
		} );

		const { result } = renderHook( () => useAgent() );

		await act( async () => {
			await result.current.send( 'quick thing' );
		} );

		expect( fetchJob ).not.toHaveBeenCalled();
		expect( result.current.transcript[ 1 ].text ).toBe( 'Straight away.' );
	} );
} );
