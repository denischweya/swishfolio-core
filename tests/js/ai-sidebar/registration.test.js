/**
 * Registration: two plugins, one toolbar filter, and no side effects on import.
 *
 * Two, because the site agent has its own sidebar — it has nothing to do with
 * what is selected, and the block panel's "select a block" empty state would be
 * wrong for it. The repeat-call assertion is the one that matters either way:
 * initAiEditor() is reachable from more than one editor bootstrap.
 */

import { initAiEditor } from '../../../src/js/ai-sidebar/index';
import AgentSidebar from '../../../src/js/ai-sidebar/agent-sidebar';
import AiSidebar from '../../../src/js/ai-sidebar/sidebar';
import { PLUGIN_NAME } from '../../../src/js/ai/constants';
import * as wpHooks from '../mocks/wp-hooks';
import * as wpPlugins from '../mocks/wp-plugins';

afterEach( () => {
	wpHooks.__reset();
	wpPlugins.registerPlugin.mockClear();
} );

describe( 'initAiEditor', () => {
	it( 'registers both sidebars and the toolbar filter exactly once', () => {
		// Importing the module must not have registered anything by itself.
		expect( wpPlugins.registerPlugin ).not.toHaveBeenCalled();
		expect( wpHooks.__getFilters( 'editor.BlockEdit' ) ).toHaveLength( 0 );

		initAiEditor();
		initAiEditor();

		expect( wpPlugins.registerPlugin ).toHaveBeenCalledTimes( 2 );
		expect( PLUGIN_NAME ).toBe( 'sfcore-ai' );

		const [ blockName, blockSettings ] =
			wpPlugins.registerPlugin.mock.calls[ 0 ];

		expect( blockName ).toBe( PLUGIN_NAME );
		expect( blockSettings.render ).toBe( AiSidebar );
		expect( blockSettings.icon ).toBeTruthy();

		const [ agentName, agentSettings ] =
			wpPlugins.registerPlugin.mock.calls[ 1 ];

		expect( agentName ).toBe( PLUGIN_NAME + '-agent' );
		expect( agentSettings.render ).toBe( AgentSidebar );
		expect( agentSettings.icon ).toBeTruthy();

		expect( wpHooks.__getFilters( 'editor.BlockEdit' ) ).toHaveLength( 1 );
	} );
} );
