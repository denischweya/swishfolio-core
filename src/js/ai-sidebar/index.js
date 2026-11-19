/**
 * Registration for the AI editing UI.
 *
 * Kept separate from the webpack entry point so the entry stays a two-line
 * bootstrap and everything here can be imported by tests without side effects
 * firing on import.
 */

import { registerPlugin } from '@wordpress/plugins';

import { PLUGIN_NAME } from '../ai/constants';
import AgentSidebar from './agent-sidebar';
import AiIcon from './icon';
import AiSidebar from './sidebar';
import BuilderBar from './builder-bar';
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

	// A second plugin, so the site agent gets its own sidebar: it has nothing
	// to do with what is selected, and the block panel's empty state would be
	// wrong for it.
	registerPlugin( PLUGIN_NAME + '-agent', {
		render: AgentSidebar,
		icon: <AiIcon />,
	} );

	// The floating prompt bar at the bottom of the canvas. No icon: it is not a
	// sidebar, so it never appears in the plugin menus.
	registerPlugin( PLUGIN_NAME + '-builder', {
		render: BuilderBar,
	} );

	registerAskAiToolbarButton();
}

export { AgentSidebar, AiSidebar, registerAskAiToolbarButton };
