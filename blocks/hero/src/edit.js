/**
 * Hero Block - Edit Component
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	URLInput,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ColorPalette,
	Button,
	ButtonGroup,
	ToggleControl,
	TextControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Icon,
} from '@wordpress/components';
import {
	starFilled,
	starEmpty,
	check,
	chevronLeft,
	chevronRight,
	arrowLeft,
	arrowRight,
	arrowUp,
	arrowDown,
	warning,
	info,
	shield,
	lock,
	key,
	people,
	globe,
	desktop,
	mobile,
	code,
	brush,
	tool,
	settings,
	category,
	tag,
	grid,
	list,
	inbox,
	envelope,
	bell,
	calendar,
	gift,
	cog,
	pencil,
	store,
	plugins,
	external,
	download,
	upload,
	video,
	image,
} from '@wordpress/icons';
import { SOCIAL_ICONS } from '../../shared/social-icons';

// Icon library for CTA buttons (same pattern as heading block)
const CTA_ICON_LIBRARY = {
	'arrow-right': arrowRight,
	'arrow-left': arrowLeft,
	'arrow-up': arrowUp,
	'arrow-down': arrowDown,
	'chevron-right': chevronRight,
	'chevron-left': chevronLeft,
	external,
	download,
	upload,
	video,
	image,
	star: starFilled,
	'star-empty': starEmpty,
	check,
	warning,
	info,
	shield,
	lock,
	key,
	people,
	globe,
	desktop,
	mobile,
	code,
	brush,
	tool,
	settings,
	category,
	tag,
	grid,
	list,
	inbox,
	envelope,
	bell,
	calendar,
	gift,
	cog,
	pencil,
	store,
	plugins,
};

// Blob shape presets - organic SVG paths
const BLOB_PRESETS = {
	blob1: 'M44.5,-76.3C58.1,-69.1,70,-57.5,77.4,-43.6C84.8,-29.7,87.8,-13.4,86.1,2.1C84.4,17.5,78,32.3,68.4,44.3C58.8,56.3,46,65.5,32,72.1C18,78.7,2.8,82.7,-12.2,81.3C-27.2,79.9,-42,73.1,-54.3,63.1C-66.6,53.1,-76.4,39.9,-81.1,25C-85.8,10.1,-85.4,-6.5,-80.4,-21.1C-75.4,-35.7,-65.8,-48.3,-53.4,-56.1C-41,-63.9,-25.8,-66.9,-10.8,-71.5C4.2,-76.1,30.9,-83.5,44.5,-76.3Z',
	blob2: 'M40.9,-69.5C53.4,-63.1,64.4,-53,72,-40.6C79.6,-28.2,83.8,-13.5,83.3,0.8C82.8,15.1,77.6,29,69.1,40.5C60.6,52,48.8,61.1,35.8,67.1C22.8,73.1,8.6,76,-6.4,76.2C-21.4,76.4,-37.2,73.9,-49.4,66.3C-61.6,58.7,-70.2,46,-75.3,32C-80.4,18,-82,-0.3,-78.4,-17C-74.8,-33.7,-66,-48.8,-53.8,-55.5C-41.6,-62.2,-26,-60.5,-12,-63.5C2,-66.5,28.4,-75.9,40.9,-69.5Z',
	blob3: 'M38.6,-65C50.5,-58.5,61.2,-49,69.2,-37.1C77.2,-25.2,82.5,-10.8,81.6,3C80.7,16.8,73.6,30.2,64.4,41.3C55.2,52.4,43.9,61.2,31.2,67.5C18.5,73.8,4.4,77.6,-10.8,77.6C-26,77.6,-42.3,73.8,-54.5,64.8C-66.7,55.8,-74.8,41.6,-79.2,26.2C-83.6,10.8,-84.3,-5.8,-79.7,-20.5C-75.1,-35.2,-65.2,-48,-52.5,-54.8C-39.8,-61.6,-24.3,-62.4,-10.4,-63.9C3.5,-65.4,26.7,-71.5,38.6,-65Z',
	blob4: 'M42.1,-72C55.2,-65.1,67,-54.8,74.7,-41.9C82.4,-29,86,-13.5,84.6,1.2C83.2,15.9,76.8,29.8,67.8,41.4C58.8,53,47.2,62.3,34.2,68.9C21.2,75.5,6.8,79.4,-7.8,78.8C-22.4,78.2,-37.2,73.1,-49.1,64.3C-61,55.5,-70,43,-75.1,29C-80.2,15,-81.4,-0.5,-77.7,-14.5C-74,-28.5,-65.4,-41,-54,-49.5C-42.6,-58,-28.4,-62.5,-14.8,-68.3C-1.2,-74.1,29,-78.9,42.1,-72Z',
	blob5: 'M45.3,-77.8C59.2,-70.7,71.6,-59.8,79.3,-46.2C87,-32.6,90,-16.3,88.4,-0.9C86.8,14.5,80.6,29,71.7,41.3C62.8,53.6,51.2,63.7,38,70.5C24.8,77.3,10,80.8,-4.5,80.1C-19,79.4,-33.2,74.5,-45.3,66.5C-57.4,58.5,-67.4,47.4,-74,34.3C-80.6,21.2,-83.8,6.1,-82.1,-8.2C-80.4,-22.5,-73.8,-36,-63.8,-46.4C-53.8,-56.8,-40.4,-64.1,-27,-71.5C-13.6,-78.9,0.2,-86.4,13.2,-85.1C26.2,-83.8,31.4,-84.9,45.3,-77.8Z',
	blob6: 'M39.5,-67.4C52.3,-60.3,64.8,-51.6,72.6,-39.7C80.4,-27.8,83.5,-12.7,82.1,1.6C80.7,15.9,74.8,29.4,66.2,40.8C57.6,52.2,46.3,61.5,33.6,68.2C20.9,74.9,6.8,79,-8.4,78.7C-23.6,78.4,-39.9,73.7,-52.6,64.7C-65.3,55.7,-74.4,42.4,-79.2,27.6C-84,12.8,-84.5,-3.5,-80,-17.8C-75.5,-32.1,-66,-44.4,-53.9,-51.8C-41.8,-59.2,-27.1,-61.7,-13.5,-66.5C0.1,-71.3,26.7,-74.5,39.5,-67.4Z',
};


// Helper: gradient mask style based on fade side
const getGradientMaskStyle = ( side ) => {
	if ( side === 'none' ) {
		return {};
	}
	const directions = {
		top: 'to top',
		bottom: 'to bottom',
		left: 'to left',
		right: 'to right',
	};
	return {
		WebkitMaskImage: `linear-gradient(${ directions[ side ] }, black 30%, transparent 100%)`,
		maskImage: `linear-gradient(${ directions[ side ] }, black 30%, transparent 100%)`,
	};
};

// Helper: render a blob SVG preview
const renderBlobSvg = ( blobKey, color, size = 60 ) => {
	const pathData = BLOB_PRESETS[ blobKey ];
	if ( ! pathData ) {
		return null;
	}
	return (
		<svg viewBox="0 0 200 200" width={ size } height={ size } xmlns="http://www.w3.org/2000/svg">
			<g transform="translate(100,100)">
				<path d={ pathData } fill={ color || '#ccc' } />
			</g>
		</svg>
	);
};

export default function Edit( { attributes, setAttributes } ) {
	// Get theme color palette dynamically
	const themeColors = useSetting( 'color.palette' ) || [];

	const {
		layout,
		heading,
		subheading,
		headingStyle,
		headingSize,
		headingTransform,
		backgroundType,
		backgroundColor,
		backgroundImage,
		overlayColor,
		overlayOpacity,
		heroImage,
		imageBorderWidth,
		imageBorderColor,
		imageShadowSize,
		imageRotation,
		showImageDecoShapes,
		ctaText,
		ctaUrl,
		ctaStyle,
		secondaryCtaText,
		secondaryCtaUrl,
		minHeight,
		contentAlign,
		verticalAlign,
		textColor,
		decorativeShape,
		decorativeColor,
		socialLinks,
		headingFullWidth,
		headingOverlayImage,
		// Background shapes
		bgShape1Type, bgShape1CustomSvg, bgShape1Color, bgShape1GradientSide, bgShape1Size, bgShape1Position, bgShape1Opacity,
		bgShape2Type, bgShape2CustomSvg, bgShape2Color, bgShape2GradientSide, bgShape2Size, bgShape2Position, bgShape2Opacity,
		bgShape3Type, bgShape3CustomSvg, bgShape3Color, bgShape3GradientSide, bgShape3Size, bgShape3Position, bgShape3Opacity,
		// Image blobs
		imageBlob1Shape, imageBlob1Color, imageBlob1Size, imageBlob1Rotation,
		imageBlob2Shape, imageBlob2Color, imageBlob2Size, imageBlob2Rotation,
		// CTA icons
		ctaIcon, ctaIconPosition,
		secondaryCtaIcon, secondaryCtaIconPosition,
	} = attributes;

	const isSplitLayout = layout === 'split-right' || layout === 'split-left';

	const getBackgroundStyle = () => {
		if ( backgroundType === 'image' && backgroundImage?.url ) {
			return {
				backgroundImage: `url(${ backgroundImage.url })`,
				backgroundSize: 'cover',
				backgroundPosition: 'center',
			};
		}
		return {
			backgroundColor: backgroundColor || '#1040C0',
		};
	};

	const getHeadingClasses = () => {
		const classes = [ 'sfcore-hero__heading' ];
		classes.push( `sfcore-hero__heading--${ headingStyle }` );
		classes.push( `sfcore-hero__heading--${ headingSize }` );
		if ( headingTransform !== 'none' ) {
			classes.push( `sfcore-hero__heading--${ headingTransform }` );
		}
		if ( headingFullWidth ) {
			classes.push( 'sfcore-hero__heading--full-width' );
		}
		if ( headingOverlayImage && isSplitLayout ) {
			classes.push( 'sfcore-hero__heading--overlay' );
		}
		return classes.join( ' ' );
	};

	// Social link management functions
	const addSocialLink = () => {
		const newLinks = [ ...socialLinks, { icon: 'facebook', url: '', customIcon: null } ];
		setAttributes( { socialLinks: newLinks } );
	};

	const updateSocialLink = ( index, field, value ) => {
		const newLinks = [ ...socialLinks ];
		newLinks[ index ] = { ...newLinks[ index ], [ field ]: value };
		setAttributes( { socialLinks: newLinks } );
	};

	const removeSocialLink = ( index ) => {
		const newLinks = socialLinks.filter( ( _, i ) => i !== index );
		setAttributes( { socialLinks: newLinks } );
	};

	const renderSocialIcon = ( iconKey ) => {
		const icon = SOCIAL_ICONS[ iconKey ];
		if ( ! icon ) return null;
		return (
			<span
				className="sfcore-hero__social-icon"
				dangerouslySetInnerHTML={ { __html: icon.svg } }
			/>
		);
	};

	const getImageShadow = () => {
		const shadows = {
			none: 'none',
			small: '4px 4px 0 #121212',
			medium: '8px 8px 0 #121212',
			large: '12px 12px 0 #121212',
			xlarge: '16px 16px 0 #121212',
		};
		return shadows[ imageShadowSize ] || shadows.large;
	};

	// Helper to render a CTA icon in the editor preview
	const renderCtaIcon = ( iconObj ) => {
		if ( ! iconObj || iconObj.type === 'none' ) {
			return null;
		}
		if ( iconObj.type === 'auto' ) {
			return (
				<span className="sfcore-hero__cta-icon">
					<Icon icon={ external } size={ 16 } />
				</span>
			);
		}
		if ( iconObj.type === 'library' && CTA_ICON_LIBRARY[ iconObj.value ] ) {
			return (
				<span className="sfcore-hero__cta-icon">
					<Icon icon={ CTA_ICON_LIBRARY[ iconObj.value ] } size={ 16 } />
				</span>
			);
		}
		if ( iconObj.type === 'custom' && iconObj.value ) {
			return (
				<span className="sfcore-hero__cta-icon">
					<img src={ iconObj.value } alt="" className="sfcore-hero__cta-icon-img" />
				</span>
			);
		}
		return null;
	};

	const blockProps = useBlockProps( {
		className: `sfcore-hero sfcore-hero--layout-${ layout } sfcore-hero--align-${ contentAlign } sfcore-hero--valign-${ verticalAlign }${ headingOverlayImage && isSplitLayout ? ' sfcore-hero--heading-overlay' : '' }`,
		style: {
			...getBackgroundStyle(),
			minHeight: `${ minHeight }px`,
			color: textColor,
		},
	} );

	const renderOverlay = () => {
		if ( backgroundType !== 'image' || ! backgroundImage?.url ) {
			return null;
		}
		return (
			<div
				className="sfcore-hero__overlay"
				style={ {
					backgroundColor: overlayColor,
					opacity: overlayOpacity / 100,
				} }
				aria-hidden="true"
			/>
		);
	};

	const renderDecorativeShape = () => {
		if ( decorativeShape === 'none' || isSplitLayout ) {
			return null;
		}
		return (
			<div
				className={ `sfcore-hero__shape sfcore-hero__shape--${ decorativeShape }` }
				style={ { backgroundColor: decorativeColor } }
				aria-hidden="true"
			/>
		);
	};

	// Render a single background shape
	const renderBgShape = ( type, customSvg, color, gradientSide, size, position, opacity ) => {
		if ( type === 'none' ) {
			return null;
		}

		const shapeClasses = `sfcore-hero__bg-shape sfcore-hero__bg-shape--${ size } sfcore-hero__bg-shape--${ position }`;
		const shapeStyle = {
			opacity: opacity / 100,
			...getGradientMaskStyle( gradientSide ),
		};

		if ( type === 'custom' && customSvg?.url ) {
			return (
				<div className={ shapeClasses } style={ shapeStyle } aria-hidden="true">
					<img src={ customSvg.url } alt="" className="sfcore-hero__bg-shape-img" />
				</div>
			);
		}

		// Built-in shape
		return (
			<div
				className={ `${ shapeClasses } sfcore-hero__bg-shape--${ type }` }
				style={ { ...shapeStyle, backgroundColor: color } }
				aria-hidden="true"
			/>
		);
	};

	// Render blob SVG inside image container
	const renderImageBlob = ( blobShape, blobColor, blobSize, blobRotation, position ) => {
		if ( blobShape === 'none' || ! BLOB_PRESETS[ blobShape ] ) {
			return null;
		}

		const sizeMap = { small: 120, medium: 180, large: 260 };
		const svgSize = sizeMap[ blobSize ] || sizeMap.medium;

		return (
			<div
				className={ `sfcore-hero__blob sfcore-hero__blob--${ position }` }
				style={ { transform: `rotate(${ blobRotation }deg)` } }
				aria-hidden="true"
			>
				<svg viewBox="0 0 200 200" width={ svgSize } height={ svgSize } xmlns="http://www.w3.org/2000/svg">
					<g transform="translate(100,100)">
						<path d={ BLOB_PRESETS[ blobShape ] } fill={ blobColor } />
					</g>
				</svg>
			</div>
		);
	};

	const renderHeroImage = () => {
		if ( ! isSplitLayout ) {
			return null;
		}

		return (
			<div className="sfcore-hero__image-container">
				{ showImageDecoShapes && (
					<>
						{ renderImageBlob( imageBlob1Shape, imageBlob1Color, imageBlob1Size, imageBlob1Rotation, '1' ) }
						{ renderImageBlob( imageBlob2Shape, imageBlob2Color, imageBlob2Size, imageBlob2Rotation, '2' ) }
					</>
				) }
				{ heroImage?.url ? (
					<div
						className="sfcore-hero__image-wrapper"
						style={ {
							borderWidth: `${ imageBorderWidth }px`,
							borderColor: imageBorderColor,
							boxShadow: getImageShadow(),
							transform: `rotate(${ imageRotation }deg)`,
						} }
					>
						<img
							src={ heroImage.url }
							alt={ heroImage.alt || '' }
							className="sfcore-hero__image"
						/>
					</div>
				) : (
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) =>
								setAttributes( {
									heroImage: {
										url: media.url,
										alt: media.alt,
										id: media.id,
									},
								} )
							}
							allowedTypes={ [ 'image' ] }
							render={ ( { open } ) => (
								<div
									className="sfcore-hero__image-placeholder"
									onClick={ open }
									onKeyDown={ ( e ) => e.key === 'Enter' && open() }
									role="button"
									tabIndex={ 0 }
								>
									<span>{ __( 'Click to add image', 'swishfolio-core' ) }</span>
								</div>
							) }
						/>
					</MediaUploadCheck>
				) }
			</div>
		);
	};

	// Background Shape Inspector Panel
	const BgShapePanel = ( { index, type, customSvg, color, gradientSide, size, position, opacity } ) => {
		const prefix = `bgShape${ index }`;
		return (
			<div style={ { padding: '12px', marginBottom: '12px', border: '1px solid #ddd', borderRadius: '4px', background: '#f9f9f9' } }>
				<p style={ { fontWeight: 600, fontSize: '13px', marginBottom: '8px', marginTop: 0 } }>
					{ __( 'Shape', 'swishfolio-core' ) } { index }
				</p>

				<SelectControl
					label={ __( 'Type', 'swishfolio-core' ) }
					value={ type }
					options={ [
						{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
						{ label: __( 'Circle', 'swishfolio-core' ), value: 'circle' },
						{ label: __( 'Square', 'swishfolio-core' ), value: 'square' },
						{ label: __( 'Triangle', 'swishfolio-core' ), value: 'triangle' },
						{ label: __( 'Custom SVG', 'swishfolio-core' ), value: 'custom' },
					] }
					onChange={ ( val ) => setAttributes( { [ `${ prefix }Type` ]: val } ) }
				/>

				{ type === 'custom' && (
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ ( media ) =>
								setAttributes( { [ `${ prefix }CustomSvg` ]: { url: media.url, id: media.id, alt: media.alt } } )
							}
							allowedTypes={ [ 'image/svg+xml', 'image' ] }
							render={ ( { open } ) => (
								<>
									{ customSvg?.url ? (
										<div className="sfcore-image-preview" style={ { marginBottom: '8px' } }>
											<img src={ customSvg.url } alt="" style={ { maxWidth: '80px', maxHeight: '80px' } } />
											<ButtonGroup>
												<Button variant="secondary" onClick={ open } size="small">
													{ __( 'Replace', 'swishfolio-core' ) }
												</Button>
												<Button variant="tertiary" isDestructive onClick={ () => setAttributes( { [ `${ prefix }CustomSvg` ]: {} } ) } size="small">
													{ __( 'Remove', 'swishfolio-core' ) }
												</Button>
											</ButtonGroup>
										</div>
									) : (
										<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
											{ __( 'Upload SVG', 'swishfolio-core' ) }
										</Button>
									) }
								</>
							) }
						/>
					</MediaUploadCheck>
				) }

				{ type !== 'none' && (
					<>
						{ type !== 'custom' && (
							<>
								<p className="components-base-control__label">
									{ __( 'Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ themeColors }
									value={ color }
									onChange={ ( val ) => setAttributes( { [ `${ prefix }Color` ]: val } ) }
								/>
							</>
						) }

						<ToggleGroupControl
							label={ __( 'Gradient Fade', 'swishfolio-core' ) }
							value={ gradientSide }
							onChange={ ( val ) => setAttributes( { [ `${ prefix }GradientSide` ]: val } ) }
							isBlock
						>
							<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="top" label={ __( 'Top', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="bottom" label={ __( 'Btm', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
						</ToggleGroupControl>

						<ToggleGroupControl
							label={ __( 'Size', 'swishfolio-core' ) }
							value={ size }
							onChange={ ( val ) => setAttributes( { [ `${ prefix }Size` ]: val } ) }
							isBlock
						>
							<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
						</ToggleGroupControl>

						<SelectControl
							label={ __( 'Position', 'swishfolio-core' ) }
							value={ position }
							options={ [
								{ label: __( 'Top Left', 'swishfolio-core' ), value: 'top-left' },
								{ label: __( 'Top Right', 'swishfolio-core' ), value: 'top-right' },
								{ label: __( 'Bottom Left', 'swishfolio-core' ), value: 'bottom-left' },
								{ label: __( 'Bottom Right', 'swishfolio-core' ), value: 'bottom-right' },
								{ label: __( 'Center Left', 'swishfolio-core' ), value: 'center-left' },
								{ label: __( 'Center Right', 'swishfolio-core' ), value: 'center-right' },
							] }
							onChange={ ( val ) => setAttributes( { [ `${ prefix }Position` ]: val } ) }
						/>

						<RangeControl
							label={ __( 'Opacity', 'swishfolio-core' ) }
							value={ opacity }
							onChange={ ( val ) => setAttributes( { [ `${ prefix }Opacity` ]: val } ) }
							min={ 0 }
							max={ 100 }
						/>
					</>
				) }
			</div>
		);
	};

	// CTA Icon Settings sub-panel
	const CtaIconPanel = ( { label, icon, setIcon, iconPosition, setIconPosition } ) => (
		<div style={ { padding: '12px', marginBottom: '12px', border: '1px solid #ddd', borderRadius: '4px', background: '#f9f9f9' } }>
			<p style={ { fontWeight: 600, fontSize: '13px', marginBottom: '8px', marginTop: 0 } }>
				{ label }
			</p>

			<SelectControl
				label={ __( 'Icon Source', 'swishfolio-core' ) }
				value={ icon.type }
				options={ [
					{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
					{ label: __( 'Auto-detect External', 'swishfolio-core' ), value: 'auto' },
					{ label: __( 'Icon Library', 'swishfolio-core' ), value: 'library' },
					{ label: __( 'Custom SVG', 'swishfolio-core' ), value: 'custom' },
				] }
				onChange={ ( type ) => setIcon( { type, value: type === 'library' ? 'arrow-right' : '' } ) }
			/>

			{ icon.type === 'library' && (
				<>
					<p className="components-base-control__label">
						{ __( 'Select Icon', 'swishfolio-core' ) }
					</p>
					<div className="sfcore-icon-picker">
						{ Object.keys( CTA_ICON_LIBRARY ).map( ( iconKey ) => (
							<Button
								key={ iconKey }
								className={ `sfcore-icon-picker__item ${ icon.value === iconKey ? 'is-selected' : '' }` }
								onClick={ () => setIcon( { ...icon, value: iconKey } ) }
								label={ iconKey }
							>
								<Icon icon={ CTA_ICON_LIBRARY[ iconKey ] } />
							</Button>
						) ) }
					</div>
				</>
			) }

			{ icon.type === 'custom' && (
				<MediaUploadCheck>
					<MediaUpload
						onSelect={ ( media ) => setIcon( { ...icon, value: media.url } ) }
						allowedTypes={ [ 'image/svg+xml', 'image' ] }
						render={ ( { open } ) => (
							<>
								{ icon.value ? (
									<div className="sfcore-custom-icon-preview" style={ { marginBottom: '8px' } }>
										<img src={ icon.value } alt="" style={ { maxWidth: '40px', maxHeight: '40px' } } />
										<ButtonGroup>
											<Button variant="secondary" onClick={ open } size="small">
												{ __( 'Replace', 'swishfolio-core' ) }
											</Button>
											<Button variant="tertiary" isDestructive onClick={ () => setIcon( { ...icon, value: '' } ) } size="small">
												{ __( 'Remove', 'swishfolio-core' ) }
											</Button>
										</ButtonGroup>
									</div>
								) : (
									<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
										{ __( 'Upload SVG', 'swishfolio-core' ) }
									</Button>
								) }
							</>
						) }
					/>
				</MediaUploadCheck>
			) }

			{ icon.type !== 'none' && (
				<ToggleGroupControl
					label={ __( 'Icon Position', 'swishfolio-core' ) }
					value={ iconPosition }
					onChange={ setIconPosition }
					isBlock
				>
					<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
					<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
				</ToggleGroupControl>
			) }
		</div>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) }>
					<SelectControl
						label={ __( 'Layout Style', 'swishfolio-core' ) }
						value={ layout }
						options={ [
							{ label: __( 'Full Background', 'swishfolio-core' ), value: 'full' },
							{ label: __( 'Split - Image Right', 'swishfolio-core' ), value: 'split-right' },
							{ label: __( 'Split - Image Left', 'swishfolio-core' ), value: 'split-left' },
						] }
						onChange={ ( value ) => setAttributes( { layout: value } ) }
					/>

					<RangeControl
						label={ __( 'Minimum Height (px)', 'swishfolio-core' ) }
						value={ minHeight }
						onChange={ ( value ) => setAttributes( { minHeight: value } ) }
						min={ 300 }
						max={ 1000 }
						step={ 10 }
					/>

					{ ! isSplitLayout && (
						<>
							<ToggleGroupControl
								label={ __( 'Content Alignment', 'swishfolio-core' ) }
								value={ contentAlign }
								onChange={ ( value ) => setAttributes( { contentAlign: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="left" label={ __( 'Left', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="center" label={ __( 'Center', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="right" label={ __( 'Right', 'swishfolio-core' ) } />
							</ToggleGroupControl>

							<ToggleGroupControl
								label={ __( 'Vertical Alignment', 'swishfolio-core' ) }
								value={ verticalAlign }
								onChange={ ( value ) => setAttributes( { verticalAlign: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="top" label={ __( 'Top', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="center" label={ __( 'Center', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="bottom" label={ __( 'Bottom', 'swishfolio-core' ) } />
							</ToggleGroupControl>
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Heading Style', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleGroupControl
						label={ __( 'Style', 'swishfolio-core' ) }
						value={ headingStyle }
						onChange={ ( value ) => setAttributes( { headingStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="normal" label={ __( 'Normal', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="shadow" label={ __( 'Shadow', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Size', 'swishfolio-core' ) }
						value={ headingSize }
						onChange={ ( value ) => setAttributes( { headingSize: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="xlarge" label={ __( 'XL', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleGroupControl
						label={ __( 'Text Transform', 'swishfolio-core' ) }
						value={ headingTransform }
						onChange={ ( value ) => setAttributes( { headingTransform: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="uppercase" label={ __( 'UPPER', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<ToggleControl
						label={ __( 'Full Width Heading', 'swishfolio-core' ) }
						help={ __( 'Stretch heading to 100% width', 'swishfolio-core' ) }
						checked={ headingFullWidth }
						onChange={ ( value ) => setAttributes( { headingFullWidth: value } ) }
					/>

					{ isSplitLayout && (
						<ToggleControl
							label={ __( 'Overlay Image', 'swishfolio-core' ) }
							help={ __( 'Let heading extend over the image', 'swishfolio-core' ) }
							checked={ headingOverlayImage }
							onChange={ ( value ) => setAttributes( { headingOverlayImage: value } ) }
						/>
					) }

					<p className="components-base-control__label">
						{ __( 'Text Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ textColor }
						onChange={ ( color ) => setAttributes( { textColor: color } ) }
						clearable
					/>
				</PanelBody>

				<PanelBody title={ __( 'Background', 'swishfolio-core' ) } initialOpen={ false }>
					<ToggleGroupControl
						label={ __( 'Background Type', 'swishfolio-core' ) }
						value={ backgroundType }
						onChange={ ( value ) => setAttributes( { backgroundType: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="color" label={ __( 'Color', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="image" label={ __( 'Image', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					{ backgroundType === 'color' && (
						<>
							<p className="components-base-control__label">
								{ __( 'Background Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ backgroundColor }
								onChange={ ( color ) => setAttributes( { backgroundColor: color } ) }
								clearable
							/>
						</>
					) }

					{ backgroundType === 'image' && (
						<>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ ( media ) =>
										setAttributes( {
											backgroundImage: {
												url: media.url,
												alt: media.alt,
												id: media.id,
											},
										} )
									}
									allowedTypes={ [ 'image' ] }
									render={ ( { open } ) => (
										<>
											{ backgroundImage?.url ? (
												<div className="sfcore-image-preview">
													<img src={ backgroundImage.url } alt="" />
													<ButtonGroup>
														<Button variant="secondary" onClick={ open }>
															{ __( 'Replace', 'swishfolio-core' ) }
														</Button>
														<Button
															variant="tertiary"
															isDestructive
															onClick={ () => setAttributes( { backgroundImage: {} } ) }
														>
															{ __( 'Remove', 'swishfolio-core' ) }
														</Button>
													</ButtonGroup>
												</div>
											) : (
												<Button variant="secondary" onClick={ open }>
													{ __( 'Select Background Image', 'swishfolio-core' ) }
												</Button>
											) }
										</>
									) }
								/>
							</MediaUploadCheck>

							{ backgroundImage?.url && (
								<>
									<p className="components-base-control__label" style={ { marginTop: '16px' } }>
										{ __( 'Overlay Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ themeColors }
										value={ overlayColor }
										onChange={ ( color ) => setAttributes( { overlayColor: color } ) }
									/>
									<RangeControl
										label={ __( 'Overlay Opacity', 'swishfolio-core' ) }
										value={ overlayOpacity }
										onChange={ ( value ) => setAttributes( { overlayOpacity: value } ) }
										min={ 0 }
										max={ 100 }
									/>
								</>
							) }
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Background Shapes', 'swishfolio-core' ) } initialOpen={ false }>
					<p className="components-base-control__help" style={ { marginTop: 0, marginBottom: '12px' } }>
						{ __( 'Add decorative shapes behind the hero content. Supports SVG upload.', 'swishfolio-core' ) }
					</p>
					<BgShapePanel index={ 1 } type={ bgShape1Type } customSvg={ bgShape1CustomSvg } color={ bgShape1Color } gradientSide={ bgShape1GradientSide } size={ bgShape1Size } position={ bgShape1Position } opacity={ bgShape1Opacity } />
					<BgShapePanel index={ 2 } type={ bgShape2Type } customSvg={ bgShape2CustomSvg } color={ bgShape2Color } gradientSide={ bgShape2GradientSide } size={ bgShape2Size } position={ bgShape2Position } opacity={ bgShape2Opacity } />
					<BgShapePanel index={ 3 } type={ bgShape3Type } customSvg={ bgShape3CustomSvg } color={ bgShape3Color } gradientSide={ bgShape3GradientSide } size={ bgShape3Size } position={ bgShape3Position } opacity={ bgShape3Opacity } />
				</PanelBody>

				{ isSplitLayout && (
					<PanelBody title={ __( 'Hero Image', 'swishfolio-core' ) } initialOpen={ true }>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ ( media ) =>
									setAttributes( {
										heroImage: {
											url: media.url,
											alt: media.alt,
											id: media.id,
										},
									} )
								}
								allowedTypes={ [ 'image' ] }
								render={ ( { open } ) => (
									<>
										{ heroImage?.url ? (
											<div className="sfcore-image-preview">
												<img src={ heroImage.url } alt="" />
												<ButtonGroup>
													<Button variant="secondary" onClick={ open }>
														{ __( 'Replace', 'swishfolio-core' ) }
													</Button>
													<Button
														variant="tertiary"
														isDestructive
														onClick={ () => setAttributes( { heroImage: {} } ) }
													>
														{ __( 'Remove', 'swishfolio-core' ) }
													</Button>
												</ButtonGroup>
											</div>
										) : (
											<Button variant="secondary" onClick={ open }>
												{ __( 'Select Hero Image', 'swishfolio-core' ) }
											</Button>
										) }
									</>
								) }
							/>
						</MediaUploadCheck>

						<RangeControl
							label={ __( 'Border Width', 'swishfolio-core' ) }
							value={ imageBorderWidth }
							onChange={ ( value ) => setAttributes( { imageBorderWidth: value } ) }
							min={ 0 }
							max={ 12 }
						/>

						<p className="components-base-control__label">
							{ __( 'Border Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ imageBorderColor }
							onChange={ ( color ) => setAttributes( { imageBorderColor: color } ) }
						/>

						<ToggleGroupControl
							label={ __( 'Shadow Size', 'swishfolio-core' ) }
							value={ imageShadowSize }
							onChange={ ( value ) => setAttributes( { imageShadowSize: value } ) }
							isBlock
						>
							<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="xlarge" label={ __( 'XL', 'swishfolio-core' ) } />
						</ToggleGroupControl>

						<RangeControl
							label={ __( 'Image Rotation', 'swishfolio-core' ) }
							value={ imageRotation }
							onChange={ ( value ) => setAttributes( { imageRotation: value } ) }
							min={ -10 }
							max={ 10 }
						/>
					</PanelBody>
				) }

				{ isSplitLayout && (
					<PanelBody title={ __( 'Image Blobs', 'swishfolio-core' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show Blobs', 'swishfolio-core' ) }
							checked={ showImageDecoShapes }
							onChange={ ( value ) => setAttributes( { showImageDecoShapes: value } ) }
						/>

						{ showImageDecoShapes && (
							<>
								<div style={ { padding: '12px', marginBottom: '12px', border: '1px solid #ddd', borderRadius: '4px', background: '#f9f9f9' } }>
									<p style={ { fontWeight: 600, fontSize: '13px', marginBottom: '8px', marginTop: 0 } }>
										{ __( 'Blob 1', 'swishfolio-core' ) }
									</p>
									<p className="components-base-control__label">
										{ __( 'Shape', 'swishfolio-core' ) }
									</p>
									<div className="sfcore-blob-picker">
										<Button
											className={ `sfcore-blob-picker__item ${ imageBlob1Shape === 'none' ? 'is-selected' : '' }` }
											onClick={ () => setAttributes( { imageBlob1Shape: 'none' } ) }
											label={ __( 'None', 'swishfolio-core' ) }
										>
											<span style={ { fontSize: '11px' } }>{ __( 'None', 'swishfolio-core' ) }</span>
										</Button>
										{ Object.keys( BLOB_PRESETS ).map( ( blobKey ) => (
											<Button
												key={ blobKey }
												className={ `sfcore-blob-picker__item ${ imageBlob1Shape === blobKey ? 'is-selected' : '' }` }
												onClick={ () => setAttributes( { imageBlob1Shape: blobKey } ) }
												label={ blobKey }
											>
												{ renderBlobSvg( blobKey, imageBlob1Color || '#ccc', 36 ) }
											</Button>
										) ) }
									</div>
									<p className="components-base-control__label">
										{ __( 'Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ themeColors }
										value={ imageBlob1Color }
										onChange={ ( color ) => setAttributes( { imageBlob1Color: color } ) }
									/>
									<ToggleGroupControl
										label={ __( 'Size', 'swishfolio-core' ) }
										value={ imageBlob1Size }
										onChange={ ( value ) => setAttributes( { imageBlob1Size: value } ) }
										isBlock
									>
										<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
										<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
										<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
									</ToggleGroupControl>
									<RangeControl
										label={ __( 'Rotation', 'swishfolio-core' ) }
										value={ imageBlob1Rotation }
										onChange={ ( value ) => setAttributes( { imageBlob1Rotation: value } ) }
										min={ -180 }
										max={ 180 }
									/>
								</div>

								<div style={ { padding: '12px', marginBottom: '12px', border: '1px solid #ddd', borderRadius: '4px', background: '#f9f9f9' } }>
									<p style={ { fontWeight: 600, fontSize: '13px', marginBottom: '8px', marginTop: 0 } }>
										{ __( 'Blob 2', 'swishfolio-core' ) }
									</p>
									<p className="components-base-control__label">
										{ __( 'Shape', 'swishfolio-core' ) }
									</p>
									<div className="sfcore-blob-picker">
										<Button
											className={ `sfcore-blob-picker__item ${ imageBlob2Shape === 'none' ? 'is-selected' : '' }` }
											onClick={ () => setAttributes( { imageBlob2Shape: 'none' } ) }
											label={ __( 'None', 'swishfolio-core' ) }
										>
											<span style={ { fontSize: '11px' } }>{ __( 'None', 'swishfolio-core' ) }</span>
										</Button>
										{ Object.keys( BLOB_PRESETS ).map( ( blobKey ) => (
											<Button
												key={ blobKey }
												className={ `sfcore-blob-picker__item ${ imageBlob2Shape === blobKey ? 'is-selected' : '' }` }
												onClick={ () => setAttributes( { imageBlob2Shape: blobKey } ) }
												label={ blobKey }
											>
												{ renderBlobSvg( blobKey, imageBlob2Color || '#ccc', 36 ) }
											</Button>
										) ) }
									</div>
									<p className="components-base-control__label">
										{ __( 'Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ themeColors }
										value={ imageBlob2Color }
										onChange={ ( color ) => setAttributes( { imageBlob2Color: color } ) }
									/>
									<ToggleGroupControl
										label={ __( 'Size', 'swishfolio-core' ) }
										value={ imageBlob2Size }
										onChange={ ( value ) => setAttributes( { imageBlob2Size: value } ) }
										isBlock
									>
										<ToggleGroupControlOption value="small" label={ __( 'S', 'swishfolio-core' ) } />
										<ToggleGroupControlOption value="medium" label={ __( 'M', 'swishfolio-core' ) } />
										<ToggleGroupControlOption value="large" label={ __( 'L', 'swishfolio-core' ) } />
									</ToggleGroupControl>
									<RangeControl
										label={ __( 'Rotation', 'swishfolio-core' ) }
										value={ imageBlob2Rotation }
										onChange={ ( value ) => setAttributes( { imageBlob2Rotation: value } ) }
										min={ -180 }
										max={ 180 }
									/>
								</div>
							</>
						) }
					</PanelBody>
				) }

				<PanelBody title={ __( 'Call to Action', 'swishfolio-core' ) } initialOpen={ false }>
					<URLInput
						label={ __( 'Primary Button URL', 'swishfolio-core' ) }
						value={ ctaUrl }
						onChange={ ( url ) => setAttributes( { ctaUrl: url } ) }
					/>
					<ToggleGroupControl
						label={ __( 'Primary Button Style', 'swishfolio-core' ) }
						value={ ctaStyle }
						onChange={ ( value ) => setAttributes( { ctaStyle: value } ) }
						isBlock
					>
						<ToggleGroupControlOption value="primary" label={ __( 'Primary', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="secondary" label={ __( 'Secondary', 'swishfolio-core' ) } />
						<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'swishfolio-core' ) } />
					</ToggleGroupControl>

					<URLInput
						label={ __( 'Secondary Button URL', 'swishfolio-core' ) }
						value={ secondaryCtaUrl }
						onChange={ ( url ) => setAttributes( { secondaryCtaUrl: url } ) }
					/>

					<CtaIconPanel
						label={ __( 'Primary Button Icon', 'swishfolio-core' ) }
						icon={ ctaIcon }
						setIcon={ ( val ) => setAttributes( { ctaIcon: val } ) }
						iconPosition={ ctaIconPosition }
						setIconPosition={ ( val ) => setAttributes( { ctaIconPosition: val } ) }
					/>

					<CtaIconPanel
						label={ __( 'Secondary Button Icon', 'swishfolio-core' ) }
						icon={ secondaryCtaIcon }
						setIcon={ ( val ) => setAttributes( { secondaryCtaIcon: val } ) }
						iconPosition={ secondaryCtaIconPosition }
						setIconPosition={ ( val ) => setAttributes( { secondaryCtaIconPosition: val } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Links', 'swishfolio-core' ) } initialOpen={ false }>
					<p className="components-base-control__help" style={ { marginTop: 0, marginBottom: '12px' } }>
						{ __( 'Add social media links that appear after the buttons.', 'swishfolio-core' ) }
					</p>

					{ socialLinks.map( ( link, index ) => (
						<div
							key={ index }
							style={ {
								padding: '12px',
								marginBottom: '12px',
								border: '1px solid #ddd',
								borderRadius: '4px',
								background: '#f9f9f9',
							} }
						>
							<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } }>
								<span style={ { fontWeight: 600, fontSize: '13px' } }>
									{ __( 'Link', 'swishfolio-core' ) } { index + 1 }
								</span>
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => removeSocialLink( index ) }
									icon="no-alt"
									label={ __( 'Remove', 'swishfolio-core' ) }
									style={ { minWidth: 'auto', padding: '0' } }
								/>
							</div>

							<SelectControl
								label={ __( 'Icon', 'swishfolio-core' ) }
								value={ link.icon }
								options={ Object.keys( SOCIAL_ICONS ).map( ( key ) => ( {
									label: SOCIAL_ICONS[ key ].label,
									value: key,
								} ) ) }
								onChange={ ( value ) => updateSocialLink( index, 'icon', value ) }
							/>

							{ link.icon === 'custom' && (
								<MediaUploadCheck>
									<MediaUpload
										onSelect={ ( media ) =>
											updateSocialLink( index, 'customIcon', {
												url: media.url,
												alt: media.alt,
												id: media.id,
											} )
										}
										allowedTypes={ [ 'image' ] }
										render={ ( { open } ) => (
											<>
												{ link.customIcon?.url ? (
													<div className="sfcore-image-preview" style={ { marginBottom: '8px' } }>
														<img
															src={ link.customIcon.url }
															alt=""
															style={ { maxWidth: '40px', maxHeight: '40px' } }
														/>
														<ButtonGroup>
															<Button variant="secondary" onClick={ open } size="small">
																{ __( 'Replace', 'swishfolio-core' ) }
															</Button>
															<Button
																variant="tertiary"
																isDestructive
																onClick={ () => updateSocialLink( index, 'customIcon', null ) }
																size="small"
															>
																{ __( 'Remove', 'swishfolio-core' ) }
															</Button>
														</ButtonGroup>
													</div>
												) : (
													<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
														{ __( 'Upload Custom Icon', 'swishfolio-core' ) }
													</Button>
												) }
											</>
										) }
									/>
								</MediaUploadCheck>
							) }

							<TextControl
								label={ __( 'URL', 'swishfolio-core' ) }
								value={ link.url }
								onChange={ ( value ) => updateSocialLink( index, 'url', value ) }
								placeholder="https://"
							/>
						</div>
					) ) }

					<Button
						variant="secondary"
						onClick={ addSocialLink }
						icon="plus"
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ __( 'Add Link', 'swishfolio-core' ) }
					</Button>
				</PanelBody>

				{ ! isSplitLayout && (
					<PanelBody title={ __( 'Decorative Shape', 'swishfolio-core' ) } initialOpen={ false }>
						<SelectControl
							label={ __( 'Shape', 'swishfolio-core' ) }
							value={ decorativeShape }
							options={ [
								{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
								{ label: __( 'Circle', 'swishfolio-core' ), value: 'circle' },
								{ label: __( 'Square', 'swishfolio-core' ), value: 'square' },
								{ label: __( 'Triangle', 'swishfolio-core' ), value: 'triangle' },
							] }
							onChange={ ( value ) => setAttributes( { decorativeShape: value } ) }
						/>
						{ decorativeShape !== 'none' && (
							<>
								<p className="components-base-control__label">
									{ __( 'Shape Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ themeColors }
									value={ decorativeColor }
									onChange={ ( color ) => setAttributes( { decorativeColor: color } ) }
								/>
							</>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ renderOverlay() }
				{ renderDecorativeShape() }
				{ renderBgShape( bgShape1Type, bgShape1CustomSvg, bgShape1Color, bgShape1GradientSide, bgShape1Size, bgShape1Position, bgShape1Opacity ) }
				{ renderBgShape( bgShape2Type, bgShape2CustomSvg, bgShape2Color, bgShape2GradientSide, bgShape2Size, bgShape2Position, bgShape2Opacity ) }
				{ renderBgShape( bgShape3Type, bgShape3CustomSvg, bgShape3Color, bgShape3GradientSide, bgShape3Size, bgShape3Position, bgShape3Opacity ) }

				<div className="sfcore-hero__inner">
					<div className="sfcore-hero__content">
						<RichText
							tagName="h1"
							className={ getHeadingClasses() }
							value={ heading }
							onChange={ ( value ) => setAttributes( { heading: value } ) }
							placeholder={ __( 'Hero Heading', 'swishfolio-core' ) }
							style={ { color: textColor } }
						/>
						<RichText
							tagName="p"
							className="sfcore-hero__subheading"
							value={ subheading }
							onChange={ ( value ) => setAttributes( { subheading: value } ) }
							placeholder={ __( 'Add a subheading...', 'swishfolio-core' ) }
							style={ { color: textColor } }
						/>
						<div className="sfcore-hero__buttons">
							<span className={ `sfcore-hero__cta sfcore-hero__cta--${ ctaStyle } sfcore-hero__cta--has-icon` }>
								{ ctaIconPosition === 'left' && renderCtaIcon( ctaIcon ) }
								<RichText
									tagName="span"
									className="sfcore-hero__cta-text"
									value={ ctaText }
									onChange={ ( value ) => setAttributes( { ctaText: value } ) }
									placeholder={ __( 'Primary Button', 'swishfolio-core' ) }
								/>
								{ ctaIconPosition === 'right' && renderCtaIcon( ctaIcon ) }
							</span>
							<span className="sfcore-hero__cta sfcore-hero__cta--outline sfcore-hero__cta--has-icon">
								{ secondaryCtaIconPosition === 'left' && renderCtaIcon( secondaryCtaIcon ) }
								<RichText
									tagName="span"
									className="sfcore-hero__cta-text"
									value={ secondaryCtaText }
									onChange={ ( value ) => setAttributes( { secondaryCtaText: value } ) }
									placeholder={ __( 'Secondary Button', 'swishfolio-core' ) }
								/>
								{ secondaryCtaIconPosition === 'right' && renderCtaIcon( secondaryCtaIcon ) }
							</span>
						</div>

						{ socialLinks && socialLinks.length > 0 && (
							<div className="sfcore-hero__social-links">
								{ socialLinks.map( ( link, index ) => (
									<span key={ index } className="sfcore-hero__social-link">
										{ link.icon === 'custom' && link.customIcon?.url ? (
											<img
												src={ link.customIcon.url }
												alt={ link.customIcon.alt || '' }
												className="sfcore-hero__social-custom-icon"
											/>
										) : (
											renderSocialIcon( link.icon )
										) }
									</span>
								) ) }
							</div>
						) }
					</div>

					{ renderHeroImage() }
				</div>
			</div>
		</>
	);
}
