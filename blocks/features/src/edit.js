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
	code,
	desktop,
	mobile,
	tablet,
	globe,
	lock,
	key,
	people,
	postComments,
	payment,
	shipping,
	store,
	tag,
	category,
	grid,
	list,
	settings,
	tool,
	brush,
	color as colorIcon,
	image,
	video,
	audio,
	file,
	download,
	upload,
	external,
	link,
	info,
	warning,
	help,
	plus,
	chevronRight,
	arrowRight,
	inbox,
	envelope,
	shield,
	gift,
	bell,
	calendar,
	cog,
	pencil,
	trash,
	plugins,
	search,
	cloud,
	chartBar,
} from '@wordpress/icons';

// Icon library mapping
const ICON_LIBRARY = {
	star: starFilled,
	'star-empty': starEmpty,
	check: check,
	code: code,
	desktop: desktop,
	mobile: mobile,
	tablet: tablet,
	globe: globe,
	lock: lock,
	key: key,
	people: people,
	comments: postComments,
	payment: payment,
	shipping: shipping,
	store: store,
	tag: tag,
	category: category,
	grid: grid,
	list: list,
	settings: settings,
	tool: tool,
	brush: brush,
	color: colorIcon,
	image: image,
	video: video,
	audio: audio,
	file: file,
	download: download,
	upload: upload,
	external: external,
	link: link,
	info: info,
	warning: warning,
	help: help,
	inbox: inbox,
	envelope: envelope,
	shield: shield,
	gift: gift,
	bell: bell,
	calendar: calendar,
	cog: cog,
	pencil: pencil,
	plugins: plugins,
	search: search,
	cloud: cloud,
	chart: chartBar,
};

