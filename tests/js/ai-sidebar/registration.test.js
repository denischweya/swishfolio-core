/**
 * Registration: one plugin, one toolbar filter, and no side effects on import.
 */

import { initAiEditor } from '../../../src/js/ai-sidebar/index';
import AiSidebar from '../../../src/js/ai-sidebar/sidebar';
import { PLUGIN_NAME } from '../../../src/js/ai/constants';
import * as wpHooks from '../mocks/wp-hooks';
import * as wpPlugins from '../mocks/wp-plugins';

afterEach( () => {
	wpHooks.__reset();
	wpPlugins.registerPlugin.mockClear();
} );

describe( 'initAiEditor', () => {
	it( 'registers the sidebar plugin and the toolbar filter exactly once', () => {
		// Importing the module must not have registered anything by itself.
		expect( wpPlugins.registerPlugin ).not.toHaveBeenCalled();
		expect( wpHooks.__getFilters( 'editor.BlockEdit' ) ).toHaveLength( 0 );

		initAiEditor();
		initAiEditor();

		expect( wpPlugins.registerPlugin ).toHaveBeenCalledTimes( 1 );
		expect( PLUGIN_NAME ).toBe( 'sfcore-ai' );

		const [ name, settings ] = wpPlugins.registerPlugin.mock.calls[ 0 ];

		expect( name ).toBe( PLUGIN_NAME );
		expect( settings.render ).toBe( AiSidebar );
		expect( settings.icon ).toBeTruthy();

		expect( wpHooks.__getFilters( 'editor.BlockEdit' ) ).toHaveLength( 1 );
	} );
} );
