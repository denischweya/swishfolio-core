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
} from '@wordpress/components';

// Bauhaus colors
const BAUHAUS_COLORS = [
	{ name: 'Primary Red', color: '#D02020' },
	{ name: 'Primary Blue', color: '#1040C0' },
	{ name: 'Primary Yellow', color: '#F0C020' },
	{ name: 'Contrast', color: '#121212' },
	{ name: 'Base', color: '#F0F0F0' },
	{ name: 'White', color: '#FFFFFF' },
];

// Social media icons
const SOCIAL_ICONS = {
	facebook: {
		label: 'Facebook',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
	},
	twitter: {
		label: 'X (Twitter)',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
	},
	instagram: {
		label: 'Instagram',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>',
	},
	linkedin: {
		label: 'LinkedIn',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
	},
	github: {
		label: 'GitHub',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>',
	},
	youtube: {
		label: 'YouTube',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>',
	},
	tiktok: {
		label: 'TikTok',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
	},
	dribbble: {
		label: 'Dribbble',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 24C5.385 24 0 18.615 0 12S5.385 0 12 0s12 5.385 12 12-5.385 12-12 12zm10.12-10.358c-.35-.11-3.17-.953-6.384-.438 1.34 3.684 1.887 6.684 1.992 7.308 2.3-1.555 3.936-4.02 4.395-6.87zm-6.115 7.808c-.153-.9-.75-4.032-2.19-7.77l-.066.02c-5.79 2.015-7.86 6.025-8.04 6.4 1.73 1.358 3.92 2.166 6.29 2.166 1.42 0 2.77-.29 4-.814zm-11.62-2.58c.232-.4 3.045-5.055 8.332-6.765.135-.045.27-.084.405-.12-.26-.585-.54-1.167-.832-1.74C7.17 11.775 2.206 11.71 1.756 11.7l-.004.312c0 2.633.998 5.037 2.634 6.855zm-2.42-8.955c.46.008 4.683.026 9.477-1.248-1.698-3.018-3.53-5.558-3.8-5.928-2.868 1.35-5.01 3.99-5.676 7.17zM9.6 2.052c.282.38 2.145 2.914 3.822 6 3.645-1.365 5.19-3.44 5.373-3.702-1.81-1.61-4.19-2.586-6.795-2.586-.825 0-1.63.1-2.4.285zm10.335 3.483c-.218.29-1.935 2.493-5.724 4.04.24.49.47.985.68 1.486.08.18.15.36.22.53 3.41-.43 6.8.26 7.14.33-.02-2.42-.88-4.64-2.31-6.38z"/></svg>',
	},
	behance: {
		label: 'Behance',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M6.938 4.503c.702 0 1.34.06 1.92.188.577.13 1.07.33 1.485.61.41.28.733.65.96 1.12.225.47.34 1.05.34 1.73 0 .74-.17 1.36-.507 1.86-.338.5-.837.9-1.502 1.22.906.26 1.576.72 2.022 1.37.448.66.665 1.45.665 2.36 0 .75-.13 1.39-.41 1.93-.28.55-.67 1-1.16 1.35-.48.348-1.05.6-1.67.767-.61.165-1.252.254-1.91.254H0V4.51h6.938v-.007zM6.545 9.66c.6 0 1.086-.16 1.467-.477.383-.316.572-.79.572-1.42 0-.36-.06-.66-.185-.9-.124-.24-.294-.43-.508-.57-.214-.14-.47-.24-.754-.3-.285-.07-.592-.1-.918-.1H3.262v3.76h3.283v.01zm.2 5.69c.364 0 .7-.04 1.01-.12.306-.08.574-.21.8-.38.227-.17.406-.4.54-.68.133-.28.2-.62.2-1.02 0-.8-.225-1.37-.675-1.715-.45-.34-1.047-.51-1.786-.51H3.262v4.43h3.483v-.01zM21.754 16.06c-.4.49-.9.83-1.5 1.03-.604.2-1.25.3-1.938.3-.69 0-1.33-.1-1.92-.31-.59-.21-1.1-.52-1.53-.94-.43-.42-.76-.94-.99-1.56-.23-.62-.35-1.34-.35-2.16 0-.8.12-1.51.36-2.14.24-.63.56-1.16.97-1.59.41-.43.9-.75 1.46-.97.56-.22 1.17-.33 1.83-.33.78 0 1.46.15 2.03.44.57.29 1.04.69 1.41 1.2.37.51.64 1.11.81 1.79.17.68.23 1.42.19 2.21h-7.04c0 .86.27 1.55.79 2.06.52.51 1.21.76 2.08.76.63 0 1.16-.16 1.59-.47.43-.31.73-.68.9-1.1l1.84.69zm-1.67-5.21c-.05-.39-.15-.74-.3-1.04-.15-.3-.35-.55-.58-.75-.24-.2-.51-.35-.82-.45-.31-.1-.65-.15-1.02-.15-.74 0-1.35.23-1.82.69-.47.46-.76 1.04-.87 1.74h5.41v-.04zM15.5 4.8h5v1.58h-5V4.8z"/></svg>',
	},
	pinterest: {
		label: 'Pinterest',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/></svg>',
	},
	custom: {
		label: 'Custom Icon',
		svg: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>',
	},
};

