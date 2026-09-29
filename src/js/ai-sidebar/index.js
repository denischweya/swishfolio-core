/**
 * Registration for the AI editing UI.
 *
 * Kept separate from the webpack entry point so the entry stays a two-line
 * bootstrap and everything here can be imported by tests without side effects
 * firing on import.
 */

import { registerPlugin } from '@wordpress/plugins';

import { PLUGIN_NAME } from '../ai/constants';
import AiIcon from './icon';
import AiSidebar from './sidebar';
import { registerAskAiToolbarButton } from './ask-ai-button';

let initialised = false;

/**
 * @return {void}
 */
export function initAiEditor() {
	if ( initialised ) {
		return;
	}

	initialised = true;

	registerPlugin( PLUGIN_NAME, {
		render: AiSidebar,
		icon: <AiIcon />,
	} );

	registerAskAiToolbarButton();
}

export { AiSidebar, registerAskAiToolbarButton };
