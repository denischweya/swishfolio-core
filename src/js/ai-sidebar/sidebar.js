/**
 * The AI sidebar.
 *
 * `PluginSidebar` from `@wordpress/editor` renders a ComplementaryArea with
 * scope "core" — the scope the post editor's InterfaceSkeleton uses — into the
 * top document, outside the iframed canvas, so the iframe is irrelevant here.
 */

import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { SIDEBAR_NAME } from '../ai/constants';
import { isAvailable } from '../ai/capabilities';
import { blockLabel } from '../ai/labels';
import AiIcon from './icon';
import ChangePreview from './change-preview';
import ErrorPanel from './error-panel';
import PromptForm from './prompt-form';
import SelectionSummary from './selection-summary';
import {
	STATUS_ERROR,
	STATUS_PROPOSED,
	STATUS_WORKING,
	useAiEditing,
} from './use-ai-editing';

const SidebarBody = () => {
	const ai = useAiEditing();

	if ( ! isAvailable() ) {
		return (
			<p className="sfcore-ai-sidebar__empty">
				{ __(
					'AI editing is unavailable: no block capability manifests were loaded.',
					'swishfolio-core'
				) }
			</p>
		);
	}

	if ( ! ai.clientId ) {
		return (
			<p className="sfcore-ai-sidebar__empty">
				{ __(
					'Select a block, then describe the change you want.',
					'swishfolio-core'
				) }
			</p>
		);
	}

	if ( ! ai.capability ) {
		return (
			<div className="sfcore-ai-sidebar__unsupported">
				<p>
					{ sprintf(
						/* translators: %s: block title. */
						__(
							'%s has no AI-editable properties yet.',
							'swishfolio-core'
						),
						blockLabel( ai.blockName )
					) }
				</p>
				<p className="sfcore-ai-sidebar__hint">
					{ __(
						'Select a Swishfolio block, or a paragraph, heading, list item or button.',
						'swishfolio-core'
					) }
				</p>
			</div>
		);
	}

	return (
		<Fragment>
			<SelectionSummary
				blockName={ ai.blockName }
				capability={ ai.capability }
				editableCount={ ai.editableCount }
			/>

			{ ai.status === STATUS_ERROR && ai.error && (
				<ErrorPanel error={ ai.error } />
			) }

			{ ai.status === STATUS_PROPOSED && ai.proposal ? (
				<ChangePreview
					proposal={ ai.proposal }
					selectedActions={ ai.selectedActions }
					onToggle={ ai.toggleAction }
					onApplyAll={ ai.applyAll }
					onApplySelected={ ai.applySelected }
					onDiscard={ ai.discard }
				/>
			) : (
				<PromptForm
					capability={ ai.capability }
					instruction={ ai.instruction }
					setInstruction={ ai.setInstruction }
					onSubmit={ ai.submit }
					isWorking={ ai.status === STATUS_WORKING }
				/>
			) }
		</Fragment>
	);
};

const AiSidebar = () => (
	<Fragment>
		<PluginSidebarMoreMenuItem target={ SIDEBAR_NAME } icon={ <AiIcon /> }>
			{ __( 'Ask AI', 'swishfolio-core' ) }
		</PluginSidebarMoreMenuItem>

		<PluginSidebar
			name={ SIDEBAR_NAME }
			title={ __( 'Ask AI', 'swishfolio-core' ) }
			icon={ <AiIcon /> }
			className="sfcore-ai-sidebar"
		>
			<div className="sfcore-ai-sidebar__body">
				<SidebarBody />
			</div>
		</PluginSidebar>
	</Fragment>
);

export default AiSidebar;
