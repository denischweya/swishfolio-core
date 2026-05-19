/**
 * Features Block - Edit Component
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

// Icon library mapping
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

// Generate unique ID
const generateId = () => `feature-${ Date.now() }-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

export default function Edit( { attributes, setAttributes } ) {
	// Inspector tab state (General / Style / Advanced)
	const [ activeTab, setActiveTab ] = useInspectorTabs();
	const themeColors = useSetting( 'color.palette' ) || [];
	const themeFontSizes = useSetting( 'typography.fontSizes' ) || [];

	const {
		featureItems,
		gridGap,
		cardBorderStyle,
		cardBorderColor,
		cardShadowColor,
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

	// Update a specific feature item
	const updateFeatureItem = ( index, field, value ) => {
		const newItems = [ ...featureItems ];
		newItems[ index ] = { ...newItems[ index ], [ field ]: value };
		setAttributes( { featureItems: newItems } );
	};

	// Add new feature item
	const addFeatureItem = () => {
		const newItem = {
			id: generateId(),
			subheading: `MODULE ${ String( featureItems.length + 1 ).padStart( 2, '0' ) }`,
			title: 'New Feature',
			titleTag: 'h3',
			description: 'Feature description goes here...',
			icon: { type: 'library', value: 'star' },
			ctaType: 'link',
			ctaText: 'Learn More',
			ctaUrl: '',
			cardSize: 'medium',
			cardStyle: 'default',
			backgroundImage: {},
			backgroundImagePosition: 'center center',
			overlayColor: '',
			overlayOpacity: 50,
		};
		setAttributes( { featureItems: [ ...featureItems, newItem ] } );
	};

	// Remove feature item
	const removeFeatureItem = ( index ) => {
		const newItems = featureItems.filter( ( _, i ) => i !== index );
		setAttributes( { featureItems: newItems } );
	};

	// Move feature item
	const moveFeatureItem = ( index, direction ) => {
		const newItems = [ ...featureItems ];
		const newIndex = index + direction;
		if ( newIndex < 0 || newIndex >= newItems.length ) {
			return;
		}
		[ newItems[ index ], newItems[ newIndex ] ] = [ newItems[ newIndex ], newItems[ index ] ];
		setAttributes( { featureItems: newItems } );
	};

	// Render icon
	const renderIcon = ( item ) => {
		if ( ! item.icon ) {
			return null;
		}
		if ( item.icon.type === 'library' && ICON_LIBRARY[ item.icon.value ] ) {
			return (
				<span
					className="sfcore-features__card-icon"
					style={ {
						color: iconColor || undefined,
						backgroundColor: iconBackgroundColor || undefined,
					} }
				>
					<Icon icon={ ICON_LIBRARY[ item.icon.value ] } />
				</span>
			);
		}
		if ( item.icon.type === 'custom' && item.icon.value ) {
			return (
				<span
					className="sfcore-features__card-icon sfcore-features__card-icon--custom"
					style={ {
						backgroundColor: iconBackgroundColor || undefined,
					} }
				>
					<img src={ item.icon.value } alt="" />
				</span>
			);
		}
		return null;
	};

	// Get card classes
	const getCardClasses = ( item ) => {
		const classes = [
			'sfcore-features__card',
			`sfcore-features__card--${ item.cardSize || 'medium' }`,
			`sfcore-features__card--${ item.cardStyle || 'default' }`,
		];
		if ( item.backgroundImage?.url ) {
			classes.push( 'sfcore-features__card--has-bg-image' );
		}
		if ( item.backgroundImage?.url && item.overlayColor ) {
			classes.push( 'sfcore-features__card--has-overlay' );
		}
		return classes.join( ' ' );
	};

	// Get card styles (shared across all cards)
	const getCardStyles = () => {
		const styles = {};
		if ( cardBackgroundColor ) {
			styles[ '--card-bg' ] = cardBackgroundColor;
		}
		if ( cardAccentColor ) {
			styles[ '--card-accent' ] = cardAccentColor;
		}
		return styles;
	};

	const blockProps = useBlockProps( {
		className: `sfcore-features sfcore-features--border-${ cardBorderStyle }`,
		style: {
			'--grid-gap': gridGap,
			'--card-border-color': cardBorderColor || undefined,
			'--card-shadow-color': cardShadowColor || undefined,
		},
	} );

	return (
		<>
			<InspectorControls>
				<InspectorTabs
					activeTab={ activeTab }
					setActiveTab={ setActiveTab }
				/>

				{ activeTab === 'general' && (
				<>
				<PanelBody title={ __( 'Layout', 'swishfolio-core' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Grid Gap', 'swishfolio-core' ) }
						value={ gridGap }
						options={ [
							{ label: __( 'Small (1rem)', 'swishfolio-core' ), value: '1rem' },
							{ label: __( 'Medium (2rem)', 'swishfolio-core' ), value: '2rem' },
							{ label: __( 'Large (3rem)', 'swishfolio-core' ), value: '3rem' },
						] }
						onChange={ ( value ) => setAttributes( { gridGap: value } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Feature Cards', 'swishfolio-core' ) } initialOpen={ true }>
					{ featureItems.map( ( item, index ) => (
						<div
							key={ item.id }
							style={ {
								padding: '12px',
								marginBottom: '12px',
								border: '1px solid #ddd',
								borderRadius: '4px',
								background: '#f9f9f9',
							} }
						>
							<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } }>
								<span style={ { fontWeight: 600, fontSize: '13px' } }>
									{ __( 'Card', 'swishfolio-core' ) } { index + 1 }
								</span>
								<div>
									<Button
										size="small"
										icon="arrow-up-alt"
										disabled={ index === 0 }
										onClick={ () => moveFeatureItem( index, -1 ) }
										label={ __( 'Move up', 'swishfolio-core' ) }
									/>
									<Button
										size="small"
										icon="arrow-down-alt"
										disabled={ index === featureItems.length - 1 }
										onClick={ () => moveFeatureItem( index, 1 ) }
										label={ __( 'Move down', 'swishfolio-core' ) }
									/>
									<Button
										size="small"
										icon="no-alt"
										isDestructive
										onClick={ () => removeFeatureItem( index ) }
										label={ __( 'Remove', 'swishfolio-core' ) }
									/>
								</div>
							</div>

							<SelectControl
								label={ __( 'Card Size', 'swishfolio-core' ) }
								value={ item.cardSize }
								options={ [
									{ label: __( 'Small (4 cols)', 'swishfolio-core' ), value: 'small' },
									{ label: __( 'Medium (6 cols)', 'swishfolio-core' ), value: 'medium' },
									{ label: __( 'Large (8 cols)', 'swishfolio-core' ), value: 'large' },
								] }
								onChange={ ( value ) => updateFeatureItem( index, 'cardSize', value ) }
							/>

							<SelectControl
								label={ __( 'Card Style', 'swishfolio-core' ) }
								value={ item.cardStyle }
								options={ [
									{ label: __( 'Default', 'swishfolio-core' ), value: 'default' },
									{ label: __( 'Accent', 'swishfolio-core' ), value: 'accent' },
									{ label: __( 'Highlight', 'swishfolio-core' ), value: 'highlight' },
								] }
								onChange={ ( value ) => updateFeatureItem( index, 'cardStyle', value ) }
							/>

							<SelectControl
								label={ __( 'Title Tag', 'swishfolio-core' ) }
								value={ item.titleTag }
								options={ [
									{ label: 'H2', value: 'h2' },
									{ label: 'H3', value: 'h3' },
									{ label: 'H4', value: 'h4' },
									{ label: 'H5', value: 'h5' },
								] }
								onChange={ ( value ) => updateFeatureItem( index, 'titleTag', value ) }
							/>

							<SelectControl
								label={ __( 'Icon Source', 'swishfolio-core' ) }
								value={ item.icon?.type || 'none' }
								options={ [
									{ label: __( 'Icon Library', 'swishfolio-core' ), value: 'library' },
									{ label: __( 'Custom Image', 'swishfolio-core' ), value: 'custom' },
									{ label: __( 'None', 'swishfolio-core' ), value: 'none' },
								] }
								onChange={ ( type ) =>
									updateFeatureItem( index, 'icon', {
										type,
										value: type === 'library' ? 'star' : '',
									} )
								}
							/>

							{ item.icon?.type === 'library' && (
								<>
									<p className="components-base-control__label">
										{ __( 'Select Icon', 'swishfolio-core' ) }
									</p>
									<div className="sfcore-icon-picker">
										{ Object.keys( ICON_LIBRARY ).map( ( iconKey ) => (
											<Button
												key={ iconKey }
												className={ `sfcore-icon-picker__item ${ item.icon.value === iconKey ? 'is-selected' : '' }` }
												onClick={ () =>
													updateFeatureItem( index, 'icon', { ...item.icon, value: iconKey } )
												}
												label={ iconKey }
											>
												<Icon icon={ ICON_LIBRARY[ iconKey ] } />
											</Button>
										) ) }
									</div>
								</>
							) }

							{ item.icon?.type === 'custom' && (
								<MediaUploadCheck>
									<MediaUpload
										onSelect={ ( media ) =>
											updateFeatureItem( index, 'icon', { ...item.icon, value: media.url } )
										}
										allowedTypes={ [ 'image/svg+xml', 'image' ] }
										render={ ( { open } ) => (
											<>
												{ item.icon.value ? (
													<div className="sfcore-custom-icon-preview" style={ { marginBottom: '8px' } }>
														<img src={ item.icon.value } alt="" style={ { maxWidth: '40px', maxHeight: '40px' } } />
														<ButtonGroup>
															<Button variant="secondary" onClick={ open } size="small">
																{ __( 'Replace', 'swishfolio-core' ) }
															</Button>
															<Button
																variant="secondary"
																isDestructive
																onClick={ () =>
																	updateFeatureItem( index, 'icon', { ...item.icon, value: '' } )
																}
																size="small"
															>
																{ __( 'Remove', 'swishfolio-core' ) }
															</Button>
														</ButtonGroup>
													</div>
												) : (
													<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
														{ __( 'Upload Icon', 'swishfolio-core' ) }
													</Button>
												) }
											</>
										) }
									/>
								</MediaUploadCheck>
							) }

							<ToggleGroupControl
								label={ __( 'CTA Type', 'swishfolio-core' ) }
								value={ item.ctaType }
								onChange={ ( value ) => updateFeatureItem( index, 'ctaType', value ) }
								isBlock
							>
								<ToggleGroupControlOption value="link" label={ __( 'Link', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="button" label={ __( 'Button', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
							</ToggleGroupControl>

							{ item.ctaType !== 'none' && (
								<URLInput
									label={ __( 'CTA URL', 'swishfolio-core' ) }
									value={ item.ctaUrl }
									onChange={ ( url ) => updateFeatureItem( index, 'ctaUrl', url ) }
								/>
							) }

							<p className="components-base-control__label" style={ { marginTop: '16px', fontWeight: 600 } }>
								{ __( 'Background Image', 'swishfolio-core' ) }
							</p>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ ( media ) =>
										updateFeatureItem( index, 'backgroundImage', {
											url: media.url,
											alt: media.alt,
											id: media.id,
										} )
									}
									allowedTypes={ [ 'image' ] }
									value={ item.backgroundImage?.id }
									render={ ( { open } ) => (
										<>
											{ item.backgroundImage?.url ? (
												<div style={ { marginBottom: '8px' } }>
													<img
														src={ item.backgroundImage.url }
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
															onClick={ () => updateFeatureItem( index, 'backgroundImage', {} ) }
															size="small"
														>
															{ __( 'Remove', 'swishfolio-core' ) }
														</Button>
													</ButtonGroup>
												</div>
											) : (
												<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
													{ __( 'Select Background Image', 'swishfolio-core' ) }
												</Button>
											) }
										</>
									) }
								/>
							</MediaUploadCheck>

							{ item.backgroundImage?.url && (
								<>
									<p className="components-base-control__label" style={ { marginTop: '16px' } }>
										{ __( 'Overlay Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ themeColors }
										value={ item.overlayColor }
										onChange={ ( color ) => updateFeatureItem( index, 'overlayColor', color ) }
										clearable
									/>

									<RangeControl
										label={ __( 'Overlay Opacity', 'swishfolio-core' ) }
										value={ item.overlayOpacity !== undefined ? item.overlayOpacity : 50 }
										onChange={ ( value ) => updateFeatureItem( index, 'overlayOpacity', value ) }
										min={ 0 }
										max={ 100 }
									/>

									<SelectControl
										label={ __( 'Image Position', 'swishfolio-core' ) }
										value={ item.backgroundImagePosition || 'center center' }
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
										onChange={ ( value ) => updateFeatureItem( index, 'backgroundImagePosition', value ) }
									/>
								</>
							) }
						</div>
					) ) }

					<Button
						variant="secondary"
						onClick={ addFeatureItem }
						icon="plus"
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ __( 'Add Feature Card', 'swishfolio-core' ) }
					</Button>
				</PanelBody>
				</>
				) }

				{ activeTab === 'style' && (
				<>
					<PanelBody title={ __( 'Card Appearance', 'swishfolio-core' ) }>
						<ToggleGroupControl
							label={ __( 'Card Border Style', 'swishfolio-core' ) }
							value={ cardBorderStyle }
							onChange={ ( value ) => setAttributes( { cardBorderStyle: value } ) }
							isBlock
						>
							<ToggleGroupControlOption value="solid" label={ __( 'Solid', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="dashed" label={ __( 'Dashed', 'swishfolio-core' ) } />
							<ToggleGroupControlOption value="none" label={ __( 'None', 'swishfolio-core' ) } />
						</ToggleGroupControl>

						<p className="components-base-control__label">
							{ __( 'Card Border Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ cardBorderColor }
							onChange={ ( color ) => setAttributes( { cardBorderColor: color } ) }
							clearable
						/>

						<p className="components-base-control__label">
							{ __( 'Card Shadow Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ cardShadowColor }
							onChange={ ( color ) => setAttributes( { cardShadowColor: color } ) }
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
							onChange={ ( value ) => setAttributes( { titleFontSize: value } ) }
							withSlider
						/>

						<p className="components-base-control__label">
							{ __( 'Subheading Font Size', 'swishfolio-core' ) }
						</p>
						<FontSizePicker
							fontSizes={ themeFontSizes }
							value={ subheadingFontSize }
							onChange={ ( value ) => setAttributes( { subheadingFontSize: value } ) }
							withSlider
						/>

						<p className="components-base-control__label">
							{ __( 'Text Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ titleColor }
							onChange={ ( color ) => setAttributes( { titleColor: color } ) }
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
							onChange={ ( color ) => setAttributes( { iconColor: color } ) }
							clearable
						/>

						<p className="components-base-control__label">
							{ __( 'Icon Background', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ iconBackgroundColor }
							onChange={ ( color ) => setAttributes( { iconBackgroundColor: color } ) }
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
							onChange={ ( color ) => setAttributes( { ctaTextColor: color } ) }
							clearable
						/>

						<p className="components-base-control__label">
							{ __( 'CTA Background Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ ctaBackgroundColor }
							onChange={ ( color ) => setAttributes( { ctaBackgroundColor: color } ) }
							clearable
						/>
					</PanelBody>

					<PanelBody
						title={ __( 'Card Colors', 'swishfolio-core' ) }
						initialOpen={ false }
					>
						<p className="components-base-control__label">
							{ __( 'Card Background', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ cardBackgroundColor }
							onChange={ ( color ) => setAttributes( { cardBackgroundColor: color } ) }
							clearable
						/>

						<p className="components-base-control__label">
							{ __( 'Card Accent Color', 'swishfolio-core' ) }
						</p>
						<ColorPalette
							colors={ themeColors }
							value={ cardAccentColor }
							onChange={ ( color ) => setAttributes( { cardAccentColor: color } ) }
							clearable
						/>
					</PanelBody>
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
				<div className="sfcore-features__grid">
					{ featureItems.map( ( item, index ) => (
						<div
							key={ item.id }
							className={ getCardClasses( item ) }
							style={ getCardStyles() }
						>
							{ item.backgroundImage?.url && (
								<img
									src={ item.backgroundImage.url }
									alt={ item.backgroundImage.alt || '' }
									className="sfcore-features__card-bg-image"
									style={ {
										objectPosition: item.backgroundImagePosition || 'center center',
									} }
								/>
							) }
							{ item.backgroundImage?.url && item.overlayColor && (
								<div
									className="sfcore-features__card-overlay"
									style={ {
										backgroundColor: item.overlayColor,
										opacity: ( item.overlayOpacity || 50 ) / 100,
									} }
								/>
							) }
							<div className="sfcore-features__card-inner">
								<div className="sfcore-features__card-header">
									<RichText
										tagName="span"
										className="sfcore-features__card-subheading"
										value={ item.subheading }
										onChange={ ( value ) => updateFeatureItem( index, 'subheading', value ) }
										placeholder={ __( 'MODULE 01', 'swishfolio-core' ) }
										style={ {
											fontSize: subheadingFontSize || undefined,
										} }
									/>
								</div>

								<RichText
									tagName={ item.titleTag || 'h3' }
									className="sfcore-features__card-title"
									value={ item.title }
									onChange={ ( value ) => updateFeatureItem( index, 'title', value ) }
									placeholder={ __( 'Feature Title', 'swishfolio-core' ) }
									style={ {
										fontSize: titleFontSize || undefined,
										color: titleColor || undefined,
									} }
								/>

								<RichText
									tagName="p"
									className="sfcore-features__card-description"
									value={ item.description }
									onChange={ ( value ) => updateFeatureItem( index, 'description', value ) }
									placeholder={ __( 'Feature description...', 'swishfolio-core' ) }
									style={ {
										color: titleColor || undefined,
									} }
								/>

								{ item.icon && item.icon.type !== 'none' && renderIcon( item ) }

								{ item.ctaType && item.ctaType !== 'none' && (
									<div className="sfcore-features__card-cta-wrapper">
										{ item.ctaType === 'link' ? (
											<div
												className="sfcore-features__card-link"
												style={ {
													color: ctaTextColor || undefined,
													backgroundColor: ctaBackgroundColor || undefined,
												} }
											>
												<RichText
													tagName="span"
													value={ item.ctaText }
													onChange={ ( value ) => updateFeatureItem( index, 'ctaText', value ) }
													placeholder={ __( 'Learn More', 'swishfolio-core' ) }
												/>
												<Icon icon={ arrowRight } size={ 16 } />
											</div>
										) : (
											<RichText
												tagName="span"
												className="sfcore-features__card-button"
												value={ item.ctaText }
												onChange={ ( value ) => updateFeatureItem( index, 'ctaText', value ) }
												placeholder={ __( 'Get Started', 'swishfolio-core' ) }
												style={ {
													color: ctaTextColor || undefined,
													backgroundColor: ctaBackgroundColor || undefined,
												} }
											/>
										) }
									</div>
								) }
							</div>
						</div>
					) ) }
				</div>
			</div>
		</>
	);
}
