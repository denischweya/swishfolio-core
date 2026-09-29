/**
 * What is selected, and how much of it the AI may touch.
 */

import { __, _n, sprintf } from '@wordpress/i18n';

import { blockLabel } from '../ai/labels';

const SelectionSummary = ( { blockName, capability, editableCount } ) => (
	<div className="sfcore-ai-sidebar__selection">
		<div className="sfcore-ai-sidebar__selection-head">
			<strong className="sfcore-ai-sidebar__selection-block">
				{ blockLabel( blockName ) }
			</strong>
			<span className="sfcore-ai-sidebar__selection-count">
				{ sprintf(
					/* translators: %d: number of AI-editable properties. */
					_n(
						'%d editable property',
						'%d editable properties',
						editableCount,
						'swishfolio-core'
					),
					editableCount
				) }
			</span>
		</div>

		{ capability.summary !== '' && (
			<p className="sfcore-ai-sidebar__selection-summary">
				{ capability.summary }
			</p>
		) }

		{ capability.tier === 'content' && (
			<p className="sfcore-ai-sidebar__selection-tier">
				{ __(
					'Text only on this block — its styling is not AI-editable.',
					'swishfolio-core'
				) }
			</p>
		) }
	</div>
);

export default SelectionSummary;
