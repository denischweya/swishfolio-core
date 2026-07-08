/**
 * Bento Cards - Edit Component.
 *
 * Parent layout block. Holds a grid of bento-card inner blocks. Emits CSS
 * custom properties on the wrapper (--grid-gap, --overlay-*, --accent-color,
 * --card-* text colours, --card-text-bg, --card-title-base-size,
 * --layered-bg-color, --layered-padding-top) used by both parent and card
 * styles.
 *
 * The grid-wide `cardStyle` attribute drives which visual style all cards
 * render — see style.scss for the five variants.
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	URLInput,
	useSetting,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ColorPalette,
	RangeControl,
	TextControl,
	ToggleControl,
	FontSizePicker,
} from '@wordpress/components';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

const ALLOWED_BLOCKS = [ 'swishfolio-core/bento-card' ];

const TEMPLATE = [
	[ 'swishfolio-core/bento-card', { cardSize: 'full' } ],
	[ 'swishfolio-core/bento-card', { cardSize: 'two-thirds' } ],
	[ 'swishfolio-core/bento-card', { cardSize: 'one-third' } ],
];

const STYLE_OPTIONS = [
	{ label: __( 'Overlay (default)', 'swishfolio-core' ), value: 'overlay' },
	{ label: __( 'Hover Reveal', 'swishfolio-core' ), value: 'hover-reveal' },
	{ label: __( 'Caption Below', 'swishfolio-core' ), value: 'caption-below' },
	{ label: __( 'Stacked', 'swishfolio-core' ), value: 'stacked' },
	{ label: __( 'CTA', 'swishfolio-core' ), value: 'cta' },
];

// Styles where the image doesn't fill the card — the only styles where the
// layered-image treatment makes visual sense.
const LAYERED_ELIGIBLE_STYLES = [ 'caption-below', 'stacked', 'cta' ];

// Styles that surface a text container / band — where text-bg makes sense.
const TEXT_BG_STYLES = [ 'caption-below', 'stacked', 'cta' ];

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];
	const themeFontSizes = useSetting( 'typography.fontSizes' ) || [];

	const {
		gridGap,
		cardStyle,
		cardOverlayColor,
		cardOverlayOpacity,
		accentColor,
		cardSubtitleColor,
		cardTitleColor,
		cardDescriptionColor,
		cardTextBg,
		cardTitleFontSize,
		layeredImages,
		layeredBgColor,
		layeredPaddingTop,
		layeredTallImage,
		layeredTallHeight,
		layeredTallSpeed,
		ctaType,
		ctaText,
		ctaUrl,
		ctaTextColor,
		ctaBgColor,
	} = attributes;

	const showLayeredToggle = LAYERED_ELIGIBLE_STYLES.includes( cardStyle );
	const showTextBg = TEXT_BG_STYLES.includes( cardStyle );

	const wrapperClasses = [
		'sfcore-bento',
		`sfcore-bento--style-${ cardStyle || 'overlay' }`,
	];
	if ( layeredImages ) {
		wrapperClasses.push( 'sfcore-bento--layered' );
		if ( layeredTallImage ) {
			wrapperClasses.push( 'sfcore-bento--tall-image' );
		}
	}

	const blockProps = useBlockProps( {
		className: wrapperClasses.join( ' ' ),
		style: {
			'--grid-gap': gridGap,
			'--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
			'--overlay-opacity': cardOverlayOpacity / 100,
			'--accent-color': accentColor || '#FFE500',
			'--card-subtitle-color': cardSubtitleColor || undefined,
			'--card-title-color': cardTitleColor || undefined,
			'--card-description-color': cardDescriptionColor || undefined,
			'--card-text-bg': cardTextBg || undefined,
			'--card-title-base-size': cardTitleFontSize || undefined,
			'--layered-bg-color': layeredBgColor || undefined,
			'--layered-padding-top': `${ layeredPaddingTop ?? 10 }%`,
			'--layered-tall-height': `${ layeredTallHeight ?? 395 }px`,
			'--layered-tall-speed': `${ layeredTallSpeed ?? 4.6 }s`,
		},
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'sfcore-bento__grid' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			template: TEMPLATE,
			orientation: 'horizontal',
		}
	);

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
				/>

				{ activeTab === 'general' && (
					<>
						<PanelBody title={ __( 'Card Style', 'swishfolio-core' ) }>
							<SelectControl
								label={ __( 'Style', 'swishfolio-core' ) }
								value={ cardStyle }
								options={ STYLE_OPTIONS }
								onChange={ ( value ) =>
									setAttributes( { cardStyle: value } )
								}
							/>

							{ showLayeredToggle && (
								<>
									<div style={ { marginTop: '16px', paddingTop: '12px', borderTop: '1px solid #ddd' } }>
										<ToggleControl
											label={ __( 'Layered Images', 'swishfolio-core' ) }
											help={ __(
												'Display each image on a background color, bottom-aligned, at a configurable width.',
												'swishfolio-core'
											) }
											checked={ !! layeredImages }
											onChange={ ( value ) =>
												setAttributes( { layeredImages: value } )
											}
										/>
									</div>

									{ layeredImages && (
										<>
											<p className="components-base-control__label" style={ { marginTop: '12px' } }>
												{ __( 'Image Backdrop Color', 'swishfolio-core' ) }
											</p>
											<ColorPalette
												colors={ themeColors }
												value={ layeredBgColor }
												onChange={ ( color ) =>
													setAttributes( { layeredBgColor: color || '' } )
												}
												clearable
											/>

											<RangeControl
												label={ __( 'Padding Top (%)', 'swishfolio-core' ) }
												help={ __(
													'Extra breathing room above the image.',
													'swishfolio-core'
												) }
												value={ layeredPaddingTop }
												onChange={ ( value ) =>
													setAttributes( {
														layeredPaddingTop: value ?? 10,
													} )
												}
												min={ 0 }
												max={ 30 }
												step={ 1 }
											/>

											<div style={ { marginTop: '16px', paddingTop: '12px', borderTop: '1px solid #eee' } }>
												<ToggleControl
													label={ __( 'Tall Image (Hover to Scroll)', 'swishfolio-core' ) }
													help={ __(
														'Allow taller images. Only the top is visible; hover reveals the rest with a scroll animation.',
														'swishfolio-core'
													) }
													checked={ !! layeredTallImage }
													onChange={ ( value ) =>
														setAttributes( { layeredTallImage: value } )
													}
												/>
											</div>

											{ layeredTallImage && (
												<>
													<RangeControl
														label={ __( 'Viewport Height (px)', 'swishfolio-core' ) }
														help={ __(
															'How much of the image is visible before hover.',
															'swishfolio-core'
														) }
														value={ layeredTallHeight }
														onChange={ ( value ) =>
															setAttributes( {
																layeredTallHeight: value ?? 395,
															} )
														}
														min={ 200 }
														max={ 800 }
														step={ 5 }
													/>

													<RangeControl
														label={ __( 'Scroll Speed (s)', 'swishfolio-core' ) }
														help={ __(
															'Duration of the hover reveal. Higher = slower scroll.',
															'swishfolio-core'
														) }
														value={ layeredTallSpeed }
														onChange={ ( value ) =>
															setAttributes( {
																layeredTallSpeed: value ?? 4.6,
															} )
														}
														min={ 0.5 }
														max={ 10 }
														step={ 0.1 }
													/>
												</>
											) }
										</>
									) }
								</>
							) }
						</PanelBody>

						<PanelBody
							title={ __( 'Layout', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<SelectControl
								label={ __( 'Grid Gap', 'swishfolio-core' ) }
								value={ gridGap }
								options={ [
									{ label: __( 'Small (1rem)', 'swishfolio-core' ), value: '1rem' },
									{ label: __( 'Medium (1.5rem)', 'swishfolio-core' ), value: '1.5rem' },
									{ label: __( 'Large (2rem)', 'swishfolio-core' ), value: '2rem' },
									{ label: __( 'Extra Large (3rem)', 'swishfolio-core' ), value: '3rem' },
								] }
								onChange={ ( value ) => setAttributes( { gridGap: value } ) }
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Section CTA', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<SelectControl
								label={ __( 'CTA Type', 'swishfolio-core' ) }
								value={ ctaType }
								options={ [
									{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
									{ label: __( 'Link', 'swishfolio-core' ), value: 'link' },
									{ label: __( 'Button', 'swishfolio-core' ), value: 'button' },
								] }
								onChange={ ( value ) => setAttributes( { ctaType: value } ) }
							/>

							{ ctaType !== 'none' && (
								<>
									<TextControl
										label={ __( 'CTA Text', 'swishfolio-core' ) }
										value={ ctaText }
										onChange={ ( value ) => setAttributes( { ctaText: value } ) }
									/>
									<URLInput
										label={ __( 'CTA URL', 'swishfolio-core' ) }
										value={ ctaUrl }
										onChange={ ( url ) => setAttributes( { ctaUrl: url } ) }
									/>
								</>
							) }
						</PanelBody>
					</>
				) }

				{ activeTab === 'style' && (
					<>
						<PanelBody title={ __( 'Card Style Colours', 'swishfolio-core' ) }>
							<p className="components-base-control__label">
								{ __( 'Accent Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ accentColor }
								onChange={ ( color ) => setAttributes( { accentColor: color || '' } ) }
								clearable
							/>

							{ cardStyle === 'overlay' || cardStyle === 'hover-reveal' ? (
								<>
									<p className="components-base-control__label" style={ { marginTop: '16px' } }>
										{ __( 'Card Overlay Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ themeColors }
										value={ cardOverlayColor }
										onChange={ ( color ) => setAttributes( { cardOverlayColor: color || '' } ) }
										clearable
									/>

									<RangeControl
										label={ __( 'Overlay Opacity', 'swishfolio-core' ) }
										value={ cardOverlayOpacity }
										onChange={ ( value ) => setAttributes( { cardOverlayOpacity: value } ) }
										min={ 0 }
										max={ 100 }
									/>
								</>
							) : null }

							<p className="components-base-control__label" style={ { marginTop: '16px' } }>
								{ __( 'Subtitle Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardSubtitleColor }
								onChange={ ( color ) => setAttributes( { cardSubtitleColor: color || '' } ) }
								clearable
							/>

							<p className="components-base-control__label" style={ { marginTop: '16px' } }>
								{ __( 'Title Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardTitleColor }
								onChange={ ( color ) => setAttributes( { cardTitleColor: color || '' } ) }
								clearable
							/>

							<p className="components-base-control__label" style={ { marginTop: '16px' } }>
								{ __( 'Description Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardDescriptionColor }
								onChange={ ( color ) => setAttributes( { cardDescriptionColor: color || '' } ) }
								clearable
							/>

							{ showTextBg && (
								<>
									<p className="components-base-control__label" style={ { marginTop: '16px' } }>
										{ __( 'Text Background', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ themeColors }
										value={ cardTextBg }
										onChange={ ( color ) => setAttributes( { cardTextBg: color || '' } ) }
										clearable
									/>
								</>
							) }
						</PanelBody>

						<PanelBody
							title={ __( 'Card Typography', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<FontSizePicker
								label={ __( 'Title Base Size', 'swishfolio-core' ) }
								fontSizes={ themeFontSizes }
								value={ cardTitleFontSize }
								onChange={ ( size ) =>
									setAttributes( { cardTitleFontSize: size || '' } )
								}
							/>
						</PanelBody>

						{ ctaType !== 'none' && (
							<PanelBody
								title={ __( 'Section CTA Colours', 'swishfolio-core' ) }
								initialOpen={ false }
							>
								<p className="components-base-control__label">
									{ __( 'Text Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ themeColors }
									value={ ctaTextColor }
									onChange={ ( color ) => setAttributes( { ctaTextColor: color || '' } ) }
									clearable
								/>

								{ ctaType === 'button' && (
									<>
										<p className="components-base-control__label" style={ { marginTop: '12px' } }>
											{ __( 'Background Color', 'swishfolio-core' ) }
										</p>
										<ColorPalette
											colors={ themeColors }
											value={ ctaBgColor }
											onChange={ ( color ) => setAttributes( { ctaBgColor: color || '' } ) }
											clearable
										/>
									</>
								) }
							</PanelBody>
						) }
					</>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<div { ...innerBlocksProps } />

				{ ctaType !== 'none' && ctaText && (
					<div className="sfcore-bento__cta-wrapper">
						<span
							className={ `sfcore-bento__cta sfcore-bento__cta--${ ctaType }` }
							style={ {
								color: ctaTextColor || undefined,
								backgroundColor:
									ctaType === 'button' ? ctaBgColor || undefined : undefined,
							} }
						>
							{ ctaText }
						</span>
					</div>
				) }
			</div>
		</>
	);
}
