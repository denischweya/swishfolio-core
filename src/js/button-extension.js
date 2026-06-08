/**
 * Button Block Extension
 *
 * Adds to core/button:
 *  - Icon (before/after the link text)
 *  - Hover text + background color
 *  - Hover animation variant (lift / scale / slide / shift)
 *
 * Frontend rendering is handled server-side in ButtonExtension.php. This file
 * powers the editor: attribute registration, inspector controls, and a live
 * preview that mirrors what the front end will output.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	SelectControl,
	BaseControl,
	ColorPicker,
	Dropdown,
	Button as WpButton,
} from '@wordpress/components';
import { Fragment, RawHTML } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const TARGET_BLOCK = 'core/button';

// Icon catalog — slugs must match ButtonExtension.php's ICONS array.
const ICON_OPTIONS = [
	{ value: '', label: __( '— None —', 'swishfolio-core' ) },
	{ value: 'arrow-right', label: __( 'Arrow right', 'swishfolio-core' ) },
	{ value: 'arrow-left', label: __( 'Arrow left', 'swishfolio-core' ) },
	{ value: 'arrow-up-right', label: __( 'Arrow up-right (external)', 'swishfolio-core' ) },
	{ value: 'arrow-down', label: __( 'Arrow down (download)', 'swishfolio-core' ) },
	{ value: 'chevron-right', label: __( 'Chevron right', 'swishfolio-core' ) },
	{ value: 'plus', label: __( 'Plus', 'swishfolio-core' ) },
	{ value: 'check', label: __( 'Check', 'swishfolio-core' ) },
	{ value: 'download', label: __( 'Download', 'swishfolio-core' ) },
	{ value: 'external', label: __( 'External', 'swishfolio-core' ) },
];

const ANIMATION_OPTIONS = [
	{ value: '', label: __( 'None', 'swishfolio-core' ) },
	{ value: 'lift', label: __( 'Lift — translate up + shadow', 'swishfolio-core' ) },
	{ value: 'scale', label: __( 'Scale — slight grow', 'swishfolio-core' ) },
	{ value: 'slide', label: __( 'Slide — background fills', 'swishfolio-core' ) },
	{ value: 'shift', label: __( 'Shift — translate right', 'swishfolio-core' ) },
];

// Icon SVG strings are localised onto window.sfcoreButtonIcons by PHP.
const getIconSvg = ( slug ) => {
	if ( ! slug ) {
		return '';
	}
	const lib = ( typeof window !== 'undefined' && window.sfcoreButtonIcons ) || {};
	return lib[ slug ] || '';
};

// 1. Register the new attributes on core/button.
function addButtonAttributes( settings, name ) {
	if ( name !== TARGET_BLOCK ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			sfcoreIcon: { type: 'string', default: '' },
			sfcoreIconPosition: { type: 'string', default: 'after' },
			sfcoreHoverTextColor: { type: 'string', default: '' },
			sfcoreHoverBgColor: { type: 'string', default: '' },
			sfcoreHoverAnimation: { type: 'string', default: '' },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'sfcore/button-extension-attributes',
	addButtonAttributes
);

// Small re-usable color swatch popover for hover colors.
const HoverColorControl = ( { label, value, onChange } ) => (
	<BaseControl
		__nextHasNoMarginBottom
		label={ label }
	>
		<Dropdown
			contentClassName="sfcore-btn-color-popover"
			popoverProps={ { placement: 'left-start', offset: 16 } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<WpButton
					variant="secondary"
					onClick={ onToggle }
					aria-expanded={ isOpen }
					className="sfcore-btn-color-trigger"
				>
					<span
						className="sfcore-btn-color-swatch"
						style={ { background: value || 'transparent' } }
					/>
					<span>{ value || __( 'Pick a color', 'swishfolio-core' ) }</span>
					{ value && (
						<WpButton
							variant="tertiary"
							size="small"
							onClick={ ( e ) => {
								e.stopPropagation();
								onChange( '' );
							} }
						>
							{ __( 'Clear', 'swishfolio-core' ) }
						</WpButton>
					) }
				</WpButton>
			) }
			renderContent={ () => (
				<ColorPicker
					color={ value }
					onChange={ onChange }
					enableAlpha
				/>
			) }
		/>
	</BaseControl>
);

// 2. Inspector controls + editor-side preview.
const withButtonControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== TARGET_BLOCK ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;
		const {
			sfcoreIcon = '',
			sfcoreIconPosition = 'after',
			sfcoreHoverTextColor = '',
			sfcoreHoverBgColor = '',
			sfcoreHoverAnimation = '',
		} = attributes;

		// Forward hover vars + animation class into the inline preview so the
		// editor reflects what the front end will render.
		const wrapperClass = [
			props.attributes.className || '',
			sfcoreIcon && 'sfcore-btn--has-icon',
			( sfcoreHoverTextColor || sfcoreHoverBgColor ) && 'sfcore-btn--has-hover',
			sfcoreHoverAnimation &&
				sfcoreHoverAnimation !== 'none' &&
				`sfcore-btn--anim-${ sfcoreHoverAnimation }`,
		]
			.filter( Boolean )
			.join( ' ' );

		const previewStyle = {};
		if ( sfcoreHoverTextColor ) {
			previewStyle[ '--sfcore-btn-hover-text' ] = sfcoreHoverTextColor;
		}
		if ( sfcoreHoverBgColor ) {
			previewStyle[ '--sfcore-btn-hover-bg' ] = sfcoreHoverBgColor;
		}

		const forwardedProps = {
			...props,
			attributes: {
				...props.attributes,
				className: wrapperClass,
				style: { ...( props.attributes.style || {} ), ...previewStyle },
			},
		};

		const iconSvg = getIconSvg( sfcoreIcon );

		return (
			<Fragment>
				<BlockEdit { ...forwardedProps } />

				<InspectorControls>
					<PanelBody
						title={ __( 'Icon', 'swishfolio-core' ) }
						initialOpen={ false }
					>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Icon', 'swishfolio-core' ) }
							value={ sfcoreIcon }
							options={ ICON_OPTIONS }
							onChange={ ( v ) =>
								setAttributes( { sfcoreIcon: v } )
							}
						/>
						{ sfcoreIcon && (
							<Fragment>
								<SelectControl
									__nextHasNoMarginBottom
									label={ __( 'Icon position', 'swishfolio-core' ) }
									value={ sfcoreIconPosition }
									options={ [
										{ value: 'before', label: __( 'Before text', 'swishfolio-core' ) },
										{ value: 'after', label: __( 'After text', 'swishfolio-core' ) },
									] }
									onChange={ ( v ) =>
										setAttributes( { sfcoreIconPosition: v } )
									}
								/>
								<PanelRow>
									<span
										className="sfcore-btn-icon-preview"
										aria-hidden="true"
									>
										<RawHTML>{ iconSvg }</RawHTML>
									</span>
								</PanelRow>
							</Fragment>
						) }
					</PanelBody>

					<PanelBody
						title={ __( 'Hover', 'swishfolio-core' ) }
						initialOpen={ false }
					>
						<HoverColorControl
							label={ __( 'Hover text color', 'swishfolio-core' ) }
							value={ sfcoreHoverTextColor }
							onChange={ ( c ) =>
								setAttributes( { sfcoreHoverTextColor: c } )
							}
						/>
						<HoverColorControl
							label={ __( 'Hover background', 'swishfolio-core' ) }
							value={ sfcoreHoverBgColor }
							onChange={ ( c ) =>
								setAttributes( { sfcoreHoverBgColor: c } )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Hover animation', 'swishfolio-core' ) }
							value={ sfcoreHoverAnimation }
							options={ ANIMATION_OPTIONS }
							onChange={ ( v ) =>
								setAttributes( { sfcoreHoverAnimation: v } )
							}
							help={ __(
								'Animation plays when the visitor hovers the button. Frontend only.',
								'swishfolio-core'
							) }
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withButtonControls' );

addFilter( 'editor.BlockEdit', 'sfcore/button-extension-controls', withButtonControls );
