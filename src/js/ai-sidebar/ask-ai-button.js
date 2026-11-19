/**
 * The "Ask AI" block toolbar button.
 *
 * Injected through `editor.BlockEdit` with a `BlockControls` fill — the same
 * mechanism ButtonExtension, ColumnsExtension and NavigationExtension already
 * use. The fill renders only for the block that may display controls, so
 * `props.name` is always the selected block's name.
 *
 * The sidebar is opened through the `core/interface` store by name rather than by
 * importing `@wordpress/interface`, which the build bundles rather than
 * externalises.
 */

import { BlockControls } from '@wordpress/block-editor';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';

import {
	INTERFACE_STORE,
	SIDEBAR_IDENTIFIER,
	SIDEBAR_SCOPE,
} from '../ai/constants';
import { getCapability, isAvailable } from '../ai/capabilities';
import { blockLabel } from '../ai/labels';
import AiIcon from './icon';

const AskAiToolbarButton = ( { blockName } ) => {
	const capability = getCapability( blockName );
	const isSupported = capability !== null;

	const isOpen = useSelect( ( select ) => {
		const interfaceStore = select( INTERFACE_STORE );

		return (
			!! interfaceStore &&
			interfaceStore.getActiveComplementaryArea( SIDEBAR_SCOPE ) ===
				SIDEBAR_IDENTIFIER
		);
	}, [] );

	const interfaceDispatch = useDispatch( INTERFACE_STORE );

	const label = isSupported
		? __( 'Ask AI', 'swishfolio-core' )
		: sprintf(
				/* translators: %s: block title. */
				__(
					'%s has no AI-editable properties yet.',
					'swishfolio-core'
				),
				blockLabel( blockName )
		  );

	return (
		<ToolbarGroup>
			<ToolbarButton
				className="sfcore-ai-toolbar-button"
				icon={ <AiIcon /> }
				label={ label }
				disabled={ ! isSupported }
				accessibleWhenDisabled
				isActive={ isOpen }
				onClick={ () => {
					if (
						interfaceDispatch &&
						interfaceDispatch.enableComplementaryArea
					) {
						interfaceDispatch.enableComplementaryArea(
							SIDEBAR_SCOPE,
							SIDEBAR_IDENTIFIER
						);
					}
				} }
			/>
		</ToolbarGroup>
	);
};

const withAskAiControl = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( ! isAvailable() ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<Fragment>
				<BlockEdit { ...props } />

				<BlockControls group="other">
					<AskAiToolbarButton blockName={ props.name } />
				</BlockControls>
			</Fragment>
		);
	},
	'withAskAiControl'
);

export function registerAskAiToolbarButton() {
	addFilter( 'editor.BlockEdit', 'sfcore/ai-ask-controls', withAskAiControl );
}

export default withAskAiControl;