// Generate unique ID
const generateId = () => `feature-${ Date.now() }-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

export default function Edit( { attributes, setAttributes } ) {
	const themeColors = useSetting( 'color.palette' ) || [];

	const {
		sectionTitle,
		sectionTitleTag,
		sectionTitleColor,
		sectionDescription,
		showSectionCta,
		primaryCtaText,
		primaryCtaUrl,
		primaryCtaStyle,
		secondaryCtaText,
		secondaryCtaUrl,
		secondaryCtaStyle,
		featureItems,
		gridGap,
		cardBorderStyle,
		cardBorderColor,
		cardShadowColor,
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
			iconColor: '',
			iconBackgroundColor: '',
			ctaType: 'link',
			ctaText: 'Learn More',
			ctaUrl: '',
			cardSize: 'medium',
			cardStyle: 'default',
			cardBackgroundColor: '',
			cardAccentColor: '',
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
		if ( item.icon.type === 'library' && ICON_LIBRARY[ item.icon.value ] ) {
			return (
				<span
					className="sfcore-features__card-icon"
					style={ {
						color: item.iconColor || undefined,
						backgroundColor: item.iconBackgroundColor || undefined,
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
						backgroundColor: item.iconBackgroundColor || undefined,
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
			`sfcore-features__card--${ item.cardSize }`,
			`sfcore-features__card--${ item.cardStyle }`,
		];
		return classes.join( ' ' );
	};

	// Get card styles
	const getCardStyles = ( item ) => {
		const styles = {};
		if ( item.cardBackgroundColor ) {
			styles[ '--card-bg' ] = item.cardBackgroundColor;
		}
		if ( item.cardAccentColor ) {
			styles[ '--card-accent' ] = item.cardAccentColor;
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

	const TitleTag = sectionTitleTag;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Section Header', 'swishfolio-core' ) }>
					<SelectControl
						label={ __( 'Title Tag', 'swishfolio-core' ) }
						value={ sectionTitleTag }
						options={ [
							{ label: 'H1', value: 'h1' },
							{ label: 'H2', value: 'h2' },
							{ label: 'H3', value: 'h3' },
							{ label: 'H4', value: 'h4' },
						] }
						onChange={ ( value ) => setAttributes( { sectionTitleTag: value } ) }
					/>

					<p className="components-base-control__label">
						{ __( 'Title Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ sectionTitleColor }
						onChange={ ( color ) => setAttributes( { sectionTitleColor: color } ) }
						clearable
					/>

					<ToggleControl
						label={ __( 'Show Section CTA Buttons', 'swishfolio-core' ) }
						checked={ showSectionCta }
						onChange={ ( value ) => setAttributes( { showSectionCta: value } ) }
					/>

					{ showSectionCta && (
						<>
							<TextControl
								label={ __( 'Primary Button Text', 'swishfolio-core' ) }
								value={ primaryCtaText }
								onChange={ ( value ) => setAttributes( { primaryCtaText: value } ) }
							/>
							<URLInput
								label={ __( 'Primary Button URL', 'swishfolio-core' ) }
								value={ primaryCtaUrl }
								onChange={ ( url ) => setAttributes( { primaryCtaUrl: url } ) }
							/>
							<ToggleGroupControl
								label={ __( 'Primary Button Style', 'swishfolio-core' ) }
								value={ primaryCtaStyle }
								onChange={ ( value ) => setAttributes( { primaryCtaStyle: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="primary" label={ __( 'Primary', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="secondary" label={ __( 'Secondary', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'swishfolio-core' ) } />
							</ToggleGroupControl>

							<TextControl
								label={ __( 'Secondary Button Text', 'swishfolio-core' ) }
								value={ secondaryCtaText }
								onChange={ ( value ) => setAttributes( { secondaryCtaText: value } ) }
							/>
							<URLInput
								label={ __( 'Secondary Button URL', 'swishfolio-core' ) }
								value={ secondaryCtaUrl }
								onChange={ ( url ) => setAttributes( { secondaryCtaUrl: url } ) }
							/>
							<ToggleGroupControl
								label={ __( 'Secondary Button Style', 'swishfolio-core' ) }
								value={ secondaryCtaStyle }
								onChange={ ( value ) => setAttributes( { secondaryCtaStyle: value } ) }
								isBlock
							>
								<ToggleGroupControlOption value="primary" label={ __( 'Primary', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="secondary" label={ __( 'Secondary', 'swishfolio-core' ) } />
								<ToggleGroupControlOption value="outline" label={ __( 'Outline', 'swishfolio-core' ) } />
							</ToggleGroupControl>
						</>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Grid Settings', 'swishfolio-core' ) } initialOpen={ false }>
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
								value={ item.icon.type }
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

							{ item.icon.type === 'library' && (
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

							{ item.icon.type === 'custom' && (
								<MediaUploadCheck>
									<MediaUpload
										onSelect={ ( media ) =>
											updateFeatureItem( index, 'icon', { ...item.icon, value: media.url } )
										}
										allowedTypes={ [ 'image/svg+xml', 'image' ] }
										render={ ( { open } ) => (
											<>
												{ item.icon.value ? (
													<div className="sfcore-custom-icon-preview">
														<img src={ item.icon.value } alt="" />
														<ButtonGroup>
															<Button variant="secondary" onClick={ open }>
																{ __( 'Replace', 'swishfolio-core' ) }
															</Button>
															<Button
																variant="tertiary"
																isDestructive
																onClick={ () =>
																	updateFeatureItem( index, 'icon', { ...item.icon, value: '' } )
																}
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

							{ item.icon.type !== 'none' && (
								<>
									<p className="components-base-control__label" style={ { marginTop: '12px' } }>
										{ __( 'Icon Color', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ themeColors }
										value={ item.iconColor }
										onChange={ ( color ) => updateFeatureItem( index, 'iconColor', color ) }
										clearable
									/>

									<p className="components-base-control__label">
										{ __( 'Icon Background', 'swishfolio-core' ) }
									</p>
									<ColorPalette
										colors={ themeColors }
										value={ item.iconBackgroundColor }
										onChange={ ( color ) => updateFeatureItem( index, 'iconBackgroundColor', color ) }
										clearable
									/>
								</>
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

							<p className="components-base-control__label" style={ { marginTop: '12px' } }>
								{ __( 'Card Background', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ item.cardBackgroundColor }
								onChange={ ( color ) => updateFeatureItem( index, 'cardBackgroundColor', color ) }
								clearable
							/>

							<p className="components-base-control__label">
								{ __( 'Card Accent Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ item.cardAccentColor }
								onChange={ ( color ) => updateFeatureItem( index, 'cardAccentColor', color ) }
								clearable
							/>
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
			</InspectorControls>

			<div { ...blockProps }>
				{ /* Section Header */ }
				<div className="sfcore-features__header">
					<RichText
						tagName={ sectionTitleTag }
						className="sfcore-features__title"
						value={ sectionTitle }
						onChange={ ( value ) => setAttributes( { sectionTitle: value } ) }
						placeholder={ __( 'Features Section Title', 'swishfolio-core' ) }
						style={ { color: sectionTitleColor || undefined } }
					/>
					<RichText
						tagName="p"
						className="sfcore-features__description"
						value={ sectionDescription }
						onChange={ ( value ) => setAttributes( { sectionDescription: value } ) }
						placeholder={ __( 'Add a description for this section...', 'swishfolio-core' ) }
					/>

					{ showSectionCta && (
						<div className="sfcore-features__header-cta">
							{ primaryCtaText && (
								<span className={ `sfcore-features__cta sfcore-features__cta--${ primaryCtaStyle }` }>
									{ primaryCtaText }
								</span>
							) }
							{ secondaryCtaText && (
								<span className={ `sfcore-features__cta sfcore-features__cta--${ secondaryCtaStyle }` }>
									{ secondaryCtaText }
								</span>
							) }
						</div>
					) }
				</div>

				{ /* Feature Cards Grid */ }
				<div className="sfcore-features__grid">
					{ featureItems.map( ( item, index ) => {
						const CardTitleTag = item.titleTag;
						return (
							<div
								key={ item.id }
								className={ getCardClasses( item ) }
								style={ getCardStyles( item ) }
							>
								<div className="sfcore-features__card-inner">
									<div className="sfcore-features__card-header">
										<RichText
											tagName="span"
											className="sfcore-features__card-subheading"
											value={ item.subheading }
											onChange={ ( value ) => updateFeatureItem( index, 'subheading', value ) }
											placeholder={ __( 'MODULE 01', 'swishfolio-core' ) }
										/>
									</div>

									<RichText
										tagName={ item.titleTag }
										className="sfcore-features__card-title"
										value={ item.title }
										onChange={ ( value ) => updateFeatureItem( index, 'title', value ) }
										placeholder={ __( 'Feature Title', 'swishfolio-core' ) }
									/>

									<RichText
										tagName="p"
										className="sfcore-features__card-description"
										value={ item.description }
										onChange={ ( value ) => updateFeatureItem( index, 'description', value ) }
										placeholder={ __( 'Feature description...', 'swishfolio-core' ) }
									/>

									{ item.icon.type !== 'none' && renderIcon( item ) }

									{ item.ctaType !== 'none' && (
										<div className="sfcore-features__card-cta-wrapper">
											{ item.ctaType === 'link' ? (
												<span className="sfcore-features__card-link">
													<RichText
														tagName="span"
														value={ item.ctaText }
														onChange={ ( value ) => updateFeatureItem( index, 'ctaText', value ) }
														placeholder={ __( 'Learn More', 'swishfolio-core' ) }
													/>
													<Icon icon={ arrowRight } size={ 16 } />
												</span>
											) : (
												<span className="sfcore-features__card-button">
													<RichText
														tagName="span"
														value={ item.ctaText }
														onChange={ ( value ) => updateFeatureItem( index, 'ctaText', value ) }
														placeholder={ __( 'Get Started', 'swishfolio-core' ) }
													/>
												</span>
											) }
										</div>
									) }
								</div>
							</div>
						);
					} ) }
				</div>
			</div>
		</>
	);
}
