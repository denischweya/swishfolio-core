/**
 * Hero Block - Save Component
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';
import { SOCIAL_ICONS } from '../../shared/social-icons';
import {
	arrowRight,
	arrowLeft,
	arrowUp,
	arrowDown,
	chevronRight,
	chevronLeft,
	external,
	download,
	upload,
	video,
	image,
	starFilled,
	starEmpty,
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
} from '@wordpress/icons';

// CTA Icon library (must match edit.js)
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

// Blob shape presets (must match edit.js)
const BLOB_PRESETS = {
	blob1: 'M44.5,-76.3C58.1,-69.1,70,-57.5,77.4,-43.6C84.8,-29.7,87.8,-13.4,86.1,2.1C84.4,17.5,78,32.3,68.4,44.3C58.8,56.3,46,65.5,32,72.1C18,78.7,2.8,82.7,-12.2,81.3C-27.2,79.9,-42,73.1,-54.3,63.1C-66.6,53.1,-76.4,39.9,-81.1,25C-85.8,10.1,-85.4,-6.5,-80.4,-21.1C-75.4,-35.7,-65.8,-48.3,-53.4,-56.1C-41,-63.9,-25.8,-66.9,-10.8,-71.5C4.2,-76.1,30.9,-83.5,44.5,-76.3Z',
	blob2: 'M40.9,-69.5C53.4,-63.1,64.4,-53,72,-40.6C79.6,-28.2,83.8,-13.5,83.3,0.8C82.8,15.1,77.6,29,69.1,40.5C60.6,52,48.8,61.1,35.8,67.1C22.8,73.1,8.6,76,-6.4,76.2C-21.4,76.4,-37.2,73.9,-49.4,66.3C-61.6,58.7,-70.2,46,-75.3,32C-80.4,18,-82,-0.3,-78.4,-17C-74.8,-33.7,-66,-48.8,-53.8,-55.5C-41.6,-62.2,-26,-60.5,-12,-63.5C2,-66.5,28.4,-75.9,40.9,-69.5Z',
	blob3: 'M38.6,-65C50.5,-58.5,61.2,-49,69.2,-37.1C77.2,-25.2,82.5,-10.8,81.6,3C80.7,16.8,73.6,30.2,64.4,41.3C55.2,52.4,43.9,61.2,31.2,67.5C18.5,73.8,4.4,77.6,-10.8,77.6C-26,77.6,-42.3,73.8,-54.5,64.8C-66.7,55.8,-74.8,41.6,-79.2,26.2C-83.6,10.8,-84.3,-5.8,-79.7,-20.5C-75.1,-35.2,-65.2,-48,-52.5,-54.8C-39.8,-61.6,-24.3,-62.4,-10.4,-63.9C3.5,-65.4,26.7,-71.5,38.6,-65Z',
	blob4: 'M42.1,-72C55.2,-65.1,67,-54.8,74.7,-41.9C82.4,-29,86,-13.5,84.6,1.2C83.2,15.9,76.8,29.8,67.8,41.4C58.8,53,47.2,62.3,34.2,68.9C21.2,75.5,6.8,79.4,-7.8,78.8C-22.4,78.2,-37.2,73.1,-49.1,64.3C-61,55.5,-70,43,-75.1,29C-80.2,15,-81.4,-0.5,-77.7,-14.5C-74,-28.5,-65.4,-41,-54,-49.5C-42.6,-58,-28.4,-62.5,-14.8,-68.3C-1.2,-74.1,29,-78.9,42.1,-72Z',
	blob5: 'M45.3,-77.8C59.2,-70.7,71.6,-59.8,79.3,-46.2C87,-32.6,90,-16.3,88.4,-0.9C86.8,14.5,80.6,29,71.7,41.3C62.8,53.6,51.2,63.7,38,70.5C24.8,77.3,10,80.8,-4.5,80.1C-19,79.4,-33.2,74.5,-45.3,66.5C-57.4,58.5,-67.4,47.4,-74,34.3C-80.6,21.2,-83.8,6.1,-82.1,-8.2C-80.4,-22.5,-73.8,-36,-63.8,-46.4C-53.8,-56.8,-40.4,-64.1,-27,-71.5C-13.6,-78.9,0.2,-86.4,13.2,-85.1C26.2,-83.8,31.4,-84.9,45.3,-77.8Z',
	blob6: 'M39.5,-67.4C52.3,-60.3,64.8,-51.6,72.6,-39.7C80.4,-27.8,83.5,-12.7,82.1,1.6C80.7,15.9,74.8,29.4,66.2,40.8C57.6,52.2,46.3,61.5,33.6,68.2C20.9,74.9,6.8,79,-8.4,78.7C-23.6,78.4,-39.9,73.7,-52.6,64.7C-65.3,55.7,-74.4,42.4,-79.2,27.6C-84,12.8,-84.5,-3.5,-80,-17.8C-75.5,-32.1,-66,-44.4,-53.9,-51.8C-41.8,-59.2,-27.1,-61.7,-13.5,-66.5C0.1,-71.3,26.7,-74.5,39.5,-67.4Z',
};


// Gradient mask style for background shapes
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

// External URL detection for auto-detect icon
const isExternalUrl = ( url ) => {
	if ( ! url ) {
		return false;
	}
	if ( url.startsWith( '/' ) || url.startsWith( '#' ) || url.startsWith( '?' ) ) {
		return false;
	}
	try {
		const linkUrl = new URL( url, window.location.origin );
		return linkUrl.origin !== window.location.origin;
	} catch {
		return false;
	}
};

export default function save( { attributes } ) {
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

	// Render a background shape
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

	// Render CTA icon (with external URL auto-detection)
	const renderCtaIcon = ( iconObj, url ) => {
		if ( ! iconObj || iconObj.type === 'none' ) {
			return null;
		}

		if ( iconObj.type === 'auto' ) {
			if ( ! isExternalUrl( url ) ) {
				return null;
			}
			return (
				<span className="sfcore-hero__cta-icon">
					{ external }
				</span>
			);
		}

		if ( iconObj.type === 'library' && CTA_ICON_LIBRARY[ iconObj.value ] ) {
			return (
				<span className="sfcore-hero__cta-icon">
					{ CTA_ICON_LIBRARY[ iconObj.value ] }
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

	// Determine if a CTA button has an icon to render
	const ctaHasIcon = ( iconObj, url ) => {
		if ( ! iconObj || iconObj.type === 'none' ) {
			return false;
		}
		if ( iconObj.type === 'auto' ) {
			return isExternalUrl( url );
		}
		if ( iconObj.type === 'library' ) {
			return !! CTA_ICON_LIBRARY[ iconObj.value ];
		}
		if ( iconObj.type === 'custom' ) {
			return !! iconObj.value;
		}
		return false;
	};

	const blockProps = useBlockProps.save( {
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

	const renderHeroImage = () => {
		if ( ! isSplitLayout || ! heroImage?.url ) {
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
			</div>
		);
	};

	const primaryHasIcon = ctaHasIcon( ctaIcon, ctaUrl );
	const secondaryHasIcon = ctaHasIcon( secondaryCtaIcon, secondaryCtaUrl );

	return (
		<div { ...blockProps }>
			{ renderOverlay() }
			{ renderDecorativeShape() }
			{ renderBgShape( bgShape1Type, bgShape1CustomSvg, bgShape1Color, bgShape1GradientSide, bgShape1Size, bgShape1Position, bgShape1Opacity ) }
			{ renderBgShape( bgShape2Type, bgShape2CustomSvg, bgShape2Color, bgShape2GradientSide, bgShape2Size, bgShape2Position, bgShape2Opacity ) }
			{ renderBgShape( bgShape3Type, bgShape3CustomSvg, bgShape3Color, bgShape3GradientSide, bgShape3Size, bgShape3Position, bgShape3Opacity ) }

			<div className="sfcore-hero__inner">
				<div className="sfcore-hero__content">
					{ heading && (
						<RichText.Content
							tagName="h1"
							className={ getHeadingClasses() }
							value={ heading }
							style={ { color: textColor } }
						/>
					) }
					{ subheading && (
						<RichText.Content
							tagName="p"
							className="sfcore-hero__subheading"
							value={ subheading }
							style={ { color: textColor } }
						/>
					) }
					{ ( ctaText || secondaryCtaText ) && (
						<div className="sfcore-hero__buttons">
							{ ctaText && ctaUrl && (
								<a
									href={ ctaUrl }
									className={ `sfcore-hero__cta sfcore-hero__cta--${ ctaStyle }${ primaryHasIcon ? ' sfcore-hero__cta--has-icon' : '' }` }
								>
									{ ctaIconPosition === 'left' && renderCtaIcon( ctaIcon, ctaUrl ) }
									<RichText.Content value={ ctaText } />
									{ ctaIconPosition === 'right' && renderCtaIcon( ctaIcon, ctaUrl ) }
								</a>
							) }
							{ secondaryCtaText && secondaryCtaUrl && (
								<a
									href={ secondaryCtaUrl }
									className={ `sfcore-hero__cta sfcore-hero__cta--outline${ secondaryHasIcon ? ' sfcore-hero__cta--has-icon' : '' }` }
								>
									{ secondaryCtaIconPosition === 'left' && renderCtaIcon( secondaryCtaIcon, secondaryCtaUrl ) }
									<RichText.Content value={ secondaryCtaText } />
									{ secondaryCtaIconPosition === 'right' && renderCtaIcon( secondaryCtaIcon, secondaryCtaUrl ) }
								</a>
							) }
						</div>
					) }

					{ socialLinks && socialLinks.length > 0 && (
						<div className="sfcore-hero__social-links">
							{ socialLinks.map( ( link, index ) => (
								<a
									key={ index }
									href={ link.url }
									className="sfcore-hero__social-link"
									target="_blank"
									rel="noopener noreferrer"
									aria-label={ link.icon !== 'custom' ? SOCIAL_ICONS[ link.icon ]?.label || 'Social link' : 'Social link' }
								>
									{ link.icon === 'custom' && link.customIcon?.url ? (
										<img
											src={ link.customIcon.url }
											alt={ link.customIcon.alt || '' }
											className="sfcore-hero__social-custom-icon"
										/>
									) : (
										<span
											className="sfcore-hero__social-icon"
											dangerouslySetInnerHTML={ { __html: SOCIAL_ICONS[ link.icon ]?.svg || '' } }
										/>
									) }
								</a>
							) ) }
						</div>
					) }
				</div>

				{ renderHeroImage() }
			</div>
		</div>
	);
}