export default function Edit( { attributes, setAttributes } ) {
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

	const renderHeroImage = () => {
		if ( ! isSplitLayout ) {
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
						colors={ BAUHAUS_COLORS }
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
								colors={ BAUHAUS_COLORS }
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
										colors={ BAUHAUS_COLORS }
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
							colors={ BAUHAUS_COLORS }
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
					<PanelBody title={ __( 'Decorative Shapes', 'swishfolio-core' ) } initialOpen={ false }>
						<ToggleControl
							label={ __( 'Show Decorative Shapes', 'swishfolio-core' ) }
							checked={ showImageDecoShapes }
							onChange={ ( value ) => setAttributes( { showImageDecoShapes: value } ) }
						/>

						{ showImageDecoShapes && (
							<>
								<SelectControl
									label={ __( 'Shape 1', 'swishfolio-core' ) }
									value={ decoShape1 }
									options={ [
										{ label: __( 'Circle', 'swishfolio-core' ), value: 'circle' },
										{ label: __( 'Square', 'swishfolio-core' ), value: 'square' },
										{ label: __( 'Triangle', 'swishfolio-core' ), value: 'triangle' },
									] }
									onChange={ ( value ) => setAttributes( { decoShape1: value } ) }
								/>
								<p className="components-base-control__label">
									{ __( 'Shape 1 Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ BAUHAUS_COLORS }
									value={ decoShape1Color }
									onChange={ ( color ) => setAttributes( { decoShape1Color: color } ) }
								/>

								<SelectControl
									label={ __( 'Shape 2', 'swishfolio-core' ) }
									value={ decoShape2 }
									options={ [
										{ label: __( 'Circle', 'swishfolio-core' ), value: 'circle' },
										{ label: __( 'Square', 'swishfolio-core' ), value: 'square' },
										{ label: __( 'Triangle', 'swishfolio-core' ), value: 'triangle' },
									] }
									onChange={ ( value ) => setAttributes( { decoShape2: value } ) }
								/>
								<p className="components-base-control__label">
									{ __( 'Shape 2 Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ BAUHAUS_COLORS }
									value={ decoShape2Color }
									onChange={ ( color ) => setAttributes( { decoShape2Color: color } ) }
								/>
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
									colors={ BAUHAUS_COLORS }
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
							<RichText
								tagName="span"
								className={ `sfcore-hero__cta sfcore-hero__cta--${ ctaStyle }` }
								value={ ctaText }
								onChange={ ( value ) => setAttributes( { ctaText: value } ) }
								placeholder={ __( 'Primary Button', 'swishfolio-core' ) }
							/>
							<RichText
								tagName="span"
								className="sfcore-hero__cta sfcore-hero__cta--outline"
								value={ secondaryCtaText }
								onChange={ ( value ) => setAttributes( { secondaryCtaText: value } ) }
								placeholder={ __( 'Secondary Button', 'swishfolio-core' ) }
							/>
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
