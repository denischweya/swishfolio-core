/**
 * What the agent is doing, while it is still doing it.
 *
 * A turn runs as a background job precisely because it can take a while, so the
 * panel would otherwise show a spinner for a minute and then everything at
 * once. Each completed step appears as it lands, which is also the only signal
 * that a long turn is progressing rather than stuck.
 */

import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const AgentProgress = ( { steps } ) => (
	<div className="sfcore-ai-agent__progress">
		<p className="sfcore-ai-agent__progress-label">
			<Spinner />
			<span>{ __( 'Working…', 'swishfolio-core' ) }</span>
		</p>

		{ steps && steps.length > 0 && (
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
						<span
							className="sfcore-ai-agent__step-mark"
							aria-hidden="true"
						>
							{ step.ok ? '✓' : '!' }
						</span>
						<span className="sfcore-ai-agent__step-text">
							{ step.summary }
						</span>
					</li>
				) ) }
			</ul>
		) }
	</div>
);

export default AgentProgress;
