/**
 * The site agent sidebar.
 *
 * A second PluginSidebar rather than a tab inside the block-editing one. The
 * two answer different questions: the block panel is meaningless without a
 * selection and says so, while a request to manage the site has nothing to do
 * with what is selected. Folding them together would mean one panel whose empty
 * state is wrong half the time.
 */

import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { Button, Spinner, TextareaControl } from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { AGENT_SIDEBAR_NAME, MAX_INSTRUCTION_LENGTH } from '../agent/constants';
import { useAgent } from '../agent/use-agent';
import AgentApproval from './agent-approval';
import AgentTranscript from './agent-transcript';
import ErrorPanel from './error-panel';
import AiIcon from './icon';

const AgentBody = () => {
	const agent = useAgent();
	const [ instruction, setInstruction ] = useState( '' );

	const canSubmit = instruction.trim() !== '' && ! agent.isWorking;

	const submit = () => {
		if ( ! canSubmit ) {
			return;
		}

		const text = instruction;

		setInstruction( '' );
		agent.send( text );
	};

	const handleKeyDown = ( event ) => {
		if ( ( event.metaKey || event.ctrlKey ) && event.key === 'Enter' ) {
			event.preventDefault();
			submit();
		}
	};

	return (
		<div className="sfcore-ai-agent">
			<AgentTranscript transcript={ agent.transcript } />

			{ agent.error && <ErrorPanel error={ agent.error } /> }

			{ agent.pending && (
				<AgentApproval
					pending={ agent.pending }
					onApprove={ agent.approve }
					onDecline={ agent.decline }
					isWorking={ agent.isWorking }
				/>
			) }

			<div className="sfcore-ai-agent__prompt">
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Ask for a change', 'swishfolio-core' ) }
					value={ instruction }
					onChange={ ( value ) =>
						setInstruction(
							value.slice( 0, MAX_INSTRUCTION_LENGTH )
						)
					}
					onKeyDown={ handleKeyDown }
					rows={ 3 }
					disabled={ agent.isWorking }
				/>

				<div className="sfcore-ai-agent__prompt-actions">
					<Button
						variant="primary"
						onClick={ submit }
						disabled={ ! canSubmit }
						isBusy={ agent.isWorking }
					>
						{ agent.isWorking
							? __( 'Working…', 'swishfolio-core' )
							: __( 'Send', 'swishfolio-core' ) }
					</Button>
					{ agent.isWorking && <Spinner /> }
				</div>
			</div>
		</div>
	);
};

const AgentSidebar = () => (
	<Fragment>
		<PluginSidebarMoreMenuItem
			target={ AGENT_SIDEBAR_NAME }
			icon={ <AiIcon /> }
		>
			{ __( 'Swish AI: Site', 'swishfolio-core' ) }
		</PluginSidebarMoreMenuItem>

		<PluginSidebar
			name={ AGENT_SIDEBAR_NAME }
			title={ __( 'Swish AI: Site', 'swishfolio-core' ) }
			icon={ <AiIcon /> }
		>
			<AgentBody />
		</PluginSidebar>
	</Fragment>
);

export { AgentBody };
export default AgentSidebar;
