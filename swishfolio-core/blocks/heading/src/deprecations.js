/**
 * Custom Heading Block - Deprecations
 *
 * Handles backward compatibility when the block structure changes.
 * Each deprecation represents a previous version of the block's save output.
 */

import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Version 1.0.0 - Initial release
 *
 * Original structure without subHeadingFontSize attribute.
 */
const v1 = {
	attributes: {
		preHeading: {
			type: 'string',
			default: '',
		},
		preHeadingBackgroundColor: {
			type: 'string',
			default: '#FFD93D',
		},
		preHeadingTextColor: {
			type: 'string',
			default: '#000000',
		},
		preHeadingRotation: {
			type: 'number',
			default: 0,
		},
		preHeadingBorderStyle: {
			type: 'string',
			default: 'solid',
		},
		preHeadingBorderWidth: {
			type: 'number',
			default: 4,
		},
		preHeadingBorderColor: {
			type: 'string',
			default: '#000000',
		},
		preHeadingShadowSize: {
			type: 'string',
			default: 'small',
		},
		showPreHeading: {
			type: 'boolean',
			default: true,
		},

		heading: {
			type: 'string',
			default: '',
		},
		headingTag: {
			type: 'string',
			default: 'h2',
		},
		headingBackgroundColor: {
			type: 'string',
			default: '',
		},
		headingTextColor: {
			type: 'string',
			default: '#000000',
		},
		headingRotation: {
			type: 'number',
			default: 0,
		},
		headingBorderStyle: {
			type: 'string',
			default: 'none',
		},
		headingBorderWidth: {
			type: 'number',
			default: 4,
		},
		headingBorderColor: {
			type: 'string',
			default: '#000000',
		},
		headingShadowSize: {
			type: 'string',
			default: 'none',
		},
		headingSize: {
			type: 'string',
			default: 'large',
		},
		headingStyle: {
			type: 'string',
			default: 'normal',
		},
		showHeading: {
			type: 'boolean',
			default: true,
		},

		subHeading: {
			type: 'string',
			default: '',
		},
		subHeadingBackgroundColor: {
			type: 'string',
			default: '',
		},
		subHeadingTextColor: {
			type: 'string',
			default: '#000000',
		},
		subHeadingRotation: {
			type: 'number',
			default: 0,
		},
		subHeadingBorderStyle: {
			type: 'string',
			default: 'none',
		},
		subHeadingBorderWidth: {
			type: 'number',
			default: 4,
		},
		subHeadingBorderColor: {
			type: 'string',
			default: '#000000',
		},
		subHeadingShadowSize: {
			type: 'string',
			default: 'none',
		},
		showSubHeading: {
			type: 'boolean',
			default: true,
		},

		leftIcon: {
			type: 'object',
			default: {
				type: 'none',
				value: '',
			},
		},
		leftIconColor: {
			type: 'string',
			default: '#000000',
		},
		leftIconBackgroundColor: {
			type: 'string',
			default: '',
		},
		leftIconSize: {
			type: 'string',
			default: 'medium',
		},

		rightIcon: {
			type: 'object',
			default: {
				type: 'none',
				value: '',
			},
		},
		rightIconColor: {
			type: 'string',
			default: '#000000',
		},
		rightIconBackgroundColor: {
			type: 'string',
			default: '',
		},
		rightIconSize: {
			type: 'string',
			default: 'medium',
		},

		contentAlign: {
			type: 'string',
			default: 'center',
		},
		blockBackgroundColor: {
			type: 'string',
			default: '',
		},
		showBackgroundPattern: {
			type: 'boolean',
			default: false,
		},
		backgroundPattern: {
			type: 'string',
			default: 'dots',
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
	 * Adds subHeadingFontSize attribute.
	 */
	migrate( attributes ) {
		return {
			...attributes,
			subHeadingFontSize: 'medium',
		};
	},

	save( { attributes } ) {
		const {
			preHeading,
			preHeadingBackgroundColor,
			preHeadingTextColor,
			preHeadingRotation,
			preHeadingBorderStyle,
			preHeadingBorderWidth,
			preHeadingBorderColor,
			preHeadingShadowSize,
			showPreHeading,

			heading,
			headingTag,
			headingBackgroundColor,
			headingTextColor,
			headingRotation,
			headingBorderStyle,
			headingBorderWidth,
			headingBorderColor,
			headingShadowSize,
			headingSize,
			headingStyle,
			showHeading,

			subHeading,
			subHeadingBackgroundColor,
			subHeadingTextColor,
			subHeadingRotation,
			subHeadingBorderStyle,
			subHeadingBorderWidth,
			subHeadingBorderColor,
			subHeadingShadowSize,
			showSubHeading,

			leftIcon,
			leftIconColor,
			leftIconBackgroundColor,
			leftIconSize,

			rightIcon,
			rightIconColor,
			rightIconBackgroundColor,
			rightIconSize,

			contentAlign,
			blockBackgroundColor,
			showBackgroundPattern,
			backgroundPattern,
		} = attributes;

		// Helper function to generate shadow CSS
		const getShadow = ( size ) => {
			const shadows = {
				none: 'none',
				small: '4px 4px 0 #000',
				medium: '8px 8px 0 #000',
				large: '12px 12px 0 #000',
				xlarge: '16px 16px 0 #000',
			};
			return shadows[ size ] || shadows.none;
		};

		// Get element styles
		const getPreHeadingStyles = () => ( {
			backgroundColor: preHeadingBackgroundColor || undefined,
			color: preHeadingTextColor || undefined,
			transform: preHeadingRotation ? `rotate(${ preHeadingRotation }deg)` : undefined,
			borderStyle: preHeadingBorderStyle !== 'none' ? preHeadingBorderStyle : undefined,
			borderWidth: preHeadingBorderStyle !== 'none' ? `${ preHeadingBorderWidth }px` : undefined,
			borderColor: preHeadingBorderStyle !== 'none' ? preHeadingBorderColor : undefined,
			boxShadow: getShadow( preHeadingShadowSize ),
		} );

		const getHeadingStyles = () => ( {
			backgroundColor: headingBackgroundColor || undefined,
			color: headingTextColor || undefined,
			transform: headingRotation ? `rotate(${ headingRotation }deg)` : undefined,
			borderStyle: headingBorderStyle !== 'none' ? headingBorderStyle : undefined,
			borderWidth: headingBorderStyle !== 'none' ? `${ headingBorderWidth }px` : undefined,
			borderColor: headingBorderStyle !== 'none' ? headingBorderColor : undefined,
			boxShadow: getShadow( headingShadowSize ),
		} );

		const getSubHeadingStyles = () => ( {
			backgroundColor: subHeadingBackgroundColor || undefined,
			color: subHeadingTextColor || undefined,
			transform: subHeadingRotation ? `rotate(${ subHeadingRotation }deg)` : undefined,
			borderStyle: subHeadingBorderStyle !== 'none' ? subHeadingBorderStyle : undefined,
			borderWidth: subHeadingBorderStyle !== 'none' ? `${ subHeadingBorderWidth }px` : undefined,
			borderColor: subHeadingBorderStyle !== 'none' ? subHeadingBorderColor : undefined,
			boxShadow: getShadow( subHeadingShadowSize ),
		} );

		// Get heading classes
		const getHeadingClasses = () => {
			const classes = [ 'sfcore-heading__main' ];
			classes.push( `sfcore-heading__main--${ headingSize }` );
			if ( headingStyle !== 'normal' ) {
				classes.push( `sfcore-heading__main--${ headingStyle }` );
			}
			if ( headingBackgroundColor ) {
				classes.push( 'sfcore-heading__main--has-bg' );
			}
			return classes.join( ' ' );
		};

		// Background pattern style
		const getBackgroundPatternStyle = () => {
			if ( ! showBackgroundPattern ) {
				return {};
			}

			const patterns = {
				dots: {
					backgroundImage: 'radial-gradient(#000 1.5px, transparent 1.5px)',
					backgroundSize: '20px 20px',
				},
				grid: {
					backgroundImage: 'linear-gradient(to right, rgba(0, 0, 0, 0.1) 1px, transparent 1px), linear-gradient(to bottom, rgba(0, 0, 0, 0.1) 1px, transparent 1px)',
					backgroundSize: '40px 40px',
				},
				'large-dots': {
					backgroundImage: 'radial-gradient(circle, #000 2px, transparent 2.5px)',
					backgroundSize: '30px 30px',
				},
			};

			return patterns[ backgroundPattern ] || {};
		};

		// Render icon
		const renderIcon = ( icon, color, bgColor, size, position ) => {
			if ( ! icon || icon.type === 'none' ) {
				return null;
			}

			const iconStyles = {
				color: color || undefined,
				backgroundColor: bgColor || undefined,
			};

			const iconClasses = `sfcore-heading__icon sfcore-heading__icon--${ position } sfcore-heading__icon--${ size }`;

			if ( icon.type === 'library' && icon.value ) {
				return (
					<span
						className={ iconClasses }
						style={ iconStyles }
						data-icon={ icon.value }
					>
						<svg
							xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 24 24"
							width="24"
							height="24"
							aria-hidden="true"
							focusable="false"
						>
							<path d="M11.776 4.454a.25.25 0 01.448 0l2.069 4.192a.25.25 0 00.188.137l4.626.672a.25.25 0 01.139.426l-3.348 3.263a.25.25 0 00-.072.222l.79 4.607a.25.25 0 01-.362.263l-4.138-2.175a.25.25 0 00-.232 0l-4.138 2.175a.25.25 0 01-.363-.263l.79-4.607a.25.25 0 00-.071-.222L4.754 9.881a.25.25 0 01.139-.426l4.626-.672a.25.25 0 00.188-.137l2.069-4.192z" />
						</svg>
					</span>
				);
			}

			if ( icon.type === 'custom' && icon.value ) {
				return (
					<span className={ `${ iconClasses } sfcore-heading__icon--custom` } style={ iconStyles }>
						<img src={ icon.value } alt="" />
					</span>
				);
			}

			return null;
		};

		const blockStyles = {
			backgroundColor: blockBackgroundColor || undefined,
			...getBackgroundPatternStyle(),
		};

		const blockProps = useBlockProps.save( {
			className: `sfcore-heading sfcore-heading--align-${ contentAlign }`,
			style: blockStyles,
		} );

		const HeadingTag = headingTag;

		return (
			<div { ...blockProps }>
				<div className="sfcore-heading__inner">
					{ renderIcon( leftIcon, leftIconColor, leftIconBackgroundColor, leftIconSize, 'left' ) }

					<div className="sfcore-heading__content">
						{ showPreHeading && preHeading && (
							<div className="sfcore-heading__pre-wrapper">
								<RichText.Content
									tagName="span"
									className="sfcore-heading__pre"
									value={ preHeading }
									style={ getPreHeadingStyles() }
								/>
							</div>
						) }

						{ showHeading && heading && (
							<div className="sfcore-heading__main-wrapper">
								<RichText.Content
									tagName={ HeadingTag }
									className={ getHeadingClasses() }
									value={ heading }
									style={ getHeadingStyles() }
								/>
							</div>
						) }

						{ showSubHeading && subHeading && (
							<div className="sfcore-heading__sub-wrapper">
								<RichText.Content
									tagName="p"
									className={ `sfcore-heading__sub ${ subHeadingBackgroundColor ? 'sfcore-heading__sub--has-bg' : '' }` }
									value={ subHeading }
									style={ getSubHeadingStyles() }
								/>
							</div>
						) }
					</div>

					{ renderIcon( rightIcon, rightIconColor, rightIconBackgroundColor, rightIconSize, 'right' ) }
				</div>
			</div>
		);
	},
};

// Export deprecations array (newest deprecations first)
export default [ v1 ];
