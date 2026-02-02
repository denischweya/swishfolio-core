/**
 * Hero Block - Deprecations
 *
 * Handles backward compatibility when the block structure changes.
 * Each deprecation represents a previous version of the block's save output.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Version 1.0.0 - Initial release
 *
 * Original structure without layout options, heading styles, or split layouts.
 * Content was directly inside the block without the .sfcore-hero__inner wrapper.
 */
const v1 = {
	attributes: {
		heading: {
			type: 'string',
			default: '',
		},
		subheading: {
			type: 'string',
			default: '',
		},
		backgroundType: {
			type: 'string',
			default: 'color',
		},
		backgroundColor: {
			type: 'string',
			default: '#1040C0',
		},
		backgroundImage: {
			type: 'object',
			default: {},
		},
		overlayColor: {
			type: 'string',
			default: '#121212',
		},
		overlayOpacity: {
			type: 'number',
			default: 50,
		},
		ctaText: {
			type: 'string',
			default: '',
		},
		ctaUrl: {
			type: 'string',
			default: '',
		},
		ctaStyle: {
			type: 'string',
			default: 'primary',
		},
		secondaryCtaText: {
			type: 'string',
			default: '',
		},
		secondaryCtaUrl: {
			type: 'string',
			default: '',
		},
		minHeight: {
			type: 'number',
			default: 600,
		},
		contentAlign: {
			type: 'string',
			default: 'center',
		},
		verticalAlign: {
			type: 'string',
			default: 'center',
		},
		textColor: {
			type: 'string',
			default: '#F0F0F0',
		},
		decorativeShape: {
			type: 'string',
			default: 'none',
		},
		decorativeColor: {
			type: 'string',
			default: '#F0C020',
		},
	},

	supports: {
		align: [ 'wide', 'full' ],
		html: false,
		spacing: {
			margin: true,
			padding: true,
		},
	},

	/**
	 * Migrate v1 attributes to v1.1.0 format.
	 * Adds default values for new attributes.
	 */
	migrate( attributes ) {
		return {
			...attributes,
			// New layout attributes
			layout: 'full',
			// New heading style attributes
			headingStyle: 'normal',
			headingSize: 'large',
			headingTransform: 'none',
			// New hero image attributes (for split layouts)
			heroImage: {},
			imageBorderWidth: 4,
			imageBorderColor: '#121212',
			imageShadowSize: 'large',
			imageRotation: 2,
			// New decorative shape attributes
			showImageDecoShapes: true,
			decoShape1: 'circle',
			decoShape1Color: '#F0C020',
			decoShape2: 'square',
			decoShape2Color: '#D02020',
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
export default [ v1 ];
