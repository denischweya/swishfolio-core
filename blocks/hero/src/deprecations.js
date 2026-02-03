/**
 * Hero Block - Deprecations
 *
 * Handles backward compatibility when the block structure changes.
 * Each deprecation represents a previous version of the block's save output.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Version 1.1.0 - Added split layouts, heading styles, social links
 *
 * Before background shapes, image blobs, and CTA icons were added.
 */

// Social media icons for v1.1
const V1_1_SOCIAL_ICONS = {
	facebook: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
	twitter: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
	instagram: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
	linkedin: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
	github: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>',
	youtube: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
	tiktok: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
	dribbble: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 24C5.385 24 0 18.615 0 12S5.385 0 12 0s12 5.385 12 12-5.385 12-12 12zm10.12-10.358c-.35-.11-3.17-.953-6.384-.438 1.34 3.684 1.887 6.684 1.992 7.308 2.3-1.555 3.936-4.02 4.395-6.87zm-6.115 7.808c-.153-.9-.75-4.032-2.19-7.77l-.066.02c-5.79 2.015-7.86 6.025-8.04 6.4 1.73 1.358 3.92 2.166 6.29 2.166 1.42 0 2.77-.29 4-.814zm-11.62-2.58c.232-.4 3.045-5.055 8.332-6.765.135-.045.27-.084.405-.12-.26-.585-.54-1.167-.832-1.74C7.17 11.775 2.206 11.71 1.756 11.7l-.004.312c0 2.633.998 5.037 2.634 6.855zm-2.42-8.955c.46.008 4.683.026 9.477-1.248-1.698-3.018-3.53-5.558-3.8-5.928-2.868 1.35-5.01 3.99-5.676 7.17zM9.6 2.052c.282.38 2.145 2.914 3.822 6 3.645-1.365 5.19-3.44 5.373-3.702-1.81-1.61-4.19-2.586-6.795-2.586-.825 0-1.63.1-2.4.285zm10.335 3.483c-.218.29-1.935 2.493-5.724 4.04.24.49.47.985.68 1.486.08.18.15.36.22.53 3.41-.43 6.8.26 7.14.33-.02-2.42-.88-4.64-2.31-6.38z"/></svg>',
	behance: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.938 4.503c.702 0 1.34.06 1.92.188.577.13 1.07.33 1.485.61.41.28.733.65.96 1.12.225.47.34 1.05.34 1.73 0 .74-.17 1.36-.507 1.86-.338.5-.837.9-1.502 1.22.906.26 1.576.72 2.022 1.37.448.66.665 1.45.665 2.36 0 .75-.13 1.39-.41 1.93-.28.55-.67 1-1.16 1.35-.48.348-1.05.6-1.67.767-.61.165-1.252.254-1.91.254H0V4.51h6.938v-.007zM6.545 9.66c.6 0 1.086-.16 1.467-.477.383-.316.572-.79.572-1.42 0-.36-.06-.66-.185-.9-.124-.24-.294-.43-.508-.57-.214-.14-.47-.24-.754-.3-.285-.07-.592-.1-.918-.1H3.262v3.76h3.283v.01zm.2 5.69c.364 0 .7-.04 1.01-.12.306-.08.574-.21.8-.38.227-.17.406-.4.54-.68.133-.28.2-.62.2-1.02 0-.8-.225-1.37-.675-1.715-.45-.34-1.047-.51-1.786-.51H3.262v4.43h3.483v-.01zM21.754 16.06c-.4.49-.9.83-1.5 1.03-.604.2-1.25.3-1.938.3-.69 0-1.33-.1-1.92-.31-.59-.21-1.1-.52-1.53-.94-.43-.42-.76-.94-.99-1.56-.23-.62-.35-1.34-.35-2.16 0-.8.12-1.51.36-2.14.24-.63.56-1.16.97-1.59.41-.43.9-.75 1.46-.97.56-.22 1.17-.33 1.83-.33.78 0 1.46.15 2.03.44.57.29 1.04.69 1.41 1.2.37.51.64 1.11.81 1.79.17.68.23 1.42.19 2.21h-7.04c0 .86.27 1.55.79 2.06.52.51 1.21.76 2.08.76.63 0 1.16-.16 1.59-.47.43-.31.73-.68.9-1.1l1.84.69zm-1.67-5.21c-.05-.39-.15-.74-.3-1.04-.15-.3-.35-.55-.58-.75-.24-.2-.51-.35-.82-.45-.31-.1-.65-.15-1.02-.15-.74 0-1.35.23-1.82.69-.47.46-.76 1.04-.87 1.74h5.41v-.04zM15.5 4.8h5v1.58h-5V4.8z"/></svg>',
	pinterest: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/></svg>',
};

