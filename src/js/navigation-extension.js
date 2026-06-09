/**
 * Navigation Block Extension
 *
 * Adds to core/navigation:
 *  - Overlay alignment (left/center/right; overlay-only)
 *  - Hover style + hover color (applies in both inline and overlay modes)
 *  - Hamburger icon picker (preset library + custom SVG upload)
 *
 * Frontend rendering is handled server-side in NavigationExtension.php. This
 * file powers the editor: attribute registration and the InspectorControls.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	BaseControl,
	ColorPicker,
	Dropdown,
	Button as WpButton,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { Fragment, RawHTML } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const TARGET_BLOCK = 'core/navigation';

const HAMBURGER_PRESETS = [
	{ value: 'lines-3', label: __( '3 lines (default)', 'swishfolio-core' ) },
	{ value: 'lines-2', label: __( '2 lines (minimal)', 'swishfolio-core' ) },
	{ value: 'x', label: __( 'X', 'swishfolio-core' ) },
	{ value: 'dots', label: __( 'Dots', 'swishfolio-core' ) },
	{ value: 'plus', label: __( 'Plus', 'swishfolio-core' ) },
	{ value: 'grid', label: __( 'Grid (3×3)', 'swishfolio-core' ) },
];

const HOVER_STYLES = [
	{ value: 'none', label: __( 'None', 'swishfolio-core' ) },
	{ value: 'underline', label: __( 'Underline', 'swishfolio-core' ) },
	{ value: 'underline-grow', label: __( 'Underline grow', 'swishfolio-core' ) },
	{ value: 'color', label: __( 'Color shift', 'swishfolio-core' ) },
];

// SVG library is localised onto window.sfcoreNavIcons by PHP.
const getPresetSvg = ( slug ) => {
	const lib =
		( typeof window !== 'undefined' && window.sfcoreNavIcons ) || {};
	return lib[ slug ] || '';
};

// 1. Register new attributes on core/navigation.
function addNavAttributes( settings, name ) {
	if ( name !== TARGET_BLOCK ) {
		return settings;
	}
	return {
		...settings,
		attributes: {
			...settings.attributes,
			sfcoreOverlayAlign: { type: 'string', default: '' },
			sfcoreHoverStyle: { type: 'string', default: 'none' },
			sfcoreHoverColor: { type: 'string', default: '' },
			sfcoreHamburgerIcon: { type: [ 'string', 'object' ], default: '' },
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'sfcore/navigation-extension-attributes',
	addNavAttributes
);

// Reusable popover color picker (matches button-extension pattern).
const ColorPopover = ( { label, value, onChange } ) => (
	<BaseControl __nextHasNoMarginBottom label={ label }>
		<Dropdown
			contentClassName="sfcore-nav-color-popover"
			popoverProps={ { placement: 'left-start', offset: 16 } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<WpButton
					variant="secondary"
					onClick={ onToggle }
					aria-expanded={ isOpen }
					className="sfcore-nav-color-trigger"
				>
					<span
						className="sfcore-nav-color-swatch"
						style={ { background: value || 'transparent' } }
					/>
					<span>
						{ value || __( 'Pick a color', 'swishfolio-core' ) }
					</span>
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
				<ColorPicker color={ value } onChange={ onChange } enableAlpha />
			) }
		/>
	</BaseControl>
);

const HamburgerPicker = ( { value, onChange } ) => {
	const presetValue =
		typeof value === 'string' && value && value !== 'custom' ? value : '';
	const customUrl =
		value && typeof value === 'object' && value.type === 'custom'
			? value.url
			: '';

	return (
		<Fragment>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Hamburger icon', 'swishfolio-core' ) }
				value={ presetValue }
				options={ [
					{ value: '', label: __( '— Use core default —', 'swishfolio-core' ) },
					...HAMBURGER_PRESETS,
				] }
				onChange={ ( v ) => onChange( v ) }
			/>

			{ presetValue && (
				<div
					className="sfcore-nav-icon-preview"
					aria-hidden="true"
				>
					<RawHTML>{ getPresetSvg( presetValue ) }</RawHTML>
				</div>
			) }

			<MediaUploadCheck>
				<MediaUpload
					onSelect={ ( media ) =>
						onChange( { type: 'custom', url: media.url } )
					}
					allowedTypes={ [ 'image/svg+xml' ] }
					render={ ( { open } ) => (
						<WpButton
							variant="secondary"
							onClick={ open }
							style={ { marginTop: 8 } }
						>
							{ customUrl
								? __( 'Replace custom SVG', 'swishfolio-core' )
								: __( 'Upload custom SVG…', 'swishfolio-core' ) }
						</WpButton>
					) }
				/>
			</MediaUploadCheck>

			{ customUrl && (
				<Fragment>
					<div className="sfcore-nav-icon-preview">
						<img
							src={ customUrl }
							alt=""
							style={ { width: 24, height: 24 } }
						/>
					</div>
					<WpButton
						variant="link"
						isDestructive
						onClick={ () => onChange( '' ) }
					>
						{ __( 'Remove custom SVG', 'swishfolio-core' ) }
					</WpButton>
				</Fragment>
			) }
		</Fragment>
	);
};

// 2. Inspector controls + editor-side preview.
const withNavControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== TARGET_BLOCK ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;
		const {
			sfcoreOverlayAlign = '',
			sfcoreHoverStyle = 'none',
			sfcoreHoverColor = '',
			sfcoreHamburgerIcon = '',
		} = attributes;

		// Forward our class + inline CSS variable into the editor preview so
		// alignment / hover / color changes are visible without saving. Mirror
		// what NavigationExtension.php does at render_block time.
		const wrapperClass = [
			props.attributes.className || '',
			sfcoreOverlayAlign && `sfcore-nav--align-${ sfcoreOverlayAlign }`,
			sfcoreHoverStyle &&
				sfcoreHoverStyle !== 'none' &&
				`sfcore-nav--hover-${ sfcoreHoverStyle }`,
		]
			.filter( Boolean )
			.join( ' ' );

		const previewStyle = {};
		if ( sfcoreHoverColor ) {
			previewStyle[ '--sfcore-nav-hover-color' ] = sfcoreHoverColor;
		}

		const forwardedProps = {
			...props,
			attributes: {
				...props.attributes,
				className: wrapperClass,
				style: { ...( props.attributes.style || {} ), ...previewStyle },
			},
		};

		return (
			<Fragment>
				<BlockEdit { ...forwardedProps } />

				<InspectorControls>
					<PanelBody
						title={ __( 'Hamburger Style', 'swishfolio-core' ) }
						initialOpen={ false }
					>
						<ToggleGroupControl
							__nextHasNoMarginBottom
							label={ __( 'Overlay alignment', 'swishfolio-core' ) }
							value={ sfcoreOverlayAlign || 'left' }
							onChange={ ( v ) =>
								setAttributes( { sfcoreOverlayAlign: v } )
							}
							isBlock
							help={ __(
								'Aligns items inside the mobile overlay only. Desktop alignment is set by the Layout panel above.',
								'swishfolio-core'
							) }
						>
							<ToggleGroupControlOption
								value="left"
								label={ __( 'Left', 'swishfolio-core' ) }
							/>
							<ToggleGroupControlOption
								value="center"
								label={ __( 'Center', 'swishfolio-core' ) }
							/>
							<ToggleGroupControlOption
								value="right"
								label={ __( 'Right', 'swishfolio-core' ) }
							/>
						</ToggleGroupControl>

						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Hover style', 'swishfolio-core' ) }
							value={ sfcoreHoverStyle }
							options={ HOVER_STYLES }
							onChange={ ( v ) =>
								setAttributes( { sfcoreHoverStyle: v } )
							}
							help={ __(
								'Applies to nav links in both inline (desktop) and overlay (mobile) modes.',
								'swishfolio-core'
							) }
						/>

						<ColorPopover
							label={ __( 'Hover color', 'swishfolio-core' ) }
							value={ sfcoreHoverColor }
							onChange={ ( c ) =>
								setAttributes( { sfcoreHoverColor: c } )
							}
						/>

						<HamburgerPicker
							value={ sfcoreHamburgerIcon }
							onChange={ ( v ) =>
								setAttributes( { sfcoreHamburgerIcon: v } )
							}
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withNavControls' );

addFilter(
	'editor.BlockEdit',
	'sfcore/navigation-extension-controls',
	withNavControls
);
