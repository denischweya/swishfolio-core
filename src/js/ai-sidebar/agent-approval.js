/**
 * The approval prompt for changes visitors would see.
 *
 * The whole batch is listed, not only the gated call, because that is what the
 * token approves and what will actually run. Showing one call and running three
 * would make the gate a formality.
 *
 * Declining sends nothing: the calls were never executed, so there is nothing to
 * undo and nothing the server needs to be told.
 */

import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const AgentApproval = ( { pending, onApprove, onDecline, isWorking } ) => (
	<Notice
		className="sfcore-ai-agent__approval"
		status="warning"
		isDismissible={ false }
	>
		<p className="sfcore-ai-agent__approval-title">
			{ __(
				'This changes what visitors see. Apply it?',
				'swishfolio-core'
			) }
		</p>

		<ul className="sfcore-ai-agent__approval-list">
			{ pending.calls.map( ( call, index ) => (
				<li key={ index }>
					<code>{ call.name }</code>
				</li>
			) ) }
		</ul>

		<p className="sfcore-ai-agent__approval-note">
			{ __(
				'Anything applied can be undone from Swish AI → Audit Log.',
				'swishfolio-core'
			) }
		</p>

		<div className="sfcore-ai-agent__approval-actions">
			<Button
				variant="primary"
				onClick={ onApprove }
				disabled={ isWorking }
				isBusy={ isWorking }
			>
				{ __( 'Apply', 'swishfolio-core' ) }
			</Button>
			<Button
				variant="tertiary"
				onClick={ onDecline }
				disabled={ isWorking }
			>
				{ __( 'Leave it', 'swishfolio-core' ) }
			</Button>
		</div>
	</Notice>
);

export default AgentApproval;