const v1_1 = {
	attributes: {
		layout: { type: 'string', default: 'full' },
		heading: { type: 'string', default: '' },
		subheading: { type: 'string', default: '' },
		headingStyle: { type: 'string', default: 'normal' },
		headingSize: { type: 'string', default: 'large' },
		headingTransform: { type: 'string', default: 'none' },
		backgroundType: { type: 'string', default: 'color' },
		backgroundColor: { type: 'string', default: '#1040C0' },
		backgroundImage: { type: 'object', default: {} },
		overlayColor: { type: 'string', default: '#121212' },
		overlayOpacity: { type: 'number', default: 50 },
		heroImage: { type: 'object', default: {} },
		imageBorderWidth: { type: 'number', default: 4 },
		imageBorderColor: { type: 'string', default: '#121212' },
		imageShadowSize: { type: 'string', default: 'large' },
		imageRotation: { type: 'number', default: 2 },
		showImageDecoShapes: { type: 'boolean', default: true },
		decoShape1: { type: 'string', default: 'circle' },
		decoShape1Color: { type: 'string', default: '#F0C020' },
		decoShape2: { type: 'string', default: 'square' },
		decoShape2Color: { type: 'string', default: '#D02020' },
		ctaText: { type: 'string', default: '' },
		ctaUrl: { type: 'string', default: '' },
		ctaStyle: { type: 'string', default: 'primary' },
		secondaryCtaText: { type: 'string', default: '' },
		secondaryCtaUrl: { type: 'string', default: '' },
		minHeight: { type: 'number', default: 600 },
		contentAlign: { type: 'string', default: 'center' },
		verticalAlign: { type: 'string', default: 'center' },
		textColor: { type: 'string', default: '#F0F0F0' },
		decorativeShape: { type: 'string', default: 'none' },
		decorativeColor: { type: 'string', default: '#F0C020' },
		socialLinks: { type: 'array', default: [] },
		headingFullWidth: { type: 'boolean', default: false },
		headingOverlayImage: { type: 'boolean', default: false },
	},

	supports: {
		align: [ 'wide', 'full' ],
		html: false,
		spacing: { margin: true, padding: true },
	},

	migrate( attributes ) {
		return {
			...attributes,
			// Background shapes - defaults to none
			bgShape1Type: 'none',
			bgShape1CustomSvg: {},
			bgShape1Color: '#F0C020',
			bgShape1GradientSide: 'none',
			bgShape1Size: 'medium',
			bgShape1Position: 'top-right',
			bgShape1Opacity: 30,
			bgShape2Type: 'none',
			bgShape2CustomSvg: {},
			bgShape2Color: '#D02020',
			bgShape2GradientSide: 'none',
			bgShape2Size: 'medium',
			bgShape2Position: 'bottom-left',
			bgShape2Opacity: 30,
			bgShape3Type: 'none',
			bgShape3CustomSvg: {},
			bgShape3Color: '#1040C0',
			bgShape3GradientSide: 'none',
			bgShape3Size: 'small',
			bgShape3Position: 'center-left',
			bgShape3Opacity: 30,
			// Image blobs
			imageBlob1Shape: 'blob1',
			imageBlob1Color: attributes.decoShape1Color || '#F0C020',
			imageBlob1Size: 'medium',
			imageBlob1Rotation: 0,
			imageBlob2Shape: 'blob2',
			imageBlob2Color: attributes.decoShape2Color || '#D02020',
			imageBlob2Size: 'medium',
			imageBlob2Rotation: 0,
			// CTA icons
			ctaIcon: { type: 'auto', value: '' },
			ctaIconPosition: 'right',
			secondaryCtaIcon: { type: 'auto', value: '' },
			secondaryCtaIconPosition: 'right',
		};
	},

	save( { attributes } ) {
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
			decoShape1,
			decoShape1Color,
			decoShape2,
			decoShape2Color,
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
							<div
								className={ `sfcore-hero__deco-shape sfcore-hero__deco-shape--1 sfcore-hero__deco-shape--${ decoShape1 }` }
								style={ { backgroundColor: decoShape1Color } }
								aria-hidden="true"
							/>
							<div
								className={ `sfcore-hero__deco-shape sfcore-hero__deco-shape--2 sfcore-hero__deco-shape--${ decoShape2 }` }
								style={ { backgroundColor: decoShape2Color } }
								aria-hidden="true"
							/>
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

		return (
			<div { ...blockProps }>
				{ renderOverlay() }
				{ renderDecorativeShape() }

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
										className={ `sfcore-hero__cta sfcore-hero__cta--${ ctaStyle }` }
									>
										<RichText.Content value={ ctaText } />
									</a>
								) }
								{ secondaryCtaText && secondaryCtaUrl && (
									<a
										href={ secondaryCtaUrl }
										className="sfcore-hero__cta sfcore-hero__cta--outline"
									>
										<RichText.Content value={ secondaryCtaText } />
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
										aria-label={ link.icon !== 'custom' ? V1_1_SOCIAL_ICONS[ link.icon ] ? link.icon : 'Social link' : 'Social link' }
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
												dangerouslySetInnerHTML={ { __html: V1_1_SOCIAL_ICONS[ link.icon ] || '' } }
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
	},
};

/**
 * Version 1.0.0 - Initial release
 *
 * Original structure without layout options, heading styles, or split layouts.
 * Content was directly inside the block without the .sfcore-hero__inner wrapper.
 */
const v1 = {
	attributes: {
		heading: { type: 'string', default: '' },
		subheading: { type: 'string', default: '' },
		backgroundType: { type: 'string', default: 'color' },
		backgroundColor: { type: 'string', default: '#1040C0' },
		backgroundImage: { type: 'object', default: {} },
		overlayColor: { type: 'string', default: '#121212' },
		overlayOpacity: { type: 'number', default: 50 },
		ctaText: { type: 'string', default: '' },
		ctaUrl: { type: 'string', default: '' },
		ctaStyle: { type: 'string', default: 'primary' },
		secondaryCtaText: { type: 'string', default: '' },
		secondaryCtaUrl: { type: 'string', default: '' },
		minHeight: { type: 'number', default: 600 },
		contentAlign: { type: 'string', default: 'center' },
		verticalAlign: { type: 'string', default: 'center' },
		textColor: { type: 'string', default: '#F0F0F0' },
		decorativeShape: { type: 'string', default: 'none' },
		decorativeColor: { type: 'string', default: '#F0C020' },
	},

	supports: {
		align: [ 'wide', 'full' ],
		html: false,
		spacing: { margin: true, padding: true },
	},

	migrate( attributes ) {
		return {
			...attributes,
			layout: 'full',
			headingStyle: 'normal',
			headingSize: 'large',
			headingTransform: 'none',
			heroImage: {},
			imageBorderWidth: 4,
			imageBorderColor: '#121212',
			imageShadowSize: 'large',
			imageRotation: 2,
			showImageDecoShapes: true,
			decoShape1: 'circle',
			decoShape1Color: '#F0C020',
			decoShape2: 'square',
			decoShape2Color: '#D02020',
			socialLinks: [],
			headingFullWidth: false,
			headingOverlayImage: false,
			// v1.2 attributes
			bgShape1Type: 'none',
			bgShape1CustomSvg: {},
			bgShape1Color: '#F0C020',
			bgShape1GradientSide: 'none',
			bgShape1Size: 'medium',
			bgShape1Position: 'top-right',
			bgShape1Opacity: 30,
			bgShape2Type: 'none',
			bgShape2CustomSvg: {},
			bgShape2Color: '#D02020',
			bgShape2GradientSide: 'none',
			bgShape2Size: 'medium',
			bgShape2Position: 'bottom-left',
			bgShape2Opacity: 30,
			bgShape3Type: 'none',
			bgShape3CustomSvg: {},
			bgShape3Color: '#1040C0',
			bgShape3GradientSide: 'none',
			bgShape3Size: 'small',
			bgShape3Position: 'center-left',
			bgShape3Opacity: 30,
			imageBlob1Shape: 'blob1',
			imageBlob1Color: '#F0C020',
			imageBlob1Size: 'medium',
			imageBlob1Rotation: 0,
			imageBlob2Shape: 'blob2',
			imageBlob2Color: '#D02020',
			imageBlob2Size: 'medium',
			imageBlob2Rotation: 0,
			ctaIcon: { type: 'auto', value: '' },
			ctaIconPosition: 'right',
			secondaryCtaIcon: { type: 'auto', value: '' },
			secondaryCtaIconPosition: 'right',
		};
	},

	save( { attributes } ) {
		const {
			heading,
			subheading,
			backgroundType,
			backgroundColor,
			backgroundImage,
			overlayColor,
			overlayOpacity,
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
		} = attributes;

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

		const blockProps = useBlockProps.save( {
			className: `sfcore-hero sfcore-hero--align-${ contentAlign } sfcore-hero--valign-${ verticalAlign }`,
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
			if ( decorativeShape === 'none' ) {
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

		return (
			<div { ...blockProps }>
				{ renderOverlay() }
				{ renderDecorativeShape() }
				<div className="sfcore-hero__content">
					{ heading && (
						<RichText.Content
							tagName="h1"
							className="sfcore-hero__heading"
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
									className={ `sfcore-hero__cta sfcore-hero__cta--${ ctaStyle }` }
								>
									<RichText.Content value={ ctaText } />
								</a>
							) }
							{ secondaryCtaText && secondaryCtaUrl && (
								<a
									href={ secondaryCtaUrl }
									className="sfcore-hero__cta sfcore-hero__cta--outline"
								>
									<RichText.Content value={ secondaryCtaText } />
								</a>
							) }
						</div>
					) }
				</div>
			</div>
		);
	},
};

// Export deprecations array (newest deprecations first)
export default [ v1_1, v1 ];
