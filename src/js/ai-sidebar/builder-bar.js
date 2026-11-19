/**
 * The floating AI prompt bar at the bottom of the editor.
 *
 * Prompts the whole page, or whatever blocks are selected, through
 * /swishfolio/v1/ai/builder. The request is queued as a background job (a whole
 * page can take more than a minute, longer than many hosts let a request live)
 * and polled at /ai/jobs/<id>; the job's steps, reported by the server while
 * the model streams, show as a live log. The finished job carries operations
 * with new block markup, applied here inside one registry.batch() so a single
 * Undo reverts the lot. Nothing is written server side.
 *
 * Rendered through a portal into document.body and positioned over the canvas
 * column with a ResizeObserver, so it stays centred on the content however the
 * sidebars open and close, and is never clipped by the editor's scroll areas.
 */

import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { createBlock, parse, serialize } from '@wordpress/blocks';
import { useRegistry, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { store as editorStore } from '@wordpress/editor';
import {
	createPortal,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import AiIcon from './icon';
import { describeError } from '../ai/rest';

const STORAGE_KEY = 'sfcore-ai-bar-open';
const LOG_KEY = 'sfcore-ai-bar-log';
const MAX_PROMPT = 2000;
const POLL_MS = 1000;
const POLL_RETRIES = 3;

const SUGGESTIONS = {
	empty: [
		__( 'Write a home page for my business', 'swishfolio-core' ),
		__( 'Write an About page', 'swishfolio-core' ),
		__( 'Write a Services page', 'swishfolio-core' ),
		__( 'Write a Contact page', 'swishfolio-core' ),
	],
	page: [
		__( 'Add a testimonials section', 'swishfolio-core' ),
		__( 'Add an FAQ section before the footer', 'swishfolio-core' ),
		__( 'Tighten all the copy on this page', 'swishfolio-core' ),
	],
	selection: [
		__( 'Make it shorter', 'swishfolio-core' ),
		__( 'Rewrite in a friendlier tone', 'swishfolio-core' ),
		__( 'Add a call to action below this', 'swishfolio-core' ),
	],
};

function readFlag( key ) {
	try {
		return window.localStorage.getItem( key ) !== '0';
	} catch ( e ) {
		return true;
	}
}

function writeFlag( key, on ) {
	try {
		window.localStorage.setItem( key, on ? '1' : '0' );
	} catch ( e ) {}
}

const wait = ( ms ) => new Promise( ( resolve ) => window.setTimeout( resolve, ms ) );

/**
 * Follow a queued job until it settles, reporting its steps as they change.
 *
 * @param {string}   jobId    Job ID.
 * @param {Function} onSteps  Receives the server's steps on every poll.
 * @param {Object}   cancel   { current: bool } set to stop following.
 * @return {Promise<Object>} The job result; rejects with the job's error.
 */
async function followJob( jobId, onSteps, cancel ) {
	let failures = 0;

	while ( ! cancel.current ) {
		await wait( POLL_MS );
		if ( cancel.current ) {
			break;
		}

		let job;
		try {
			job = await apiFetch( { path: '/swishfolio/v1/ai/jobs/' + jobId } );
			failures = 0;
		} catch ( err ) {
			// A dropped poll is not a failed job; give the network a few tries.
			if ( ++failures > POLL_RETRIES || ( err && err.code && err.code !== 'fetch_error' ) ) {
				throw err;
			}
			continue;
		}

		onSteps( Array.isArray( job.steps ) ? job.steps : [] );

		if ( job.status === 'done' ) {
			return job.result || {};
		}
		if ( job.status === 'failed' ) {
			const error = job.error || {};
			throw {
				code: error.code || 'sfcore_job_failed',
				message: error.message,
				data: error.data || { detail: error.detail },
			};
		}
	}

	throw { name: 'AbortError' };
}

const StepIcon = ( { status } ) => {
	if ( status === 'active' ) {
		return <span className="sfcore-ai-bar__spin" aria-hidden="true" />;
	}
	if ( status === 'failed' ) {
		return (
			<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" className="sfcore-ai-bar__step-x">
				<path fill="currentColor" d="M4.2 3.1 8 6.9l3.8-3.8 1.1 1.1L9.1 8l3.8 3.8-1.1 1.1L8 9.1l-3.8 3.8-1.1-1.1L6.9 8 3.1 4.2z" />
			</svg>
		);
	}
	return (
		<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" className="sfcore-ai-bar__step-ok">
			<path fill="currentColor" d="M6.4 11.2 3.2 8l1.1-1.1 2.1 2.1 5.3-5.3 1.1 1.1z" />
		</svg>
	);
};

const StepLog = ( { steps } ) => {
	const ref = useRef( null );

	// Keep the newest step in view as the log grows.
	useEffect( () => {
		if ( ref.current ) {
			ref.current.scrollTop = ref.current.scrollHeight;
		}
	}, [ steps ] );

	return (
	<ol ref={ ref } className="sfcore-ai-bar__log" aria-label={ __( 'AI progress', 'swishfolio-core' ) }>
		{ steps.map( ( step ) => (
			<li key={ step.key } className={ 'is-' + step.status }>
				<StepIcon status={ step.status } />
				<span className="sfcore-ai-bar__log-label">{ step.label }</span>
				{ step.detail && <span className="sfcore-ai-bar__log-detail">{ step.detail }</span> }
				{ step.status !== 'active' && step.d >= 1 && (
					<span className="sfcore-ai-bar__log-time">{ Math.round( step.d ) }s</span>
				) }
			</li>
		) ) }
	</ol>
	);
};

/**
 * Re-create any block that fails validation from its parsed attributes, the
 * same thing the editor's "Attempt recovery" does, so a small markup slip from
 * the model never lands as a broken block.
 *
 * @param {Array} blocks Parsed blocks.
 * @return {Array} Valid blocks.
 */
function recover( blocks ) {
	return blocks.map( ( block ) => {
		const inner = recover( block.innerBlocks || [] );

		if ( block.isValid === false ) {
			return createBlock( block.name, block.attributes, inner );
		}

		return { ...block, innerBlocks: inner };
	} );
}

function toBlocks( markup ) {
	return recover( parse( markup ) ).filter( ( block ) => block.name );
}

function useCanvasBox() {
	const [ box, setBox ] = useState( null );

	useEffect( () => {
		const doc = window.document;
		let el = null;
		let observer = null;

		const measure = () => {
			if ( ! el || ! doc.body.contains( el ) ) {
				el = doc.querySelector( '.interface-interface-skeleton__content' );
				if ( el && observer ) {
					observer.disconnect();
					observer.observe( el );
				}
			}
			if ( ! el ) {
				return;
			}
			const rect = el.getBoundingClientRect();
			setBox( { left: rect.left, width: rect.width, bottom: window.innerHeight - rect.bottom } );
		};

		observer = new window.ResizeObserver( measure );
		measure();
		window.addEventListener( 'resize', measure );
		const timer = window.setInterval( measure, 1500 );

		return () => {
			observer.disconnect();
			window.removeEventListener( 'resize', measure );
			window.clearInterval( timer );
		};
	}, [] );

	return box;
}

const BuilderBar = () => {
	const registry = useRegistry();
	const box = useCanvasBox();

	const { postId, selectedIds, selectedLabel, isEmpty } = useSelect( ( select ) => {
		const be = select( blockEditorStore );
		const multi = be.getMultiSelectedBlockClientIds();
		const single = be.getSelectedBlockClientId();
		const ids = multi.length ? multi : single ? [ single ] : [];
		const order = be.getBlockOrder( '' );
		let label = '';

		if ( ids.length === 1 ) {
			const name = be.getBlockName( ids[ 0 ] );
			const type = name ? select( 'core/blocks' ).getBlockType( name ) : null;
			label = type ? type.title : name;
		} else if ( ids.length > 1 ) {
			/* translators: %d: number of selected blocks. */
			label = sprintf( __( '%d blocks', 'swishfolio-core' ), ids.length );
		}

		return {
			postId: select( editorStore ).getCurrentPostId(),
			selectedIds: ids,
			selectedLabel: label,
			isEmpty:
				order.length === 0 ||
				order.every( ( id ) => be.isUnmodifiedDefaultBlock?.( be.getBlock( id ) ) ),
		};
	}, [] );

	const [ open, setOpen ] = useState( () => readFlag( STORAGE_KEY ) );
	const [ showLog, setShowLog ] = useState( () => readFlag( LOG_KEY ) );
	const [ steps, setSteps ] = useState( [] );
	const [ wholePage, setWholePage ] = useState( false );
	const [ prompt, setPrompt ] = useState( '' );
	const [ working, setWorking ] = useState( false );
	const [ elapsed, setElapsed ] = useState( 0 );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( null );
	const cancelRef = useRef( null );
	const inputRef = useRef( null );

	const scope = selectedIds.length && ! wholePage ? 'selection' : 'page';
	const kind = scope === 'selection' ? 'selection' : isEmpty ? 'empty' : 'page';

	// A new selection means the user is pointing at something new: go back to
	// targeting it rather than staying stuck on "whole page".
	const selectionKey = selectedIds.join( ',' );
	useEffect( () => {
		setWholePage( false );
	}, [ selectionKey ] );

	useEffect( () => {
		if ( ! working ) {
			return undefined;
		}
		setElapsed( 0 );
		const started = Date.now();
		const timer = window.setInterval( () => setElapsed( Math.round( ( Date.now() - started ) / 1000 ) ), 1000 );
		return () => window.clearInterval( timer );
	}, [ working ] );

	const toggle = useCallback( ( next ) => {
		setOpen( next );
		writeFlag( STORAGE_KEY, next );
		if ( next ) {
			window.setTimeout( () => inputRef.current && inputRef.current.focus(), 50 );
		}
	}, [] );

	const apply = useCallback(
		( operations, targetIds, rootClientId ) => {
			const be = registry.select( blockEditorStore );
			const dispatch = registry.dispatch( blockEditorStore );
			const created = [];

			registry.batch( () => {
				const inserts = operations.filter( ( op ) => op.op === 'insert_before' || op.op === 'insert_after' );
				const replaces = operations.filter( ( op ) => op.op === 'replace' );
				const removes = operations.filter( ( op ) => op.op === 'remove' );
				const appends = operations.filter( ( op ) => op.op === 'append' );

				inserts.forEach( ( op ) => {
					const anchor = targetIds[ op.index ];
					const blocks = toBlocks( op.markup );
					if ( ! anchor || ! blocks.length ) {
						return;
					}
					const index = be.getBlockIndex( anchor ) + ( op.op === 'insert_after' ? 1 : 0 );
					dispatch.insertBlocks( blocks, index, be.getBlockRootClientId( anchor ) || undefined, false );
					created.push( ...blocks );
				} );

				replaces.forEach( ( op ) => {
					const anchor = targetIds[ op.index ];
					const blocks = toBlocks( op.markup );
					if ( ! anchor || ! blocks.length ) {
						return;
					}
					dispatch.replaceBlocks( anchor, blocks );
					created.push( ...blocks );
				} );

				const removeIds = removes.map( ( op ) => targetIds[ op.index ] ).filter( Boolean );

				// Appending to an empty page also clears the placeholder paragraph.
				if ( appends.length && scope === 'page' && isEmpty ) {
					removeIds.push( ...targetIds );
				}

				if ( removeIds.length ) {
					dispatch.removeBlocks( removeIds, false );
				}

				appends.forEach( ( op ) => {
					const blocks = toBlocks( op.markup );
					if ( ! blocks.length ) {
						return;
					}
					const last = scope === 'selection' ? targetIds[ targetIds.length - 1 ] : null;
					const index = last && be.getBlock( last )
						? be.getBlockIndex( last ) + 1
						: be.getBlockOrder( rootClientId || '' ).length;
					dispatch.insertBlocks( blocks, index, rootClientId || undefined, false );
					created.push( ...blocks );
				} );
			} );

			if ( created.length ) {
				dispatch.selectBlock( created[ 0 ].clientId );
			}

			return created.length;
		},
		[ registry, scope, isEmpty ]
	);

	const submit = useCallback( async ( text ) => {
		const instruction = ( text !== undefined ? text : prompt ).trim();
		if ( ! instruction || working || typeof postId !== 'number' ) {
			return;
		}

		const be = registry.select( blockEditorStore );
		const targetIds = scope === 'selection' ? selectedIds : be.getBlockOrder( '' );
		const rootClientId = scope === 'selection' ? be.getBlockRootClientId( targetIds[ 0 ] ) : '';
		const sections = scope === 'page' && isEmpty ? [] : targetIds.map( ( id ) => serialize( [ be.getBlock( id ) ] ) );
		const reference = scope === 'selection' ? serialize( be.getBlocks() ) : '';

		const cancel = { current: false };
		cancelRef.current = cancel;
		setWorking( true );
		setError( null );
		setResult( null );
		setSteps( [ { key: 'send', label: __( 'Sending your request', 'swishfolio-core' ), detail: '', status: 'active', d: 0 } ] );

		// Server steps, then the editor's own "applying" step after them.
		let serverSteps = [];
		const local = ( step ) => setSteps( [ ...serverSteps, step ] );

		try {
			const queued = await apiFetch( {
				path: '/swishfolio/v1/ai/builder',
				method: 'POST',
				data: {
					postId,
					instruction,
					scope,
					sections,
					reference,
					title: registry.select( editorStore ).getEditedPostAttribute( 'title' ) || '',
					async: true,
				},
			} );

			if ( cancel.current ) {
				throw { name: 'AbortError' };
			}

			const response = queued && queued.queued
				? await followJob( queued.jobId, ( next ) => {
					serverSteps = next;
					setSteps( next );
				}, cancel )
				: queued;

			if ( cancel.current ) {
				throw { name: 'AbortError' };
			}

			const ops = Array.isArray( response.operations ) ? response.operations : [];
			local( { key: 'apply', label: __( 'Applying to the page', 'swishfolio-core' ), detail: '', status: 'active', d: 0 } );
			const count = ops.length ? apply( ops, targetIds, rootClientId ) : 0;
			local( {
				key: 'apply',
				label: count
					? sprintf(
						/* translators: %d: number of changes. */
						_n( 'Applied %d change', 'Applied %d changes', count, 'swishfolio-core' ),
						count
					)
					: __( 'Nothing to apply', 'swishfolio-core' ),
				detail: count ? __( 'One Undo reverts it all', 'swishfolio-core' ) : '',
				status: 'done',
				d: 0,
			} );

			setResult( {
				summary: response.summary || '',
				count,
			} );
			if ( count ) {
				setPrompt( '' );
			}
		} catch ( err ) {
			if ( err && err.name === 'AbortError' ) {
				setError( __( 'Stopped. Nothing was changed on the page.', 'swishfolio-core' ) );
			} else {
				const described = describeError( err );
				// Prefer the actionable hint, then the server's own sentence
				// (it is written for users), then the generic line.
				setError( described.hint || ( err && err.message ) || described.message );
			}
			setSteps( ( current ) => current.map( ( step ) => ( step.status === 'active' ? { ...step, status: 'failed' } : step ) ) );
		} finally {
			cancelRef.current = null;
			setWorking( false );
		}
	}, [ prompt, working, postId, scope, selectedIds, isEmpty, registry, apply ] );

	// The server finishes the job it started, but its result is discarded:
	// nothing reaches the page after Stop.
	const stop = () => {
		if ( cancelRef.current ) {
			cancelRef.current.current = true;
		}
	};

	const toggleLog = () => {
		setShowLog( ! showLog );
		writeFlag( LOG_KEY, ! showLog );
	};

	const undo = () => {
		registry.dispatch( editorStore ).undo();
		setResult( null );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' && ! event.shiftKey && ! event.nativeEvent.isComposing ) {
			event.preventDefault();
			submit();
		} else if ( event.key === 'Escape' ) {
			event.preventDefault();
			toggle( false );
		}
	};

	// Grow the textarea with its content, up to the CSS max-height.
	useEffect( () => {
		const el = inputRef.current;
		if ( el ) {
			el.style.height = 'auto';
			el.style.height = el.scrollHeight + 'px';
		}
	}, [ prompt, open ] );

	if ( typeof postId !== 'number' || ! box ) {
		return null;
	}

	const style = { left: box.left, width: box.width, bottom: box.bottom + 36 };
	const current = steps.filter( ( step ) => step.status === 'active' ).pop() || steps[ steps.length - 1 ];

	const placeholder = {
		empty: __( 'Describe the page you want, and AI will write it…', 'swishfolio-core' ),
		page: __( 'Ask AI to change or add to this page…', 'swishfolio-core' ),
		selection: __( 'Ask AI to change the selected block…', 'swishfolio-core' ),
	}[ kind ];

	const workingLabel =
		kind === 'empty'
			? __( 'Writing your page', 'swishfolio-core' )
			: kind === 'page'
			? __( 'Working on the page', 'swishfolio-core' )
			: __( 'Working on the selection', 'swishfolio-core' );

	const bar = (
		<div className="sfcore-ai-bar" style={ style }>
			{ ! open ? (
				<button
					type="button"
					className="sfcore-ai-bar__pill"
					onClick={ () => toggle( true ) }
					aria-expanded="false"
				>
					<AiIcon />
					<span>{ __( 'Ask AI', 'swishfolio-core' ) }</span>
				</button>
			) : (
				<div className="sfcore-ai-bar__panel" role="region" aria-label={ __( 'AI builder', 'swishfolio-core' ) }>
					{ ( result || error ) && ! working && (
						<div className={ 'sfcore-ai-bar__status' + ( error ? ' is-error' : '' ) }>
							<p>
								{ error
									? error
									: result.count
									? result.summary ||
									  sprintf(
											/* translators: %d: number of changes. */
											_n( 'Applied %d change.', 'Applied %d changes.', result.count, 'swishfolio-core' ),
											result.count
									  )
									: result.summary || __( 'AI suggested no changes.', 'swishfolio-core' ) }
							</p>
							<div className="sfcore-ai-bar__status-actions">
								{ steps.length > 1 && (
									<Button variant="tertiary" size="small" onClick={ toggleLog } aria-expanded={ showLog }>
										{ showLog ? __( 'Hide steps', 'swishfolio-core' ) : __( 'Steps', 'swishfolio-core' ) }
									</Button>
								) }
								{ result && result.count > 0 && (
									<Button variant="tertiary" size="small" onClick={ undo }>
										{ __( 'Undo', 'swishfolio-core' ) }
									</Button>
								) }
								<Button
									variant="tertiary"
									size="small"
									onClick={ () => {
										setResult( null );
										setError( null );
										setSteps( [] );
									} }
								>
									{ __( 'Dismiss', 'swishfolio-core' ) }
								</Button>
							</div>
						</div>
					) }
					{ ( result || error ) && ! working && showLog && steps.length > 1 && (
						<StepLog steps={ steps } />
					) }

					<div className="sfcore-ai-bar__row">
						<button
							type="button"
							className="sfcore-ai-bar__target"
							onClick={ () => selectedIds.length && setWholePage( ! wholePage ) }
							disabled={ ! selectedIds.length || working }
							title={
								selectedIds.length
									? __( 'Switch between the selection and the whole page', 'swishfolio-core' )
									: __( 'Select a block to target just that block', 'swishfolio-core' )
							}
						>
							{ scope === 'selection' ? selectedLabel : __( 'Whole page', 'swishfolio-core' ) }
						</button>

						<textarea
							ref={ inputRef }
							className="sfcore-ai-bar__input"
							rows={ 1 }
							value={ prompt }
							placeholder={ placeholder }
							disabled={ working }
							onChange={ ( event ) => setPrompt( event.target.value.slice( 0, MAX_PROMPT ) ) }
							onKeyDown={ onKeyDown }
							aria-label={ placeholder }
						/>

						{ working ? (
							<button type="button" className="sfcore-ai-bar__send is-stop" onClick={ stop } aria-label={ __( 'Stop', 'swishfolio-core' ) }>
								<span className="sfcore-ai-bar__stop-square" />
							</button>
						) : (
							<button
								type="button"
								className="sfcore-ai-bar__send"
								onClick={ () => submit() }
								disabled={ ! prompt.trim() }
								aria-label={ __( 'Send', 'swishfolio-core' ) }
							>
								<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
									<path fill="currentColor" d="M12 4l7 7-1.4 1.4L13 7.8V20h-2V7.8l-4.6 4.6L5 11z" />
								</svg>
							</button>
						) }

						<button
							type="button"
							className="sfcore-ai-bar__collapse"
							onClick={ () => toggle( false ) }
							aria-label={ __( 'Hide AI bar', 'swishfolio-core' ) }
							title={ __( 'Hide (Esc)', 'swishfolio-core' ) }
						>
							<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
								<path fill="currentColor" d="M12 15.4l-6-6L7.4 8l4.6 4.6L16.6 8 18 9.4z" />
							</svg>
						</button>
					</div>

					{ working ? (
						<div className="sfcore-ai-bar__progress">
							<div className="sfcore-ai-bar__working">
								<span className="sfcore-ai-bar__now">
									<span className="sfcore-ai-bar__shimmer">{ current ? current.label : workingLabel }</span>
									{ current && current.detail && (
										<span className="sfcore-ai-bar__now-detail">{ current.detail }</span>
									) }
								</span>
								<span className="sfcore-ai-bar__working-side">
									<button type="button" className="sfcore-ai-bar__log-toggle" onClick={ toggleLog } aria-expanded={ showLog }>
										{ showLog ? __( 'Hide steps', 'swishfolio-core' ) : sprintf(
											/* translators: %d: number of steps so far. */
											__( 'Steps (%d)', 'swishfolio-core' ),
											steps.length
										) }
									</button>
									<span className="sfcore-ai-bar__elapsed">{ elapsed }s</span>
								</span>
							</div>
							{ showLog && steps.length > 1 && <StepLog steps={ steps } /> }
						</div>
					) : (
						! prompt && ! result && ! error && (
							<div className="sfcore-ai-bar__chips">
								{ SUGGESTIONS[ kind ].map( ( suggestion ) => (
									<button
										key={ suggestion }
										type="button"
										className="sfcore-ai-bar__chip"
										onClick={ () => {
											setPrompt( suggestion );
											inputRef.current && inputRef.current.focus();
										} }
									>
										{ suggestion }
									</button>
								) ) }
							</div>
						)
					) }
				</div>
			) }
		</div>
	);

	return createPortal( bar, window.document.body );
};

export default BuilderBar;
