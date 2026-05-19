/**
 * Shared InspectorTabs component.
 *
 * Renders a General / Style / Advanced tab switcher at the top of the block
 * inspector (modelled on the Kadence Blocks pattern). Each block keeps the
 * active tab in local state via the `useInspectorTabs` hook and conditionally
 * renders its PanelBody groups per tab.
 */

import { __ } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { settings, styles, tool } from '@wordpress/icons';

import './inspector-tabs.scss';

const TAB_CONFIG = {
	general: { title: __( 'General', 'swishfolio-core' ), icon: settings },
	style: { title: __( 'Style', 'swishfolio-core' ), icon: styles },
	advanced: { title: __( 'Advanced', 'swishfolio-core' ), icon: tool },
};

/**
 * Tab state hook. Mirrors Kadence: plain local state, defaults to "general",
 * does not persist across reloads.
 *
 * @param {string} initial Initial active tab name.
 * @return {[string, Function]} [activeTab, setActiveTab]
 */
export function useInspectorTabs( initial = 'general' ) {
	return useState( initial );
}

export default function InspectorTabs( {
	activeTab,
	setActiveTab,
	allowedTabs = [ 'general', 'style', 'advanced' ],
} ) {
	return (
		<div className="sfcore-inspector-tabs">
			{ allowedTabs.map( ( name ) => {
				const tab = TAB_CONFIG[ name ];
				if ( ! tab ) {
					return null;
				}
				return (
					<Button
						key={ name }
						className={ `sfcore-inspector-tabs__tab ${
							activeTab === name ? 'is-active' : ''
						}` }
						onClick={ () => setActiveTab( name ) }
						aria-pressed={ activeTab === name }
					>
						<Icon icon={ tab.icon } />
						<span>{ tab.title }</span>
					</Button>
				);
			} ) }
		</div>
	);
}
