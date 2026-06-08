/**
 * Feature Card Block - Edit Component.
 *
 * Renders one card inside the Features block. Per-card style attributes,
 * when set, override the parent block's globals via inline style; when
 * empty, the inline style falls back to the parent's CSS custom properties
 * (--features-*), so the global wins.
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
	Button,
	ButtonGroup,
	ColorPalette,
	RangeControl,
	FontSizePicker,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Icon,
} from '@wordpress/components';
import {
	starFilled,
	starEmpty,
	check,
	code,
	desktop,
	mobile,
	globe,
	lock,
	key,
	people,
	store,
	tag,
	category,
	grid,
	list,
	settings,
	tool,
	brush,
	image,
	video,
	download,
	upload,
	external,
	info,
	warning,
	inbox,
	envelope,
	shield,
	gift,
	bell,
	calendar,
	cog,
	pencil,
	plugins,
	arrowRight,
} from '@wordpress/icons';
import InspectorTabs, {
	useInspectorTabs,
} from '../../shared/components/inspector-tabs';

// Icon library mapping (matches the Features block icon set).
const ICON_LIBRARY = {
	star: starFilled,
	'star-empty': starEmpty,
	check,
	code,
	desktop,
	mobile,
	globe,
	lock,
	key,
	people,
	store,
	tag,
	category,
	grid,
	list,
	settings,
	tool,
	brush,
	image,
	video,
	download,
	upload,
	external,
	info,
	warning,
	inbox,
	envelope,
	shield,
	gift,
	bell,
	calendar,
	cog,
	pencil,
	plugins,
};

// When an override attribute is empty, fall back to the parent's CSS var.
const inherit = ( override, varName ) =>
	override || `var(${ varName })`;

export default function Edit( { attributes, setAttributes } ) {
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];
	const themeFontSizes = useSetting( 'typography.fontSizes' ) || [];

	const {
		subheading,
		title,
		titleTag,
		description,
		icon,
		ctaType,
		ctaText,
		ctaUrl,
		cardSize,
		cardStyle,
		backgroundImage,
		backgroundImagePosition,
		overlayColor,
		overlayOpacity,
		titleFontSize,
		subheadingFontSize,
		titleColor,
		iconColor,
		iconBackgroundColor,
		ctaTextColor,
		ctaBackgroundColor,
		cardBackgroundColor,
		cardAccentColor,
	} = attributes;

	const classes = [
		'sfcore-features__card',
		`sfcore-features__card--${ cardSize || 'medium' }`,
		`sfcore-features__card--${ cardStyle || 'default' }`,
	];
	if ( backgroundImage?.url ) {
		classes.push( 'sfcore-features__card--has-bg-image' );
	}
	if ( backgroundImage?.url && overlayColor ) {
		classes.push( 'sfcore-features__card--has-overlay' );
	}

	const blockProps = useBlockProps( {
		className: classes.join( ' ' ),
		style: {
			'--card-bg': inherit( cardBackgroundColor, '--features-card-bg' ),
			'--card-accent': inherit( cardAccentColor, '--features-card-accent' ),
		},
	} );

	const renderIcon = () => {
		if ( ! icon || icon.type === 'none' ) {
			return null;
		}
		const iconStyle = {
			color: inherit( iconColor, '--features-icon-color' ),
			backgroundColor: inherit( iconBackgroundColor, '--features-icon-bg' ),
		};
		if ( icon.type === 'library' && ICON_LIBRARY[ icon.value ] ) {
			return (
				<span className="sfcore-features__card-icon" style={ iconStyle }>
					<Icon icon={ ICON_LIBRARY[ icon.value ] } />
				</span>
			);
		}
		if ( icon.type === 'custom' && icon.value ) {
			return (
				<span
					className="sfcore-features__card-icon sfcore-features__card-icon--custom"
					style={ {
						backgroundColor: inherit(
							iconBackgroundColor,
							'--features-icon-bg'
						),
					} }
				>
					<img src={ icon.value } alt="" />
				</span>
			);
		}
		return null;
	};

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
				/>

				{ activeTab === 'general' && (
					<>
						<PanelBody title={ __( 'Card', 'swishfolio-core' ) }>
							<SelectControl
								label={ __( 'Card Size', 'swishfolio-core' ) }
								value={ cardSize }
								options={ [
									{ label: __( 'Small (4 cols)', 'swishfolio-core' ), value: 'small' },
									{ label: __( 'Medium (6 cols)', 'swishfolio-core' ), value: 'medium' },
									{ label: __( 'Large (8 cols)', 'swishfolio-core' ), value: 'large' },
								] }
								onChange={ ( value ) => setAttributes( { cardSize: value } ) }
							/>

							<SelectControl
								label={ __( 'Card Style', 'swishfolio-core' ) }
								value={ cardStyle }
								options={ [
									{ label: __( 'Default', 'swishfolio-core' ), value: 'default' },
									{ label: __( 'Accent', 'swishfolio-core' ), value: 'accent' },
									{ label: __( 'Highlight', 'swishfolio-core' ), value: 'highlight' },
								] }
								onChange={ ( value ) => setAttributes( { cardStyle: value } ) }
							/>

							<SelectControl
								label={ __( 'Title Tag', 'swishfolio-core' ) }
								value={ titleTag }
								options={ [
									{ label: 'H2', value: 'h2' },
									{ label: 'H3', value: 'h3' },
									{ label: 'H4', value: 'h4' },
									{ label: 'H5', value: 'h5' },
								] }
								onChange={ ( value ) => setAttributes( { titleTag: value } ) }
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Icon', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<SelectControl
								label={ __( 'Icon Source', 'swishfolio-core' ) }
								value={ icon?.type || 'none' }
								options={ [
									{ label: __( 'Icon Library', 'swishfolio-core' ), value: 'library' },
									{ label: __( 'Custom Image', 'swishfolio-core' ), value: 'custom' },
									{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
								] }
								onChange={ ( type ) =>
									setAttributes( {
										icon: { type, value: type === 'library' ? 'star' : '' },
									} )
								}
							/>

							{ icon?.type === 'library' && (
								<>
									<p className="components-base-control__label">
										{ __( 'Select Icon', 'swishfolio-core' ) }
									</p>
									<div className="sfcore-icon-picker">
										{ Object.keys( ICON_LIBRARY ).map( ( iconKey ) => (
											<Button
												key={ iconKey }
												className={ `sfcore-icon-picker__item ${
													icon.value === iconKey ? 'is-selected' : ''
												}` }
												onClick={ () =>
													setAttributes( {
														icon: { ...icon, value: iconKey },
													} )
												}
												label={ iconKey }
											>
												<Icon icon={ ICON_LIBRARY[ iconKey ] } />
											</Button>
										) ) }
									</div>
								</>
							) }

							{ icon?.type === 'custom' && (
								<MediaUploadCheck>
									<MediaUpload
										onSelect={ ( media ) =>
											setAttributes( {
												icon: { ...icon, value: media.url },
											} )
										}
										allowedTypes={ [ 'image/svg+xml', 'image' ] }
										render={ ( { open } ) => (
											<>
												{ icon.value ? (
													<div className="sfcore-custom-icon-preview">
														<img src={ icon.value } alt="" />
														<ButtonGroup>
															<Button variant="secondary" onClick={ open } size="small">
																{ __( 'Replace', 'swishfolio-core' ) }
															</Button>
															<Button
																variant="secondary"
																isDestructive
																onClick={ () =>
																	setAttributes( {
																		icon: { ...icon, value: '' },
																	} )
																}
																size="small"
															>
																{ __( 'Remove', 'swishfolio-core' ) }
															</Button>
														</ButtonGroup>
													</div>
												) : (
													<Button variant="secondary" onClick={ open }>
														{ __( 'Upload Icon', 'swishfolio-core' ) }
													</Button>
												) }
											</>
										) }
									/>
								</MediaUploadCheck>
							) }
						</PanelBody>

						<PanelBody
							title={ __( 'Call to Action', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<ToggleGroupControl
								label={ __( 'CTA Type', 'swishfolio-core' ) }
								value={ ctaType }
								onChange={ ( value ) => setAttributes( { ctaType: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="link" label={ __( 'Link', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="button" label={ __( 'Button', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
							</ToggleGroupControl>

							{ ctaType !== 'none' && (
								<URLInput
									label={ __( 'CTA URL', 'swishfolio-core' ) }
									value={ ctaUrl }
									onChange={ ( url ) => setAttributes( { ctaUrl: url } ) }
								/>
							) }
						</PanelBody>

						<PanelBody
							title={ __( 'Background Image', 'swishfolio-core' ) }
							initialOpen={ false }
						>
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
									value={ backgroundImage?.id }
									render={ ( { open } ) => (
										<>
											{ backgroundImage?.url ? (
												<div style={ { marginBottom: '8px' } }>
													<img
														src={ backgroundImage.url }
														alt=""
														style={ {
															width: '100%',
															height: '120px',
															objectFit: 'cover',
															borderRadius: '4px',
															marginBottom: '8px',
														} }
													/>
													<ButtonGroup>
														<Button variant="secondary" onClick={ open } size="small">
															{ __( 'Replace', 'swishfolio-core' ) }
														</Button>
														<Button
															variant="secondary"
															isDestructive
															onClick={ () =>
																setAttributes( { backgroundImage: {} } )
															}
															size="small"
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
								<SelectControl
									label={ __( 'Image Position', 'swishfolio-core' ) }
									value={ backgroundImagePosition || 'center center' }
									options={ [
										{ label: __( 'Top Left', 'swishfolio-core' ), value: 'top left' },
										{ label: __( 'Top Center', 'swishfolio-core' ), value: 'top center' },
										{ label: __( 'Top Right', 'swishfolio-core' ), value: 'top right' },
										{ label: __( 'Center Left', 'swishfolio-core' ), value: 'center left' },
										{ label: __( 'Center', 'swishfolio-core' ), value: 'center center' },
										{ label: __( 'Center Right', 'swishfolio-core' ), value: 'center right' },
										{ label: __( 'Bottom Left', 'swishfolio-core' ), value: 'bottom left' },
										{ label: __( 'Bottom Center', 'swishfolio-core' ), value: 'bottom center' },
										{ label: __( 'Bottom Right', 'swishfolio-core' ), value: 'bottom right' },
									] }
									onChange={ ( value ) =>
										setAttributes( { backgroundImagePosition: value } )
									}
								/>
							) }
						</PanelBody>
					</>
				) }

				{ activeTab === 'style' && (
					<>
						<PanelBody
							title={ __( 'Style Overrides', 'swishfolio-core' ) }
						>
							<p className="components-base-control__help">
								{ __(
									'Leave a field empty to inherit the parent block’s global style.',
									'swishfolio-core'
								) }
							</p>

							<p className="components-base-control__label">
								{ __( 'Card Background', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardBackgroundColor }
								onChange={ ( color ) =>
									setAttributes( { cardBackgroundColor: color } )
								}
								clearable
							/>

							<p className="components-base-control__label">
								{ __( 'Card Accent Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ cardAccentColor }
								onChange={ ( color ) =>
									setAttributes( { cardAccentColor: color } )
								}
								clearable
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Typography', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<p className="components-base-control__label">
								{ __( 'Title Font Size', 'swishfolio-core' ) }
							</p>
							<FontSizePicker
								fontSizes={ themeFontSizes }
								value={ titleFontSize }
								onChange={ ( value ) =>
									setAttributes( { titleFontSize: value } )
								}
								withSlider
							/>

							<p className="components-base-control__label">
								{ __( 'Subheading Font Size', 'swishfolio-core' ) }
							</p>
							<FontSizePicker
								fontSizes={ themeFontSizes }
								value={ subheadingFontSize }
								onChange={ ( value ) =>
									setAttributes( { subheadingFontSize: value } )
								}
								withSlider
							/>

							<p className="components-base-control__label">
								{ __( 'Text Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ titleColor }
								onChange={ ( color ) =>
									setAttributes( { titleColor: color } )
								}
								clearable
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Icon Colors', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<p className="components-base-control__label">
								{ __( 'Icon Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ iconColor }
								onChange={ ( color ) =>
									setAttributes( { iconColor: color } )
								}
								clearable
							/>

							<p className="components-base-control__label">
								{ __( 'Icon Background', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ iconBackgroundColor }
								onChange={ ( color ) =>
									setAttributes( { iconBackgroundColor: color } )
								}
								clearable
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'CTA Colors', 'swishfolio-core' ) }
							initialOpen={ false }
						>
							<p className="components-base-control__label">
								{ __( 'CTA Text Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ ctaTextColor }
								onChange={ ( color ) =>
									setAttributes( { ctaTextColor: color } )
								}
								clearable
							/>

							<p className="components-base-control__label">
								{ __( 'CTA Background Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ ctaBackgroundColor }
								onChange={ ( color ) =>
									setAttributes( { ctaBackgroundColor: color } )
								}
								clearable
							/>
						</PanelBody>

						{ backgroundImage?.url && (
							<PanelBody
								title={ __( 'Overlay', 'swishfolio-core' ) }
								initialOpen={ false }
							>
								<p className="components-base-control__label">
									{ __( 'Overlay Color', 'swishfolio-core' ) }
								</p>
								<ColorPalette
									colors={ themeColors }
									value={ overlayColor }
									onChange={ ( color ) =>
										setAttributes( { overlayColor: color } )
									}
									clearable
								/>

								<RangeControl
									label={ __( 'Overlay Opacity', 'swishfolio-core' ) }
									value={
										overlayOpacity !== undefined ? overlayOpacity : 50
									}
									onChange={ ( value ) =>
										setAttributes( { overlayOpacity: value } )
									}
									min={ 0 }
									max={ 100 }
								/>
							</PanelBody>
						) }
					</>
				) }

				{ activeTab === 'advanced' && (
					<PanelBody title={ __( 'Advanced', 'swishfolio-core' ) }>
						<p className="components-base-control__help">
							{ __(
								'Spacing, border, and typography are available in the editor’s native Styles tab for this block.',
								'swishfolio-core'
							) }
						</p>
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ backgroundImage?.url && (
					<img
						src={ backgroundImage.url }
						alt={ backgroundImage.alt || '' }
						className="sfcore-features__card-bg-image"
						style={ {
							objectPosition: backgroundImagePosition || 'center center',
						} }
					/>
				) }
				{ backgroundImage?.url && overlayColor && (
					<div
						className="sfcore-features__card-overlay"
						style={ {
							backgroundColor: overlayColor,
							opacity: ( overlayOpacity || 50 ) / 100,
						} }
					/>
				) }
				<div className="sfcore-features__card-inner">
					<div className="sfcore-features__card-header">
						<RichText
							tagName="span"
							className="sfcore-features__card-subheading"
							value={ subheading }
							onChange={ ( value ) => setAttributes( { subheading: value } ) }
							placeholder={ __( 'MODULE 01', 'swishfolio-core' ) }
							style={ {
								fontSize: inherit(
									subheadingFontSize,
									'--features-subheading-font-size'
								),
							} }
						/>
					</div>

					<RichText
						tagName={ titleTag || 'h3' }
						className="sfcore-features__card-title"
						value={ title }
						onChange={ ( value ) => setAttributes( { title: value } ) }
						placeholder={ __( 'Feature Title', 'swishfolio-core' ) }
						style={ {
							fontSize: inherit(
								titleFontSize,
								'--features-title-font-size'
							),
							color: inherit( titleColor, '--features-title-color' ),
						} }
					/>

					<RichText
						tagName="p"
						className="sfcore-features__card-description"
						value={ description }
						onChange={ ( value ) =>
							setAttributes( { description: value } )
						}
						placeholder={ __( 'Feature description...', 'swishfolio-core' ) }
						style={ {
							color: inherit( titleColor, '--features-title-color' ),
						} }
					/>

					{ renderIcon() }

					{ ctaType && ctaType !== 'none' && (
						<div className="sfcore-features__card-cta-wrapper">
							{ ctaType === 'link' ? (
								<div
									className="sfcore-features__card-link"
									style={ {
										color: inherit(
											ctaTextColor,
											'--features-cta-text-color'
										),
										backgroundColor: inherit(
											ctaBackgroundColor,
											'--features-cta-bg'
										),
									} }
								>
									<RichText
										tagName="span"
										value={ ctaText }
										onChange={ ( value ) =>
											setAttributes( { ctaText: value } )
										}
										placeholder={ __( 'Learn More', 'swishfolio-core' ) }
									/>
									<Icon icon={ arrowRight } size={ 16 } />
								</div>
							) : (
								<RichText
									tagName="span"
									className="sfcore-features__card-button"
									value={ ctaText }
									onChange={ ( value ) =>
										setAttributes( { ctaText: value } )
									}
									placeholder={ __( 'Get Started', 'swishfolio-core' ) }
									style={ {
										color: inherit(
											ctaTextColor,
											'--features-cta-text-color'
										),
										backgroundColor: inherit(
											ctaBackgroundColor,
											'--features-cta-bg'
										),
									} }
								/>
							) }
						</div>
					) }
				</div>
			</div>
		</>
	);
}
