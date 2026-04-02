/**
 * Bento Cards Block - Edit Component
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
	TextControl,
} from '@wordpress/components';

// Generate unique ID
const generateId = () => `card-${ Date.now() }-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;

export default function Edit( { attributes, setAttributes } ) {
	const themeColors = useSetting( 'color.palette' ) || [];

	const {
		cards,
		gridGap,
		cardOverlayColor,
		cardOverlayOpacity,
		accentColor,
		ctaType,
		ctaText,
		ctaUrl,
		ctaTextColor,
		ctaBgColor,
	} = attributes;

	// Update a specific card
	const updateCard = ( index, field, value ) => {
		const newCards = [ ...cards ];
		newCards[ index ] = { ...newCards[ index ], [ field ]: value };
		setAttributes( { cards: newCards } );
	};

	// Add new card
	const addCard = () => {
		const newCard = {
			id: generateId(),
			image: {},
			subtitle: 'sub title',
			title: 'title',
			titleColor: '',
			description: 'description',
			cardSize: 'one-third',
		};
		setAttributes( { cards: [ ...cards, newCard ] } );
	};

	// Remove card
	const removeCard = ( index ) => {
		const newCards = cards.filter( ( _, i ) => i !== index );
		setAttributes( { cards: newCards } );
	};

	// Move card
	const moveCard = ( index, direction ) => {
		const newCards = [ ...cards ];
		const newIndex = index + direction;
		if ( newIndex < 0 || newIndex >= newCards.length ) {
			return;
		}
		[ newCards[ index ], newCards[ newIndex ] ] = [ newCards[ newIndex ], newCards[ index ] ];
		setAttributes( { cards: newCards } );
	};

	// Get card classes
	const getCardClasses = ( card ) => {
		const classes = [
			'sfcore-bento__card',
			`sfcore-bento__card--${ card.cardSize || 'one-third' }`,
		];
		if ( card.image?.url ) {
			classes.push( 'sfcore-bento__card--has-image' );
		}
		return classes.join( ' ' );
	};

	const blockProps = useBlockProps( {
		className: 'sfcore-bento',
		style: {
			'--grid-gap': gridGap,
			'--overlay-color': cardOverlayColor || 'rgba(6, 26, 20, 0.85)',
			'--overlay-opacity': cardOverlayOpacity / 100,
			'--accent-color': accentColor || '#FFE500',
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Section Settings', 'swishfolio-core' ) } initialOpen={ true }>
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

					<p className="components-base-control__label">
						{ __( 'Accent Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ accentColor }
						onChange={ ( color ) => setAttributes( { accentColor: color } ) }
						clearable
					/>

					<p className="components-base-control__label" style={ { marginTop: '16px' } }>
						{ __( 'Card Overlay Color', 'swishfolio-core' ) }
					</p>
					<ColorPalette
						colors={ themeColors }
						value={ cardOverlayColor }
						onChange={ ( color ) => setAttributes( { cardOverlayColor: color } ) }
						clearable
					/>

					<RangeControl
						label={ __( 'Overlay Opacity', 'swishfolio-core' ) }
						value={ cardOverlayOpacity }
						onChange={ ( value ) => setAttributes( { cardOverlayOpacity: value } ) }
						min={ 0 }
						max={ 100 }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Cards', 'swishfolio-core' ) } initialOpen={ false }>
					{ cards.map( ( card, index ) => (
						<div
							key={ card.id }
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
										onClick={ () => moveCard( index, -1 ) }
										label={ __( 'Move up', 'swishfolio-core' ) }
									/>
									<Button
										size="small"
										icon="arrow-down-alt"
										disabled={ index === cards.length - 1 }
										onClick={ () => moveCard( index, 1 ) }
										label={ __( 'Move down', 'swishfolio-core' ) }
									/>
									<Button
										size="small"
										icon="no-alt"
										isDestructive
										onClick={ () => removeCard( index ) }
										label={ __( 'Remove', 'swishfolio-core' ) }
									/>
								</div>
							</div>

							<SelectControl
								label={ __( 'Card Size', 'swishfolio-core' ) }
								value={ card.cardSize }
								options={ [
									{ label: __( 'Full Width (3/3)', 'swishfolio-core' ), value: 'full' },
									{ label: __( 'Two Thirds (2/3)', 'swishfolio-core' ), value: 'two-thirds' },
									{ label: __( 'One Third (1/3)', 'swishfolio-core' ), value: 'one-third' },
								] }
								onChange={ ( value ) => updateCard( index, 'cardSize', value ) }
							/>

							<p className="components-base-control__label" style={ { marginTop: '12px' } }>
								{ __( 'Card Image', 'swishfolio-core' ) }
							</p>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ ( media ) =>
										updateCard( index, 'image', {
											url: media.url,
											alt: media.alt,
											id: media.id,
										} )
									}
									allowedTypes={ [ 'image' ] }
									value={ card.image?.id }
									render={ ( { open } ) => (
										<>
											{ card.image?.url ? (
												<div style={ { marginBottom: '8px' } }>
													<img
														src={ card.image.url }
														alt=""
														style={ {
															width: '100%',
															height: '100px',
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
															onClick={ () => updateCard( index, 'image', {} ) }
															size="small"
														>
															{ __( 'Remove', 'swishfolio-core' ) }
														</Button>
													</ButtonGroup>
												</div>
											) : (
												<Button variant="secondary" onClick={ open } style={ { marginBottom: '8px' } }>
													{ __( 'Select Image', 'swishfolio-core' ) }
												</Button>
											) }
										</>
									) }
								/>
							</MediaUploadCheck>

							<p className="components-base-control__label" style={ { marginTop: '12px' } }>
								{ __( 'Card Title Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ card.titleColor }
								onChange={ ( color ) => updateCard( index, 'titleColor', color ) }
								clearable
							/>
						</div>
					) ) }

					<Button
						variant="secondary"
						onClick={ addCard }
						icon="plus"
						style={ { width: '100%', justifyContent: 'center' } }
					>
						{ __( 'Add Card', 'swishfolio-core' ) }
					</Button>
				</PanelBody>

				<PanelBody title={ __( 'Call to Action', 'swishfolio-core' ) } initialOpen={ false }>
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

							<p className="components-base-control__label" style={ { marginTop: '12px' } }>
								{ __( 'Text Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ ctaTextColor }
								onChange={ ( color ) => setAttributes( { ctaTextColor: color } ) }
								clearable
							/>

							<p className="components-base-control__label" style={ { marginTop: '12px' } }>
								{ __( 'Background Color', 'swishfolio-core' ) }
							</p>
							<ColorPalette
								colors={ themeColors }
								value={ ctaBgColor }
								onChange={ ( color ) => setAttributes( { ctaBgColor: color } ) }
								clearable
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ /* Cards Grid */ }
				<div className="sfcore-bento__grid">
					{ cards.map( ( card, index ) => (
						<div
							key={ card.id }
							className={ getCardClasses( card ) }
						>
							{ card.image?.url && (
								<img
									src={ card.image.url }
									alt={ card.image.alt || '' }
									className="sfcore-bento__card-image"
								/>
							) }
							<div className="sfcore-bento__card-overlay" />
							<div className="sfcore-bento__card-content">
								<RichText
									tagName="span"
									className="sfcore-bento__card-subtitle"
									value={ card.subtitle }
									onChange={ ( value ) => updateCard( index, 'subtitle', value ) }
									placeholder={ __( 'Subtitle...', 'swishfolio-core' ) }
									style={ card.titleColor ? { color: card.titleColor } : undefined }
								/>
								<div className="sfcore-bento__card-info">
									<RichText
										tagName="h3"
										className="sfcore-bento__card-title"
										value={ card.title }
										onChange={ ( value ) => updateCard( index, 'title', value ) }
										placeholder={ __( 'Card Title...', 'swishfolio-core' ) }
										style={ card.titleColor ? { color: card.titleColor } : undefined }
									/>
									<RichText
										tagName="p"
										className="sfcore-bento__card-description"
										value={ card.description }
										onChange={ ( value ) => updateCard( index, 'description', value ) }
										placeholder={ __( 'Description...', 'swishfolio-core' ) }
										style={ card.titleColor ? { color: card.titleColor } : undefined }
									/>
								</div>
							</div>
						</div>
					) ) }
				</div>

				{ /* Block CTA */ }
				{ ctaType !== 'none' && ctaText && (
					<div className="sfcore-bento__cta-wrapper">
						<span
							className={ `sfcore-bento__cta sfcore-bento__cta--${ ctaType }` }
							style={ {
								color: ctaTextColor || undefined,
								backgroundColor: ctaType === 'button' ? ( ctaBgColor || undefined ) : undefined,
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
