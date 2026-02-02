/**
 * Columns Block Extension
 *
 * Adds vertical alignment controls to core/columns and core/column blocks.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Add custom attributes to columns and column blocks.
 */
function addVerticalAlignmentAttribute( settings, name ) {
	if ( name !== 'core/columns' && name !== 'core/column' ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			sfcoreVerticalAlignment: {
				type: 'string',
				default: '',
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'sfcore/columns-vertical-alignment-attribute',
	addVerticalAlignmentAttribute
);

/**
 * Add vertical alignment controls to the block inspector.
 */
const withVerticalAlignmentControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, attributes, setAttributes } = props;

		if ( name !== 'core/columns' && name !== 'core/column' ) {
			return <BlockEdit { ...props } />;
		}

		const { sfcoreVerticalAlignment } = attributes;

		const alignmentOptions = [
			{ label: __( 'Default', 'swishfolio-core' ), value: '' },
			{ label: __( 'Top', 'swishfolio-core' ), value: 'top' },
			{ label: __( 'Center', 'swishfolio-core' ), value: 'center' },
			{ label: __( 'Bottom', 'swishfolio-core' ), value: 'bottom' },
			{ label: __( 'Space Between', 'swishfolio-core' ), value: 'space-between' },
			{ label: __( 'Space Around', 'swishfolio-core' ), value: 'space-around' },
			{ label: __( 'Space Evenly', 'swishfolio-core' ), value: 'space-evenly' },
			{ label: __( 'Stretch', 'swishfolio-core' ), value: 'stretch' },
		];

		const panelTitle = name === 'core/columns'
			? __( 'Columns Vertical Alignment', 'swishfolio-core' )
			: __( 'Column Content Alignment', 'swishfolio-core' );

		const helpText = name === 'core/columns'
			? __( 'Align all columns vertically within the container.', 'swishfolio-core' )
			: __( 'Align content vertically within this column.', 'swishfolio-core' );

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ panelTitle }
						initialOpen={ false }
					>
						<SelectControl
							label={ __( 'Vertical Alignment', 'swishfolio-core' ) }
							value={ sfcoreVerticalAlignment || '' }
							options={ alignmentOptions }
							onChange={ ( value ) => setAttributes( { sfcoreVerticalAlignment: value } ) }
							help={ helpText }
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withVerticalAlignmentControls' );

addFilter(
	'editor.BlockEdit',
	'sfcore/columns-vertical-alignment-controls',
	withVerticalAlignmentControls
);

/**
 * Add alignment class to block wrapper in editor.
 */
function addVerticalAlignmentClass( extraProps, blockType, attributes ) {
	if ( blockType.name !== 'core/columns' && blockType.name !== 'core/column' ) {
		return extraProps;
	}

	const { sfcoreVerticalAlignment } = attributes;

	if ( sfcoreVerticalAlignment ) {
		extraProps.className = `${ extraProps.className || '' } sfcore-vertical-align-${ sfcoreVerticalAlignment }`.trim();
	}

	return extraProps;
}

addFilter(
	'blocks.getSaveContent.extraProps',
	'sfcore/columns-vertical-alignment-class',
	addVerticalAlignmentClass
);

/**
 * Add alignment class to editor wrapper.
 */
const withVerticalAlignmentEditorClass = createHigherOrderComponent( ( BlockListBlock ) => {
	return ( props ) => {
		const { name, attributes } = props;

		if ( name !== 'core/columns' && name !== 'core/column' ) {
			return <BlockListBlock { ...props } />;
		}

		const { sfcoreVerticalAlignment } = attributes;

		if ( ! sfcoreVerticalAlignment ) {
			return <BlockListBlock { ...props } />;
		}

		const className = `sfcore-vertical-align-${ sfcoreVerticalAlignment }`;

		return <BlockListBlock { ...props } className={ className } />;
	};
}, 'withVerticalAlignmentEditorClass' );

addFilter(
	'editor.BlockListBlock',
	'sfcore/columns-vertical-alignment-editor-class',
	withVerticalAlignmentEditorClass
);
