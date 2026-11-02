/**
 * The conversation, as the owner reads it.
 *
 * Tool calls are shown as one line each, in the words CallSummary produced
 * server-side. The raw result is deliberately absent: a page's whole block tree
 * is exactly what the model needed and pure noise to the person reading.
 */

import { __ } from '@wordpress/i18n';

const StepList = ( { steps } ) => {
	if ( ! steps || ! steps.length ) {
		return null;
	}

	return (
		<ul className="sfcore-ai-agent__steps">
			{ steps.map( ( step, index ) => (
				<li
					key={ index }
					className={
						step.ok
							? 'sfcore-ai-agent__step'
							: 'sfcore-ai-agent__step is-error'
					}
				>
					<span className="sfcore-ai-agent__step-mark" aria-hidden="true">
						{ step.ok ? '✓' : '!' }
					</span>
					<span className="sfcore-ai-agent__step-text">
						{ step.summary }
					</span>
				</li>
			) ) }
		</ul>
	);
};

const AgentTranscript = ( { transcript } ) => {
	if ( ! transcript.length ) {
		return (
			<p className="sfcore-ai-agent__empty">
				{ __(
					'Ask for a change to this site — a new page, a rewrite, a different look. Anything visitors would see is shown to you for approval first.',
					'swishfolio-core'
				) }
			</p>
		);
	}

	return (
		<ol className="sfcore-ai-agent__transcript">
			{ transcript.map( ( entry, index ) => (
				<li
					key={ index }
					className={ 'sfcore-ai-agent__turn is-' + entry.role }
				>
					<span className="sfcore-ai-agent__who">
						{ entry.role === 'user'
							? __( 'You', 'swishfolio-core' )
							: __( 'AI', 'swishfolio-core' ) }
					</span>

					{ entry.text && (
						<p className="sfcore-ai-agent__text">{ entry.text }</p>
					) }

					<StepList steps={ entry.steps } />

					{ entry.capped && (
						<p className="sfcore-ai-agent__capped">
							{ __(
								'It stopped after too many steps. Try asking for a smaller piece.',
								'swishfolio-core'
							) }
						</p>
					) }
				</li>
			) ) }
		</ol>
	);
};

export default AgentTranscript;
