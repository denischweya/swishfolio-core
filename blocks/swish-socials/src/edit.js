/**
 * Swish Socials Block - Editor
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls, MediaUpload } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	RangeControl,
	Button,
	ColorPalette,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { SOCIAL_ICONS, SOCIAL_ICON_OPTIONS } from '../../shared/social-icons';

const BAUHAUS_COLORS = [
	{ name: 'Red', color: '#D02020' },
	{ name: 'Blue', color: '#1040C0' },
	{ name: 'Yellow', color: '#F0C020' },
	{ name: 'Contrast', color: '#121212' },
	{ name: 'Base', color: '#F0F0F0' },
	{ name: 'White', color: '#FFFFFF' },
];

const DEFAULT_LINK = {
	icon: 'facebook',
	url: '',
	label: '',
	customIcon: { url: '', id: 0, alt: '' },
	isExternal: true,
};

export default function Edit( { attributes, setAttributes } ) {
	const {
		socialLinks,
		iconShape,
		iconSize,
		borderWidth,
		borderColor,
		iconColor,
		backgroundColor,
		shadowSize,
		hoverEffect,
		useBrandColors,
		alignment,
		gapSize,
	} = attributes;

	const updateLink = ( index, updates ) => {
		const newLinks = [ ...socialLinks ];
		newLinks[ index ] = { ...newLinks[ index ], ...updates };
		setAttributes( { socialLinks: newLinks } );
	};

	const removeLink = ( index ) => {
		const newLinks = socialLinks.filter( ( _, i ) => i !== index );
		setAttributes( { socialLinks: newLinks } );
	};

	const addLink = () => {
		setAttributes( { socialLinks: [ ...socialLinks, { ...DEFAULT_LINK } ] } );
	};

	const getLinkStyle = ( link ) => {
		const style = {};
		if ( useBrandColors && link.icon !== 'custom' ) {
			style.backgroundColor = SOCIAL_ICONS[ link.icon ]?.color || '#121212';
			style.color = '#FFFFFF';
		} else {
			style.color = iconColor;
			style.backgroundColor = backgroundColor;
		}
		style.borderWidth = `${ borderWidth }px`;
		style.borderColor = borderColor;
		style.borderStyle = 'solid';
		return style;
	};

	const getIconSvg = ( link ) => {
		if ( link.icon === 'custom' && link.customIcon?.url ) {
			return null;
		}
		return SOCIAL_ICONS[ link.icon ]?.svg || '';
	};

	const blockProps = useBlockProps( {
		className: `sfcore-socials sfcore-socials--align-${ alignment } sfcore-socials--gap-${ gapSize }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Links', 'swishfolio-core' ) } initialOpen={ true }>
					{ socialLinks.map( ( link, index ) => (
						<div key={ index } className="sfcore-socials-link-item">
							<div className="sfcore-socials-link-item__header">
								<span className="sfcore-socials-link-item__number">
									{ index + 1 }
								</span>
								<Button
									icon="no-alt"
									isDestructive
									size="small"
									onClick={ () => removeLink( index ) }
									label={ __( 'Remove', 'swishfolio-core' ) }
								/>
							</div>
							<SelectControl
								label={ __( 'Icon', 'swishfolio-core' ) }
								value={ link.icon }
								options={ SOCIAL_ICON_OPTIONS }
								onChange={ ( val ) => updateLink( index, { icon: val } ) }
							/>
							<TextControl
								label={ __( 'URL', 'swishfolio-core' ) }
								value={ link.url }
								onChange={ ( val ) => updateLink( index, { url: val } ) }
								placeholder="https://"
							/>
							<TextControl
								label={ __( 'Label (optional)', 'swishfolio-core' ) }
								value={ link.label }
								onChange={ ( val ) => updateLink( index, { label: val } ) }
								placeholder={ SOCIAL_ICONS[ link.icon ]?.label || '' }
							/>
							<ToggleControl
								label={ __( 'Open in new tab', 'swishfolio-core' ) }
								checked={ link.isExternal !== false }
								onChange={ ( val ) => updateLink( index, { isExternal: val } ) }
							/>
							{ link.icon === 'custom' && (
								<div className="sfcore-socials-link-item__custom-icon">
									{ link.customIcon?.url ? (
										<div className="sfcore-socials-link-item__custom-preview">
											<img
												src={ link.customIcon.url }
												alt={ link.customIcon.alt || '' }
												style={ { width: 32, height: 32 } }
											/>
											<Button
												isDestructive
												size="small"
												onClick={ () =>
													updateLink( index, {
														customIcon: { url: '', id: 0, alt: '' },
													} )
												}
											>
												{ __( 'Remove Icon', 'swishfolio-core' ) }
											</Button>
										</div>
									) : (
										<MediaUpload
											onSelect={ ( media ) =>
												updateLink( index, {
													customIcon: {
														url: media.url,
														id: media.id,
														alt: media.alt || '',
													},
												} )
											}
											allowedTypes={ [ 'image/svg+xml', 'image' ] }
											render={ ( { open } ) => (
												<Button
													variant="secondary"
													size="small"
													onClick={ open }
												>
													{ __( 'Upload SVG Icon', 'swishfolio-core' ) }
												</Button>
											) }
										/>
									) }
								</div>
							) }
						</div>
					) ) }
					<Button
						variant="primary"
						onClick={ addLink }
						style={ { marginTop: '12px' } }
					>
						{ __( 'Add Link', 'swishfolio-core' ) }
					</Button>
				</PanelBody>

				<PanelBody title={ __( 'Icon Style', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleGroupControl
						label={ __( 'Shape', 'swishfolio-core' ) }
						value={ iconShape }
						onChange={ ( val ) => setAttributes( { iconShape: val } ) }
						isBlock
					>
						<ToggleGroupControlOption value="square" label={ __( 'Square', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="rounded" label={ __( 'Rounded', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="circle" label={ __( 'Circle', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Size', 'swishfolio-core' ) }
						value={ iconSize }
						onChange={ ( val ) => setAttributes( { iconSize: val } ) }
						isBlock
					>
						<ToggleGroupControlOption value="small" label="S" />
						<ToggleGroupControlOption value="medium" label="M" />
						<ToggleGroupControlOption value="large" label="L" />
						<ToggleGroupControlOption value="xlarge" label="XL" />
					</ToggleGroupControl>

					<ToggleControl
						label={ __( 'Use Brand Colors', 'swishfolio-core' ) }
						checked={ useBrandColors }
						onChange={ ( val ) => setAttributes( { useBrandColors: val } ) }
					/>

					{ ! useBrandColors && (
						<>
							<p className="components-base-control__label">
								{ __( 'Icon Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ BAUHAUS_COLORS }
								value={ iconColor }
								onChange={ ( val ) => setAttributes( { iconColor: val || '#121212' } ) }
							/>
							<p className="components-base-control__label">
								{ __( 'Background Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ BAUHAUS_COLORS }
								value={ backgroundColor }
								onChange={ ( val ) => setAttributes( { backgroundColor: val || 'transparent' } ) }
							/>
						</>
					) }

					<RangeControl
						label={ __( 'Border Width', 'swishfolio-core' ) }
						value={ borderWidth }
						onChange={ ( val ) => setAttributes( { borderWidth: val } ) }
						min={ 0 }
						max={ 8 }
					/>
					<p className="components-base-control__label">
						{ __( 'Border Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ BAUHAUS_COLORS }
						value={ borderColor }
						onChange={ ( val ) => setAttributes( { borderColor: val || '#121212' } ) }
					/>

					<ToggleGroupControl
						label={ __( 'Shadow', 'swishfolio-core' ) }
						value={ shadowSize }
						onChange={ ( val ) => setAttributes( { shadowSize: val } ) }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="small" label="S" />
						<ToggleGroupControlOption value="medium" label="M" />
						<ToggleGroupControlOption value="large" label="L" />
					</ToggleGroupControl>

					<SelectControl
						label={ __( 'Hover Effect', 'swishfolio-core' ) }
						value={ hoverEffect }
						options={ [
							{ label: __( 'Lift', 'swishfolio-core' ), value: 'lift' },
							{ label: __( 'Scale', 'swishfolio-core' ), value: 'scale' },
							{ label: __( 'Glow', 'swishfolio-core' ), value: 'glow' },
							{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
						] }
						onChange={ ( val ) => setAttributes( { hoverEffect: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleGroupControl
						label={ __( 'Alignment', 'swishfolio-core' ) }
						value={ alignment }
						onChange={ ( val ) => setAttributes( { alignment: val } ) }
						isBlock
					>
						<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="center" label={ __( 'Center', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Gap', 'swishfolio-core' ) }
						value={ gapSize }
						onChange={ ( val ) => setAttributes( { gapSize: val } ) }
						isBlock
					>
						<ToggleGroupControlOption value="small" label="S" />
						<ToggleGroupControlOption value="medium" label="M" />
						<ToggleGroupControlOption value="large" label="L" />
					</ToggleGroupControl>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ socialLinks.length === 0 ? (
					<div className="sfcore-socials__placeholder">
						<p>{ __( 'Add social links in the block settings panel →', 'swishfolio-core' ) }</p>
						<Button variant="primary" onClick={ addLink }>
							{ __( 'Add Link', 'swishfolio-core' ) }
						</Button>
					</div>
				) : (
					socialLinks.map( ( link, index ) => {
						const svg = getIconSvg( link );
						const style = getLinkStyle( link );

						return (
							<span
								key={ index }
								className={ `sfcore-socials__link sfcore-socials__link--${ iconShape } sfcore-socials__link--${ iconSize } sfcore-socials__link--shadow-${ shadowSize } sfcore-socials__link--hover-${ hoverEffect }` }
								style={ style }
								title={ link.label || SOCIAL_ICONS[ link.icon ]?.label || '' }
							>
								{ link.icon === 'custom' && link.customIcon?.url ? (
									<img
										src={ link.customIcon.url }
										alt=""
										className="sfcore-socials__icon-img"
									/>
								) : (
									<span
										className="sfcore-socials__icon"
										dangerouslySetInnerHTML={ { __html: svg } }
									/>
								) }
							</span>
						);
					} )
				) }
			</div>
		</>
	);
}
