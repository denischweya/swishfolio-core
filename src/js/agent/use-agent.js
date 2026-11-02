/**
 * Conversation state for the site agent.
 *
 * The transcript the user reads and the history the server needs are different
 * things, and both are kept. The transcript is what was said and done, in order,
 * including steps and errors; the history is the provider-shaped message list,
 * which contains tool-call plumbing nobody wants to read. Deriving one from the
 * other in either direction would mean either showing the user raw tool JSON or
 * throwing away what the next turn has to replay.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	JOB_STATUS_DONE,
	JOB_STATUS_FAILED,
	POLL_INTERVAL_MS,
	STATUS_DONE,
	STATUS_ERROR,
	STATUS_IDLE,
	STATUS_PENDING,
	STATUS_WORKING,
} from './constants';
import { describeError, fetchJob, sendTurn } from './rest';

/**
 * @return {Object} Panel state and the two actions that drive it.
 */
export function useAgent() {
	const [ transcript, setTranscript ] = useState( [] );
	const [ history, setHistory ] = useState( [] );
	const [ status, setStatus ] = useState( STATUS_IDLE );
	const [ error, setError ] = useState( null );
	const [ pending, setPending ] = useState( null );
	const [ jobId, setJobId ] = useState( null );
	const [ liveSteps, setLiveSteps ] = useState( [] );
	const mounted = useRef( true );

	useEffect(
		() => () => {
			mounted.current = false;
		},
		[]
	);

	const applyRun = useCallback( ( run ) => {
		setJobId( null );
		setLiveSteps( [] );

		setHistory( Array.isArray( run.history ) ? run.history : [] );
		setStatus( run.status === STATUS_PENDING ? STATUS_PENDING : STATUS_DONE );
		setPending(
			run.status === STATUS_PENDING
				? { calls: run.pending || [], confirmToken: run.confirmToken }
				: null
		);

		setTranscript( ( current ) => [
			...current,
			{
				role: 'assistant',
				text: run.message,
				steps: run.steps || [],
				capped: run.status === 'capped',
			},
		] );
	}, [] );

	const fail = useCallback( ( rejection ) => {
		const described = describeError( rejection );

		setError( described );
		setStatus( STATUS_ERROR );
		setPending( null );
		setJobId( null );
		setLiveSteps( [] );
	}, [] );

	/*
	 * A turn runs as a background job because up to eight provider calls can
	 * outlast PHP's execution limit. Polling is how its steps reach the panel
	 * while it works, rather than all at once when it finishes.
	 */
	const settle = useCallback(
		( queued ) => {
			if ( ! queued || ! queued.queued ) {
				applyRun( queued );

				return Promise.resolve();
			}

			setJobId( queued.jobId );

			return new Promise( ( resolve ) => {
				const poll = () => {
					if ( ! mounted.current ) {
						resolve();

						return;
					}

					fetchJob( queued.jobId )
						.then( ( job ) => {
							if ( ! mounted.current ) {
								resolve();

								return;
							}

							setLiveSteps( job.steps || [] );

							if ( job.status === JOB_STATUS_FAILED ) {
								fail( {
									code:
										( job.error && job.error.code ) ||
										'sfcore_job_failed',
									data: {
										detail:
											job.error && job.error.detail,
									},
								} );
								resolve();

								return;
							}

							if ( job.status === JOB_STATUS_DONE ) {
								applyRun( job.result );
								resolve();

								return;
							}

							setTimeout( poll, POLL_INTERVAL_MS );
						} )
						.catch( ( rejection ) => {
							fail( rejection );
							resolve();
						} );
				};

				setTimeout( poll, POLL_INTERVAL_MS );
			} );
		},
		[ applyRun, fail ]
	);

	/**
	 * @param {string} instruction What the user typed.
	 *
	 * @return {Promise<void>} Resolves when the turn settles.
	 */
	const send = useCallback(
		( instruction ) => {
			const text = String( instruction || '' ).trim();

			if ( ! text ) {
				return Promise.resolve();
			}

			setError( null );
			setPending( null );
			setStatus( STATUS_WORKING );
			setTranscript( ( current ) => [
				...current,
				{ role: 'user', text },
			] );

			return sendTurn( { instruction: text, history, async: true } )
				.then( settle )
				.catch( fail );
		},
		[ history, settle, fail ]
	);

	/**
	 * Approve the pending proposal and let the run finish.
	 *
	 * @return {Promise<void>} Resolves when the turn settles.
	 */
	const approve = useCallback( () => {
		if ( ! pending ) {
			return Promise.resolve();
		}

		const token = pending.confirmToken;

		setError( null );
		setStatus( STATUS_WORKING );
		setPending( null );

		return sendTurn( {
			instruction: '',
			history,
			confirmToken: token,
			async: true,
		} )
			.then( settle )
			.catch( fail );
	}, [ pending, history, settle, fail ] );

	/**
	 * Decline the proposal. Nothing is sent: the pending calls were never run,
	 * so there is nothing to undo and nothing for the server to be told.
	 *
	 * @return {void}
	 */
	const decline = useCallback( () => {
		setPending( null );
		setStatus( STATUS_DONE );
		setTranscript( ( current ) => [
			...current,
			{
				role: 'assistant',
				text: __( 'Left as it was.', 'swishfolio-core' ),
				steps: [],
			},
		] );
	}, [] );

	return {
		transcript,
		status,
		error,
		pending,
		jobId,
		liveSteps,
		send,
		approve,
		decline,
		isWorking: status === STATUS_WORKING,
	};
}
